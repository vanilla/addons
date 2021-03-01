<?php

/**
 * @copyright 2010-2016 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 */

use Garden\Container\ContainerException;
use Garden\Container\NotFoundException;

/**
 * Troll Management Features
 *
 * TODO:
 * Verify that activity feed works properly with/without troll content. Added new event to core to get it working.
 *
 * Add additional troll management options:
 *  - Disemvoweler
 *  - Troll Annoyances (slow page loading times, random over capacity errors, form submission failures, etc).
 *  - Trolls' posts don't bump the thread.
 *  - Admin page that shows all trolls and their punishments
 *  - Sink troll comments by default
 *  - Custom per-troll punishments
 *  - Speed optimizations (add troll state to user attributes, and return from troll specific functions quickly when possible).
 *
 * @author Mark O'Sullivan <mark@vanillaforums.com>
 */
class TrollManagementPlugin extends Gdn_Plugin {

    static $trolls = null;

    /**
     * @var Gdn_Session.
     */
    private $session;

    /**
     * TrollManagementPlugin constructor.
     *
     * @param Gdn_Session $session Injected session.
     */
    public function __construct(Gdn_Session $session) {
        parent::__construct();
        $this->session = $session;
    }

    /**
     * Setup: on enable
     */
    public function setup() {
        $this->structure();
    }

    /**
     * Database structure: on update
     */
    public function structure() {
        Gdn::structure()
            ->table('User')
            ->column('Troll', 'int', '0')
            ->column('Fingerprint', 'varchar(50)', null, 'index')
            ->set();
    }

    /**
     * Get list of current troll user IDs.
     *
     * @return array
     */
    public static function getTrolls() {
        if (is_null(self::$trolls)) {
            self::$trolls = c('Plugins.TrollManagement.Cache');
            if (!is_array(self::$trolls)) {
                self::$trolls = [];
            }
        }

        return self::$trolls;
    }

    /**
     * Save list of current troll IDs to config.
     *
     * @param array $trolls
     */
    public static function setTrolls($trolls) {
        saveToConfig('Plugins.TrollManagement.Cache', $trolls);
        self::$trolls = $trolls;
    }

    /**
     * Validates the current user's permissions & transientkey and then marks a user as a troll.
     *
     * @param UserController $sender
     * @param int|string $userID The userID number.
     * @param boolean $troll Whether to mark the user as a troll.
     * @throws Gdn_UserException Throws user exception.
     * @throws ContainerException Throws exception if there's a problem getting a container.
     * @throws NotFoundException Throws exception if there's a problem getting a container.
     */
    public function userController_markTroll_create($sender, $userID, $troll = true) {
        $sender->permission('Garden.Moderation.Manage');

        $trollUserID = $userID;
        // Make sure the user has a higher permission level than the user they want to mark as a troll.
        $trollPermissions = $sender->userModel->getPermissions($trollUserID);
        $rankCompare = $this->session->getPermissions()->compareRankTo($trollPermissions);
        if ($rankCompare < 0) {
            throw forbiddenException('@'.t('You are not allowed to mark a user that has higher permissions than you as a troll.'));
        }
        if ($rankCompare === 0) {
            throw forbiddenException('@'.t('You are not allowed to mark a user with the same permission level as you as a troll.'));
        }

        // Validate the transient key && permissions
        // Make sure we are posting back.
        if (!$sender->Request->isAuthenticatedPostBack()) {
            throw permissionException('Javascript');
        }

        $trolls = self::getTrolls();

        // Toggle troll value in DB
        if (in_array($trollUserID, $trolls)) {
            Gdn::sql()->update('User', ['Troll' => 0], ['UserID' => $trollUserID])->put();
            unset($trolls[array_search($trollUserID, $trolls)]);
        } else {
            Gdn::sql()->update('User', ['Troll' => 1], ['UserID' => $trollUserID])->put();
            $trolls[] = $trollUserID;
        }

        self::setTrolls($trolls);

        $sender->jsonTarget('', '', 'Refresh');
        $sender->render('Blank', 'Utility', 'Dashboard');
    }

    /**
     * Set a fingerprint the user. See setFingerPrint();
     *
     * @param Gdn_Controller $sender
     */
    public function base_render_before($sender) {
        // Don't do anything if the user isn't signed in.
        if (!Gdn::session()->isValid()) {
            return;
        }
        $this->setFingerprint();
    }

