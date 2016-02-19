<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */

// Define the plugin:
$PluginInfo['QnA'] = array(
    'Name' => 'Q&A',
    'Description' => "Users may designate a discussion as a Question and then officially accept one or more of the comments as the answer.",
    'Version' => '1.2.4',
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'MobileFriendly' => true,
    'Author' => 'Todd Burry',
    'AuthorEmail' => 'todd@vanillaforums.com',
    'AuthorUrl' => 'http://www.vanillaforums.org/profile/todd'
);

/**
 * Adds Question & Answer format to Vanilla.
 *
 * You can set Plugins.QnA.UseBigButtons = true in config to separate 'New Discussion'
 * and 'Ask Question' into "separate" forms each with own big button in Panel.
 */
class QnAPlugin extends Gdn_Plugin {
    /// PROPERTIES ///

    protected $Reactions = false;
    protected $Badges = false;

    /// METHODS ///

    public function __construct() {
        parent::__construct();

        if (Gdn::pluginManager()->checkPlugin('Reactions') && c('Plugins.QnA.Reactions', true)) {
            $this->Reactions = true;
        }

        if (Gdn::applicationManager()->checkApplication('Reputation') && c('Plugins.QnA.Badges', true)) {
            $this->Badges = true;
        }

    }

    public function setup() {
        $this->structure();
    }

    public function structure() {
        Gdn::structure()
            ->table('Discussion');

        $QnAExists = Gdn::structure()->columnExists('QnA');
        $DateAcceptedExists = Gdn::structure()->columnExists('DateAccepted');

        Gdn::structure()
            ->column('QnA', array('Unanswered', 'Answered', 'Accepted', 'Rejected'), null, 'index')
            ->column('DateAccepted', 'datetime', true) // The
            ->column('DateOfAnswer', 'datetime', true) // The time to answer an accepted question.
            ->set();

        Gdn::structure()
            ->table('Comment')
            ->column('QnA', array('Accepted', 'Rejected'), null)
            ->column('DateAccepted', 'datetime', true)
            ->column('AcceptedUserID', 'int', true)
            ->set();

        Gdn::structure()
            ->table('User')
            ->column('CountAcceptedAnswers', 'int', '0')
            ->set();

        Gdn::SQL()->replace(
            'ActivityType',
            array('AllowComments' => '0', 'RouteCode' => 'question', 'Notify' => '1', 'Public' => '0', 'ProfileHeadline' => '', 'FullHeadline' => ''),
            array('Name' => 'QuestionAnswer'), true);
        Gdn::SQL()->replace(
            'ActivityType',
            array('AllowComments' => '0', 'RouteCode' => 'answer', 'Notify' => '1', 'Public' => '0', 'ProfileHeadline' => '', 'FullHeadline' => ''),
            array('Name' => 'AnswerAccepted'), true);

        if ($QnAExists && !$DateAcceptedExists) {
            // Default the date accepted to the accepted answer's date.
            $Px = Gdn::database()->DatabasePrefix;
            $Sql = "update {$Px}Discussion d set DateAccepted = (select min(c.DateInserted) from {$Px}Comment c where c.DiscussionID = d.DiscussionID and c.QnA = 'Accepted')";
            Gdn::SQL()->query($Sql, 'update');
            Gdn::SQL()->update('Discussion')
                ->set('DateOfAnswer', 'DateAccepted', false, false)
                ->put();

            Gdn::SQL()->update('Comment c')
                ->join('Discussion d', 'c.CommentID = d.DiscussionID')
                ->set('c.DateAccepted', 'c.DateInserted', false, false)
                ->set('c.AcceptedUserID', 'd.InsertUserID', false, false)
                ->where('c.QnA', 'Accepted')
                ->where('c.DateAccepted', null)
                ->put();
        }

        $this->structureReactions();
        $this->structureBadges();
    }

