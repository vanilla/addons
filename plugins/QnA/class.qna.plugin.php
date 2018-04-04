<?php
/**
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license GNU GPLv2 http://www.opensource.org/licenses/gpl-2.0.php
 */

/**
 * Adds Question & Answer format to Vanilla.
 *
 * You can set Plugins.QnA.UseBigButtons = true in config to separate 'New Discussion'
 * and 'Ask Question' into "separate" forms each with own big button in Panel.
 */
class QnAPlugin extends Gdn_Plugin {

    /** @var bool|array  */
    protected $Reactions = false;

    /** @var bool|array  */
    protected $Badges = false;

    /**
     * QnAPlugin constructor.
     */
    public function __construct() {
        parent::__construct();

        if (Gdn::addonManager()->isEnabled('Reactions', \Vanilla\Addon::TYPE_ADDON) && c('Plugins.QnA.Reactions', true)) {
            $this->Reactions = true;
        }

        if ((Gdn::addonManager()->isEnabled('Reputation', \Vanilla\Addon::TYPE_ADDON) || Gdn::addonManager()->isEnabled('badges', \Vanilla\Addon::TYPE_ADDON))
            && c('Plugins.QnA.Badges', true)) {
            $this->Badges = true;
        }
    }

    /**
     * Run once on enable.
     */
    public function setup() {
        $this->structure();

        touchConfig('QnA.Points.Enabled', false);
        touchConfig('QnA.Points.Answer', 1);
        touchConfig('QnA.Points.AcceptedAnswer', 1);
    }

    /**
     * Add Javascript.
     *
     * @param $sender
     * @param $args
     */
    public function base_render_before($sender, $args) {
        if ($sender->MasterView == 'admin') {
            $sender->addJsFile('QnA.js', 'plugins/QnA');
        }
    }

    /**
     * Database updates.
     */
    public function structure() {
        include __DIR__.'/structure.php';
    }

    /**
     * Create a method called "QnA" on the SettingController.
     *
     * @param $sender Sending controller instance
     */
    public function settingsController_qnA_create($sender) {
        // Prevent non-admins from accessing this page
        $sender->permission('Garden.Settings.Manage');

        $sender->title(sprintf(t('%s settings'), t('Q&A')));
        $sender->setData('PluginDescription', $this->getPluginKey('Description'));
        $sender->addSideMenu('settings/QnA');

        $sender->Form = new Gdn_Form();
        $validation = new Gdn_Validation();
        $configurationModel = new Gdn_ConfigurationModel($validation);
        $configurationModel->setField([
            'QnA.Points.Enabled' => c('QnA.Points.Enabled', false),
            'QnA.Points.Answer' => c('QnA.Points.Answer', 1),
            'QnA.Points.AcceptedAnswer' => c('QnA.Points.AcceptedAnswer', 1),
        ]);
        $sender->Form->setModel($configurationModel);

        // If seeing the form for the first time...
        if ($sender->Form->authenticatedPostBack() === false) {
            $sender->Form->setData($configurationModel->Data);
        } else {
            $configurationModel->Validation->applyRule('QnA.Points.Enabled', 'Boolean');

            if ($sender->Form->getFormValue('QnA.Points.Enabled')) {
                $configurationModel->Validation->applyRule('QnA.Points.Answer', 'Required');
                $configurationModel->Validation->applyRule('QnA.Points.Answer', 'Integer');

                $configurationModel->Validation->applyRule('QnA.Points.AcceptedAnswer', 'Required');
                $configurationModel->Validation->applyRule('QnA.Points.AcceptedAnswer', 'Integer');

                if ($sender->Form->getFormValue('QnA.Points.Answer') < 0) {
                    $sender->Form->setFormValue('QnA.Points.Answer', 0);
                }
                if ($sender->Form->getFormValue('QnA.Points.AcceptedAnswer') < 0) {
                    $sender->Form->setFormValue('QnA.Points.AcceptedAnswer', 0);
                }
            }

            if ($sender->Form->save() !== false) {
                // Update the AcceptAnswer reaction points.
                try {
                    if ($this->Reactions) {
                        $reactionModel = new ReactionModel();
                        $reactionModel->save([
                            'UrlCode' => 'AcceptAnswer',
                            'Points' => c('QnA.Points.AcceptedAnswer'),
                        ]);
                    }
                } catch(Exception $e) {
                    // Do nothing; no reaction was found to update so just press on.
                }
                $sender->StatusMessage = t('Your changes have been saved.');
            }
        }

        $sender->render($this->getView('configuration.php'));
    }