    /**
     * Set and return a Fingerprint to the provided userID's (or in session, when null) user.
     *
     * @param int|null $userID
     * @return string Fingerprint
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function setFingerprint($userID = null): string {
        $userID = $userID ?? Gdn::session()->UserID;

        $cookieFingerprint = val('__vnf', $_COOKIE, null);
        $databaseFingerprint = val('Fingerprint', Gdn::session()->User, null);
        $expires = time() + 60 * 60 * 24 * 256; // Expire one year from now

        // Cookie and user record both empty, assign both
        if (empty($cookieFingerprint) && empty($databaseFingerprint)) {
            $databaseFingerprint = uniqid();
            Gdn::sql()->update('User', ['Fingerprint' => $databaseFingerprint], ['UserID' => $userID])->put();
            safeCookie('__vnf', $databaseFingerprint, $expires);
            return $databaseFingerprint;
        }

        // If the cookie exists...
        if (!empty($cookieFingerprint)) {
            // If the cookie disagrees with the database, update the database
            if ($databaseFingerprint != $cookieFingerprint) {
                Gdn::sql()->update('User', ['Fingerprint' => $cookieFingerprint], ['UserID' => $userID])->put();
                return $cookieFingerprint;
            }
        } else if (!empty($databaseFingerprint)) {
            // If only the user record exists, propagate it to the cookie
            safeCookie('__vnf', $databaseFingerprint, $expires);
            return $databaseFingerprint;
        }

        return false;
    }

    /**
     * Hide counters on profile of trolls unless viewer is a moderator.
     *
     * @param ProfileController $sender
     */
    public function profileController_userLoaded_handler($sender) {
        if (valr('User.Troll', $sender) && !Gdn::session()->checkPermission('Garden.Moderation.Manage')) {
            saveToConfig('Vanilla.Profile.ShowCounts', false, false);
        }
    }

    /**
     * Display shared accounts on the user profiles for moderators.
     *
     * @param ProfileController $sender
     */
    public function profileController_render_before($sender) {
        if (!Gdn::session()->checkPermission('Garden.Moderation.Manage')) {
            return;
        }

        if (!property_exists($sender, 'User')) {
            return;
        }

        // Get the current user's fingerprint value
        $databaseFingerprint = val('Fingerprint', $sender->User);
        if (!$databaseFingerprint) {
            return;
        }

        // Display all accounts that share that fingerprint value
        $sharedFingerprintModule = new SharedFingerprintModule($sender);
        $sharedFingerprintModule->getData(valr('User.UserID', $sender), $databaseFingerprint);
        $sender->addModule($sharedFingerprintModule);
    }

    /**
     * Attach to the Discussion model and remove all records by trolls (unless the current user is a troll)
     *
     * @param DiscussionModel $sender
     */
    public function discussionModel_afterAddColumns_handler($sender) {
        $this->_cleanDataSet($sender, 'Data');
    }

    /**
     * Attach to the Comment model and remove all records by trolls (unless the current user is a troll)
     *
     * @param CommentModel $sender
     */
    public function commentModel_afterGet_handler($sender) {
        $this->_cleanDataSet($sender, 'Comments');
    }

    /**
     * Attach to the Activity model and remove all records by trolls (unless the current user is a troll)
     *
     * @param ActivityModel $sender
     */
    public function activityModel_afterGet_handler($sender) {
        $this->_cleanDataSet($sender, 'Data');
    }

    /**
     * Attach to the Comment model and check if the user is a troll.
     *
     * @param CommentModel $sender
     * @param array $args Event arguments.
     */
    public function commentModel_beforeSaveComment_handler(CommentModel $sender, array $args) {
        $this->checkTroll(Gdn::session()->UserID, $args);
    }

    /**
     * Look in the sender event arguments for a dataset to clean of troll content.
     */
    private function _cleanDataSet($sender, $dataEventArgument) {
        // Don't do anything if there are no trolls
        $trolls = self::getTrolls();
        if (!count($trolls)) {
            return;
        }

        if (!array_key_exists($dataEventArgument, $sender->EventArguments)) {
            return;
        }

        // Examine the data, and remove any rows that belong to the trolls
        $data = &$sender->EventArguments[$dataEventArgument];
        $result = &$data->result();
        $isPrivileged = Gdn::session()->checkPermission('Garden.Moderation.Manage');
        foreach ($result as $index => $row) {
            // If this is a troll post...
            if (in_array(val('InsertUserID', $row), $trolls)) {

                if ($isPrivileged) {
                    // Mark as a troll post for moderators
                    setValue('IsTroll', $result[$index], true);
                } else {
                    // Remove it unless it belongs to the current user
                    if (Gdn::session()->UserID != val('InsertUserID', $row)) {
                        unset($result[$index]);
                    }
                }
            }
        }

        if (!empty($result)) {
            // Be sure the the array is properly indexed after unset. (important for json_encode)
            $result = array_values($result);
        }
    }

    /**
     * Identify troll comments for moderators.
     *
     * @param type $sender
     */
    public function base_beforeCommentBody_handler($sender) {
        // Note that for DiscussionController, the IsTroll var is not being set.
        $this->_showAdmin($sender, 'Object');
    }