    /**
     * Define all of the structure related to badges.
     */
    public function structureBadges() {
        // Define 'Answer' badges
        if (!$this->Badges || !class_exists('BadgeModel'))
            return;

        $BadgeModel = new BadgeModel();

        // Answer Counts
        $BadgeModel->define(array(
            'Name' => 'First Answer',
            'Slug' => 'answer',
            'Type' => 'UserCount',
            'Body' => 'Answering questions is a great way to show your support for a community!',
            'Photo' => 'http://badges.vni.la/100/answer.png',
            'Points' => 2,
            'Attributes' => array('Column' => 'CountAcceptedAnswers'),
            'Threshold' => 1,
            'Class' => 'Answerer',
            'Level' => 1,
            'CanDelete' => 0
        ));
        $BadgeModel->define(array(
            'Name' => '5 Answers',
            'Slug' => 'answer-5',
            'Type' => 'UserCount',
            'Body' => 'Your willingness to share knowledge has definitely been noticed.',
            'Photo' => 'http://badges.vni.la/100/answer-2.png',
            'Points' => 3,
            'Attributes' => array('Column' => 'CountAcceptedAnswers'),
            'Threshold' => 5,
            'Class' => 'Answerer',
            'Level' => 2,
            'CanDelete' => 0
        ));
        $BadgeModel->define(array(
            'Name' => '25 Answers',
            'Slug' => 'answer-25',
            'Type' => 'UserCount',
            'Body' => 'Looks like you&rsquo;re starting to make a name for yourself as someone who knows the score!',
            'Photo' => 'http://badges.vni.la/100/answer-3.png',
            'Points' => 5,
            'Attributes' => array('Column' => 'CountAcceptedAnswers'),
            'Threshold' => 25,
            'Class' => 'Answerer',
            'Level' => 3,
            'CanDelete' => 0
        ));
        $BadgeModel->define(array(
            'Name' => '50 Answers',
            'Slug' => 'answer-50',
            'Type' => 'UserCount',
            'Body' => 'Why use Google when we could just ask you?',
            'Photo' => 'http://badges.vni.la/100/answer-4.png',
            'Points' => 10,
            'Attributes' => array('Column' => 'CountAcceptedAnswers'),
            'Threshold' => 50,
            'Class' => 'Answerer',
            'Level' => 4,
            'CanDelete' => 0
        ));
        $BadgeModel->define(array(
            'Name' => '100 Answers',
            'Slug' => 'answer-100',
            'Type' => 'UserCount',
            'Body' => 'Admit it, you read Wikipedia in your spare time.',
            'Photo' => 'http://badges.vni.la/100/answer-5.png',
            'Points' => 15,
            'Attributes' => array('Column' => 'CountAcceptedAnswers'),
            'Threshold' => 100,
            'Class' => 'Answerer',
            'Level' => 5,
            'CanDelete' => 0
        ));
        $BadgeModel->define(array(
            'Name' => '250 Answers',
            'Slug' => 'answer-250',
            'Type' => 'UserCount',
            'Body' => 'Is there *anything* you don&rsquo;t know?',
            'Photo' => 'http://badges.vni.la/100/answer-6.png',
            'Points' => 20,
            'Attributes' => array('Column' => 'CountAcceptedAnswers'),
            'Threshold' => 250,
            'Class' => 'Answerer',
            'Level' => 6,
            'CanDelete' => 0
        ));
    }

    /**
     * Define all of the structure releated to reactions.
     * @return type
     */
    public function structureReactions() {
        // Define 'Accept' reaction
        if (!$this->Reactions)
            return;

        $Rm = new ReactionModel();

        if (Gdn::structure()->table('ReactionType')->columnExists('Hidden')) {

            // AcceptAnswer
            $Rm->defineReactionType(array('UrlCode' => 'AcceptAnswer', 'Name' => 'Accept Answer', 'Sort' => 0, 'Class' => 'Positive', 'IncrementColumn' => 'Score', 'IncrementValue' => 5, 'Points' => 3, 'Permission' => 'Garden.Curation.Manage', 'Hidden' => 1,
                'Description' => "When someone correctly answers a question, they are rewarded with this reaction."));

        }

        Gdn::structure()->reset();
    }


    /// EVENTS ///

    public function base_addonEnabled_handler($Sender, $Args) {
        switch (strtolower($Args['AddonName'])) {
            case 'reactions':
                $this->Reactions = true;
                $this->structureReactions();
                break;
            case 'reputation':
                $this->Badges = true;
                $this->structureBadges();
                break;
        }
    }

    public function base_beforeCommentDisplay_handler($Sender, $Args) {
        $QnA = getValueR('Comment.QnA', $Args);

        if ($QnA && isset($Args['CssClass'])) {
            $Args['CssClass'] = concatSep(' ', $Args['CssClass'], "QnA-Item-$QnA");
        }
    }

    public function base_discussionTypes_handler($Sender, $Args) {
        $Args['Types']['Question'] = array(
            'Singular' => 'Question',
            'Plural' => 'Questions',
            'AddUrl' => '/post/question',
            'AddText' => 'Ask a Question'
        );
    }

