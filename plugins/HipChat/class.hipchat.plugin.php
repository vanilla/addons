<?php
/**
 * A HipChat integration.
 *
 * @copyright 2008-2016 Vanilla Forums, Inc.
 * @license GNU GPLv2
 */

$PluginInfo['HipChat'] = array(
    'Description' => 'HipChat integration, first version: Posts every new discussion to HipChat. Requires cURL.',
    'Version' => '0.1',
    'RequiredApplications' => array('Vanilla' => '2.2'),
    'License' => 'GNU GPL2',
    'SettingsUrl' => '/settings/hipchat',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'Author' => "Lincoln Russell"
);

/**
 * Class HipChatPlugin.
 */
class HipChatPlugin extends Gdn_Plugin {

    /**
     * Settings page.
     *
     * @param SettingsController $sender
     */
    public function settingsController_hipChat_create($sender) {
        $sender->permission('Garden.Settings.Manage');
        $conf = new ConfigurationModule($sender);
        $conf->initialize(array(
            //'HipChat.Checkbox' => array('Control' => 'CheckBox', 'LabelCode' => 'A checkbox to tick'),
            //'HipChat.Select' => array('Control' => 'Dropdown', 'LabelCode' => '@'.sprintf(t('Max number of %s'), t('images')),
                // 'Items' => array('Unlimited' => t('Unlimited'), 'None' => t('None'), 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5)),

            'HipChat.Account' => array(
                'Control' => 'TextBox',
                'Type' => 'string',
                'LabelCode' => t('Account Name'),
                'Options' => array('class' => 'InputBox LargeInput')
            ),
            'HipChat.Room' => array(
                'Control' => 'TextBox',
                'Type' => 'int',
                'LabelCode' => t('Room ID'),
                'Options' => array('class' => 'InputBox SmallInput')
            ),
            'HipChat.Token' => array(
                'Control' => 'TextBox',
                'Type' => 'string',
                'LabelCode' => t('Authorization Token'),
                'Options' => array('class' => 'InputBox LargeInput')
            ),
        ));

        $sender->addSideMenu();
        $sender->setData('Title', sprintf(T('%s Settings'), 'HipChat'));
        $sender->ConfigurationModule = $conf;
        $conf->renderAll();
    }

    /**
     * Send a notification to a HipChat room.
     *
     * @param string $message
     * @param string $color
     */
    protected static function sayInHipChat($message = '', $color = "green") {
        if (!c('HipChat.Token') || !c('HipChat.Account')) {
            return;
        }

        // Prepare our API endpoint.
        $url = sprintf('https://%1$s.hipchat.com/v2/room/%2$s/notification?auth_token=%3$s',
            c('HipChat.Account'),
            c('HipChat.Room'),
            c('HipChat.Token')
        );

        $data = ["color" => $color, "message" => $message, "notify" => false, "message_format" => "html"];
        $data = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Post every new discussion to HipChat.
     *
     * @param PostController $sender
     * @param array $args
     */
    public function postController_afterDiscussionSave_handler($sender, $args) {
        if (!$args['Discussion'] || !$discussionID = val('DiscussionID', $args['Discussion'])) {
            return;
        }

        // Prep HipChat message.
        $author = Gdn::userModel()->getID(val('InsertUserID', $args['Discussion']));
        $message = sprintf('%1$s: %2$s',
            userAnchor($author),
            anchor(val('Name', $args['Discussion']), discussionUrl($args['Discussion']))
        );

        // Say it.
        self::sayInHipChat($message);
    }

    /**
     * Run once on enable.
     */
    public function setup() {
    }
}
