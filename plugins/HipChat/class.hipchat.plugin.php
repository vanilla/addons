<?php
/**
 * A HipChat integration.
 *
 * @copyright 2008-2016 Vanilla Forums, Inc.
 * @license GNU GPLv2
 */

$PluginInfo['HipChat'] = array(
    'Description' => 'HipChat integration, first version: Posts every new discussion to HipChat. Requires cURL.',
    'Version' => '0.3',
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
     * Post every new discussion to HipChat.
     *
     * @param PostController $sender
     * @param array $args
     */
    public function discussionModel_afterSaveDiscussion_handler($sender, $args) {
        // Make sure we have a valid discussion.
        if (!$args['Discussion'] || !val('DiscussionID', $args['Discussion'])) {
            return;
        }

        // Only trigger for new discussions.
        if (!$args['Insert']) {
            return;
        }

        // Prep HipChat message.
        $author = Gdn::userModel()->getID(val('InsertUserID', $args['Discussion']));
        $message = sprintf(
            '%1$s: %2$s',
            userAnchor($author),
            anchor(val('Name', $args['Discussion']), discussionUrl($args['Discussion']))
        );

        // Say it.
        HipChat::say($message);
    }

    /**
     * Post every new registration to HipChat.
     *
     * @param UserModel $sender
     * @param array $args
     */
    public function userModel_afterInsertUser_handler($sender, $args) {
        // Determine how to link their name.
        $name = val('Name', $args['InsertFields']);
        if (c('Garden.Registration.Method') == 'Approval') {
            $user = anchor($name, '/user/applicants', '', array('WithDomain' => true));
            $reason = sliceParagraph(val('DiscoveryText', $args['InsertFields']));
            $message = sprintf(t('New member: %1$s (%2$s)'), $user, $reason);
        } else {
            // Use conservative name linking structure.
            $user = anchor($name, '/profile/'.$args['InsertUserID'].'/'.$name, '', array('WithDomain' => true));
            $message = sprintf(t('New member: %1$s'), $user);
        }

        // Say it.
        HipChat::say($message);
    }

    /**
     * Run once on enable.
     */
    public function setup() {
    }
}