    /**
     *
     * @param Gdn_Controller $Sender
     * @param array $Args
     */
//   public function Base_AfterReactions_Handler($Sender, $Args) {
//   // public function Base_CommentOptions_Handler($Sender, $Args) {
//      $Discussion = GetValue('Discussion', $Args);
//      $Comment = GetValue('Comment', $Args);
//
//      if (!$Comment)
//         return;
//
//      $CommentID = GetValue('CommentID', $Comment);
//      if (!is_numeric($CommentID))
//         return;
//
//      if (!$Discussion) {
//         static $DiscussionModel = null;
//         if ($DiscussionModel === null)
//            $DiscussionModel = new DiscussionModel();
//         $Discussion = $DiscussionModel->GetID(GetValue('DiscussionID', $Comment));
//      }
//
//      if (!$Discussion || strtolower(GetValue('Type', $Discussion)) != 'question')
//         return;
//
//      // Check permissions.
//      $CanAccept = Gdn::Session()->CheckPermission('Garden.Moderation.Manage');
//      $CanAccept |= Gdn::Session()->UserID == GetValue('InsertUserID', $Discussion) && Gdn::Session()->UserID != GetValue('InsertUserID', $Comment);
//
//      if (!$CanAccept)
//         return;
//
//      $QnA = GetValue('QnA', $Comment);
//      if ($QnA)
//         return;
//
//      // Write the links.
//      $Types = GetValue('ReactionTypes', $Sender->EventArguments);
//      if ($Types)
//         echo Bullet();
//
//      $Query = http_build_query(array('commentid' => $CommentID, 'tkey' => Gdn::Session()->TransientKey()));
//      echo Anchor(Sprite('ReactAccept', 'ReactSprite').T('Accept', 'Accept'), '/discussion/qna/accept?'.$Query, array('class' => 'React QnA-Yes', 'title' => T('Accept this answer.')));
//      echo Anchor(Sprite('ReactReject', 'ReactSprite').T('Reject', 'Reject'), '/discussion/qna/reject?'.$Query, array('class' => 'React QnA-No', 'title' => T('Reject this answer.')));
//
//      static $InformMessage = true;
//
//      if ($InformMessage && Gdn::Session()->UserID == GetValue('InsertUserID', $Discussion) && in_array(GetValue('QnA', $Discussion), array('', 'Answered'))) {
//         $Sender->InformMessage(T('Click accept or reject beside an answer.'), 'Dismissable');
//         $InformMessage = false;
//      }
//   }

    public function base_commentInfo_handler($Sender, $Args) {
        $Type = getValue('Type', $Args);
        if ($Type != 'Comment')
            return;

        $QnA = getValueR('Comment.QnA', $Args);

        if ($QnA && ($QnA == 'Accepted' || Gdn::session()->checkPermission('Garden.Moderation.Manage'))) {
            $Title = t("QnA $QnA Answer", "$QnA Answer");
            echo ' <span class="Tag QnA-Box QnA-'.$QnA.'" title="'.htmlspecialchars($Title).'"><span>'.$Title.'</span></span> ';
        }
    }

    public function discussionController_commentOptions_handler($Sender, $Args) {
        $Comment = $Args['Comment'];
        if (!$Comment)
            return;
        $Discussion = Gdn::controller()->data('Discussion');

        if (getValue('Type', $Discussion) != 'Question')
            return;

        if (!Gdn::session()->checkPermission('Vanilla.Discussions.Edit', true, 'Category', $Discussion->PermissionCategoryID))
            return;

        $Args['CommentOptions']['QnA'] = array('Label' => t('Q&A').'...', 'Url' => '/discussion/qnaoptions?commentid='.$Comment->CommentID, 'Class' => 'Popup');
    }

    public function base_discussionOptions_handler($Sender, $Args) {
        $Discussion = $Args['Discussion'];
        if (!Gdn::session()->checkPermission('Vanilla.Discussions.Edit', true, 'Category', $Discussion->PermissionCategoryID))
            return;

        if (isset($Args['DiscussionOptions'])) {
            $Args['DiscussionOptions']['QnA'] = array('Label' => t('Q&A').'...', 'Url' => '/discussion/qnaoptions?discussionid='.$Discussion->DiscussionID, 'Class' => 'Popup');
        } elseif (isset($Sender->Options)) {
            $Sender->Options .= '<li>'.anchor(t('Q&A').'...', '/discussion/qnaoptions?discussionid='.$Discussion->DiscussionID, 'Popup QnAOptions') . '</li>';
        }
    }

    public function commentModel_beforeNotification_handler($Sender, $Args) {
        $ActivityModel = $Args['ActivityModel'];
        $Comment = (array)$Args['Comment'];
        $CommentID = $Comment['CommentID'];
        $Discussion = (array)$Args['Discussion'];

        if ($Comment['InsertUserID'] == $Discussion['InsertUserID'])
            return;
        if (strtolower($Discussion['Type']) != 'question')
            return;
        if (!c('Plugins.QnA.Notifications', true))
            return;

        $HeadlineFormat = t('HeadlingFormat.Answer', '{ActivityUserID,user} answered your question: <a href="{Url,html}">{Data.Name,text}</a>');

        $Activity = array(
            'ActivityType' => 'Comment',
            'ActivityUserID' => $Comment['InsertUserID'],
            'NotifyUserID' => $Discussion['InsertUserID'],
            'HeadlineFormat' => $HeadlineFormat,
            'RecordType' => 'Comment',
            'RecordID' => $CommentID,
            'Route' => "/discussion/comment/$CommentID#Comment_$CommentID",
            'Data' => array(
                'Name' => getValue('Name', $Discussion)
            )
        );

        $ActivityModel->queue($Activity, 'DiscussionComment');
    }

    /**
     * @param CommentModel $Sender
     * @param array $Args
     */
    public function commentModel_beforeUpdateCommentCount_handler($Sender, $Args) {
        $Discussion =& $Args['Discussion'];

        // Mark the question as answered.
        if (strtolower($Discussion['Type']) == 'question' && !$Discussion['Sink'] && !in_array($Discussion['QnA'], array('Answered', 'Accepted'))) {
            $Sender->SQL->set('QnA', 'Answered');
        }
    }

