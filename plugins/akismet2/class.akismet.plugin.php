<?php
/**
 * @copyright 2008-2016 Vanilla Forums, Inc.
 * @license Proprietary
 */

/**
 * Class AkismetPlugin
 */
class AkismetPlugin extends Gdn_Plugin {

    /** Operation used by logging to flag SPAM. */
    const SPAM_OPERATION = 'Spam';

    /** Akismet account username. */
    const USERNAME = 'Akismet';

    /** @var AkismetAPI */
    private $akismetAPI;

    /** @var UserModel */
    private $userModel;

    /**
     * AkismetPlugin constructor.
     *
     * @param AkismetAPI $akismetAPI
     * @param UserModel $userModel
     */
    public function __construct(AkismetAPI $akismetAPI, UserModel $userModel) {
        $this->akismetAPI = $akismetAPI;
        $this->userModel = $userModel;

        $this->akismetAPI->setBlog(Gdn::request()->url('/', true));
        if ($key = $this->getKey()) {
            $this->akismetAPI->setKey($key);
        }

        parent::__construct();
    }

    /**
     * Hook into Vanilla to run checks.
     *
     * @param $sender
     * @param $args
     */
    public function base_checkSpam_handler($sender, $args) {
        echo null;
        if ($this->isConfigured() === false || $args['IsSpam']) {
            // Addon not configured or the content has already been flagged.
            return;
        }

        $recordType = $args['RecordType'];
        $data =& $args['Data'];

        $result = false;
        switch ($recordType) {
            case 'Registration':
                $body = $data['DiscoveryText'] ?? null;
                $data['Name'] = '';
                $data['Body'] = $body;
                if ($body) {
                    // Only check for spam if there is discovery text.
                    $result = $this->isSpam($recordType, $data, true);
                }
                break;
            case 'Comment':
            case 'Discussion':
            case 'Activity':
            case 'ActivityComment':
                $result = $this->isSpam($recordType, $data, true);
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
        if (array_key_exists('Email', $data)) {
            $comment->setCommentAuthorEmail($data['Email']);
        }
        if (array_key_exists('IPAddress', $data)) {
            $comment->setUserIP($data['IPAddress']);
        }
        if (array_key_exists('Username', $data)) {
            $comment->setCommentAuthor($data['Username']);
        }

        $locale = Gdn::Locale()->current();
        $localeParts = preg_split('`(_|-)`', $locale, 2);
        if (count($localeParts) == 2) {
            $language = $localeParts[0];
        } else {
            $language = $locale;
        }
        $comment->setBlogLang($language);

        $charset = c('Garden.Charset', 'utf-8');
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
            $userAgent = Gdn::request()->getValueFrom(Gdn_Request::INPUT_SERVER, 'HTTP_USER_AGENT');
            $referrer = $value = Gdn::request()->getValueFrom(Gdn_Request::INPUT_SERVER, 'HTTP_REFERER');

            if ($userAgent) {
                $comment->setUserAgent($userAgent);
            }
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
     * Is the addon configured to be in test mode?
     *
     * @return bool
     */
    private function inTestMode() {
        $result = (bool)c('Akismet.TestMode');
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
        if (!Gdn::session()->isValid()) {
            return false;
        }

        $comment = $this->buildComment($recordType, $data, $includeRequest);
        $result = $this->akismetAPI->commentCheck($comment);
        return $result;
    }

    /**
     * Get the configured Akismet API key.
     *
     * @return string|null
     */
    private function getKey() {
        $result = c('Akismet.Key', $this->getMasterKey());
        return $result;
    }

    /**
     * Get the configured "master" Akismet API key.
     *
     * @return string|null
     */
    private function getMasterKey() {
        $result = c('Akismet.MasterKey', null);
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
     * Hook in after a log item is restored.
     *
     * @param LogModel $sender
     * @param array $args
     */
    public function logModel_afterRestore_handler(LogModel $sender, array $args) {
        if (!array_key_exists('Log', $args) || !is_array($args['Log'])) {
            return;
        }

        $recordType = $args['Log']['RecordType'] ?? null;
        if (!$recordType) {
            return;
        }

        if (!array_key_exists('Operation', $args['Log']) || $args['Log']['Operation'] !== self::SPAM_OPERATION) {
            return;
        }

        $data = $args['Log']['Data'] ?? null;
        if (!$data || !is_array($data)) {
            return;
        }

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
        if (!array_key_exists('Log', $args) || !is_array($args['Log'])) {
            return;
        }

        if (!array_key_exists('Operation', $args['Log']) || $args['Log']['Operation'] !== self::SPAM_OPERATION) {
            return;
        }

        $recordType = $args['Log']['RecordType'] ?? null;
        if (!$recordType) {
            return;
        }

        $data = $args['Log']['Data'] ?? null;
        if (!$data) {
            return;
        }
        $data = dbdecode($data);

        if (array_key_exists('Akismet', $data) && $data['Akismet']) {
            return;
        }

        $comment = $this->buildComment($recordType, $data);
        if ($comment) {
            $this->akismetAPI->submitSpam($comment);
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
        if (Gdn::request()->isAuthenticatedPostBack()) {
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
    private function structure() {
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
        $result = c('Akismet.UserID', null);
        return $result;
    }
}
