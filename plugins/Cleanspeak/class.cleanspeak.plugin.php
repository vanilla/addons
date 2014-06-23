<?php
/**
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */
// Define the plugin:
$PluginInfo['Cleanspeak'] = array(
    'Name' => 'Cleanspeak',
    'Description' => 'Cleanspeak integration for Vanilla.',
    'Version' => '0.0.1alpha',
    'RequiredApplications' => array('Vanilla' => '2.0.18'),
    'SettingsUrl' => '/settings/cleanspeak',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'Author' => 'John Ashton',
    'AuthorEmail' => 'john@vanillaforums.com',
    'AuthorUrl' => 'http://www.github.com/John0x00'
);

class CleanspeakPlugin extends Gdn_Plugin {


    /**
     * @param QueueModel $sender
     * @param $args
     */
    public function queueModel_checkpremoderation_handler($sender, &$args) {

        $cleanSpeak = new Cleanspeak();
        $args['Premoderate'] = false;

        if (!$this->isConfigured()) {
            throw new Gdn_UserException('Cleanspeak is not configured.');
            return;
        }


        $data =& $args['Data'];
        $options =& $args['Options'];

        // Make an api request to cleanspeak.
        $foreignUser = Gdn::UserModel()->GetID($data['InsertUserID'], DATASET_TYPE_ARRAY);
        if (!$foreignUser) {
            throw new Gdn_UserException('Can not find user.');
        }

        $content = array(
            'content' => array(
                'applicationId' => C('Plugins.Cleanspeak.ApplicationID'),
                'createInstant' => time(),
                'parts' => $cleanSpeak->getParts($data),
                'senderDisplayName' => $foreignUser['Name'],
                'senderId' => $cleanSpeak->getUserUUID($data['InsertUserID'])
            )
        );
        if (GetValue('DiscussionID', $data) && GetValue('Name', $data)) {
            $content['content']['location'] = DiscussionUrl($data);
        }
        $UUID = $cleanSpeak->getRandomUUID($data);

        try {
            $result = $cleanSpeak->moderation($UUID, $content, true);
        } catch (Gdn_UserException $e) {
            // Error communicating with cleanspeak
            // Content will go into premoderation queue
            // InsertUserID will not be updated.
            $args['Premoderate'] = true;
            return;
        }

        // Content is allowed
        if (GetValue('contentAction', $result) == 'allow') {
            return;
        }

        // Content is in Pre Moderation Queue
        if (GetValue('requiresApproval', $result) == 'requiresApproval'
            || GetValue('contentAction', $result) == 'queuedForApproval') {
            $args['Premoderate'] = true;
            $args['ForeignID'] = $UUID;
            $args['InsertUserID'] = $this->getUserID();
            return;
        }

        //if not handled by above; then add to queue for preapproval.
        $args['Premoderate'] = true;
        return;

    }


    public function modController_cleanspeakHubPostback_create($sender) {

        /**
         * http://localhost/api/v1/mod.json/cleanspeakHubPostback/?access_token=d7db8b7f0034c13228e4761bf1bfd434
         *
         *
         */

//        {
//        "type" : "contentApproval",
//          "approvals" : {
//              "00001b39-dc65-9308-a25e-37537c2913eb" : "approved",
//              "00001b39-dc65-9308-a25e-37537c2914eb" : "approved",
//              "00000001-dc65-9308-a25e-37537c2914eb" : "approved"
//        },
//          "moderatorId": "b00916ba-f647-4e9f-b2a6-537f69f89b87",
//          "moderatorEmail" : "catherine@email.com",
//          "moderatorExternalId": "foo-bar-baz"
//        }

        // Turns into:


//        Post to site ID: 6969
//
//        array (size=5)
//          'type' => string 'contentApproval' (length=15)
//          'approvals' =>
//            array (size=2)
//              '00001b39-dc65-9308-a25e-37537c2913eb' => string 'approved' (length=8)
//              '00001b39-dc65-9308-a25e-37537c2914eb' => string 'approved' (length=8)
//          'moderatorId' => string 'b00916ba-f647-4e9f-b2a6-537f69f89b87' (length=36)
//          'moderatorEmail' => string 'catherine@email.com' (length=19)
//          'moderatorExternalId' => string 'foo-bar-baz' (length=11)

//        Post to site ID: 1
//
//        array (size=5)
//          'type' => string 'contentApproval' (length=15)
//          'approvals' =>
//            array (size=1)
//              '00000001-dc65-9308-a25e-37537c2914eb' => string 'approved' (length=8)
//          'moderatorId' => string 'b00916ba-f647-4e9f-b2a6-537f69f89b87' (length=36)
//          'moderatorEmail' => string 'catherine@email.com' (length=19)
//          'moderatorExternalId' => string 'foo-bar-baz' (length=11)

        $post = Gdn::Request()->Post();
        Cleanspeak::fix($post, file_get_contents('php://input'));
        if (!$post) {
            throw new Gdn_UserException('Invalid Request Type');
        }
        if ($post['type'] == 'contentApproval') {
            foreach ($post['approvals'] as $UUID => $action) {

                $ints = QueueModel::getIntsFromUUID($UUID);
                $siteID = $ints[0];
                $siteApprovals[$siteID][$UUID] = $action;

            }

        }

        foreach ($siteApprovals as $siteID => $siteApproval) {
            $sitePost = array();
            $sitePost['type'] = $post['type'];
            $sitePost['approvals'] = $siteApproval;
            $sitePost['moderatorId'] = $post['moderatorId'];
            $sitePost['moderatorEmail'] = $post['moderatorEmail'];
            $sitePost['moderatorExternalId'] = $post['moderatorExternalId'];

            echo "Post to site ID: " . $siteID . "<br />\n";
            var_dump($sitePost);
        }
    }