    /**
     * Trigger reaction or badge creation if those addons are enabled later.
     *
     * @param $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function base_addonEnabled_handler($sender, $args) {
        switch (strtolower($args['AddonName'])) {
            case 'reactions':
                $this->Reactions = true;
                $this->structure();
                break;
            case 'badges':
                $this->Badges = true;
                $this->structure();
                break;
        }
    }

    /**
     *
     *
     * @param $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function base_beforeCommentDisplay_handler($sender, $args) {
        $qnA = valr('Comment.QnA', $args);

        if ($qnA && isset($args['CssClass'])) {
            $args['CssClass'] = concatSep(' ', $args['CssClass'], "QnA-Item-$qnA");
        }
    }

    /**
     *
     *
     * @param $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function base_discussionTypes_handler($sender, $args) {
        if (!c('Plugins.QnA.UseBigButtons')) {
            $args['Types']['Question'] = [
                'Singular' => 'Question',
                'Plural' => 'Questions',
                'AddUrl' => '/post/question',
                'AddText' => 'Ask a Question'
            ];
        }
    }

    /**
     *
     * @param $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function base_commentInfo_handler($sender, $args) {
        $type = val('Type', $args);
        if ($type != 'Comment') {
            return;
        }

        $qnA = valr('Comment.QnA', $args);

        if ($qnA && ($qnA == 'Accepted' || Gdn::session()->checkPermission('Garden.Moderation.Manage'))) {
            $title = t("QnA $qnA Answer", "$qnA Answer");
            echo ' <span class="Tag QnA-Box QnA-'.$qnA.'" title="'.htmlspecialchars($title).'"><span>'.$title.'</span></span> ';
        }
    }

    /**
     *
     *
     * @param DiscussionController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionController_commentOptions_handler($sender, $args) {
        $comment = $args['Comment'];
        if (!$comment) {
            return;
        }
        $discussion = Gdn::controller()->data('Discussion');

        if (val('Type', $discussion) != 'Question') {
            return;
        }

        if (!Gdn::session()->checkPermission('Vanilla.Discussions.Edit', true, 'Category', $discussion->PermissionCategoryID)) {
            return;
        }

        $args['CommentOptions']['QnA'] = ['Label' => t('Q&A').'...', 'Url' => '/discussion/qnaoptions?commentid='.$comment->CommentID, 'Class' => 'Popup'];
    }

    /**
     *
     *
     * @param CommentModel $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function base_discussionOptions_handler($sender, $args) {
        $discussion = $args['Discussion'];
        if (!Gdn::session()->checkPermission('Vanilla.Discussions.Edit', true, 'Category', $discussion->PermissionCategoryID)) {
            return;
        }

        if (isset($args['DiscussionOptions'])) {
            $args['DiscussionOptions']['QnA'] = ['Label' => t('Q&A').'...', 'Url' => '/discussion/qnaoptions?discussionid='.$discussion->DiscussionID, 'Class' => 'Popup'];
        } elseif (isset($sender->Options)) {
            $sender->Options .= '<li>'.anchor(t('Q&A').'...', '/discussion/qnaoptions?discussionid='.$discussion->DiscussionID, 'Popup QnAOptions') . '</li>';
        }
    }

    /**
     *
     *
     * @param CommentModel $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function commentModel_beforeNotification_handler($sender, $args) {
        $activityModel = $args['ActivityModel'];
        $comment = (array)$args['Comment'];
        $commentID = $comment['CommentID'];
        $discussion = (array)$args['Discussion'];

        if ($comment['InsertUserID'] == $discussion['InsertUserID']) {
            return;
        }
        if (strtolower($discussion['Type']) != 'question') {
            return;
        }
        if (!c('Plugins.QnA.Notifications', true)) {
            return;
        }

        $headlineFormat = t('HeadlineFormat.Answer', '{ActivityUserID,user} answered your question: <a href="{Url,html}">{Data.Name,text}</a>');

        $activity = [
            'ActivityType' => 'Comment',
            'ActivityUserID' => $comment['InsertUserID'],
            'NotifyUserID' => $discussion['InsertUserID'],
            'HeadlineFormat' => $headlineFormat,
            'RecordType' => 'Comment',
            'RecordID' => $commentID,
            'Route' => "/discussion/comment/$commentID#Comment_$commentID",
            'Data' => [
                'Name' => val('Name', $discussion)
            ]
        ];

        $activityModel->queue($activity, 'DiscussionComment');
    }

    /**
     *
     *
     * @param CommentModel $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function commentModel_beforeUpdateCommentCount_handler($sender, $args) {
        $discussion =& $args['Discussion'];

        // Mark the question as answered.
        if (strtolower($discussion['Type']) == 'question' && !$discussion['Sink'] && !in_array($discussion['QnA'], ['Answered', 'Accepted'])) {
            if($args['Counts']['CountComments'] > 0) {
                $sender->SQL->set('QnA', 'Answered');
            } else {
                $sender->SQL->set('QnA', 'Unanswered');
            }
        }
    }

    /**
     * Modify flow of discussion by pinning accepted answers.
     *
     * @param DiscussionController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionController_beforeDiscussionRender_handler($sender, $args) {
        if ($sender->data('Discussion.QnA')) {
            $sender->CssClass .= ' Question';
        }

        if (strcasecmp($sender->data('Discussion.QnA'), 'Accepted') != 0) {
            return;
        }

        // Find the accepted answer(s) to the question.
        $commentModel = new CommentModel();
        $answers = $commentModel->getWhere(['DiscussionID' => $sender->data('Discussion.DiscussionID'), 'Qna' => 'Accepted'])->result();

        if (class_exists('ReplyModel')) {
            $replyModel = new ReplyModel();
            $discussion = null;
            $replyModel->joinReplies($discussion, $answers);
        }

        $sender->setData('Answers', $answers);

        // Remove the accepted answers from the comments.
        // Allow this to be skipped via config.
        if (c('QnA.AcceptedAnswers.Filter', true)) {
            if (isset($sender->Data['Comments'])) {
                $comments = $sender->Data['Comments']->result();
                $comments = array_filter($comments, function($row) {
                    return strcasecmp(val('QnA', $row), 'accepted');
                });
                $sender->Data['Comments'] = new Gdn_DataSet(array_values($comments));
            }
        }
    }

    /**
     * Write the accept/reject buttons.
     *
     * @param DiscussionController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionController_afterCommentBody_handler($sender, $args) {
        $discussion = $sender->data('Discussion');
        $comment = val('Comment', $args);

        if (!$comment) {
            return;
        }

        $commentID = val('CommentID', $comment);
        if (!is_numeric($commentID)) {
            return;
        }

        if (!$discussion) {
            $discussion = DiscussionModel::instance()->getID(val('DiscussionID', $comment));
        }

        if (!$discussion || strtolower(val('Type', $discussion)) != 'question') {
            return;
        }

        // Check permissions.
        $canAccept = Gdn::session()->checkPermission('Garden.Moderation.Manage');
        $canAccept |= Gdn::session()->UserID == val('InsertUserID', $discussion);

        if (!$canAccept) {
            return;
        }

        $qnA = val('QnA', $comment);
        if ($qnA) {
            return;
        }


        $query = http_build_query(['commentid' => $commentID, 'tkey' => Gdn::session()->transientKey()]);

        echo '<div class="ActionBlock QnA-Feedback">';

        echo '<span class="DidThisAnswer">'.t('Did this answer the question?').'</span> ';

        echo '<span class="QnA-YesNo">';

        echo anchor(t('Yes'), '/discussion/qna/accept?'.$query, ['class' => 'React QnA-Yes', 'title' => t('Accept this answer.')]);
        echo ' '.bullet().' ';
        echo anchor(t('No'), '/discussion/qna/reject?'.$query, ['class' => 'React QnA-No', 'title' => t('Reject this answer.')]);

        echo '</span>';

        echo '</div>';
    }

    /**
     *
     *
     * @param DiscussionController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionController_afterDiscussion_handler($sender, $args) {
        if ($sender->data('Answers')) {
            include $sender->fetchViewLocation('Answers', '', 'plugins/QnA');
        }
    }

    /**
     *
     *
     * @param DiscussionController $sender Sending controller instance.
     * @param array $args Event arguments.
     *
     * @throws notFoundException
     */
    public function discussionController_qnA_create($sender, $args) {
        $comment = Gdn::sql()->getWhere('Comment', ['CommentID' => $sender->Request->get('commentid')])->firstRow(DATASET_TYPE_ARRAY);
        if (!$comment) {
            throw notFoundException('Comment');
        }

        $discussion = Gdn::sql()->getWhere('Discussion', ['DiscussionID' => $comment['DiscussionID']])->firstRow(DATASET_TYPE_ARRAY);

        // Check for permission.
        if (!(Gdn::session()->UserID == val('InsertUserID', $discussion) || Gdn::session()->checkPermission('Garden.Moderation.Manage'))) {
            throw permissionException('Garden.Moderation.Manage');
        }
        if (!Gdn::session()->validateTransientKey($sender->Request->get('tkey'))) {
            throw permissionException();
        }

        switch ($args[0]) {
            case 'accept':
                $qna = 'Accepted';
                break;
            case 'reject':
                $qna = 'Rejected';
                break;
        }

        if (isset($qna)) {
            $discussionSet = ['QnA' => $qna];
            $CommentSet = ['QnA' => $qna];

            if ($qna == 'Accepted') {
                $CommentSet['DateAccepted'] = Gdn_Format::toDateTime();
                $CommentSet['AcceptedUserID'] = Gdn::session()->UserID;

                if (!$discussion['DateAccepted']) {
                    $discussionSet['DateAccepted'] = Gdn_Format::toDateTime();
                    $discussionSet['DateOfAnswer'] = $comment['DateInserted'];
                }
            }

            // Update the comment.
            Gdn::sql()->put('Comment', $CommentSet, ['CommentID' => $comment['CommentID']]);

            // Update the discussion.
            if ($discussion['QnA'] != $qna && (!$discussion['QnA'] || in_array($discussion['QnA'], ['Unanswered', 'Answered', 'Rejected']))) {
                Gdn::sql()->put(
                    'Discussion',
                    $discussionSet,
                    ['DiscussionID' => $comment['DiscussionID']]);
            }

            // Determine QnA change
            if ($comment['QnA'] != $qna) {
                $change = 0;
                switch ($qna) {
                    case 'Rejected':
                        $change = -1;
                        if ($comment['QnA'] != 'Accepted') {
                            $change = 0;
                        }
                        break;

                    case 'Accepted':
                        $change = 1;
                        break;

                    default:
                        if ($comment['QnA'] == 'Rejected') {
                            $change = 0;
                        }
                        if ($comment['QnA'] == 'Accepted') {
                            $change = -1;
                        }
                        break;
                }
            }

            // Apply change effects
            if ($change && $discussion['InsertUserID'] != $comment['InsertUserID']) {
                // Update the user
                $userID = val('InsertUserID', $comment);
                $this->recalculateUserQnA($userID);

                // Update reactions
                if ($this->Reactions) {
                    include_once(Gdn::controller()->fetchViewLocation('reaction_functions', '', 'plugins/Reactions'));
                    $reactionModel = new ReactionModel();

                    // Assume that the reaction is done by the question's owner
                    $questionOwner = $discussion['InsertUserID'];
                    // If there's change, reactions will take care of it
                    $reactionModel->react('Comment', $comment['CommentID'], 'AcceptAnswer', $questionOwner, true);
                } else {
                    $nbsPoint = $change * (int)c('QnA.Points.AcceptedAnswer', 1);
                    if ($nbsPoint && c('QnA.Points.Enabled', false)) {
                        CategoryModel::givePoints($comment['InsertUserID'], $nbsPoint, 'QnA', $discussion['CategoryID']);
                    }
                }
            }

            $headlineFormat = t('HeadlineFormat.AcceptAnswer', '{ActivityUserID,You} accepted {NotifyUserID,your} answer to a question: <a href="{Url,html}">{Data.Name,text}</a>');

            // Record the activity.
            if ($qna == 'Accepted') {
                $activity = [
                    'ActivityType' => 'AnswerAccepted',
                    'NotifyUserID' => $comment['InsertUserID'],
                    'HeadlineFormat' => $headlineFormat,
                    'RecordType' => 'Comment',
                    'RecordID' => $comment['CommentID'],
                    'Route' => commentUrl($comment, '/'),
                    'Emailed' => ActivityModel::SENT_PENDING,
                    'Notified' => ActivityModel::SENT_PENDING,
                    'Data' => [
                        'Name' => val('Name', $discussion)
                    ]
                ];

                $ActivityModel = new ActivityModel();
                $ActivityModel->save($activity);

                $this->EventArguments['Activity'] =& $activity;
                $this->fireEvent('AfterAccepted');
            }
        }
        redirectTo("/discussion/comment/{$comment['CommentID']}#Comment_{$comment['CommentID']}");
    }