    /**
     * Modify flow of discussion by pinning accepted answers.
     *
     * @param $Sender
     * @param $Args
     */
    public function discussionController_beforeDiscussionRender_handler($Sender, $Args) {
        if ($Sender->data('Discussion.QnA'))
            $Sender->CssClass .= ' Question';

        if (strcasecmp($Sender->data('Discussion.QnA'), 'Accepted') != 0)
            return;

        // Find the accepted answer(s) to the question.
        $CommentModel = new CommentModel();
        $Answers = $CommentModel->getWhere(array('DiscussionID' => $Sender->data('Discussion.DiscussionID'), 'Qna' => 'Accepted'))->result();

        if (class_exists('ReplyModel')) {
            $ReplyModel = new ReplyModel();
            $Discussion = null;
            $ReplyModel->joinReplies($Discussion, $Answers);
        }

        $Sender->setData('Answers', $Answers);

        // Remove the accepted answers from the comments.
        // Allow this to be skipped via config.
        if (c('QnA.AcceptedAnswers.Filter', true)) {
            if (isset($Sender->Data['Comments'])) {
                $Comments = $Sender->Data['Comments']->result();
                $Comments = array_filter($Comments, function($Row) {
                    return strcasecmp(getValue('QnA', $Row), 'accepted');
                });
                $Sender->Data['Comments'] = new Gdn_DataSet(array_values($Comments));
            }
        }
    }

    /**
     * Write the accept/reject buttons.
     * @staticvar null $DiscussionModel
     * @staticvar boolean $InformMessage
     * @param type $Sender
     * @param type $Args
     * @return type
     */
    public function discussionController_afterCommentBody_handler($Sender, $Args) {
        $Discussion = $Sender->data('Discussion');
        $Comment = getValue('Comment', $Args);

        if (!$Comment)
            return;

        $CommentID = getValue('CommentID', $Comment);
        if (!is_numeric($CommentID))
            return;

        if (!$Discussion) {
            static $DiscussionModel = null;
            if ($DiscussionModel === null)
                $DiscussionModel = new DiscussionModel();
            $Discussion = $DiscussionModel->getID(getValue('DiscussionID', $Comment));
        }

        if (!$Discussion || strtolower(getValue('Type', $Discussion)) != 'question')
            return;

        // Check permissions.
        $CanAccept = Gdn::session()->checkPermission('Garden.Moderation.Manage');
        $CanAccept |= Gdn::session()->UserID == getValue('InsertUserID', $Discussion);

        if (!$CanAccept)
            return;

        $QnA = getValue('QnA', $Comment);
        if ($QnA)
            return;

        // Write the links.
//      $Types = GetValue('ReactionTypes', $Sender->EventArguments);
//      if ($Types)
//         echo Bullet();

        $Query = http_build_query(array('commentid' => $CommentID, 'tkey' => Gdn::session()->transientKey()));

        echo '<div class="ActionBlock QnA-Feedback">';

//      echo '<span class="FeedbackLabel">'.T('Feedback').'</span>';

        echo '<span class="DidThisAnswer">'.t('Did this answer the question?').'</span> ';

        echo '<span class="QnA-YesNo">';

        echo anchor(t('Yes'), '/discussion/qna/accept?'.$Query, array('class' => 'React QnA-Yes', 'title' => t('Accept this answer.')));
        echo ' '.bullet().' ';
        echo anchor(t('No'), '/discussion/qna/reject?'.$Query, array('class' => 'React QnA-No', 'title' => t('Reject this answer.')));

        echo '</span>';

        echo '</div>';

//      static $InformMessage = true;
//
//      if ($InformMessage && Gdn::Session()->UserID == GetValue('InsertUserID', $Discussion) && in_array(GetValue('QnA', $Discussion), array('', 'Answered'))) {
//         $Sender->InformMessage(T('Click accept or reject beside an answer.'), 'Dismissable');
//         $InformMessage = false;
//      }
    }

    /**
     *
     * @param DiscussionController $Sender
     * @param type $Args
     * @return type
     */
    public function discussionController_afterDiscussion_handler($Sender, $Args) {
        if ($Sender->data('Answers'))
            include $Sender->fetchViewLocation('Answers', '', 'plugins/QnA');
    }