    /**
     * Identify troll discussions for moderators.
     *
     * @param type $sender
     */
    public function base_beforeDiscussionMeta_handler($sender) {
        $this->_showAdmin($sender, 'Discussion', 'tag');
    }

    /**
     * Display troll warning on comments and discussions for moderators to see.
     *
     * @param Gdn_Controller $sender
     * @param string $eventArgumentName
     * @param string $style optional. "message" or "tag"
     * @return void
     */
    private function _showAdmin($sender, $eventArgumentName, $style = 'message') {

        // Don't do anything if the user is not admin (sanity check).
        if (!Gdn::session()->checkPermission('Garden.Moderation.Manage')) {
            return;
        }

        // Don't do anything if there are no trolls
        $trolls = self::getTrolls();
        if (!count($trolls)) {
            return;
        }

        $object = $sender->EventArguments[$eventArgumentName];
        $insertUserID = val('InsertUserID', $object);

        if (in_array($insertUserID, $trolls)) {
            if ($style === 'message') {
                echo '<div style="display: block; line-height: 1.2; padding: 8px; margin: -4px 0 8px; background: rgba(0, 0, 0, 0.05); color: #d00; font-size: 11px;">' . t('Troll.Content', '<b>Troll</b> <ul> <li>This user has been marked as a troll.</li> <li>Their content is only visible to moderators and the troll.</li> <li>This message does not appear for the troll.</li></ul>') . '</div>';
            } else {
                echo '<span class="Tag Tag-Troll" title="' . t('This user has been marked as a troll.') . '">Troll</span>';
            }
        }
    }

    /**
     * Do not let troll comments bump discussions.
     *
     * @param CommentModel $sender
     */
    public function commentModel_beforeUpdateCommentCount_handler($sender) {
        $trolls = self::getTrolls();
        if (!count($trolls)) {
            return;
        }

        // Pretend the discussion is sunk
        if (in_array(Gdn::session()->UserID, $trolls)) {
            $sender->EventArguments['Discussion']['Sink'] = true;
        }
    }

    /**
     * Auto-sink troll discussions.
     *
     * @param DiscussionModel $sender
     */
    public function discussionModel_beforeSaveDiscussion_handler($sender) {
        $trolls = self::getTrolls();
        if (!count($trolls)) {
            return;
        }

        if (in_array(Gdn::session()->UserID, $trolls)) {
            $sender->EventArguments['FormPostValues']['Sink'] = 1;
        }
    }

    /**
     * If user has been marked as troll, write a message at the top of their
     * profile for moderators to read.
     *
     * @param ProfileController $sender
     */
    public function profileController_beforeUserInfo_handler($sender) {
        if (Gdn::session()->checkPermission('Garden.Moderation.Manage')) {
            $userID = $sender->User->UserID;

            $trolls = self::getTrolls();
            if (!count($trolls)) {
                return;
            }

            if (in_array($userID, $trolls)) {
                echo '
            <div class="Hero Warning">
               <h3>' . t('Troll') . '</h3>
               ' . t('This user has been marked as a troll.') . '
            </div>';
            }
        }
    }

    /**
     * Add toggle option to add/remove troll status from users
     *
     * @param ProfileController $sender
     */
    public function profileController_beforeProfileOptions_handler($sender) {
        if (Gdn::session()->checkPermission('Garden.Moderation.Manage')) {
            $userID = $sender->User->UserID;

            $trolls = self::getTrolls();

            $troll = in_array($userID, $trolls) ? 1 : 0;

            $sender->EventArguments['ProfileOptions']['TrollToggle'] = [
                'Text' => t(($troll) ? 'Unmark as Troll' : 'Mark as Troll'),
                'Url' => '/user/marktroll?userid=' . $userID . '&troll=' . (int) (!$troll),
                'CssClass' => 'Hijack Button-Troll'
            ];
        }
    }

    /**
     * Check if the user creating the discussion is a troll.
     *
     * @param DiscussionModel $sender
     * @param array $args
     */
    public function discussionModel_BeforeNotification_handler(DiscussionModel $sender, array &$args) {
        $discussion = $args['Discussion'];
        $this->checkTroll($discussion['InsertUserID'], $args);
    }

    /**
     * Check if the user creating the comment is a troll.
     *
     * @param CommentModel $sender
     * @param array $args
     */
    public function commentModel_BeforeNotification_handler(CommentModel $sender, array &$args) {
        $comment = $args['Comment'];
        $this->checkTroll($comment['InsertUserID'], $args);
    }

    /**
     * Check if the user creating the activity post or comment is a troll.
     *
     * @param ActivityModel $sender
     * @param array $args
     */
    public function activityModel_beforeWallNotificationSend_handler(ActivityModel $sender, array &$args) {
        $activity = $args['Activity'];
        $this->checkTroll($activity['ActivityUserID'], $args);
    }

