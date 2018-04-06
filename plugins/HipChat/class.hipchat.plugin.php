<?php
/**
 * A HipChat integration.
 *
 * @copyright 2008-2017 Vanilla Forums, Inc.
 * @license GNU GPLv2
 */

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
        $conf->initialize([
            //'HipChat.Select' => array('Control' => 'Dropdown', 'LabelCode' => '@'.sprintf(t('Max number of %s'), t('images')),
                // 'Items' => array('Unlimited' => t('Unlimited'), 'None' => t('None'), 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5)),
            'HipChat.Account' => [
                'Control' => 'TextBox',
                'Type' => 'string',
                'LabelCode' => t('Account Name'),
                'Options' => ['class' => 'InputBox LargeInput']
            ],
            'HipChat.Room' => [
                'Control' => 'TextBox',
                'Type' => 'int',
                'LabelCode' => t('Room ID'),
                'Options' => ['class' => 'InputBox SmallInput']
            ],
            'HipChat.Token' => [
                'Control' => 'TextBox',
                'Type' => 'string',
                'LabelCode' => t('Authorization Token'),
                'Options' => ['class' => 'InputBox LargeInput']
            ],
            'HipChat.PostComments' => [
                'Control' => 'CheckBox',
                'LabelCode' => t('Post comments to HipChat'),
                'Default' => false
            ],
        ]);

        $sender->addSideMenu();
        $sender->setData('Title', sprintf(t('%s Settings'), 'HipChat'));
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
        // Make sure we have a valid discussion. Only trigger for new discussions.
        if (!$args['Discussion'] || !val('DiscussionID', $args['Discussion']) || !$args['Insert']) {
            return;
        }

        // Prep HipChat message.
        $author = Gdn::userModel()->getID(val('InsertUserID', $args['Discussion']));
        $message = sprintf(
            '%1$s started a discussion: %2$s',
            userAnchor($author),
            anchor(val('Name', $args['Discussion']), discussionUrl($args['Discussion']))
        );

        // Say it.
        HipChat::say($message);
    }

    /**
     * Optionally post every new comment to HipChat.
     *
     * @param PostController $sender
     * @param array $args
     */
    public function commentModel_afterSaveComment_handler($sender, $args) {
        // Only post comments if enabled.
        if (!c('HipChat.PostComments')) {
            return;
        }

        // Make sure we have a valid comment and that it's new.
        if (val('CommentID', $args) && val('Insert', $args)) {
            return;
        }

        // Why would Vanilla pass the discussion data? That would be too easy.
        $discussionModel = new DiscussionModel();
        $discussion = $discussionModel->getID($args['CommentData']['DiscussionID'], DATASET_TYPE_ARRAY);

        // Get the comment data fresh so we don't get polluted info.
        $commentModel = new CommentModel();
        $comment = $commentModel->getID(val('CommentID', $args));

        // Prep HipChat message.
        $author = Gdn::userModel()->getID(val('InsertUserID', $comment));
        $message = sprintf(
            '%1$s commented on %2$s',
            userAnchor($author),
            anchor(val('Name', $discussion), discussionUrl($discussion))
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
            $user = anchor($name, '/user/applicants', '', ['WithDomain' => true]);
            $reason = sliceParagraph(val('DiscoveryText', $args['InsertFields']));
            $message = sprintf(t('New member: %1$s (%2$s)'), $user, $reason);
        } else {
            // Use conservative name linking structure.
            $user = anchor($name, '/profile/'.$args['InsertUserID'].'/'.$name, '', ['WithDomain' => true]);
            $message = sprintf(t('New member: %1$s'), $user);
        }

        // Say it.
        HipChat::say($message);
    }
}