    /**
     *
     * @param DiscussionController $Sender
     * @param array $Args
     */
    public function discussionController_QnA_create($Sender, $Args = array()) {
        $Comment = Gdn::SQL()->getWhere('Comment', array('CommentID' => $Sender->Request->get('commentid')))->firstRow(DATASET_TYPE_ARRAY);
        if (!$Comment)
            throw notFoundException('Comment');

        $Discussion = Gdn::SQL()->getWhere('Discussion', array('DiscussionID' => $Comment['DiscussionID']))->firstRow(DATASET_TYPE_ARRAY);

        // Check for permission.
        if (!(Gdn::session()->UserID == getValue('InsertUserID', $Discussion) || Gdn::session()->checkPermission('Garden.Moderation.Manage'))) {
            throw permissionException('Garden.Moderation.Manage');
        }
        if (!Gdn::session()->validateTransientKey($Sender->Request->get('tkey')))
            throw permissionException();

        switch ($Args[0]) {
            case 'accept':
                $QnA = 'Accepted';
                break;
            case 'reject':
                $QnA = 'Rejected';
                break;
        }

        if (isset($QnA)) {
            $DiscussionSet = array('QnA' => $QnA);
            $CommentSet = array('QnA' => $QnA);

            if ($QnA == 'Accepted') {
                $CommentSet['DateAccepted'] = Gdn_Format::toDateTime();
                $CommentSet['AcceptedUserID'] = Gdn::session()->UserID;

                if (!$Discussion['DateAccepted']) {
                    $DiscussionSet['DateAccepted'] = Gdn_Format::toDateTime();
                    $DiscussionSet['DateOfAnswer'] = $Comment['DateInserted'];
                }
            }

            // Update the comment.
            Gdn::SQL()->put('Comment', $CommentSet, array('CommentID' => $Comment['CommentID']));

            // Update the discussion.
            if ($Discussion['QnA'] != $QnA && (!$Discussion['QnA'] || in_array($Discussion['QnA'], array('Unanswered', 'Answered', 'Rejected'))))
                Gdn::SQL()->put(
                    'Discussion',
                    $DiscussionSet,
                    array('DiscussionID' => $Comment['DiscussionID']));

            // Determine QnA change
            if ($Comment['QnA'] != $QnA) {

                $Change = 0;
                switch ($QnA) {
                    case 'Rejected':
                        $Change = -1;
                        if ($Comment['QnA'] != 'Accepted') $Change = 0;
                        break;

                    case 'Accepted':
                        $Change = 1;
                        break;

                    default:
                        if ($Comment['QnA'] == 'Rejected') $Change = 0;
                        if ($Comment['QnA'] == 'Accepted') $Change = -1;
                        break;
                }

            }

            // Apply change effects
            if ($Change) {
                // Update the user
                $UserID = getValue('InsertUserID', $Comment);
                $this->recalculateUserQnA($UserID);

                // Update reactions
                if ($this->Reactions) {
                    include_once(Gdn::controller()->fetchViewLocation('reaction_functions', '', 'plugins/Reactions'));
                    $Rm = new ReactionModel();

                    // If there's change, reactions will take care of it
                    $Rm->react('Comment', $Comment['CommentID'], 'AcceptAnswer', null, true);
                }
            }

            // Record the activity.
            if ($QnA == 'Accepted') {
                $Activity = array(
                    'ActivityType' => 'AnswerAccepted',
                    'NotifyUserID' => $Comment['InsertUserID'],
                    'HeadlineFormat' => '{ActivityUserID,You} accepted {NotifyUserID,your} answer.',
                    'RecordType' => 'Comment',
                    'RecordID' => $Comment['CommentID'],
                    'Route' => commentUrl($Comment, '/'),
                    'Emailed' => ActivityModel::SENT_PENDING,
                    'Notified' => ActivityModel::SENT_PENDING,
                );

                $ActivityModel = new ActivityModel();
                $ActivityModel->save($Activity);
            }
        }
        redirect("/discussion/comment/{$Comment['CommentID']}#Comment_{$Comment['CommentID']}");
    }

    public function discussionController_QnAOptions_create($Sender, $DiscussionID = '', $CommentID = '') {
        if ($DiscussionID)
            $this->_discussionOptions($Sender, $DiscussionID);
        elseif ($CommentID)
            $this->_commentOptions($Sender, $CommentID);

    }

    public function recalculateDiscussionQnA($Discussion) {
        // Find comments in this discussion with a QnA value.
        $Set = array();

        $Row = Gdn::SQL()->getWhere('Comment',
            array('DiscussionID' => getValue('DiscussionID', $Discussion), 'QnA is not null' => ''), 'QnA, DateAccepted', 'asc', 1)->firstRow(DATASET_TYPE_ARRAY);

        if (!$Row) {
            if (getValue('CountComments', $Discussion) > 0)
                $Set['QnA'] = 'Unanswered';
            else
                $Set['QnA'] = 'Answered';

            $Set['DateAccepted'] = null;
            $Set['DateOfAnswer'] = null;
        } elseif ($Row['QnA'] == 'Accepted') {
            $Set['QnA'] = 'Accepted';
            $Set['DateAccepted'] = $Row['DateAccepted'];
            $Set['DateOfAnswer'] = $Row['DateInserted'];
        } elseif ($Row['QnA'] == 'Rejected') {
            $Set['QnA'] = 'Rejected';
            $Set['DateAccepted'] = null;
            $Set['DateOfAnswer'] = null;
        }

        Gdn::controller()->DiscussionModel->setField(getValue('DiscussionID', $Discussion), $Set);
    }