    /**
     *
     *
     * @param DiscussionController $sender Sending controller instance.
     * @param string|int $discussionID Identifier of the discussion
     * @param string|int $commentID Identifier of the comment.
     */
    public function discussionController_qnAOptions_create($sender, $discussionID = '', $commentID = '') {
        if ($discussionID) {
            $this->_discussionOptions($sender, $discussionID);
        } elseif ($commentID) {
            $this->_commentOptions($sender, $commentID);
        }
    }

    /**
     *
     * @param $discussion A discussion.
     */
    public function recalculateDiscussionQnA($discussion) {
        // Find comments in this discussion with a QnA value.
        $set = [];

        $row = Gdn::sql()->getWhere('Comment',
            ['DiscussionID' => val('DiscussionID', $discussion), 'QnA is not null' => ''], 'QnA, DateAccepted', 'asc', 1)->firstRow(DATASET_TYPE_ARRAY);

        if (!$row) {
            if (val('CountComments', $discussion) > 0) {
                $set['QnA'] = 'Unanswered';
            } else {
                $set['QnA'] = 'Answered';
            }

            $set['DateAccepted'] = null;
            $set['DateOfAnswer'] = null;
        } elseif ($row['QnA'] == 'Accepted') {
            $set['QnA'] = 'Accepted';
            $set['DateAccepted'] = $row['DateAccepted'];
            $set['DateOfAnswer'] = $row['DateInserted'];
        } elseif ($row['QnA'] == 'Rejected') {
            $set['QnA'] = 'Rejected';
            $set['DateAccepted'] = null;
            $set['DateOfAnswer'] = null;
        }

        Gdn::controller()->DiscussionModel->setField(val('DiscussionID', $discussion), $set);
    }

