<?php
/**
 * Based on the Civil Tongue plugin.
 *
 * @package CivilTongueEx
 */

// 1.0 - Fix empty pattern when list ends in semi-colon, use non-custom permission (2012-03-12 Lincoln)

/**
 * Class CivilTonguePlugin
 */
class CivilTonguePlugin extends Gdn_Plugin {

    /** @var mixed  */
    public $Replacement;

    /**
     *
     */
    public function  __construct() {
        parent::__construct();
        $this->Replacement = c('Plugins.CivilTongue.Replacement', '');
    }

    /**
     *
     *
     * @param $Sender
     * @param $Args
     */
    public function base_filterContent_handler($Sender, $Args) {
        if (!isset($Args['String']))
            return;

        $Args['String'] = $this->replace($Args['String']);
    }

    /**
     *
     *
     * @param $Sender
     * @param array $Args
     */
    public function pluginController_tongue_create($Sender, $Args = array()) {
        $Sender->permission('Garden.Settings.Manage');
        $Sender->Form = new Gdn_Form();
        $Validation = new Gdn_Validation();
        $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
        $ConfigurationModel->setField(array('Plugins.CivilTongue.Words', 'Plugins.CivilTongue.Replacement'));
        $Sender->Form->setModel($ConfigurationModel);

        if ($Sender->Form->authenticatedPostBack() === FALSE) {

            $Sender->Form->setData($ConfigurationModel->Data);
        } else {
            $Data = $Sender->Form->formValues();

            if ($Sender->Form->save() !== FALSE)
                $Sender->StatusMessage = t("Your settings have been saved.");
        }

        $Sender->addSideMenu('plugin/tongue');
        $Sender->setData('Title', t('Civil Tongue'));
        $Sender->render($this->getView('index.php'));

    }

    /**
     *
     *
     * @param $Sender
     * @param $Args
     */
    public function profileController_render_before($Sender, $Args) {
        $this->activityController_render_before($Sender, $Args);
        $this->discussionsController_render_before($Sender, $Args);
    }

    /**
     * Clean up activities and activity comments.
     *
     * @param Controller $Sender
     * @param array $Args
     */
    public function activityController_render_before($Sender, $Args) {
        $User = val('User', $Sender);
        if ($User)
            setValue('About', $User, $this->replace(val('About', $User)));

        if (isset($Sender->Data['Activities'])) {
            $Activities =& $Sender->Data['Activities'];
            foreach ($Activities as &$Row) {
                setValue('Story', $Row, $this->replace(val('Story', $Row)));

                if (isset($Row['Comments'])) {
                    foreach ($Row['Comments'] as &$Comment) {
                        $Comment['Body'] = $this->replace($Comment['Body']);
                    }
                }

                if (val('Headline', $Row)) {
                    $Row['Headline'] = $this->replace($Row['Headline']);
                }
            }
        }

        // Reactions store their data in the Data key.
        if (isset($Sender->Data['Data']) && is_array($Sender->Data['Data'])) {
            $Data =& $Sender->Data['Data'];
            foreach ($Data as &$Row) {
                if (!is_array($Row) || !isset($Row['Body'])) {
                    continue;
                }
                setValue('Body', $Row, $this->replace(val('Body', $Row)));
            }
        }

        $CommentData = val('CommentData', $Sender);
        if ($CommentData) {
            $Result =& $CommentData->result();
            foreach ($Result as &$Row) {
                $Value = $this->replace(val('Story', $Row));
                setValue('Story', $Row, $Value);

                $Value = $this->replace(val('DiscussionName', $Row));
                setValue('DiscussionName', $Row, $Value);

                $Value = $this->replace(val('Body', $Row));
                setValue('Body', $Row, $Value);
            }
        }

        $Comments = val('Comments', $Sender->Data);
        if ($Comments) {
            $Result =& $Comments->result();
            foreach ($Result as &$Row) {
                $Value = $this->replace(val('Story', $Row));
                setValue('Story', $Row, $Value);

                $Value = $this->replace(val('DiscussionName', $Row));
                setValue('DiscussionName', $Row, $Value);

                $Value = $this->replace(val('Body', $Row));
                setValue('Body', $Row, $Value);

            }
        }

    }