    /**
     * Check if the user is a troll.
     *
     * @param int $userID
     * @param array $args
     */
    private function checkTroll(int $userID, array &$args) {
        if ($args['IsValid'] === false || !isset($args['UserModel'])) {
            return;
        }
        $userModel = $args['UserModel'];
        $user = $userModel->get($userID);
        if ($user && $user->Troll === 1) {
            $args['IsValid'] = false;
        }
    }

    /**
     * Create a method called "QnA" on the SettingController.
     *
     * @param $sender Sending controller instance
     */
    public function settingsController_trollManagement_create($sender) {
        // Prevent non-admins from accessing this page
        $sender->permission('Garden.Settings.Manage');

        $sender->title(sprintf(t('%s settings'), t('Troll Management')));
        $sender->setData('PluginDescription', $this->getPluginKey('Description'));

        $sender->Form = new Gdn_Form();
        $validation = new Gdn_Validation();
        $configurationModel = new Gdn_ConfigurationModel($validation);

        $configurationModel->setField([
            'TrollManagement.PerFingerPrint.Enabled' => c('TrollManagement.PerFingerPrint.Enabled', false),
            'TrollManagement.PerFingerPrint.MaxUserAccounts' => c('TrollManagement.PerFingerPrint.MaxUserAccounts', 5),
        ]);

        $sender->Form->setModel($configurationModel);

        // If seeing the form for the first time...
        if ($sender->Form->authenticatedPostBack() === false) {
            $sender->Form->setData($configurationModel->Data);
        } else {
            $configurationModel->Validation->applyRule('TrollManagement.PerFingerPrint.Enabled', 'Boolean');

            if ($sender->Form->getFormValue('TrollManagement.PerFingerPrint.Enabled')) {
                $configurationModel->Validation->applyRule('TrollManagement.PerFingerPrint.MaxUserAccounts', 'Required');
                $configurationModel->Validation->applyRule('TrollManagement.PerFingerPrint.MaxUserAccounts', 'Integer');
                $configurationModel->Validation->applyRule(
                    'TrollManagement.PerFingerPrint.MaxUserAccounts',
                    'validatePositiveNumber',
                    sprintf(t('%s must be a positive number.'), t("Maximum user's accounts"))
                );
            }

            if ($sender->Form->save()) {
                $sender->StatusMessage = t('Your changes have been saved.');
            }
        }

        $sender->render($this->getView('configuration.php'));
    }

    /**
     * Add a _probable_ justification as to why a user's is on the applicant's list if there are too many user
     * accounts using the same fingerprint.
     *
     * @param Controller $sender
     */
    public function base_applicantInfo_handler($sender, $args) {
        if (c('TrollManagement.PerFingerPrint.Enabled', false)) {
            $maxSiblingAccounts = c('TrollManagement.PerFingerPrint.MaxUserAccounts');
            $userFingerprint = val('Fingerprint', $args['User']);
            $fingerprintUsages = $this->getSharedFingerprintsUsersCount($userFingerprint);
            if ($fingerprintUsages >= $maxSiblingAccounts) {
                $sender->EventArguments['ApplicantMeta'][] = sprintf(
                    t("%s accounts are sharing the '%s' fingerprint."),
                    $fingerprintUsages,
                    $userFingerprint
                );
            }
        }
    }

    /**
     * Return a count of users using the same provided fingerprint.
     *
     * @param string $fingerprint
     */
    public function getSharedFingerprintsUsersCount(string $fingerprint) {
        $sql = clone Gdn::sql();
        $sql->reset();
        $users = $sql
            ->select('userID AS siblingsCount', 'count')
            ->from('User')
            ->where('Fingerprint', $fingerprint)
            ->get()->firstRow(DATASET_TYPE_ARRAY);
        return $users['siblingsCount'];
    }

    /**
     * Upon user registration, we trigger an early Fingerprint tag & we check if this new registration trips the
     * maximum amount of user accounts allowed for a single fingerprint. If it is, we give this new user the
     * 'Applicant' role.
     *
     * @param UserModel $sender
     * @param array $args
     */
    public function userModel_afterRegister_handler($sender, $args) {
        $userID = $args['UserID'];

        $maxSiblingAccounts = c('TrollManagement.PerFingerPrint.MaxUserAccounts');
        $userFingerprint = $this->setFingerprint($userID);

        $fingerprintUsages = $this->getSharedFingerprintsUsersCount($userFingerprint);
        if ($fingerprintUsages >= $maxSiblingAccounts) {
            Gdn::userModel()->addRoles($userID, [RoleModel::APPLICANT_ID], true);
        }
    }
}