    /**
     *
     *
     * @param string|int $userID User identifier
     */
    public function recalculateUserQnA($userID) {
        $countAcceptedAnswers = Gdn::sql()->getCount('Comment', ['InsertUserID' => $userID, 'QnA' => 'Accepted']);
        Gdn::userModel()->setField($userID, 'CountAcceptedAnswers', $countAcceptedAnswers);
    }

    /**
     *
     *
     * @param $sender controller instance.
     * @param int|string $commentID Identifier of the comment.
     *
     * @throws notFoundException
     */
    public function _commentOptions($sender, $commentID) {
        $sender->Form = new Gdn_Form();

        $comment = $sender->CommentModel->getID($commentID, DATASET_TYPE_ARRAY);

        if (!$comment) {
            throw notFoundException('Comment');
        }

        $discussion = $sender->DiscussionModel->getID(val('DiscussionID', $comment), DATASET_TYPE_ARRAY);

        $sender->permission('Vanilla.Discussions.Edit', true, 'Category', val('PermissionCategoryID', $discussion));

        if ($sender->Form->authenticatedPostBack()) {
            $newQnA = $sender->Form->getFormValue('QnA');
            if (!$newQnA) {
                $newQnA = null;
            }

            $currentQnA = val('QnA', $comment);

            if ($currentQnA != $newQnA) {
                $set = ['QnA' => $newQnA];

                if ($newQnA == 'Accepted') {
                    $set['DateAccepted'] = Gdn_Format::toDateTime();
                    $set['AcceptedUserID'] = Gdn::session()->UserID;
                } else {
                    $set['DateAccepted'] = null;
                    $set['AcceptedUserID'] = null;
                }

                $sender->CommentModel->setField($commentID, $set);
                $sender->Form->setValidationResults($sender->CommentModel->validationResults());

                // Determine QnA change
                if ($currentQnA != $newQnA) {
                    $change = 0;
                    switch ($newQnA) {
                        case 'Rejected':
                            $change = -1;
                            if ($currentQnA != 'Accepted') {
                                $change = 0;
                            }
                            break;

                        case 'Accepted':
                            $change = 1;
                            break;

                        default:
                            if ($currentQnA == 'Rejected') {
                                $change = 0;
                            }
                            if ($currentQnA == 'Accepted') {
                                $change = -1;
                            }
                            break;
                    }
                }

                // Apply change effects
                if ($change && $discussion['InsertUserID'] != $comment['InsertUserID']) {
                    // Update the user
                    $userID = val('InsertUserID', $comment);
                    $this->recalculateUserQnA($userID);

                    // Update reactions
                    if ($this->Reactions) {
                        include_once(Gdn::controller()->fetchViewLocation('reaction_functions', '', 'plugins/Reactions'));
                        $reactionModel = new ReactionModel();

                        // Assume that the reaction is done by the question's owner
                        $questionOwner = $discussion['InsertUserID'];
                        // If there's change, reactions will take care of it
                        $reactionModel->react('Comment', $comment['CommentID'], 'AcceptAnswer', $questionOwner, true);
                    } else {
                        $nbsPoint = $change * (int)c('QnA.Points.AcceptedAnswer', 1);
                        if ($nbsPoint && c('QnA.Points.Enabled', false)) {
                            CategoryModel::givePoints($comment['InsertUserID'], $nbsPoint, 'QnA', $discussion['CategoryID']);
                        }
                    }
                }
            }

            // Recalculate the Q&A status of the discussion.
            $this->recalculateDiscussionQnA($discussion);

            Gdn::controller()->jsonTarget('', '', 'Refresh');
        } else {
            $sender->Form->setData($comment);
        }

        $sender->setData('Comment', $comment);
        $sender->setData('Discussion', $discussion);
        $sender->setData('_QnAs', ['Accepted' => t('Yes'), 'Rejected' => t('No'), '' => t("Don't know")]);
        $sender->setData('Title', t('Q&A Options'));
        $sender->render('CommentOptions', '', 'plugins/QnA');
    }