    public function recalculateUserQnA($UserID) {
        $CountAcceptedAnswers = Gdn::SQL()->getCount('Comment', array('InsertUserID' => $UserID, 'QnA' => 'Accepted'));
        Gdn::userModel()->setField($UserID, 'CountAcceptedAnswers', $CountAcceptedAnswers);
    }

    public function _commentOptions($Sender, $CommentID) {
        $Sender->Form = new Gdn_Form();

        $Comment = $Sender->CommentModel->getID($CommentID, DATASET_TYPE_ARRAY);

        if (!$Comment)
            throw notFoundException('Comment');

        $Discussion = $Sender->DiscussionModel->getID(getValue('DiscussionID', $Comment));

        $Sender->permission('Vanilla.Discussions.Edit', true, 'Category', getValue('PermissionCategoryID', $Discussion));

        if ($Sender->Form->authenticatedPostBack()) {
            $QnA = $Sender->Form->getFormValue('QnA');
            if (!$QnA)
                $QnA = null;

            $CurrentQnA = getValue('QnA', $Comment);

//         ->Column('DateAccepted', 'datetime', true)
//         ->Column('AcceptedUserID', 'int', true)

            if ($CurrentQnA != $QnA) {
                $Set = array('QnA' => $QnA);

                if ($QnA == 'Accepted') {
                    $Set['DateAccepted'] = Gdn_Format::toDateTime();
                    $Set['AcceptedUserID'] = Gdn::session()->UserID;
                } else {
                    $Set['DateAccepted'] = null;
                    $Set['AcceptedUserID'] = null;
                }

                $Sender->CommentModel->setField($CommentID, $Set);
                $Sender->Form->setValidationResults($Sender->CommentModel->validationResults());

                // Determine QnA change
                if ($Comment['QnA'] != $QnA) {

                    $Change = 0;
                    switch ($QnA) {
                        case 'Rejected':
                            $Change = -1;
                            if ($Comment['QnA'] != 'Accepted') $Change = 0;
                            break;

                        case 'Accepted':
                            $Change = 1;
                            break;

                        default:
                            if ($Comment['QnA'] == 'Rejected') $Change = 0;
                            if ($Comment['QnA'] == 'Accepted') $Change = -1;
                            break;
                    }

                }

                // Apply change effects
                if ($Change) {

                    // Update the user
                    $UserID = getValue('InsertUserID', $Comment);
                    $this->recalculateUserQnA($UserID);

                    // Update reactions
                    if ($this->Reactions) {
                        include_once(Gdn::controller()->fetchViewLocation('reaction_functions', '', 'plugins/Reactions'));
                        $Rm = new ReactionModel();

                        // If there's change, reactions will take care of it
                        $Rm->react('Comment', $Comment['CommentID'], 'AcceptAnswer');
                    }
                }

            }

            // Recalculate the Q&A status of the discussion.
            $this->recalculateDiscussionQnA($Discussion);

            Gdn::controller()->jsonTarget('', '', 'Refresh');
        } else {
            $Sender->Form->setData($Comment);
        }

        $Sender->setData('Comment', $Comment);
        $Sender->setData('Discussion', $Discussion);
        $Sender->setData('_QnAs', array('Accepted' => t('Yes'), 'Rejected' => t('No'), '' => t("Don't know")));
        $Sender->setData('Title', t('Q&A Options'));
        $Sender->render('CommentOptions', '', 'plugins/QnA');
    }

    protected function _discussionOptions($Sender, $DiscussionID) {
        $Sender->Form = new Gdn_Form();

        $Discussion = $Sender->DiscussionModel->getID($DiscussionID);

        if (!$Discussion)
            throw notFoundException('Discussion');

        $Sender->permission('Vanilla.Discussions.Edit', true, 'Category', val('PermissionCategoryID', $Discussion));

        // Both '' and 'Discussion' denote a discussion type of discussion.
        if (!getValue('Type', $Discussion))
            setValue('Type', $Discussion, 'Discussion');

        if ($Sender->Form->isPostBack()) {
            $Sender->DiscussionModel->setField($DiscussionID, 'Type', $Sender->Form->getFormValue('Type'));
//         $Form = new Gdn_Form();
            $Sender->Form->setValidationResults($Sender->DiscussionModel->validationResults());

//         if ($Sender->DeliveryType() == DELIVERY_TYPE_ALL || $Redirect)
//            $Sender->RedirectUrl = Gdn::Controller()->Request->PathAndQuery();
            Gdn::controller()->jsonTarget('', '', 'Refresh');
        } else {
            $Sender->Form->setData($Discussion);
        }

        $Sender->setData('Discussion', $Discussion);
        $Sender->setData('_Types', array('Question' => '@'.t('Question Type', 'Question'), 'Discussion' => '@'.t('Discussion Type', 'Discussion')));
        $Sender->setData('Title', t('Q&A Options'));
        $Sender->render('DiscussionOptions', '', 'plugins/QnA');
    }