    /**
     * Clean up the last title.
     *
     * @param CategoriesController $Sender
     */
    public function categoriesController_render_before($Sender) {
        $categoryTree = $Sender->data('CategoryTree');
        if ($categoryTree) {
            $this->sanitizeCategories($categoryTree);
            $Sender->setData('CategoryTree', $categoryTree);
        }

        // When category layout is table.
        $Discussions = val('Discussions', $Sender->Data, false);
        if ($Discussions) {
            foreach ($Discussions as &$Discussion) {
                $Discussion->Name = $this->replace($Discussion->Name);
                $Discussion->Body = $this->replace($Discussion->Body);
            }
        }
    }

    /**
     * Recursively replace the LastTitle field in a category tree.
     *
     * @param array $categories
     */
    protected function sanitizeCategories(array &$categories) {
        foreach ($categories as &$row) {
            if (isset($row['LastTitle'])) {
                $row['LastTitle'] = $this->replace($row['LastTitle']);
            }
            if (!empty($row['Children'])) {
                $this->sanitizeCategories($row['Children']);
            }
        }
    }

    /**
     * Cleanup discussions if category layout is Mixed
     * @param $Sender
     * @param $Args
     */
    public function categoriesController_discussions_render($Sender, $Args) {

        foreach ($Sender->CategoryDiscussionData as $discussions) {
            if (!$discussions instanceof Gdn_DataSet) {
                continue;
            }
            $r = $discussions->result();
            foreach ($r as &$row) {
                setValue('Name', $row, $this->replace(val('Name', $row)));
            }
        }
    }

    /**
     * Censor words in /discussions
     *
     * @param DiscussionsController $sender
     */
    public function discussionsController_render_before($sender) {
        $discussions = $sender->data('Discussions', []);
        if (is_array($discussions) || $discussions instanceof \Traversable) {
            foreach ($discussions as &$discussion) {
                $discussion->Name = $this->replace($discussion->Name);
                $discussion->Body = $this->replace($discussion->Body);
            }
        }
    }

    /**
     * Censor words in discussions / comments.
     *
     * @param DiscussionController $Sender Sending Controller.
     * @param array $Args Sending arguments.
     */
    public function discussionController_render_before($Sender, $Args) {
        // Process OP
        $Discussion = val('Discussion', $Sender);
        if ($Discussion) {
            $Discussion->Name = $this->replace($Discussion->Name);
            if (isset($Discussion->Body)) {
                $Discussion->Body = $this->replace($Discussion->Body);
            }
        }
        // Get comments (2.1+)
        $Comments = $Sender->data('Comments');

        // Backwards compatibility to 2.0.18
        if (isset($Sender->CommentData)) {
            $Comments = $Sender->CommentData->result();
        }

        // Process comments
        if ($Comments) {
            foreach ($Comments as $Comment) {
                $Comment->Body = $this->replace($Comment->Body);
            }
        }
        if (val('Title', $Sender->Data)) {
            $Sender->Data['Title'] = $this->replace($Sender->Data['Title']);
        }
    }

    /**
     * Clean up the search results.
     *
     * @param SearchController $Sender
     */
    public function searchController_render_before($Sender) {
        if (isset($Sender->Data['SearchResults'])) {
            $Results =& $Sender->Data['SearchResults'];
            foreach ($Results as &$Row) {
                $Row['Title'] = $this->replace($Row['Title']);
                $Row['Summary'] = $this->replace($Row['Summary']);
            }
        }
    }

    /**
     * Clean up the search results.
     *
     * @param RootController $Sender
     */
    public function rootController_bestOf_render($Sender) {
        if (isset($Sender->Data['Data'])) {
            foreach ($Sender->Data['Data'] as &$Row) {
                $Row['Name'] = $this->replace($Row['Name']);
                $Row['Body'] = $this->replace($Row['Body']);
            }
        }
    }

    /**
     *
     *
     * @param $Text
     * @return mixed
     */
    public function replace($Text) {
        $Patterns = $this->getPatterns();
        $Result = preg_replace($Patterns, $this->Replacement, $Text);
//      $Result = preg_replace_callback($Patterns, function($m) { return $m[0][0].str_repeat('*', strlen($m[0]) - 1); }, $Text);
        return $Result;
    }