    /**
     *
     *
     * @param $sender controller instance.
     * @param int|string $discussionID Identifier of the discussion.
     *
     * @throws notFoundException
     */
    protected function _discussionOptions($sender, $discussionID) {
        $sender->Form = new Gdn_Form();

        $discussion = $sender->DiscussionModel->getID($discussionID);

        if (!$discussion) {
            throw notFoundException('Discussion');
        }

        $sender->permission('Vanilla.Discussions.Edit', true, 'Category', val('PermissionCategoryID', $discussion));

        // Both '' and 'Discussion' denote a discussion type of discussion.
        if (!val('Type', $discussion)) {
            setValue('Type', $discussion, 'Discussion');
        }

        if ($sender->Form->authenticatedPostBack()) {
            $sender->DiscussionModel->setField($discussionID, 'Type', $sender->Form->getFormValue('Type'));

            // Update the QnA field.  Default to "Unanswered" for questions. Null the field for other types.
            $qna = val('QnA', $discussion);
            switch ($sender->Form->getFormValue('Type')) {
                case 'Question':
                    $sender->DiscussionModel->setField(
                        $discussionID,
                        'QnA',
                        $qna ? $qna : 'Unanswered'
                    );
                    break;
                default:
                    $sender->DiscussionModel->setField($discussionID, 'QnA', null);
            }
            $sender->Form->setValidationResults($sender->DiscussionModel->validationResults());

            Gdn::controller()->jsonTarget('', '', 'Refresh');
        } else {
            $sender->Form->setData($discussion);
        }

        $sender->setData('Discussion', $discussion);
        $sender->setData('_Types', ['Question' => '@'.t('Question Type', 'Question'), 'Discussion' => '@'.t('Discussion Type', 'Discussion')]);
        $sender->setData('Title', t('Q&A Options'));
        $sender->render('DiscussionOptions', '', 'plugins/QnA');
    }