    /**
     * @param PluginController $sender
     * @throws Gdn_UserException
     */
    public function modController_cleanspeakPostback_create($sender) {

        // Minimum Permissions needed
        $sender->Permission('Garden.Moderation.Manage');

        /*
        http://localhost/api/v1/mod.json/cleanspeakPostback/?access_token=d7db8b7f0034c13228e4761bf1bfd434            {
                "type" : "contentApproval",
                "approvals" : {
                    "8207bc26-f048-478d-8945-84f236cb5637" : "approved",
                    "86d9e3e1-5752-41dc-aa55-2a832728ec33" : "dismissed",
                    "a1fca416-5573-4662-a31a-a4ff808c34dd" : "rejected",
                    "af777ea8-1874-463c-a97c-a1f9e494bee1" : "approved",
                    "73031050-2016-44fc-b8f6-b97184793587" : "approved"
                },
                "moderatorId": "b00916ba-f647-4e9f-b2a6-537f69f89b87",
                "moderatorEmail" : "catherine@email.com",
                "moderatorExternalId": "foo-bar-baz"
            }
            {
                "type" : "contentDelete",
                "applicationId" : "63d797d4-0603-48f7-8fef-5008edc670dd",
                "id" : "3f8f66cb-d933-4e5e-a76d-5b3a4d9209cd",
                "moderatorId": "b00916ba-f647-4e9f-b2a6-537f69f89b87",
                "moderatorEmail" : "catherine@email.com",
                "moderatorExternalId": "foo-bar-baz"
            }
            {
                "type" : "userAction",
                "action" : "Warn",
                "applicationIds" : [ "2c84ed53-6b75-4bef-ab68-eddb9ee253b4" ],
                "comment" : "a comment",
                "key" : "Language",
                "userId" : "f9caf789-b316-4233-bd62-19f8fb649275",
                "moderatorId": "b00916ba-f647-4e9f-b2a6-537f69f89b87",
                "moderatorEmail" : "catherine@email.com",
                "moderatorExternalId": "foo-bar-baz"
            }

        */

        $post = Gdn::Request()->Post();
        Cleanspeak::fix($post, file_get_contents('php://input'));
        if (!$post) {
            throw new Gdn_UserException('Invalid Request Type');
        }

        $type = $post['type'];
        switch ($type) {
            case 'contentApproval':
                $this->contentApproval($sender);
                break;
            case 'contentDelete':
                $this->contentDelete($sender);
                break;
            case 'userAction':
                $this->userAction($sender);
                break;
            default:
                throw new Gdn_UserException('Unknown moderation type: ' . $type);
        }

        $sender->Render('blank', 'utility', 'dashboard');

    }

    /**
     * Set Moderator information from post.
     */
    protected function setModerator() {
        $post = Gdn::Request()->Post();
        Cleanspeak::fix($post, file_get_contents('php://input'));
        $queueModel = QueueModel::Instance();
        $queueModel->setModerator(
            $this->getModeratorUserID(
                array(
                    "moderatorId" => $post['moderatorId'],
                    "moderatorEmail" => $post['moderatorEmail'],
                    "moderatorExternalId" => $post['moderatorExternalId']
                )
            )
        );
    }

