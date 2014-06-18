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
    'Author' => 'Jonh Ashton',
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

return;


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
                'senderId' => $cleanSpeak->generateUUIDFromInts($data['InsertUserID'], 0, 0, 0)
            )
        );

        if (GetValue('DiscussionID', $data)) {
            $content['content']['location'] = DiscussionUrl($data);
        }

        $UUID = $cleanSpeak->getRandomUUID($data);
        $result = $cleanSpeak->moderation($UUID, $content);

        if (GetValue('contentAction', $result) == 'allow') {
            return;
        }

        file_put_contents('/tmp/cleanspeak.log', var_export($result, true), FILE_APPEND);

        if (true == true) {
            $args['Premoderate'] = true;
            $args['ForeignID'] = $UUID;
            $args['InsertUserID'] = $this->getUserID();
        }

    }

    /**
     * Creates the Virtual Controller
     *
     * @param PluginController $sender
     */
    public function pluginController_cleanspeak_create($sender) {
        $sender->Permission('Garden.Settings.Manage');
        $sender->Title('Cleanspeak');
        $sender->AddSideMenu('plugin/Cleanspeak');
        $sender->Form = new Gdn_Form();
        $this->Dispatch($sender, $sender->RequestArgs);
    }

    public function controller_test() {
        $cs = new Cleanspeak();
        $cs->testUUID();
    }

    /**
     * @param PluginController $sender
     * @throws Gdn_UserException
     */
    public function controller_moderation($sender) {
        //    public function ModController_CleanspeakPostback_Create($sender) {
        /*
        http://localhost/api/v1/plugin.json/cleanspeak/moderation?access_token=d7db8b7f0034c13228e4761bf1bfd434
            {
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
        $queueModel = QueueModel::Instance();
        $this->setModerator();
        $id = $post['id'];
        $queueModel->deny(array('ForeignID' => $id));
        $sender->setData('Success', true);

    }

    /**
     * Handle User action post back notification.
     *
     * @param PluginController $sender
     * @throws Gdn_UserException On unknown user action
     */
    protected function userAction($sender) {
        $post = Gdn::Request()->Post();
        $this->setModerator();

        $action = $post['action'];
        $UUID = $post['userId'];

        switch (strtolower($action)) {
            case 'warn':
                $this->warnUser($UUID);
                break;
            default:
                throw new Gdn_UserException('Unknown UserAction: ' . $action);
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

        $ints = Cleanspeak::getIntsFromUUID($UUID);
        $userID = $ints[0];
        if ($ints[1] != 0 || $ints[2] != 0 || $ints[3] != 0) {
            throw new Gdn_UserException('Invalid UUID');
        }
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
        $UserID = Gdn::SQL()->GetWhere('User', array('Name' => 'Cleanspeak', 'Admin' => 2))->Value('UserID');

        if (!$UserID) {
            $UserID = Gdn::SQL()->Insert('User', array(
                    'Name' => 'Cleanspeak',
                    'Password' => RandomString('20'),
                    'HashMethod' => 'Random',
                    'Email' => 'cleanspeak@domain.com',
                    'DateInserted' => Gdn_Format::ToDateTime(),
                    'Admin' => '2'
                ));
        }
        SaveToConfig('Plugins.Cleanspeak.UserID', $UserID);

        //@todo make this part of plugin settings
//        SaveToConfig('Plugins.Cleanspeak.ApplicationID', null);

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
        return (C('Plugins.Cleanspeak.ApplicationID') && C('Plugins.Cleanspeak.UserID'));
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
     */
    public function settingsController_cleanspeak_create($sender, $args) {
        $sender->Permission('Garden.Settings.Manage');
        $sender->Title('Cleanspeak');
        $sender->AddSideMenu('plugin/Cleanspeak');
        $sender->Form = new Gdn_Form();

        $sender->Render('settings', '', 'plugins/Cleanspeak');

    }

}