    /**
     *
     *
     * @param DiscussionModel $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionModel_beforeGet_handler($sender, $args) {
        if (Gdn::controller()) {
            $unanswered = Gdn::controller()->ClassName == 'DiscussionsController' && Gdn::controller()->RequestMethod == 'unanswered';

            if ($unanswered) {
                $args['Wheres']['Type'] = 'Question';
                $sender->SQL->beginWhereGroup()
                    ->where('d.QnA', null)
                    ->orWhereIn('d.QnA', ['Unanswered', 'Rejected'])
                    ->endWhereGroup();
                Gdn::controller()->title(t('Unanswered Questions'));
            } elseif ($qnA = Gdn::request()->get('qna')) {
                $args['Wheres']['QnA'] = $qnA;
            }
        }
    }

    /**
     *
     *
     * @param DiscussionModel $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionModel_beforeSaveDiscussion_handler($sender, $args) {
        $post =& $args['FormPostValues'];
        if ($args['Insert'] && val('Type', $post) == 'Question') {
            $post['QnA'] = 'Unanswered';
        }
    }

    /**
     * New Html method of adding to discussion filters.
     *
     * @param $sender
     */
    public function base_afterDiscussionFilters_handler($sender) {
        $count = Gdn::cache()->get('QnA-UnansweredCount');
        if ($count === Gdn_Cache::CACHEOP_FAILURE) {
            $count =
                '<span class="Aside">'
                    .'<span class="Popin Count" rel="/discussions/unansweredcount"></span>'
                .'</span>';
        } else {
            $count =
                '<span class="Aside">'
                    .'<span class="Count">'.$count.'</span>'
                .'</span>';
        }

        $extraClass = ($sender->RequestMethod == 'unanswered') ? 'Active' : '';
        $sprite = sprite('SpUnansweredQuestions');

        echo "<li class='QnA-UnansweredQuestions $extraClass'>"
                .anchor(
                    $sprite.' '.t('Unanswered').' '.$count,
                    '/discussions/unanswered',
                    'UnansweredQuestions'
                )
            .'</li>';
    }