    /**
     *
     *
     * @return array
     */
    public function getpatterns() {
        // Get config.
        static $Patterns = NULL;

        if ($Patterns === NULL) {
            $Patterns = array();
            $Words = c('Plugins.CivilTongue.Words', null);
            if ($Words !== null) {
                $ExplodedWords = explode(';', $Words);
                foreach ($ExplodedWords as $Word) {
                    if (trim($Word)) {
                        $Patterns[] = '`\b'.preg_quote(trim($Word), '`').'\b`isu';
                    }
                }
            }
        }
        return $Patterns;
    }

    /**
     *
     */
    public function setup() {
        // Set default configuration
        saveToConfig('Plugins.CivilTongue.Replacement', '****');
    }

    /**
     * Cleanup Emails.
     *
     * @param Gdn_Email $Sender
     */
    public function gdn_email_beforeSendMail_handler($Sender) {
        $Sender->PhpMailer->Subject = $this->replace($Sender->PhpMailer->Subject);
        $Sender->PhpMailer->Body = $this->replace($Sender->PhpMailer->Body);
        $Sender->PhpMailer->AltBody = $this->replace($Sender->PhpMailer->AltBody);
    }

    /**
     * Cleanup Inform messages.
     *
     * @param $Sender
     * @param $Args
     */
    public function notificationsController_informNotifications_handler($Sender, &$Args) {
        $Activities = val('Activities', $Args);
        foreach ($Activities as $Key => &$Activity) {
            if (val('Headline', $Activity)) {
                $Activity['Headline'] = $this->replace($Activity['Headline']);
                $Args['Activities'][$Key]['Headline'] = $this->replace($Args['Activities'][$Key]['Headline']);
            }
        }
    }

    /**
     * Cleanup private messages displayed on the messages page.
     *
     * @param $Sender
     * @param $Args
     */
    public function messagesController_beforeMessages_handler($Sender, $Args) {
        foreach ($Args['MessageData'] as &$message) {
            $body = val("Body", $message);
            if ($body) {
                $message->Body = $this->replace($body);
            }
        }
    }

    /**
     * Cleanup private messages displayed on the messages page.
     *
     * @param $Sender
     * @param $Args
     */
    public function messagesController_beforeMessagesAll_handler($Sender, $Args) {
        $conversations = val('Conversations', $Args);
        foreach ($conversations as $key => &$conversation) {
            if (val('LastBody', $conversation)) {
                $conversation['LastBody'] = $this->replace($conversation['LastBody']);
                $Args['Conversations'][$key]['LastBody'] = $this->replace($Args['Conversations'][$key]['LastBody']);
            }
        }
    }

    /**
     * Cleanup private messages displayed in the flyout.
     *
     * @param $Sender
     * @param $Args
     */
    public function messagesController_beforeMessagesPopin_handler($Sender, $Args) {
        $conversations = val('Conversations', $Args);
        foreach ($conversations as $key => &$conversation) {
            if (val('LastBody', $conversation)) {
                $conversation['LastBody'] = $this->replace($conversation['LastBody']);
                $Args['Conversations'][$key]['LastBody'] = $this->replace($Args['Conversations'][$key]['LastBody']);
            }
        }
    }

    /**
     * Filter content in conversation notifications.
     *
     * @param ConversationModel $sender The sending object.
     * @param array $args
     */
    public function conversationModel_afterAdd_handler($sender, &$args) {
        if (val('Body', $args)) {
            $args['Body'] = $this->replace($args['Body']);
        }
        if (val('Subject', $args)) {
            $args['Subject'] = $this->replace($args['Subject']);
        }
    }

    /**
     * Filter content in converation message notifications.
     *
     * @param ConversationMessageModel $sender The sending object.
     * @param array $args
     */
    public function conversationMessageModel_afterAdd_handler($sender, &$args) {
        if (val('Body', $args)) {
            $args['Body'] = $this->replace($args['Body']);
        }
        if (val('Subject', $args)) {
            $args['Subject'] = $this->replace($args['Subject']);
        }
    }

