<?php
/**
 * @copyright 2009-2016 Vanilla Forums, Inc.
 * @license GNU GPLv2
 */

/**
 * Class LastEditedPlugin
 */
class LastEditedPlugin extends Gdn_Plugin {

    /**
     * Add some CSS.
     *
     * @param $sender
     */
    public function assetModel_styleCss_handler($sender) {
        $sender->addCssFile('lastedited.css', 'plugins/LastEdited');
    }

    /**
     * Render after OP on single discussion.
     *
     * @param $sender
     */
    public function discussionController_afterDiscussionBody_handler($sender) {
        $this->drawEdited($sender);
    }

    /**
     * Render after comments on single discussion.
     *
     * @param $sender
     */
    public function discussionController_afterCommentBody_handler($sender) {
        $this->drawEdited($sender);
    }

    /**
     * Render on post form.
     *
     * @param $sender
     */
    public function postController_afterCommentBody_handler($sender) {
        $this->drawEdited($sender);
    }

     /**
      * Output 'edited' notice.
      *
      * @param $sender
      */
    protected function drawEdited($sender) {
        $record = $sender->data('Discussion');
        if (!$record) {
            $record = $sender->data('Record');
        }
        if (!$record) {
            return;
        }

        $permissionCategoryID = val('PermissionCategoryID', $record);

        $data = $record;
        $recordType = 'discussion';
        $recordID = val('DiscussionID', $data);

        // But override if comment
        if (isset($sender->EventArguments['Comment']) || val('RecordType', $record) == 'comment') {
            $data = $sender->EventArguments['Comment'];
            $recordType = 'comment';
            $recordID = val('CommentID', $data);
        }

        $userCanEdit = Gdn::session()->checkPermission('Vanilla.'.ucfirst($recordType).'s.Edit', true, 'Category', $permissionCategoryID);

        if (is_null($data->DateUpdated)) {
            return;
        }

        // Do not show log link if no log would have been generated.
        $elapsed = Gdn_Format::toTimestamp(val('DateUpdated', $data)) - Gdn_Format::toTimestamp(val('DateInserted', $data));
        $grace = c('Garden.Log.FloodControl', 20) * 60;
        if ($elapsed < $grace) {
            return;
        }

        $updatedUserID = $data->UpdateUserID;

        $userData = Gdn::userModel()->getID($updatedUserID);
        $userName =  val('Name', $userData, t('Unknown User'));
        $userName = htmlspecialchars($userName);

        $edited = [
            'EditUser' => $userName,
            'EditDate' => Gdn_Format::date($data->DateUpdated, 'html'),
            'EditLogUrl' => url("/log/record/{$recordType}/{$recordID}"),
            'EditWord' => 'at'
        ];

        $dateUpdateTime = Gdn_Format::toTimestamp($data->DateUpdated);
        if (date('ymd', $dateUpdateTime) != date('ymd')) {
            $edited['EditWord'] = 'on';
        }

        $format = t('PostEdited.Plain', 'Post edited by {EditUser} {EditWord} {EditDate}');
        if ($userCanEdit) {
            $format = t('PostEdited.Log', 'Post edited by {EditUser} {EditWord} {EditDate} (<a href="{EditLogUrl}">log</a>)');
        }

        echo '<div class="PostEdited">'.formatString($format, $edited).'</div>';
    }
}