    /**
     * Old Html method of adding to discussion filters.
     */
    public function discussionsController_afterDiscussionTabs_handler() {
        if (stringEndsWith(Gdn::request()->path(), '/unanswered', true)) {
            $cssClass = ' class="Active"';
        } else {
            $cssClass = '';
        }

        $count = Gdn::cache()->get('QnA-UnansweredCount');
        if ($count === Gdn_Cache::CACHEOP_FAILURE) {
            $count = ' <span class="Popin Count" rel="/discussions/unansweredcount">';
        } else {
            $count = ' <span class="Count">'.$count.'</span>';
        }

        echo '<li'.$cssClass.'><a class="TabLink QnA-UnansweredQuestions" href="'.url('/discussions/unanswered').'">'.t('Unanswered Questions', 'Unanswered').$count.'</span></a></li>';
    }

    /**
     *
     *
     * @param DiscussionsController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionsController_unanswered_create($sender, $args) {
        $sender->View = 'Index';
        $sender->setData('_PagerUrl', 'discussions/unanswered/{Page}');

        // Be sure to display every unanswered question (ie from groups)
        $categories = CategoryModel::categories();

        $this->EventArguments['Categories'] = &$categories;
        $this->fireEvent('UnansweredBeforeSetCategories');

        $sender->setCategoryIDs(array_keys($categories));

        $sender->index(val(0, $args, 'p1'));
        $this->InUnanswered = true;
    }

    /**
     *
     *
     * @param DiscussionsController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionsController_beforeBuildPager_handler($sender, $args) {
        if (Gdn::controller()->RequestMethod == 'unanswered') {
            $count = $this->getUnansweredCount();
            $sender->setData('CountDiscussions', $count);
        }
    }

    /**
     * Return the number of unanswered questions.
     *
     * @return int
     */
    public function getUnansweredCount() {
        // TODO: Dekludge this when category permissions are refactored (tburry).
        $cacheKey = Gdn::request()->webRoot().'/QnA-UnansweredCount';
        $questionCount = Gdn::cache()->get($cacheKey);

        if ($questionCount === Gdn_Cache::CACHEOP_FAILURE) {
            $questionCount = Gdn::sql()
                ->beginWhereGroup()
                ->where('QnA', null)
                ->orWhereIn('QnA', ['Unanswered', 'Rejected'])
                ->endWhereGroup()
                ->getCount('Discussion', ['Type' => 'Question']);

            Gdn::cache()->store($cacheKey, $questionCount, [Gdn_Cache::FEATURE_EXPIRY => 15 * 60]);
        }

        // Check to see if another plugin can handle this.
        $this->EventArguments['questionCount'] = &$questionCount;
        $this->fireEvent('unansweredCount');

        return $questionCount;
    }

    /**
     * Displays the amounts of unanswered questions.
     *
     * @param DiscussionsController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionsController_unanswered_render($sender, $args) {
        $sender->setData('CountDiscussions', false);

        // Add 'Ask a Question' button if using BigButtons.
        if (c('Plugins.QnA.UseBigButtons')) {
            $questionModule = new NewQuestionModule($sender, 'plugins/QnA');
            $sender->addModule($questionModule);
        }

        // Remove announcements that aren't questions...
        if (is_a($sender->data('Announcements'), 'Gdn_DataSet')) {
            $sender->data('Announcements')->result();
            $announcements = [];
            foreach ($sender->data('Announcements') as $i => $row) {
                if (val('Type', $row) == 'Question') {
                    $announcements[] = $row;
                }
            }
            trace($announcements);
            $sender->setData('Announcements', $announcements);
            $sender->AnnounceData = $announcements;
        }
    }

    /**
     * Displays the amounts of unanswered questions.
     *
     * @param DiscussionsController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionsController_unansweredCount_create($sender, $args) {
        $count = $this->getUnansweredCount();

        $sender->setData('UnansweredCount', $count);
        $sender->setData('_Value', $count);
        $sender->render('Value', 'Utility', 'Dashboard');
    }

    /**
     *
     *
     * @param $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function base_beforeDiscussionMeta_handler($sender, $args) {
        $discussion = $args['Discussion'];

        if (strtolower(val('Type', $discussion)) != 'question') {
            return;
        }

        $qnA = val('QnA', $discussion);
        $title = '';
        switch ($qnA) {
            case '':
            case 'Unanswered':
            case 'Rejected':
                $text = 'Question';
                $qnA = 'Question';
                break;
            case 'Answered':
                $text = 'Answered';
                if (val('InsertUserID', $discussion) == Gdn::session()->UserID) {
                    $qnA = 'Answered';
                    $title = ' title="'.t("Someone's answered your question. You need to accept/reject the answer.").'"';
                }
                break;
            case 'Accepted':
                $text = 'Accepted Answer';
                $title = ' title="'.t("This question's answer has been accepted.").'"';
                break;
            default:
                $qnA = false;
        }
        if ($qnA) {
            echo ' <span class="Tag QnA-Tag-'.$qnA.'"'.$title.'>'.t("Q&A $qnA", $text).'</span> ';
        }
    }

    /**
     * Notifies the current user when one of his questions have been answered.
     *
     * @param NotificationsController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function notificationsController_beforeInformNotifications_handler($sender, $args) {
        $path = trim($sender->Request->getValue('Path'), '/');
        if (preg_match('`^(vanilla/)?discussion[^s]`i', $path)) {
            return;
        }

        // Check to see if the user has answered questions.
        $count = Gdn::sql()->getCount('Discussion', ['Type' => 'Question', 'InsertUserID' => Gdn::session()->UserID, 'QnA' => 'Answered']);
        if ($count > 0) {
            $sender->informMessage(formatString(t("You've asked questions that have now been answered", "<a href=\"{/discussions/mine?qna=Answered,url}\">You've asked questions that now have answers</a>. Make sure you accept/reject the answers.")), 'Dismissable');
        }
    }

    /**
     * Add 'Ask a Question' button if using BigButtons.
     *
     * @param CategoriesController $sender Sending controller instance.
     */
    public function categoriesController_render_before($sender) {
        if (c('Plugins.QnA.UseBigButtons')) {
            $questionModule = new NewQuestionModule($sender, 'plugins/QnA');
            $sender->addModule($questionModule);
        }
    }

