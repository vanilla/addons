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
        Gdn::structure()->table('ContentManagerAction')
            ->primaryKey('ContentManagerActionID')
            ->column('Name', 'varchar(192)', true)
            ->column('Body', 'varchar(192)', true)
            ->column('TableName', 'varchar(192)', true)
            ->column('ColumnName', 'varchar(192)', true)
            ->set(false, false);

        /*
        TODO: Insert actions:

        Activity.Story, Discussion.Title, Discussion.Body, Comment.Body
        'MoveToSpamQueue', 'Move to spam queue.'
        'DeleteAndBan', 'Delete it and ban user.'
        'ReportIt', 'Report it.' (if Reporting2 enabled) 

        User.Reason
        'DeclineUser.', 'Decline the user.'
        'ApproveUser', 'Approve the user.'

        Null.Null = Any
        'SendMessage', 'Send me a message.'
        Who is me? Better to have 2 actions? Like "Send message to mods & admins", "Send message to admins"
        */

        // "When {recordtype.field} {condition} {pattern}, then {action}."
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
}