    public function discussionModel_beforeGet_handler($Sender, $Args) {
        if (Gdn::controller()) {
            $Unanswered = Gdn::controller()->ClassName == 'DiscussionsController' && Gdn::controller()->RequestMethod == 'unanswered';

            if ($Unanswered) {
                $Args['Wheres']['Type'] = 'Question';
                $Sender->SQL->whereIn('d.QnA', array('Unanswered', 'Rejected'));
                Gdn::controller()->title('Unanswered Questions');
            } elseif ($QnA = Gdn::request()->get('qna')) {
                $Args['Wheres']['QnA'] = $QnA;
            }
        }
    }

    /**
     *
     * @param DiscussionModel $Sender
     * @param array $Args
     */
    public function discussionModel_beforeSaveDiscussion_handler($Sender, $Args) {
//      $Sender->Validation->ApplyRule('Type', 'Required', T('Choose whether you want to ask a question or start a discussion.'));

        $Post =& $Args['FormPostValues'];
        if ($Args['Insert'] && getValue('Type', $Post) == 'Question') {
            $Post['QnA'] = 'Unanswered';
        }
    }

    /* New Html method of adding to discussion filters */
    public function base_afterDiscussionFilters_handler($Sender) {
        $Count = Gdn::cache()->get('QnA-UnansweredCount');
        if ($Count === Gdn_Cache::CACHEOP_FAILURE)
            $Count = ' <span class="Aside"><span class="Popin Count" rel="/discussions/unansweredcount"></span>';
        else
            $Count = ' <span class="Aside"><span class="Count">'.$Count.'</span></span>';

        echo '<li class="QnA-UnansweredQuestions '.($Sender->RequestMethod == 'unanswered' ? ' Active' : '').'">'
            .anchor(sprite('SpUnansweredQuestions').' '.t('Unanswered').$Count, '/discussions/unanswered', 'UnansweredQuestions')
            .'</li>';
    }

    /* Old Html method of adding to discussion filters */
    public function discussionsController_afterDiscussionTabs_handler($Sender, $Args) {
        if (stringEndsWith(Gdn::request()->path(), '/unanswered', true))
            $CssClass = ' class="Active"';
        else
            $CssClass = '';

        $Count = Gdn::cache()->get('QnA-UnansweredCount');
        if ($Count === Gdn_Cache::CACHEOP_FAILURE)
            $Count = ' <span class="Popin Count" rel="/discussions/unansweredcount">';
        else
            $Count = ' <span class="Count">'.$Count.'</span>';

        echo '<li'.$CssClass.'><a class="TabLink QnA-UnansweredQuestions" href="'.url('/discussions/unanswered').'">'.t('Unanswered Questions', 'Unanswered').$Count.'</span></a></li>';
    }

    /**
     * @param DiscussionsController $Sender
     * @param array $Args
     */
    public function discussionsController_unanswered_create($Sender, $Args = array()) {
        $Sender->View = 'Index';
        $Sender->setData('_PagerUrl', 'discussions/unanswered/{Page}');
        $Sender->index(getValue(0, $Args, 'p1'));
        $this->InUnanswered = true;
    }

    public function discussionsController_beforeBuildPager_handler($Sender, &$Args = array()) {
        if (Gdn::controller()->RequestMethod == 'unanswered') {
            $Count = $this->getUnansweredCount();
            $Sender->setData('CountDiscussions', $Count);
        }
    }

    public function getUnansweredCount() {
        $Count = Gdn::cache()->get('QnA-UnansweredCount');
        if ($Count === Gdn_Cache::CACHEOP_FAILURE) {
            Gdn::SQL()->whereIn('QnA', array('Unanswered', 'Rejected'));
            $Count = Gdn::SQL()->getCount('Discussion', array('Type' => 'Question'));
            Gdn::cache()->store('QnA-UnansweredCount', $Count, array(Gdn_Cache::FEATURE_EXPIRY => 15 * 60));
        }
        return $Count;
    }

    /**
     *
     * @param DiscussionsController $Sender
     * @param type $Args
     */
    public function discussionsController_unanswered_render($Sender, $Args) {
        $Sender->setData('CountDiscussions', false);

        // Add 'Ask a Question' button if using BigButtons.
        if (c('Plugins.QnA.UseBigButtons')) {
            $QuestionModule = new NewQuestionModule($Sender, 'plugins/QnA');
            $Sender->addModule($QuestionModule);
        }

        // Remove announcements that aren't questions...
        if (is_a($Sender->data('Announcements'), 'Gdn_DataSet')) {
            $Sender->data('Announcements')->result();
            $Announcements = array();
            foreach ($Sender->data('Announcements') as $i => $Row) {
                if (getValue('Type', $Row) == 'Question')
                    $Announcements[] = $Row;
            }
            trace($Announcements);
            $Sender->setData('Announcements', $Announcements);
            $Sender->AnnounceData = $Announcements;
        }
    }