    /**
     * This view gets loaded in via ajax. We need to filter with an event before it's rendered.
     *
     * @param PollModule $sender Poll Module.
     * @param array $args Sending arguments.
     */
    public function pollModule_afterLoadPoll_handler($sender, &$args) {
        if ($options = val('PollOptions', $args)) {
            foreach ($options as &$option) {
                $option['Body'] = $this->replace($option['Body']);
            }
            $args['PollOptions'] =  $options;
        }
        if ($name = val('Name', val('Poll', $args))) {
            $args['Poll']->Name = $this->replace($name);
        }
    }

    /**
     * Replace bad words in the group list
     *
     * Vanilla's proprietary group plugin hook.
     *
     * @param SettingsController $sender Sending controller instance
     * @param array $args Event's arguments
     */
    public function groupsController_beforeGroupLists_handler($sender, $args) {
        $sections = ['MyGroups', 'NewGroups', 'Groups'];

        foreach ($sections as $section) {
            $groups = $sender->data($section);
            if ($groups) {
                foreach ($groups as &$group) {
                    $group['Name'] = $this->replace($group['Name']);
                    $group['Description'] = $this->replace($group['Description']);
                }
                $sender->setData($section, $groups);
            }
        }
    }

    /**
     * Replace bad words in the group browsing list
     *
     * Vanilla's proprietary group plugin hook.
     *
     * @param SettingsController $sender Sending controller instance
     * @param array $args Event's arguments
     */
    public function groupsController_beforeBrowseGroupList_handler($sender, $args) {
        $groups = $sender->data('Groups');
        if ($groups) {
            foreach ($groups as &$group) {
                $group['Name'] = $this->replace($group['Name']);
                $group['Description'] = $this->replace($group['Description']);
            }
            $sender->setData('Groups', $groups);
        }
    }

    /**
     * Replace bad words in the group view and the events list
     *
     * Vanilla's proprietary group plugin hook.
     *
     * @param SettingsController $sender Sending controller instance
     * @param array $args Event's arguments
     */
    public function base_groupLoaded_handler($sender, $args) {
        $args['Group']['Name'] = $this->replace($args['Group']['Name']);
        $args['Group']['Description'] = $this->replace($args['Group']['Description']);
    }

    /**
     * Replace bad words in the event list of a group
     *
     * Vanilla's proprietary group plugin hook.
     *
     * @param SettingsController $sender Sending controller instance
     * @param array $args Event's arguments
     */
    public function groupController_groupEventsLoaded_handler($sender, $args) {
        $events = &$args['Events'];
        foreach ($events as &$event) {
            $event['Name'] = $this->replace($event['Name']);
            $event['Body'] = $this->replace($event['Body']);
            $event['Location'] = $this->replace($event['Location']);
        }
    }

    /**
     * Replace bad words in the events list
     *
     * Vanilla's proprietary group plugin hook.
     *
     * @param SettingsController $sender Sending controller instance
     * @param array $args Event's arguments
     */
    public function eventsController_eventsLoaded_handler($sender, $args) {
        $sections = ['UpcomingEvents', 'RecentEvents'];

        foreach ($sections as $section) {
            $events = &$args[$section];
            foreach ($events as &$event) {
                $event['Name'] = $this->replace($event['Name']);
                $event['Body'] = $this->replace($event['Body']);
                $event['Location'] = $this->replace($event['Location']);
            }
            unset($events, $event);
        }
    }

    /**
     * Replace bad words in the event view
     *
     * Vanilla's proprietary group plugin hook.
     *
     * @param SettingsController $sender Sending controller instance
     * @param array $args Event's arguments
     */
    public function eventController_eventLoaded_handler($sender, $args) {
        $args['Event']['Name'] = $this->replace($args['Event']['Name']);
        $args['Event']['Body'] = $this->replace($args['Event']['Body']);
        $args['Event']['Location'] = $this->replace($args['Event']['Location']);

        if (isset($args['Group'])) {
            $args['Group']['Name'] = $this->replace($args['Group']['Name']);
            $args['Group']['Description'] = $this->replace($args['Group']['Description']);
        }
    }
}
