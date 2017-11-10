<?php
/**
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU GPL v2
 */

/**
 * Class ContentManagerPlugin
 */
class ContentManagerPlugin extends Gdn_Plugin {
    const CACHE_KEY = 'ContentManagerRules';

    /** @var array All active rules. */
    private $rules = [];

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

        // User.DiscoveryText => DeclineUser
        Gdn::sql()->insert(
            'ContentManagerAction',
            [
                'Name' => 'DeclineUser',
                'Body' => 'Decline the user.',
                'TableName' => 'User',
                'ColumnName' => 'DiscoveryText'
            ]
        );

        // User.DiscoveryText => ApproveUser
        Gdn::sql()->insert(
            'ContentManagerAction',
            [
                'Name' => 'ApproveUser',
                'Body' => 'Approve the user.',
                'TableName' => 'User',
                'ColumnName' => 'DiscoveryText'
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
                ['Contains', 'StartsWith', 'EndsWith', 'Regex'],
                false
            )
            ->column('Pattern', 'varchar(192)', false)
            ->column('ContentManagerActionID', 'int(11)', false)
            ->set(false, false);
    }

    public function __construct() {
        $this->rules = $this->getRules();

        parent::__construct();
    }

    /**
     * Get all rules and group them by table name.
     *
     * @return array The rules.
     */
    public function getRules() {
        // Fetch all rules to be able to quickly decide if an event must be tracked.
        $rules = Gdn::cache()->get(self::CACHE_KEY);

        if ($rules !== Gdn_Cache::CACHEOP_FAILURE) {
            return $rules;
        }

        $rules = Gdn::sql()
            ->select('r.*, a.*')
            ->from('ContentManagerRule r')
            ->join(
                'ContentManagerAction a',
                'r.ContentManagerActionID = a.ContentManagerActionID',
                'left'
            )
            ->get()
            ->resultArray();

        $result = [];
        foreach ($rules as $rule) {
            $result[$rule['TableName']][] = $rule;
        }
        Gdn::cache()->store(
            self::CACHE_KEY,
            $result,
            [Gdn_Cache::FEATURE_EXPIRY => 3600] // 60 * 60 = 1 hour
        );

        return $result;
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
        if (!array_key_exists('Discussion', $this->rules)) {
            return;
        }
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
        if (!array_key_exists('Comment', $this->rules)) {
            return;
        }
    }

    /**
     * Handle User.DiscoveryText rules.
     *
     * @param UserModel $sender Instance of the calling class.
     * @param  mixed $args Event arguments.
     *
     * @return void.
     */
    public function userModel_afterSave_handler($sender, $args) {
        // Ensure there is a rule for users.
        if (!array_key_exists('User', $this->rules)) {
            return;
        }

        // Ensure user needs to be approved
        $userRoles = $sender->getRoles($args['UserID'])->resultArray();
        $userRoleIDs = array_column($userRoles, 'RoleID');
        $applicantRoleIDs = RoleModel::getDefaultRoles(RoleModel::TYPE_APPLICANT);
        if (!array_intersect($applicantRoleIDs, userRoleIDs)) {
            decho('This user is no applicant');
            // return;
        }

        $user = $sender->getID($args['UserID']);
        foreach ($this->rules['User'] as $rule) {
            $conditionMethod = 'condition'.$rule['Condition'];
            if (method_exists($this, $conditionMethod)) {
                $haystack = $user->{$rule['ColumnName']};
                $needle = $rule['Pattern'];
                $conditionResult = $this->{$conditionMethod}($needle, $haystack);
            }
        }
    }

    private function actionApproveUser($user) {

    }

    private function actionDeclineUser($user) {

    }

    public function conditionStartsWith($needle, $haystack, $caseSensitive = false) {
        if (!$caseSensitive) {
            $needle = strtolower($needle);
            $haystack = strtolower($haystack);
        }

        return mb_substr($haystack, 0, strlen($needle)) === $needle;
    }

    /**
     * Helper function to find out if one string ends with another string.
     *
     * @param string $needle The string to search for.
     * @param string $haystack The string to look in.
     * @param boolean $caseSensitive Whether the search should be case sensitive.
     *
     * @return boolean Whether $haystack ends with $needle.
     */
    public function conditionEndsWith($needle, $haystack, $caseSensitive = false) {
        if (!$caseSensitive) {
            $needle = strtolower($needle);
            $haystack = strtolower($haystack);
        }

        return mb_substr($haystack, -strlen($needle)) === $needle;
    }

    public function conditionContains($needle, $haystack, $caseSensitive = false) {
        if (!$caseSensitive) {
            $needle = strtolower($needle);
            $haystack = strtolower($haystack);
        }

        return mb_strpos($haystack, $needle) !== false;
    }

    /**
     * [conditionRegex description]
     * @param  [type] $pattern [description]
     * @param  [type] $subject [description]
     *
     * @return boolean Whether $pattern applies to $subject.
     */
    public function conditionRegex($pattern, $subject) {
        preg_match($pattern, $subject, $matches);

        return count($matches) !== 0;
    }

    /**
     * Stub for the settings.
     *
     * Saving rules must invalidate the cache!
     * @param  [type] $sender [description]
     * @return [type]         [description]
     */
    public function settingsController_contentManager_create($sender) {
        /*
        $haystack = "Dies ist ein langer Text. Zumindest so lang, dass er sich für einige Tests eignet...";
        decho($haystack);
        $pattern = "/dies.*so/";
        decho($this->conditionRegex($pattern, $haystack), $pattern);
        $pattern = "/dies.*so/i";
        decho($this->conditionRegex($pattern, $haystack), $pattern);
        $pattern = "/\,/";
        decho($this->conditionRegex($pattern, $haystack), $pattern);
        */

       // Create table for rules.
        Gdn::structure()->table('ContentManagerRule')
            ->column(
                'Condition',
                ['Contains', 'StartsWith', 'EndsWith', 'Regex'],
                false
            )
            ->set(false, false);


        $sender = Gdn::userModel();
        $args['UserID'] = Gdn::request()->get('UserID', 2);
        $this->userModel_afterSave_handler($sender, $args);
    }
}
