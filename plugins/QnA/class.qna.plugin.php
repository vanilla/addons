<?php
/**
 * @copyright 2009-2017 Vanilla Forums Inc.
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
    public function settingsController_QnA_create($sender) {
        // Prevent non-admins from accessing this page
        $sender->permission('Garden.Settings.Manage');

        $sender->title(sprintf(t('%s settings'), t('Q&A')));
        $sender->setData('PluginDescription', $this->getPluginKey('Description'));
        $sender->addSideMenu('settings/QnA');

        $sender->Form = new Gdn_Form();
        $validation = new Gdn_Validation();
        $configurationModel = new Gdn_ConfigurationModel($validation);
        $configurationModel->setField(array(
            'QnA.Points.Enabled' => c('QnA.Points.Enabled', false),
            'QnA.Points.Answer' => c('QnA.Points.Answer', 1),
            'QnA.Points.AcceptedAnswer' => c('QnA.Points.AcceptedAnswer', 1),
        ));
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
        $QnA = valr('Comment.QnA', $args);

        if ($QnA && isset($args['CssClass'])) {
            $args['CssClass'] = concatSep(' ', $args['CssClass'], "QnA-Item-$QnA");
        }
    }

    /**
     *
     *
     * @param $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function base_discussionTypes_handler($sender, $args) {
        if (!C('Plugins.QnA.UseBigButtons')) {
            $args['Types']['Question'] = array(
                'Singular' => 'Question',
                'Plural' => 'Questions',
                'AddUrl' => '/post/question',
                'AddText' => 'Ask a Question'
            );
        }
    }

    /**
     *
     * @param $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function base_commentInfo_handler($sender, $args) {
        $Type = val('Type', $args);
        if ($Type != 'Comment') {
            return;
        }

        $QnA = valr('Comment.QnA', $args);

        if ($QnA && ($QnA == 'Accepted' || Gdn::session()->checkPermission('Garden.Moderation.Manage'))) {
            $Title = t("QnA $QnA Answer", "$QnA Answer");
            echo ' <span class="Tag QnA-Box QnA-'.$QnA.'" title="'.htmlspecialchars($Title).'"><span>'.$Title.'</span></span> ';
        }
    }

    /**
     *
     *
     * @param DiscussionController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionController_commentOptions_handler($sender, $args) {
        $Comment = $args['Comment'];
        if (!$Comment) {
            return;
        }
        $Discussion = Gdn::controller()->data('Discussion');

        if (val('Type', $Discussion) != 'Question') {
            return;
        }

        if (!Gdn::session()->checkPermission('Vanilla.Discussions.Edit', true, 'Category', $Discussion->PermissionCategoryID)) {
            return;
        }

        $args['CommentOptions']['QnA'] = array('Label' => t('Q&A').'...', 'Url' => '/discussion/qnaoptions?commentid='.$Comment->CommentID, 'Class' => 'Popup');
    }

    /**
     *
     *
     * @param CommentModel $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function base_discussionOptions_handler($sender, $args) {
        $Discussion = $args['Discussion'];
        if (!Gdn::session()->checkPermission('Vanilla.Discussions.Edit', true, 'Category', $Discussion->PermissionCategoryID)) {
            return;
        }

        if (isset($args['DiscussionOptions'])) {
            $args['DiscussionOptions']['QnA'] = array('Label' => t('Q&A').'...', 'Url' => '/discussion/qnaoptions?discussionid='.$Discussion->DiscussionID, 'Class' => 'Popup');
        } elseif (isset($sender->Options)) {
            $sender->Options .= '<li>'.anchor(t('Q&A').'...', '/discussion/qnaoptions?discussionid='.$Discussion->DiscussionID, 'Popup QnAOptions') . '</li>';
        }
    }

    /**
     *
     *
     * @param CommentModel $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function commentModel_beforeNotification_handler($sender, $args) {
        $ActivityModel = $args['ActivityModel'];
        $Comment = (array)$args['Comment'];
        $CommentID = $Comment['CommentID'];
        $Discussion = (array)$args['Discussion'];

        if ($Comment['InsertUserID'] == $Discussion['InsertUserID']) {
            return;
        }
        if (strtolower($Discussion['Type']) != 'question') {
            return;
        }
        if (!c('Plugins.QnA.Notifications', true)) {
            return;
        }

        $HeadlineFormat = t('HeadlineFormat.Answer', '{ActivityUserID,user} answered your question: <a href="{Url,html}">{Data.Name,text}</a>');

        $Activity = array(
            'ActivityType' => 'Comment',
            'ActivityUserID' => $Comment['InsertUserID'],
            'NotifyUserID' => $Discussion['InsertUserID'],
            'HeadlineFormat' => $HeadlineFormat,
            'RecordType' => 'Comment',
            'RecordID' => $CommentID,
            'Route' => "/discussion/comment/$CommentID#Comment_$CommentID",
            'Data' => array(
                'Name' => val('Name', $Discussion)
            )
        );

        $ActivityModel->queue($Activity, 'DiscussionComment');
    }

    /**
     *
     *
     * @param CommentModel $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function commentModel_beforeUpdateCommentCount_handler($sender, $args) {
        $Discussion =& $args['Discussion'];

        // Mark the question as answered.
        if (strtolower($Discussion['Type']) == 'question' && !$Discussion['Sink'] && !in_array($Discussion['QnA'], array('Answered', 'Accepted'))) {
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
        $CommentModel = new CommentModel();
        $Answers = $CommentModel->getWhere(array('DiscussionID' => $sender->data('Discussion.DiscussionID'), 'Qna' => 'Accepted'))->result();

        if (class_exists('ReplyModel')) {
            $ReplyModel = new ReplyModel();
            $Discussion = null;
            $ReplyModel->joinReplies($Discussion, $Answers);
        }

        $sender->setData('Answers', $Answers);

        // Remove the accepted answers from the comments.
        // Allow this to be skipped via config.
        if (c('QnA.AcceptedAnswers.Filter', true)) {
            if (isset($sender->Data['Comments'])) {
                $Comments = $sender->Data['Comments']->result();
                $Comments = array_filter($Comments, function($Row) {
                    return strcasecmp(val('QnA', $Row), 'accepted');
                });
                $sender->Data['Comments'] = new Gdn_DataSet(array_values($Comments));
            }
        }
    }

    /**
     * Write the accept/reject buttons.
     *
     * @staticvar null $DiscussionModel
     * @staticvar boolean $InformMessage
     *
     * @param DiscussionController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionController_afterCommentBody_handler($sender, $args) {
        $Discussion = $sender->data('Discussion');
        $Comment = val('Comment', $args);

        if (!$Comment) {
            return;
        }

        $CommentID = val('CommentID', $Comment);
        if (!is_numeric($CommentID)) {
            return;
        }

        if (!$Discussion) {
            static $DiscussionModel = null;
            if ($DiscussionModel === null) {
                $DiscussionModel = new DiscussionModel();
            }
            $Discussion = $DiscussionModel->getID(val('DiscussionID', $Comment));
        }

        if (!$Discussion || strtolower(val('Type', $Discussion)) != 'question') {
            return;
        }

        // Check permissions.
        $CanAccept = Gdn::session()->checkPermission('Garden.Moderation.Manage');
        $CanAccept |= Gdn::session()->UserID == val('InsertUserID', $Discussion);

        if (!$CanAccept) {
            return;
        }

        $QnA = val('QnA', $Comment);
        if ($QnA) {
            return;
        }


        $Query = http_build_query(array('commentid' => $CommentID, 'tkey' => Gdn::session()->transientKey()));

        echo '<div class="ActionBlock QnA-Feedback">';

        echo '<span class="DidThisAnswer">'.t('Did this answer the question?').'</span> ';

        echo '<span class="QnA-YesNo">';

        echo anchor(t('Yes'), '/discussion/qna/accept?'.$Query, array('class' => 'React QnA-Yes', 'title' => t('Accept this answer.')));
        echo ' '.bullet().' ';
        echo anchor(t('No'), '/discussion/qna/reject?'.$Query, array('class' => 'React QnA-No', 'title' => t('Reject this answer.')));

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
    public function discussionController_QnA_create($sender, $args) {
        $Comment = Gdn::SQL()->getWhere('Comment', array('CommentID' => $sender->Request->get('commentid')))->firstRow(DATASET_TYPE_ARRAY);
        if (!$Comment) {
            throw notFoundException('Comment');
        }

        $Discussion = Gdn::SQL()->getWhere('Discussion', array('DiscussionID' => $Comment['DiscussionID']))->firstRow(DATASET_TYPE_ARRAY);

        // Check for permission.
        if (!(Gdn::session()->UserID == val('InsertUserID', $Discussion) || Gdn::session()->checkPermission('Garden.Moderation.Manage'))) {
            throw permissionException('Garden.Moderation.Manage');
        }
        if (!Gdn::session()->validateTransientKey($sender->Request->get('tkey'))) {
            throw permissionException();
        }

        switch ($args[0]) {
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
            if ($Discussion['QnA'] != $QnA && (!$Discussion['QnA'] || in_array($Discussion['QnA'], array('Unanswered', 'Answered', 'Rejected')))) {
                Gdn::SQL()->put(
                    'Discussion',
                    $DiscussionSet,
                    array('DiscussionID' => $Comment['DiscussionID']));
            }

            // Determine QnA change
            if ($Comment['QnA'] != $QnA) {
                $Change = 0;
                switch ($QnA) {
                    case 'Rejected':
                        $Change = -1;
                        if ($Comment['QnA'] != 'Accepted') {
                            $Change = 0;
                        }
                        break;

                    case 'Accepted':
                        $Change = 1;
                        break;

                    default:
                        if ($Comment['QnA'] == 'Rejected') {
                            $Change = 0;
                        }
                        if ($Comment['QnA'] == 'Accepted') {
                            $Change = -1;
                        }
                        break;
                }
            }

            // Apply change effects
            if ($Change && $Discussion['InsertUserID'] != $Comment['InsertUserID']) {
                // Update the user
                $UserID = val('InsertUserID', $Comment);
                $this->recalculateUserQnA($UserID);

                // Update reactions
                if ($this->Reactions) {
                    include_once(Gdn::controller()->fetchViewLocation('reaction_functions', '', 'plugins/Reactions'));
                    $Rm = new ReactionModel();

                    // Assume that the reaction is done by the question's owner
                    $questionOwner = $Discussion['InsertUserID'];
                    // If there's change, reactions will take care of it
                    $Rm->react('Comment', $Comment['CommentID'], 'AcceptAnswer', $questionOwner, true);
                } else {
                    $nbsPoint = $Change * (int)c('QnA.Points.AcceptedAnswer', 1);
                    if ($nbsPoint && c('QnA.Points.Enabled', false)) {
                        UserModel::givePoints($Comment['InsertUserID'], $nbsPoint, 'QnA');
                    }
                }
            }

            $headlineFormat = t('HeadlineFormat.AcceptAnswer', '{ActivityUserID,You} accepted {NotifyUserID,your} answer to a question: <a href="{Url,html}">{Data.Name,text}</a>');

            // Record the activity.
            if ($QnA == 'Accepted') {
                $Activity = array(
                    'ActivityType' => 'AnswerAccepted',
                    'NotifyUserID' => $Comment['InsertUserID'],
                    'HeadlineFormat' => $headlineFormat,
                    'RecordType' => 'Comment',
                    'RecordID' => $Comment['CommentID'],
                    'Route' => commentUrl($Comment, '/'),
                    'Emailed' => ActivityModel::SENT_PENDING,
                    'Notified' => ActivityModel::SENT_PENDING,
                    'Data' => array(
                        'Name' => val('Name', $Discussion)
                    )
                );

                $ActivityModel = new ActivityModel();
                $ActivityModel->save($Activity);

                $this->EventArguments['Activity'] =& $Activity;
                $this->fireEvent('AfterAccepted');
            }
        }
        redirect("/discussion/comment/{$Comment['CommentID']}#Comment_{$Comment['CommentID']}");
    }

    /**
     *
     *
     * @param DiscussionController $sender Sending controller instance.
     * @param string|int $discussionID Identifier of the discussion
     * @param string|int $commentID Identifier of the comment.
     */
    public function discussionController_QnAOptions_create($sender, $discussionID = '', $commentID = '') {
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
        $Set = array();

        $Row = Gdn::SQL()->getWhere('Comment',
            array('DiscussionID' => val('DiscussionID', $discussion), 'QnA is not null' => ''), 'QnA, DateAccepted', 'asc', 1)->firstRow(DATASET_TYPE_ARRAY);

        if (!$Row) {
            if (val('CountComments', $discussion) > 0) {
                $Set['QnA'] = 'Unanswered';
            } else {
                $Set['QnA'] = 'Answered';
            }

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

        Gdn::controller()->DiscussionModel->setField(val('DiscussionID', $discussion), $Set);
    }

    /**
     *
     *
     * @param string|int $userID User identifier
     */
    public function recalculateUserQnA($userID) {
        $CountAcceptedAnswers = Gdn::SQL()->getCount('Comment', array('InsertUserID' => $userID, 'QnA' => 'Accepted'));
        Gdn::userModel()->setField($userID, 'CountAcceptedAnswers', $CountAcceptedAnswers);
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

        $Comment = $sender->CommentModel->getID($commentID, DATASET_TYPE_ARRAY);

        if (!$Comment) {
            throw notFoundException('Comment');
        }

        $Discussion = $sender->DiscussionModel->getID(val('DiscussionID', $Comment), DATASET_TYPE_ARRAY);

        $sender->permission('Vanilla.Discussions.Edit', true, 'Category', val('PermissionCategoryID', $Discussion));

        if ($sender->Form->authenticatedPostBack()) {
            $newQnA = $sender->Form->getFormValue('QnA');
            if (!$newQnA) {
                $newQnA = null;
            }

            $CurrentQnA = val('QnA', $Comment);

            if ($CurrentQnA != $newQnA) {
                $Set = array('QnA' => $newQnA);

                if ($newQnA == 'Accepted') {
                    $Set['DateAccepted'] = Gdn_Format::toDateTime();
                    $Set['AcceptedUserID'] = Gdn::session()->UserID;
                } else {
                    $Set['DateAccepted'] = null;
                    $Set['AcceptedUserID'] = null;
                }

                $sender->CommentModel->setField($commentID, $Set);
                $sender->Form->setValidationResults($sender->CommentModel->validationResults());

                // Determine QnA change
                if ($CurrentQnA != $newQnA) {
                    $Change = 0;
                    switch ($newQnA) {
                        case 'Rejected':
                            $Change = -1;
                            if ($CurrentQnA != 'Accepted') {
                                $Change = 0;
                            }
                            break;

                        case 'Accepted':
                            $Change = 1;
                            break;

                        default:
                            if ($CurrentQnA == 'Rejected') {
                                $Change = 0;
                            }
                            if ($CurrentQnA == 'Accepted') {
                                $Change = -1;
                            }
                            break;
                    }
                }

                // Apply change effects
                if ($Change && $Discussion['InsertUserID'] != $Comment['InsertUserID']) {
                    // Update the user
                    $UserID = val('InsertUserID', $Comment);
                    $this->recalculateUserQnA($UserID);

                    // Update reactions
                    if ($this->Reactions) {
                        include_once(Gdn::controller()->fetchViewLocation('reaction_functions', '', 'plugins/Reactions'));
                        $Rm = new ReactionModel();

                        // Assume that the reaction is done by the question's owner
                        $questionOwner = $Discussion['InsertUserID'];
                        // If there's change, reactions will take care of it
                        $Rm->react('Comment', $Comment['CommentID'], 'AcceptAnswer', $questionOwner, true);
                    } else {
                        $nbsPoint = $Change * (int)c('QnA.Points.AcceptedAnswer', 1);
                        if ($nbsPoint && c('QnA.Points.Enabled', false)) {
                            UserModel::givePoints($Comment['InsertUserID'], $nbsPoint, 'QnA');
                        }
                    }
                }
            }

            // Recalculate the Q&A status of the discussion.
            $this->recalculateDiscussionQnA($Discussion);

            Gdn::controller()->jsonTarget('', '', 'Refresh');
        } else {
            $sender->Form->setData($Comment);
        }

        $sender->setData('Comment', $Comment);
        $sender->setData('Discussion', $Discussion);
        $sender->setData('_QnAs', array('Accepted' => t('Yes'), 'Rejected' => t('No'), '' => t("Don't know")));
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

        $Discussion = $sender->DiscussionModel->getID($discussionID);

        if (!$Discussion) {
            throw notFoundException('Discussion');
        }

        $sender->permission('Vanilla.Discussions.Edit', true, 'Category', val('PermissionCategoryID', $Discussion));

        // Both '' and 'Discussion' denote a discussion type of discussion.
        if (!val('Type', $Discussion)) {
            setValue('Type', $Discussion, 'Discussion');
        }

        if ($sender->Form->isPostBack()) {
            $sender->DiscussionModel->setField($discussionID, 'Type', $sender->Form->getFormValue('Type'));

            // Update the QnA field.  Default to "Unanswered" for questions. Null the field for other types.
            $qna = val('QnA', $Discussion);
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
//         $Form = new Gdn_Form();
            $sender->Form->setValidationResults($sender->DiscussionModel->validationResults());

//         if ($sender->DeliveryType() == DELIVERY_TYPE_ALL || $Redirect)
//            $sender->RedirectUrl = Gdn::Controller()->Request->PathAndQuery();
            Gdn::controller()->jsonTarget('', '', 'Refresh');
        } else {
            $sender->Form->setData($Discussion);
        }

        $sender->setData('Discussion', $Discussion);
        $sender->setData('_Types', array('Question' => '@'.t('Question Type', 'Question'), 'Discussion' => '@'.t('Discussion Type', 'Discussion')));
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
            $Unanswered = Gdn::controller()->ClassName == 'DiscussionsController' && Gdn::controller()->RequestMethod == 'unanswered';

            if ($Unanswered) {
                $args['Wheres']['Type'] = 'Question';
                $sender->SQL->beginWhereGroup()
                    ->where('d.QnA', null)
                    ->orWhereIn('d.QnA', array('Unanswered', 'Rejected'))
                    ->endWhereGroup();
                Gdn::controller()->title(t('Unanswered Questions'));
            } elseif ($QnA = Gdn::request()->get('qna')) {
                $args['Wheres']['QnA'] = $QnA;
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
        $Post =& $args['FormPostValues'];
        if ($args['Insert'] && val('Type', $Post) == 'Question') {
            $Post['QnA'] = 'Unanswered';
        }
    }

    /**
     * New Html method of adding to discussion filters.
     *
     * @param $sender
     */
    public function base_afterDiscussionFilters_handler($sender) {
        $Count = Gdn::cache()->get('QnA-UnansweredCount');
        if ($Count === Gdn_Cache::CACHEOP_FAILURE) {
            $Count = ' <span class="Aside"><span class="Popin Count" rel="/discussions/unansweredcount"></span>';
        } else {
            $Count = ' <span class="Aside"><span class="Count">'.$Count.'</span></span>';
        }

        echo '<li class="QnA-UnansweredQuestions '.($sender->RequestMethod == 'unanswered' ? ' Active' : '').'">'
            .anchor(sprite('SpUnansweredQuestions').' '.t('Unanswered').$Count, '/discussions/unanswered', 'UnansweredQuestions')
            .'</li>';
    }

    /**
     * Old Html method of adding to discussion filters.
     */
    public function discussionsController_afterDiscussionTabs_handler() {
        if (stringEndsWith(Gdn::request()->path(), '/unanswered', true)) {
            $CssClass = ' class="Active"';
        } else {
            $CssClass = '';
        }

        $Count = Gdn::cache()->get('QnA-UnansweredCount');
        if ($Count === Gdn_Cache::CACHEOP_FAILURE) {
            $Count = ' <span class="Popin Count" rel="/discussions/unansweredcount">';
        } else {
            $Count = ' <span class="Count">'.$Count.'</span>';
        }

        echo '<li'.$CssClass.'><a class="TabLink QnA-UnansweredQuestions" href="'.url('/discussions/unanswered').'">'.t('Unanswered Questions', 'Unanswered').$Count.'</span></a></li>';
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
            $Count = $this->getUnansweredCount();
            $sender->setData('CountDiscussions', $Count);
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
                ->orWhereIn('QnA', array('Unanswered', 'Rejected'))
                ->endWhereGroup()
                ->getCount('Discussion', array('Type' => 'Question'));

            Gdn::cache()->store($cacheKey, $questionCount, array(Gdn_Cache::FEATURE_EXPIRY => 15 * 60));
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
            $QuestionModule = new NewQuestionModule($sender, 'plugins/QnA');
            $sender->addModule($QuestionModule);
        }

        // Remove announcements that aren't questions...
        if (is_a($sender->data('Announcements'), 'Gdn_DataSet')) {
            $sender->data('Announcements')->result();
            $Announcements = array();
            foreach ($sender->data('Announcements') as $i => $Row) {
                if (val('Type', $Row) == 'Question') {
                    $Announcements[] = $Row;
                }
            }
            trace($Announcements);
            $sender->setData('Announcements', $Announcements);
            $sender->AnnounceData = $Announcements;
        }
    }

    /**
     * Displays the amounts of unanswered questions.
     *
     * @param DiscussionsController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionsController_unansweredCount_create($sender, $args) {
        $Count = $this->getUnansweredCount();

        $sender->setData('UnansweredCount', $Count);
        $sender->setData('_Value', $Count);
        $sender->render('Value', 'Utility', 'Dashboard');
    }

    /**
     *
     *
     * @param $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function base_beforeDiscussionMeta_handler($sender, $args) {
        $Discussion = $args['Discussion'];

        if (strtolower(val('Type', $Discussion)) != 'question') {
            return;
        }

        $QnA = val('QnA', $Discussion);
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
                if (val('InsertUserID', $Discussion) == Gdn::session()->UserID) {
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
     * Notifies the current user when one of his questions have been answered.
     *
     * @param NotificationsController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function notificationsController_beforeInformNotifications_handler($sender, $args) {
        $Path = trim($sender->Request->getValue('Path'), '/');
        if (preg_match('`^(vanilla/)?discussion[^s]`i', $Path)) {
            return;
        }

        // Check to see if the user has answered questions.
        $Count = Gdn::SQL()->getCount('Discussion', array('Type' => 'Question', 'InsertUserID' => Gdn::session()->UserID, 'QnA' => 'Answered'));
        if ($Count > 0) {
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
            $QuestionModule = new NewQuestionModule($sender, 'plugins/QnA');
            $sender->addModule($QuestionModule);
        }
    }

    /**
     * Add 'Ask a Question' button if using BigButtons.
     *
     * @param DiscussionController $sender Sending controller instance.
     */
    public function discussionController_render_before($sender) {
        if (c('Plugins.QnA.UseBigButtons')) {
            $QuestionModule = new NewQuestionModule($sender, 'plugins/QnA');
            $sender->addModule($QuestionModule);
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
        $Forms = $sender->data('Forms');
        $Forms[] = array('Name' => 'Question', 'Label' => sprite('SpQuestion').t('Ask a Question'), 'Url' => 'post/question');
        $sender->setData('Forms', $Forms);
    }

    /**
     * Create the new question method on post controller.
     *
     * @param PostController $sender Sending controller instance.
     */
    public function postController_question_create($sender, $CategoryUrlCode = '') {
        // Create & call PostController->Discussion()
        $sender->View = PATH_PLUGINS.'/QnA/views/post.php';
        $sender->setData('Type', 'Question');
        $sender->discussion($CategoryUrlCode);
    }

    /**
     * Override the PostController->Discussion() method before render to use our view instead.
     *
     * @param PostController $sender Sending controller instance.
     */
    public function postController_beforeDiscussionRender_handler($sender) {
        // Override if we are looking at the question url.
        if ($sender->RequestMethod == 'question') {
            $sender->Form->addHidden('Type', 'Question');
            $sender->title(t('Ask a Question'));
            $sender->setData('Breadcrumbs', array(array('Name' => $sender->data('Title'), 'Url' => '/post/question')));
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

        UserModel::givePoints(GDN::session()->UserID, c('QnA.Points.Answer', 1), 'QnA');
    }
}