    /**
     * Handle content approval post back notification.
     *
     * @param PluginController $sender
     * @throws Gdn_UserException if unknown action.
     */
    protected function contentApproval($sender) {
        $post = Gdn::Request()->Post();
        Cleanspeak::fix($post, file_get_contents('php://input'));

        // Content Approval
        $queueModel = QueueModel::Instance();
        $this->setModerator();

        foreach ($post['approvals'] as $UUID => $action) {
            switch ($action) {
                case 'approved':
                    $result = $queueModel->approveWhere(array('ForeignID' => $UUID));
                break;
                case 'dismissed':
                    $queueModel->approveWhere(array('ForeignID' => $UUID));
                    break;
                case 'rejected':
                    $queueModel->denyWhere(array('ForeignID' => $UUID));
                    break;
                default:
                    throw new Gdn_UserException('Unknown action.');
            }
        }

        if (!$result) {
            $sender->SetData('Errors', $queueModel->ValidationResults());
        }

    }

    /**
     * Handle content removal post back notification.
     *
     * @param PluginController $sender
     */
    protected function contentDelete($sender) {
        $post = Gdn::Request()->Post();
        Cleanspeak::fix($post, file_get_contents('php://input'));

        $queueModel = QueueModel::Instance();
        $this->setModerator();
        $id = $post['id'];
        $deleted = $queueModel->deny(array('ForeignID' => $id));
        if ($deleted) {
            $sender->setData('Success', true);
        } else {
            $sender->SetData('Errors', 'Error deleting content.');
        }

    }

    /**
     * Handle User action post back notification.
     *
     * @param PluginController $sender
     * @throws Gdn_UserException On unknown user action
     */
    protected function userAction($sender) {
        $post = Gdn::Request()->Post();
        $cleanspeak = Cleanspeak::Instance();
        Cleanspeak::fix($post, file_get_contents('php://input'));

        $this->setModerator();
        $action = $post['action'];
        $UUID = $post['userId'];
        switch (strtolower($action)) {
            case 'warn':
                $this->warnUser($UUID);
                break;
            case 'ban':
                $sender->Permission(array('Garden.Moderation.Manage','Garden.Users.Edit','Moderation.Users.Ban'), FALSE);
                $this->BanUser($UUID);
                break;
            case 'unban':
                $sender->Permission(array('Garden.Moderation.Manage','Garden.Users.Edit','Moderation.Users.Ban'), FALSE);
                $this->BanUser($UUID, true);
                break;
            default:
                throw new Gdn_UserException('Unknown UserAction: ' . $action);
        }

    }

    /**
     * Ban/Unban a user.
     *
     * @param string $UUID Unique User ID.
     * @param bool $unBan Set to true to un-ban a user.
     * @return bool user was ban/unbanned.
     * @throws Exception User not found, Attempt to remove system acccount.
     */
    protected function banUser($UUID, $unBan = false) {

        $userID = Cleanspeak::getUserIDFromUUID($UUID);
        $restoreContent = true;
        $deleteContent = true;

        //@todo Use cleanspeak reason.
        $reason = 'Cleanspeak: Moderator Banned.';

        $user = Gdn::UserModel()->GetID($userID, DATASET_TYPE_ARRAY);
        if (!$user) {
            throw NotFoundException('User');
        }

        $userModel = Gdn::UserModel();

        // Block banning the superadmin or System accounts
        $user = $userModel->GetID($userID);
        if (GetValue('Admin', $user) == 2) {
            throw ForbiddenException("@You may not ban a System user.");
        } elseif (GetValue('Admin', $user)) {
            throw ForbiddenException("@You may not ban a user with the Admin flag set.");
        }


        if ($unBan) {
            $userModel->Unban($userID, array('RestoreContent' => $restoreContent));
        } else {
            // Just because we're banning doesn't mean we can nuke their content
            $deleteContent = (CheckPermission('Garden.Moderation.Manage')) ? $deleteContent : FALSE;
            $userModel->Ban($userID, array('Reason' => $reason, 'DeleteContent' => $deleteContent));
        }

    }

