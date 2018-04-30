<?php
/**
 * @copyright 2008-2016 Vanilla Forums, Inc.
 * @license Proprietary
 */

use Vanilla\Addon;
use Vanilla\AddonManager;

/**
 * Class Akismet2Plugin
 */
class Akismet2Plugin extends Gdn_Plugin {

    /** Operation used by logging to flag SPAM. */
    const SPAM_OPERATION = 'Spam';

    /** Akismet account username. */
    const USERNAME = 'Akismet';

    /** @var AkismetAPI */
    private $akismetAPI;

    /** @var Gdn_Configuration */
    private $configuration;

    /** @var Gdn_Locale */
    private $locale;

    /** @var Gdn_Request */
    private $request;

    /** @var Gdn_Session */
    private $session;

    /** @var UserModel */
    private $userModel;

    /**
     * AkismetPlugin constructor.
     *
     * @param AddonManager $addonManager
     * @param AkismetAPI $akismetAPI
     * @param Gdn_Configuration $configuration
     * @param Gdn_Locale $locale
     * @param Gdn_Request $request
     * @param Gdn_Session $session
     * @param UserModel $userModel
     */
    public function __construct(
        AddonManager $addonManager,
        AkismetAPI $akismetAPI,
        Gdn_Configuration $configuration,
        Gdn_Locale $locale,
        Gdn_Request $request,
        Gdn_Session $session,
        UserModel $userModel) {

        $this->akismetAPI = $akismetAPI;
        $this->configuration = $configuration;
        $this->locale = $locale;
        $this->request = $request;
        $this->session = $session;
        $this->userModel = $userModel;

        $this->akismetAPI->setBlog($this->request->url('/', true));
        if ($key = $this->getKey()) {
            $this->akismetAPI->setKey($key);
        }
        $this->akismetAPI->setIncludeServer($this->includeServer());

        $addon = $addonManager->lookupByClassName(__CLASS__);
        if ($addon instanceof Addon) {
            $addonVersion = $addon->getVersion();
            $this->akismetAPI->setDefaultHeader('User-Agent', 'Vanilla/'.APPLICATION_VERSION.' | Akismet/'.$addonVersion);
        }
    }

    /**
     * Hook into Vanilla to run checks.
     *
     * @param $sender
     * @param $args
     */
    public function base_checkSpam_handler($sender, $args) {
        if ($this->isConfigured() === false || $args['IsSpam']) {
            // Addon not configured or the content has already been flagged.
            return;
        }

        $recordType = $args['RecordType'];
        $data =& $args['Data'];

        $result = false;
        switch ($recordType) {
            case 'Activity':
            case 'ActivityComment':
            case 'Comment':
            case 'Discussion':
                $result = $this->isSpam($recordType, $data, true);
                break;
            case 'Registration':
                $body = $data['DiscoveryText'] ?? null;
                $data['Name'] = '';
                $data['Body'] = $body;
                if ($body) {
                    // Only check for spam if there is discovery text.
                    $result = $this->isSpam($recordType, $data, true);
                }
        }

        // Akismet says it's SPAM. Include some additional data with the log entry.
        if ($result) {
            $data['Log_InsertUserID'] = $this->userID();
            $data['Akismet'] = true;
        }

        $sender->EventArguments['IsSpam'] = $result;
    }

    /**
     * Prepare data as an Akismet comment payload.
     *
     * @param string $recordType
     * @param array $data
     * @param bool $includeRequest
     * @return AkismetComment
     */
    private function buildComment($recordType, array $data, $includeRequest = false) {
        $comment = new AkismetComment();

        $email = val('Email', $data, val('InsertEmail', $data));
        if ($email) {
            $comment->setCommentAuthorEmail($email);
        }

        $username = val('Username', $data, val('InsertName', $data));
        if ($username) {
            $comment->setCommentAuthor($username);
        }

        if (array_key_exists('IPAddress', $data)) {
            $comment->setUserIP($data['IPAddress']);
        }

        $dates = ['DateInserted' => 'setCommentDateGMT', 'DateUpdated' => 'setCommentPostModifiedGMT'];
        foreach ($dates as $date => $setMethod) {
            $rawDate = val($date, $data);
            if ($rawDate) {
                $gmtDate = $this->getGMTDateTime($rawDate);
                if ($gmtDate && is_callable([$comment, $setMethod])) {
                    call_user_func([$comment, $setMethod], $gmtDate);
                }
            }
        }

        $language = $this->getLanguage();
        $comment->setBlogLang($language);

        $charset = $this->configuration->get('Garden.Charset', 'utf-8');
        $comment->setBlogCharset($charset);

        switch ($recordType) {
            case 'Comment':
                $comment->setCommentType('reply');
                break;
            case 'Discussion':
                $comment->setCommentType('forum-post');
                break;
        }

        $content = [];
        $contentFields = ['Name', 'Body', 'Story'];
        foreach ($contentFields as $contentField) {
            if (array_key_exists($contentField, $data)) {
                $content[] = $data[$contentField];
            }
        }
        $comment->setCommentContent(implode("\n\n", $content));

        if ($includeRequest) {
            $userAgent = $this->request->getValueFrom(Gdn_Request::INPUT_SERVER, 'HTTP_USER_AGENT');
            if ($userAgent) {
                $comment->setUserAgent($userAgent);
            }

            $referrer = $value = $this->request->getValueFrom(Gdn_Request::INPUT_SERVER, 'HTTP_REFERER');
            if ($referrer) {
                $comment->setReferrer($referrer);
            }
        }

        // If in "test mode", set the "is_test" flag on all data.
        if ($this->inTestMode()) {
            $comment->setIsTest(true);
        }
        return $comment;
    }

