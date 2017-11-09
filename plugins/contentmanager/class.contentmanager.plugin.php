<?php
/**
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU GPL v2
 */

/**
 * Class ContentManagerPlugin
 */
class ContentManagerPlugin extends Gdn_Plugin {
    /**
     * Executed when the plugin is enabled.
     */
    public function setup() {
        $this->structure();
    }

    /**
     * Create tables to hold rules and actions.
     */
    public function structure() {
        // Create table for possible actions.
        Gdn::structure()->table('ContentManagerAction')
            ->primaryKey('ContentManagerActionID')
            ->column('Name', 'varchar(192)', true)
            ->column('Body', 'varchar(192)', true)
            ->column('TableName', 'varchar(192)', true)
            ->column('ColumnName', 'varchar(192)', true)
            ->set(false, false);

        // Discussion.Name => MoveToSpamQueue
        Gdn::sql()->insert(
            'ContentManagerAction',
            [
                'Name' => 'MoveToSpamQueue',
                'Body' => 'Move to spam queue.',
                'TableName' => 'Discussion',
                'ColumnName' => 'Name'
            ]
        );

        // Discussion.Body => MoveToSpamQueue
        Gdn::sql()->insert(
            'ContentManagerAction',
            [
                'Name' => 'MoveToSpamQueue',
                'Body' => 'Move to spam queue.',
                'TableName' => 'Discussion',
                'ColumnName' => 'Body'
            ]
        );

        // Comment.Body => MoveToSpamQueue
        Gdn::sql()->insert(
            'ContentManagerAction',
            [
                'Name' => 'MoveToSpamQueue',
                'Body' => 'Move to spam queue.',
                'TableName' => 'Comment',
                'ColumnName' => 'Body'
            ]
        );

        // Discussion.Name => DeleteAndBan
        Gdn::sql()->insert(
            'ContentManagerAction',
            [
                'Name' => 'DeleteAndBan',
                'Body' => 'Delete it and ban user.',
                'TableName' => 'Discussion',
                'ColumnName' => 'Name'
            ]
        );

        // Discussion.Body => DeleteAndBan
        Gdn::sql()->insert(
            'ContentManagerAction',
            [
                'Name' => 'DeleteAndBan',
                'Body' => 'Delete it and ban user.',
                'TableName' => 'Discussion',
                'ColumnName' => 'Body'
            ]
        );

        // Comment.Body => DeleteAndBan
        Gdn::sql()->insert(
            'ContentManagerAction',
            [
                'Name' => 'DeleteAndBan',
                'Body' => 'Delete it and ban user.',
                'TableName' => 'Comment',
                'ColumnName' => 'Body'
            ]
        );

        // Discussion.Name => ReportIt
        Gdn::sql()->insert(
            'ContentManagerAction',
            [
                'Name' => 'ReportIt',
                'Body' => 'Report it.',
                'TableName' => 'Discussion',
                'ColumnName' => 'Name'
            ]
        );

        // Discussion.Body => ReportIt
        Gdn::sql()->insert(
            'ContentManagerAction',
            [
                'Name' => 'ReportIt',
                'Body' => 'Report it.',
                'TableName' => 'Discussion',
                'ColumnName' => 'Body'
            ]
        );

        // Comment.Body => ReportIt
        Gdn::sql()->insert(
            'ContentManagerAction',
            [
                'Name' => 'ReportIt',
                'Body' => 'Report it.',
                'TableName' => 'Comment',
                'ColumnName' => 'Body'
            ]
        );

        // User.Reason => DeclineUser
        Gdn::sql()->insert(
            'ContentManagerAction',
            [
                'Name' => 'DeclineUser',
                'Body' => 'Decline the user.',
                'TableName' => 'User',
                'ColumnName' => 'Reason'
            ]
        );

        // User.Reason => ApproveUser
        Gdn::sql()->insert(
            'ContentManagerAction',
            [
                'Name' => 'ApproveUser',
                'Body' => 'Approve the user.',
                'TableName' => 'User',
                'ColumnName' => 'Reason'
            ]
        );

        // Any => SendAdminMessage
        Gdn::sql()->insert(
            'ContentManagerAction',
            [
                'Name' => 'SendAdminMessage',
                'Body' => 'Send message to administrators.'
            ]
        );

        // Any => SendModMessage
        Gdn::sql()->insert(
            'ContentManagerAction',
            [
                'Name' => 'SendModMessage',
                'Body' => 'Send message to moderators.'
            ]
        );

        // Create table for rules.
        Gdn::structure()->table('ContentManagerRule')
            ->primaryKey('ContentManagerRuleID')
            ->column('TableName', 'varchar(192)', false)
            ->column('ColumnName', 'varchar(192)', false)
            ->column(
                'Condition',
                ['contains', 'starts with', 'ends with', 'matches the pattern'],
                false
            )
            ->column('Pattern', 'varchar(192)', false)
            ->column('ContentManagerActionID', 'int(11)', false)
            ->set(false, false);
    }

    public function __construct() {
        // Fetch all rules to be able to quikly decide if an event must e tracked.
    }

    /**
     * Handle Discussion.Body and Discussion.Name rules.
     *
     * @param DiscussionModel $sender Instance of the calling class.
     * @param  mixed $args Event arguments.
     *
     * @return void.
     */
    public function discussionModel_afterSaveDiscussion_handler($sender, $args) {

    }

    /**
     * Handle Comment.Body rules.
     *
     * @param CommentModel $sender Instance of the calling class.
     * @param  mixed $args Event arguments.
     *
     * @return void.
     */
    public function commentModel_afterSaveComment_handler($sender, $args) {

    }

    /**
     * Handle User.Reason rules.
     *
     * @param UserModel $sender Instance of the calling class.
     * @param  mixed $args Event arguments.
     *
     * @return void.
     */
    public function userModel_afterSave_handler($sender, $args) {

    }
}