    /**
     * Warn a user.
     *
     * @param string $UUID Unique user identification
     * @param string $reason
     * @throws Gdn_UserException Error sending message to user.
     */
    protected function warnUser($UUID, $reason = '') {
        $cleanspeak = Cleanspeak::Instance();
        $userID = $cleanspeak->getUserIDFromUUID($UUID);
        $user = Gdn::UserModel()->GetID($userID);
        if (!$user) {
            throw new Gdn_UserException('User not found: '. $UUID);
        }

        // Send a message to the person being warned.
        $Model = new ConversationModel();
        $MessageModel = new ConversationMessageModel();

        switch ($reason) {
            default:
                $body = 'You have been warned.';
        }

        $Row = array(
            'Subject' => T('HeadlineFormat.Warning.ToUser', "You've been warned."),
            'Type' => 'warning',
            'Body' => $body,
            'Format' => C('Garden.InputFormatter'),
            'RecipientUserID' => (array)$userID
        );

        $ConversationID = $Model->Save($Row, $MessageModel);
        if ($ConversationID) {
            throw new Gdn_UserException('Error sending message to user');
        }


    }

    public function setup() {

        // Get a user for operations.
        $userID = Gdn::SQL()->GetWhere('User', array('Name' => 'Cleanspeak', 'Admin' => 2))->Value('UserID');

        if (!$userID) {
            $userID = Gdn::SQL()->Insert('User', array(
                    'Name' => 'Cleanspeak',
                    'Password' => RandomString('20'),
                    'HashMethod' => 'Random',
                    'Email' => 'cleanspeak@domain.com',
                    'DateInserted' => Gdn_Format::ToDateTime(),
                    'Admin' => '2'
                ));
        }
        SaveToConfig('Plugins.Cleanspeak.UserID', $userID);

    }

    /**
     * Get cleanspeak UserID from config.
     * @return mixed Int or NULL.
     */
    public function getUserID() {
        return C('Plugins.Cleanspeak.UserID', NULL);
    }

    /**
     * Check to see if plugin is configured.
     * @return bool
     */
    public function isConfigured() {

        return (C('Plugins.Cleanspeak.ApplicationID')
            && C('Plugins.Cleanspeak.UserID')
            && C('Plugins.Cleanspeak.ApiUrl')
        );
    }

    /**
     * Get the moderator user id.
     *
     * @param array $moderator Moderator information from postback
     *  [moderatorID]
     *  [moderatorEmail]
     *  [moderatorExternalId]
     *
     * @return bool
     */
    public function getModeratorUserID($moderator) {
        $userID = false;

        $id = GetValue('moderatorExternalId', $moderator);
        if ($id) {
            $user = Gdn::UserModel()->GetID($id, DATASET_TYPE_ARRAY);
            if ($user) {
                $userID = $user['UserID'];
            }
        }

        $email = GetValue('moderatorEmail', $moderator);
        if ($email) {
            $user = Gdn::UserModel()->GetWhere(array('Email' => $email))->ResultArray();
            if (sizeof($user) == 1) {
                $userID = $user[0]['UserID'];
            }
        }

        return $userID;
    }

    /**
     * Plugin settings page.
     *
     * @param SettingsController $sender Sending Controller,
     * @param array $args Sending Arguments
     */
    public function settingsController_cleanspeak_create($sender, $args) {
        $sender->Permission('Garden.Settings.Manage');
        $sender->Title('Cleanspeak');
        $sender->AddSideMenu('plugin/Cleanspeak');
        $sender->Form = new Gdn_Form();

        $validation = new Gdn_Validation();
        $configurationModel = new Gdn_ConfigurationModel($validation);
        $configurationModel->SetField(array(
                'ApiUrl',
                'ApplicationID',
            ));
        // Set the model on the form.
        $sender->Form->SetModel($configurationModel);

        if ($sender->Form->AuthenticatedPostBack() === FALSE) {
            // Apply the config settings to the form.
            $sender->Form->SetData($configurationModel->Data);
        } else {
            $FormValues = $sender->Form->FormValues();
            if ($sender->Form->IsPostBack()) {
                $sender->Form->ValidateRule('ApplicationID', 'function:ValidateRequired', 'Application ID is required');
                $sender->Form->ValidateRule('ApiUrl', 'function:ValidateRequired', 'Api Url is required');

                if ($sender->Form->ErrorCount() == 0) {
                    SaveToConfig('Plugins.Cleanspeak.ApplicationID', $FormValues['ApplicationID']);
                    SaveToConfig('Plugins.Cleanspeak.ApiUrl', $FormValues['ApiUrl']);
                    $sender->InformMessage(T('Settings updated.'));
                } else {
                    $sender->InformMessage(T("Error saving settings to config."));
                }


            }
        }

        $sender->Form->SetValue('ApplicationID', C('Plugins.Cleanspeak.ApplicationID'));
        $sender->Form->SetValue('ApiUrl', C('Plugins.Cleanspeak.ApiUrl'));

        $sender->Render($this->GetView('settings.php'));


    }

}