    /**
     * @param DiscussionsController $Sender
     * @param array $Args
     */
    public function discussionsController_unansweredCount_create($Sender, $Args = array()) {
        $Count = $this->getUnansweredCount();

        $Sender->setData('UnansweredCount', $Count);
        $Sender->setData('_Value', $Count);
        $Sender->render('Value', 'Utility', 'Dashboard');
    }

    public function base_beforeDiscussionMeta_handler($Sender, $Args) {
        $Discussion = $Args['Discussion'];

        if (strtolower(getValue('Type', $Discussion)) != 'question')
            return;

        $QnA = getValue('QnA', $Discussion);
        $Title = '';
        switch ($QnA) {
            case '':
            case 'Unanswered':
            case 'Rejected':
                $Text = 'Question';
                $QnA = 'Question';
                break;
            case 'Answered':
                $Text = 'Answered';
                if (getValue('InsertUserID', $Discussion) == Gdn::session()->UserID) {
                    $QnA = 'Answered';
                    $Title = ' title="'.t("Someone's answered your question. You need to accept/reject the answer.").'"';
                }
                break;
            case 'Accepted':
                $Text = 'Answered';
                $Title = ' title="'.t("This question's answer has been accepted.").'"';
                break;
            default:
                $QnA = false;
        }
        if ($QnA) {
            echo ' <span class="Tag QnA-Tag-'.$QnA.'"'.$Title.'>'.t("Q&A $QnA", $Text).'</span> ';
        }
    }

    /**
     * @param Gdn_Controller $Sender
     * @param array $Args
     */
    public function notificationsController_beforeInformNotifications_handler($Sender, $Args) {
        $Path = trim($Sender->Request->getValue('Path'), '/');
        if (preg_match('`^(vanilla/)?discussion[^s]`i', $Path))
            return;

        // Check to see if the user has answered questions.
        $Count = Gdn::SQL()->getCount('Discussion', array('Type' => 'Question', 'InsertUserID' => Gdn::session()->UserID, 'QnA' => 'Answered'));
        if ($Count > 0) {
            $Sender->informMessage(formatString(t("You've asked questions that have now been answered", "<a href=\"{/discussions/mine?qna=Answered,url}\">You've asked questions that now have answers</a>. Make sure you accept/reject the answers.")), 'Dismissable');
        }
    }

    /**
     * Add 'Ask a Question' button if using BigButtons.
     */
    public function categoriesController_render_before($Sender) {
        if (c('Plugins.QnA.UseBigButtons')) {
            $QuestionModule = new NewQuestionModule($Sender, 'plugins/QnA');
            $Sender->addModule($QuestionModule);
        }
    }

    /**
     * Add 'Ask a Question' button if using BigButtons.
     */
    public function discussionController_render_before($Sender) {
        if (c('Plugins.QnA.UseBigButtons')) {
            $QuestionModule = new NewQuestionModule($Sender, 'plugins/QnA');
            $Sender->addModule($QuestionModule);
        }

        if ($Sender->data('Discussion.Type') == 'Question') {
            $Sender->setData('_CommentsHeader', t('Answers'));
        }
    }


    /**
     * Add the "new question" option to the new discussion button group dropdown.
     */
//   public function Base_BeforeNewDiscussionButton_Handler($Sender) {
//      $NewDiscussionModule = &$Sender->EventArguments['NewDiscussionModule'];
//
//      $Category = Gdn::Controller()->Data('Category.UrlCode');
//      if ($Category)
//         $Category = '/'.rawurlencode($Category);
//      else
//         $Category = '';
//
//      $NewDiscussionModule->AddButton(T('Ask a Question'), 'post/question'.$Category);
//   }

    /**
     * Add the question form to vanilla's post page.
     */
    public function postController_afterForms_handler($Sender) {
        $Forms = $Sender->data('Forms');
        $Forms[] = array('Name' => 'Question', 'Label' => sprite('SpQuestion').t('Ask a Question'), 'Url' => 'post/question');
        $Sender->setData('Forms', $Forms);
    }

    /**
     * Create the new question method on post controller.
     */
    public function postController_question_create($Sender, $CategoryUrlCode = '') {
        // Create & call PostController->Discussion()
        $Sender->View = PATH_PLUGINS.'/QnA/views/post.php';
        $Sender->setData('Type', 'Question');
        $Sender->discussion($CategoryUrlCode);
    }

    /**
     * Override the PostController->Discussion() method before render to use our view instead.
     */
    public function postController_beforeDiscussionRender_handler($Sender) {
        // Override if we are looking at the question url.
        if ($Sender->RequestMethod == 'question') {
            $Sender->Form->addHidden('Type', 'Question');
            $Sender->title(t('Ask a Question'));
            $Sender->setData('Breadcrumbs', array(array('Name' => $Sender->data('Title'), 'Url' => '/post/question')));
        }
    }

    /**
     * Add 'New Question Form' location to Messages.
     */
    public function messageController_afterGetLocationData_handler($Sender, $Args) {
        $Args['ControllerData']['Vanilla/Post/Question'] = t('New Question Form');
    }
}