    /**
     * Should server vars be included when performing comment checks?
     *
     * @return bool
     */
    private function includeServer() {
        $result = (bool)$this->configuration->get('Akismet.IncludeServer');
        return $result;
    }

    /**
     * Is the addon configured to be in test mode?
     *
     * @return bool
     */
    private function inTestMode() {
        $result = (bool)$this->configuration->get('Akismet.TestMode');
        return $result;
    }

    /**
     * Check with Akismet to see if this may be SPAM.
     *
     * @param string $recordType
     * @param array $data
     * @param bool $includeRequest
     * @return bool
     */
    private function isSpam($recordType, array $data, $includeRequest = false) {
        if (!$this->session->isValid()) {
            return false;
        }

        $comment = $this->buildComment($recordType, $data, $includeRequest);
        $result = $this->akismetAPI->commentCheck($comment);
        return $result;
    }

    private function getGMTDateTime($datetime) {
        $result = false;

        $time = strtotime($datetime);
        if ($time) {
            $result = gmdate('c', $time);
        }

        return $result;
    }

    /**
     * Get the configured Akismet API key.
     *
     * @return string|null
     */
    private function getKey() {
        $result = $this->configuration->get('Akismet.Key', $this->getMasterKey());
        return $result;
    }

    /**
     * Get the current language.
     *
     * @return string
     */
    private function getLanguage() {
        $locale = $this->locale->current();
        $localeParts = preg_split('`(_|-)`', $locale, 2);
        if (count($localeParts) == 2) {
            $result = $localeParts[0];
        } else {
            $result = $locale;
        }

        return $result;
    }

    /**
     * Get the configured "master" Akismet API key.
     *
     * @return string|null
     */
    private function getMasterKey() {
        $result = $this->configuration->get('Akismet.MasterKey', null);
        return $result;
    }

    /**
     * Is Akismet configured for use?
     *
     * @return bool
     */
    private function isConfigured() {
        $result = $this->akismetAPI->getKey() ? true : false;
        return $result;
    }

    /**
     * Is this a SPAM log entry?
     *
     * @param array $log
     * @return bool
     */
    private function logIsSpam(array $log) {
        $result = false;

        if (array_key_exists('Operation', $log) && $log['Operation'] === self::SPAM_OPERATION) {
            $result = true;
        }

        return $result;
    }

    /**
     * Hook in after a log item is restored.
     *
     * @param LogModel $sender
     * @param array $args
     */
    public function logModel_afterRestore_handler(LogModel $sender, array $args) {
        if (!$this->logIsSpam($args['Log'])) {
            return;
        }
        $data = $args['Log']['Data'];
        $recordType = $args['Log']['RecordType'];

        // Make sure this was previously flagged as SPAM by Akismet.
        if (!array_key_exists('Akismet', $data) || !$data['Akismet']) {
            return;
        }

        $comment = $this->buildComment($recordType, $data);
        if ($comment) {
            $this->akismetAPI->submitHam($comment);
        }
    }

    /**
     * @param LogModel $sender
     * @param array $args
     */
    public function logModel_afterInsert_handler(LogModel $sender, array $args) {
        if (!$this->logIsSpam($args['Log'])) {
            return;
        }
        $data = dbdecode($args['Log']['Data']);
        $recordType = $args['Log']['RecordType'];

        // Make sure this entry wasn't already flagged as SPAM by Akismet.
        if (array_key_exists('Akismet', $data) && $data['Akismet']) {
            return;
        }

        // Only submit items flagged by moderators or higher.
        if ($this->session->checkRankedPermission('Garden.Moderation.Manage')) {
            $comment = $this->buildComment($recordType, $data);
            if ($comment) {
                $this->akismetAPI->submitSpam($comment);
            }
        }
    }

    /**
     * Settings page.
     *
     * @param SettingsController $sender
     */
    public function settingsController_akismet_create(SettingsController $sender) {
        $sender->permission('Garden.Settings.Manage');
        $sender->setData('Title', t('Akismet Settings'));

        $configurationModule = new ConfigurationModule($sender);

        // Do key validation so we don't break our entire site.
        // Always allow a blank key, because the plugin turns off in that scenario.
        if ($this->request->isAuthenticatedPostBack()) {
            $key = $configurationModule->form()->getFormValue('Akismet.Key');
            if ($key !== '' && $this->akismetAPI->verifyKey($key) === false) {
                $configurationModule->form()->addError('Key is invalid.');
            }
        }

        // Allow for master hosted key
        $keyDesc = 'Enter the key you obtained from <a href="http://akismet.com">akismet.com</a>';
        if ($this->getMasterKey()) {
            $keyDesc = 'No key is required! You may optionally use your own.';
        }
        $configurationModule->initialize([
            'Akismet.Key' => ['Description' => $keyDesc]
        ]);

        $sender->addSideMenu('settings/plugins');
        $configurationModule->renderAll();
    }

    /**
     * {@inheritdoc}
     */
    public function setup() {
        $this->structure();
    }

    /**
     * Update the database.
     */
    public function structure() {
        $userID = $this->userID();
        if (!$userID) {
            $user = $this->userModel->getWhere([
                'Name' => self::USERNAME,
                'Admin' => 2
            ])->resultArray();

            if (!$user) {
                $userID = $this->userModel->save([
                    'Name' => self::USERNAME,
                    'Password' => betterRandomString(20),
                    'HashMethod' => 'Random',
                    'Email' => 'akismet@example.com',
                    'Admin' => 2
                ]);
            } else {
                $userID = $user['UserID'];
            }

            saveToConfig('Akismet.UserID', $userID);
        }
    }

    /**
     * Get the ID of the Akismet user.
     *
     * @return int|null
     */
    private function userID() {
        $result = $this->configuration->get('Akismet.UserID', null);
        return $result;
    }
}
