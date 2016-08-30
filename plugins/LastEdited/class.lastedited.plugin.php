<?php
/**
 * @copyright 2009-2016 Vanilla Forums, Inc.
 * @license GNU GPLv2
 */

$PluginInfo['LastEdited'] = [
    'Name' => 'Last Edited',
    'Description' => 'Appends "Post edited by [User] at [Time]" to the end of edited posts and links to change log.',
    'Version' => '1.2',
    'MobileFriendly' => true,
    'RequiredApplications' => ['Vanilla' => '2.1'],
    'HasLocale' => true,
    'RegisterPermissions' => false,
    'Author' => "Tim Gunter",
    'AuthorEmail' => 'tim@vanillaforums.com',
    'AuthorUrl' => 'http://www.vanillaforums.com',
    'Icon' => 'last-edited.png'
];

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
      * @param $Sender
      */
    protected function drawEdited($Sender) {
        $Record = $Sender->data('Discussion');
        if (!$Record) {
            $Record = $Sender->data('Record');
        }
        if (!$Record) {
            return;
        }

        $PermissionCategoryID = val('PermissionCategoryID', $Record);

        $Data = $Record;
        $RecordType = 'discussion';
        $RecordID = val('DiscussionID', $Data);

        // But override if comment
        if (isset($Sender->EventArguments['Comment']) || val('RecordType', $Record) == 'comment') {
            $Data = $Sender->EventArguments['Comment'];
            $RecordType = 'comment';
            $RecordID = val('CommentID', $Data);
        }

        $UserCanEdit = Gdn::session()->checkPermission('Vanilla.'.ucfirst($RecordType).'s.Edit', true, 'Category', $PermissionCategoryID);

        if (is_null($Data->DateUpdated)) {
            return;
        }

        // Do not show log link if no log would have been generated.
        $elapsed = Gdn_Format::toTimestamp(val('DateUpdated', $Data)) - Gdn_Format::toTimestamp(val('DateInserted', $Data));
        $grace = c('Garden.Log.FloodControl', 20) * 60;
        if ($elapsed < $grace) {
            return;
        }

        $UpdatedUserID = $Data->UpdateUserID;

        $UserData = Gdn::userModel()->getID($UpdatedUserID);
        $Edited = array(
            'EditUser' => val('Name', $UserData, t('Unknown User')),
            'EditDate' => Gdn_Format::date($Data->DateUpdated, 'html'),
            'EditLogUrl' => url("/log/record/{$RecordType}/{$RecordID}"),
            'EditWord' => 'at'
        );

        $DateUpdateTime = Gdn_Format::toTimestamp($Data->DateUpdated);
        if (date('ymd', $DateUpdateTime) != date('ymd')) {
            $Edited['EditWord'] = 'on';
        }

        $Format = t('PostEdited.Plain', 'Post edited by {EditUser} {EditWord} {EditDate}');
        if ($UserCanEdit) {
            $Format = t('PostEdited.Log', 'Post edited by {EditUser} {EditWord} {EditDate} (<a href="{EditLogUrl}">log</a>)');
        }

        echo '<div class="PostEdited">'.formatString($Format, $Edited).'</div>';
    }
}
