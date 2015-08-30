<?php
/**
 * Based on the Civil Tongue plugin.
 *
 * @package CivilTongueEx
 */

$PluginInfo['CivilTongueEx'] = array(
    'Name' => 'Civil Tongue Ex',
    'Description' => 'A swear word filter for your forum. Making your forum safer for younger audiences.',
    'Version' => '1.1',
    'MobileFriendly' => true,
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'Author' => "Todd Burry",
    'AuthorEmail' => 'todd@vanillaforums.com',
    'AuthorUrl' => 'http://vanillaforums.org/profile/todd',
    'SettingsUrl' => '/dashboard/plugin/tongue',
    'SettingsPermission' => 'Garden.Settings.Manage'
);

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
     * Add settings page to Dashboard sidebar menu.
     */
    public function base_getAppsettingsMenuItems_handler(&$Sender) {
        $Menu = $Sender->EventArguments['SideMenu'];
        $Menu->addLink('Forum', t('Censored Words'), 'plugin/tongue', 'Garden.Settings.Manage', array('class' => 'nav-bad-words'));
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
        if (isset($Sender->Data['Categories'])) {
            foreach ($Sender->Data['Categories'] as &$Row) {
                if (is_array($Row)) {
                    if (isset($Row['LastTitle'])) {
                        $Row['LastTitle'] = $this->replace($Row['LastTitle']);
                    }
                } elseif (is_object($Row)) {
                    if (isset($Row->LastTitle)) {
                        $Row->LastTitle = $this->replace($Row->LastTitle);
                    }
                }
            }
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
     *
     *
     * @param $Sender
     * @param $Args
     */
    public function discussionsController_render_before($Sender, $Args) {
        $Discussions = val('Discussions', $Sender->Data);
        foreach ($Discussions as &$Discussion) {
            $Discussion->Name = $this->replace($Discussion->Name);
            $Discussion->Body = $this->replace($Discussion->Body);
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
     * @param $Sender
     */
    public function utilityController_civilPatterns_create($Sender) {
        $Patterns = $this->getPatterns();

        $Text = "What's a person to do? ass";
        $Result = array();

        foreach ($Patterns as $Pattern) {
            $r = preg_replace($Pattern, $this->Replace, $Text);
            if ($r != $Text) {
                $Result[] = $Pattern;
            }
        }

        $Sender->setData('Matches', $Result);
        $Sender->setData('Patterns', $Patterns);
        $Sender->render('Blank', 'Utility');
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
                        $Patterns[] = '`\b'.preg_quote(trim($Word), '`').'\b`is';
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
     * Cleanup poll and poll options.
     *
     * @param PollModule $Sender Sending Controller.
     * @param array $Args Sending arguments.
     */
    public function pollModule_afterLoadPoll_handler($Sender, &$Args) {
        if (empty($Args['PollOptions']) || !is_array($Args['PollOptions'])) {
            return;
        }
        if (empty($Args['Poll']) || !is_object($Args['Poll'])) {
            return;
        }
        $Args['Poll']->Name = $this->replace($Args['Poll']->Name);

        foreach ($Args['PollOptions'] as &$Option) {
            $Option['Body'] = $this->replace($Option['Body']);
        }
        $Args['Poll']->Name = $this->replace($Args['Poll']->Name);
    }
}