    /**
     * Add 'Ask a Question' button if using BigButtons.
     *
     * @param DiscussionController $sender Sending controller instance.
     */
    public function discussionController_render_before($sender) {
        if (c('Plugins.QnA.UseBigButtons')) {
            $questionModule = new NewQuestionModule($sender, 'plugins/QnA');
            $sender->addModule($questionModule);
        }

        if ($sender->data('Discussion.Type') == 'Question') {
            $sender->setData('_CommentsHeader', t('Answers'));
        }
    }

    /**
     * Add the question form to vanilla's post page.
     *
     * @param PostController $sender Sending controller instance.
     */
    public function postController_afterForms_handler($sender) {
        $forms = $sender->data('Forms');
        $forms[] = ['Name' => 'Question', 'Label' => sprite('SpQuestion').t('Ask a Question'), 'Url' => 'post/question'];
        $sender->setData('Forms', $forms);
    }

    /**
     * Create the new question method on post controller.
     *
     * @param PostController $sender Sending controller instance.
     */
    public function postController_question_create($sender, $categoryUrlCode = '') {
        // Create & call PostController->discussion()
        $sender->View = PATH_PLUGINS.'/QnA/views/post.php';
        $sender->setData('Type', 'Question');
        $sender->discussion($categoryUrlCode);
    }

    /**
     * Override the PostController->discussion() method before render to use our view instead.
     *
     * @param PostController $sender Sending controller instance.
     */
    public function postController_beforeDiscussionRender_handler($sender) {
        // Override if we are looking at the question url.
        if ($sender->RequestMethod == 'question') {
            $sender->Form->addHidden('Type', 'Question');
            $sender->title(t('Ask a Question'));
            $sender->setData('Breadcrumbs', [['Name' => $sender->data('Title'), 'Url' => '/post/question']]);
        }
    }

    /**
     * Add 'New Question Form' location to Messages.
     */
    public function messageController_afterGetLocationData_handler($sender, $args) {
        $args['ControllerData']['Vanilla/Post/Question'] = t('New Question Form');
    }

    /**
     * Give point(s) to users for their first answer on an unanswered question!
     *
     * @param CommentModel $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function base_afterSaveComment_handler($sender, $args) {
        if (!c('QnA.Points.Enabled', false) || !$args['Insert']) {
            return;
        }

        $discussionModel = new DiscussionModel();
        $discussion = $discussionModel->getID($args['CommentData']['DiscussionID'], DATASET_TYPE_ARRAY);

        $isCommentAnAnswer = $discussion['Type'] === 'Question';
        $isQuestionResolved = $discussion['QnA'] === 'Accepted';
        $isCurrentUserOriginalPoster = $discussion['InsertUserID'] == GDN::session()->UserID;
        if (!$isCommentAnAnswer || $isQuestionResolved || $isCurrentUserOriginalPoster) {
            return;
        }

        $userAnswersToQuestion = $sender->getWhere([
            'DiscussionID' => $args['CommentData']['DiscussionID'],
            'InsertUserID' => $args['CommentData']['InsertUserID'],
        ]);
        // Award point(s) only for the first answer to the question
        if ($userAnswersToQuestion->count() > 1) {
            return;
        }

        CategoryModel::givePoints(GDN::session()->UserID, c('QnA.Points.Answer', 1), 'QnA', $discussion['CategoryID']);
    }
}
