<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */

// Define the plugin:
$PluginInfo['QnA'] = array(
   'Name' => 'Q&A',
   'Description' => "Users may designate a discussion as a Question and then officially accept one or more of the comments as the answer.",
   'Version' => '1.1',
   'RequiredApplications' => array('Vanilla' => '2.0.18'),
   'MobileFriendly' => TRUE,
   'Author' => 'Todd Burry',
   'AuthorEmail' => 'todd@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.org/profile/todd'
);

/**
 * Adds Question & Answer format to Vanilla.
 *
 * You can set Plugins.QnA.UseBigButtons = TRUE in config to separate 'New Discussion'
 * and 'Ask Question' into "separate" forms each with own big button in Panel.
 */
class QnAPlugin extends Gdn_Plugin {
   /// PROPERTIES ///

   /// METHODS ///

   public function Setup() {
      $this->Structure();
   }

   public function Structure() {
      Gdn::Structure()
         ->Table('Discussion');

      $QnAExists = Gdn::Structure()->ColumnExists('QnA');
      $DateAcceptedExists = Gdn::Structure()->ColumnExists('DateAccepted');

      Gdn::Structure()
         ->Column('QnA', array('Unanswered', 'Answered', 'Accepted', 'Rejected'), NULL)
         ->Column('DateAccepted', 'datetime', TRUE) // The
         ->Column('DateOfAnswer', 'datetime', TRUE) // The time to answer an accepted question.
         ->Set();

      Gdn::Structure()
         ->Table('Comment')
         ->Column('QnA', array('Accepted', 'Rejected'), NULL)
         ->Column('DateAccepted', 'datetime', TRUE)
         ->Column('AcceptedUserID', 'int', TRUE)
         ->Set();

      Gdn::SQL()->Replace(
         'ActivityType',
         array('AllowComments' => '0', 'RouteCode' => 'question', 'Notify' => '1', 'Public' => '0', 'ProfileHeadline' => '', 'FullHeadline' => ''),
         array('Name' => 'QuestionAnswer'), TRUE);
      Gdn::SQL()->Replace(
         'ActivityType',
         array('AllowComments' => '0', 'RouteCode' => 'answer', 'Notify' => '1', 'Public' => '0', 'ProfileHeadline' => '', 'FullHeadline' => ''),
         array('Name' => 'AnswerAccepted'), TRUE);
      // Add Activity Type for Answer Rejected
      Gdn::SQL()->Replace(
         'ActivityType',
         array('AllowComments' => '0', 'RouteCode' => 'answer', 'Notify' => '1', 'Public' => '0', 'ProfileHeadline' => '', 'FullHeadline' => ''),
         array('Name' => 'AnswerRejected'), TRUE);

      if ($QnAExists && !$DateAcceptedExists) {
         // Default the date accepted to the accepted answer's date.
         $Px = Gdn::Database()->DatabasePrefix;
         $Sql = "update {$Px}Discussion d set DateAccepted = (select min(c.DateInserted) from {$Px}Comment c where c.DiscussionID = d.DiscussionID and c.QnA = 'Accepted')";
         Gdn::SQL()->Query($Sql, 'update');
         Gdn::SQL()->Update('Discussion')
            ->Set('DateOfAnswer', 'DateAccepted', FALSE, FALSE)
            ->Put();

         Gdn::SQL()->Update('Comment c')
            ->Join('Discussion d', 'c.CommentID = d.DiscussionID')
            ->Set('c.DateAccepted', 'c.DateInserted', FALSE, FALSE)
            ->Set('c.AcceptedUserID', 'd.InsertUserID', FALSE, FALSE)
            ->Where('c.QnA', 'Accepted')
            ->Where('c.DateAccepted', NULL)
            ->Put();
      }
   }


   /// EVENTS ///

   public function Base_BeforeCommentDisplay_Handler($Sender, $Args) {
      $QnA = GetValueR('Comment.QnA', $Args);

      if ($QnA && isset($Args['CssClass'])) {
         $Args['CssClass'] = ConcatSep(' ', $Args['CssClass'], "QnA-Item-$QnA");
      }
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
//         static $DiscussionModel = NULL;
//         if ($DiscussionModel === NULL)
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
//      static $InformMessage = TRUE;
//
//      if ($InformMessage && Gdn::Session()->UserID == GetValue('InsertUserID', $Discussion) && in_array(GetValue('QnA', $Discussion), array('', 'Answered'))) {
//         $Sender->InformMessage(T('Click accept or reject beside an answer.'), 'Dismissable');
//         $InformMessage = FALSE;
//      }
//   }

   public function Base_CommentInfo_Handler($Sender, $Args) {
      $Type = GetValue('Type', $Args);
      if ($Type != 'Comment')
         return;

      $QnA = GetValueR('Comment.QnA', $Args);

      if ($QnA && ($QnA == 'Accepted' || Gdn::Session()->CheckPermission('Garden.Moderation.Manage'))) {
         $Title = T("QnA $QnA Answer", "$QnA Answer");
         echo ' <span class="Tag QnA-Box QnA-'.$QnA.'" title="'.htmlspecialchars($Title).'"><span>'.$Title.'</span></span> ';
      }
   }

   public function DiscussionController_CommentOptions_Handler($Sender, $Args) {
      $Comment = $Args['Comment'];
      if (!$Comment)
         return;
      $Discussion = Gdn::Controller()->Data('Discussion');

      if (GetValue('Type', $Discussion) != 'Question')
         return;

      if (!Gdn::Session()->CheckPermission('Vanilla.Discussions.Edit', TRUE, 'Category', $Discussion->PermissionCategoryID))
         return;

      $Args['CommentOptions']['QnA'] = array('Label' => T('Q&A').'...', 'Url' => '/discussion/qnaoptions?commentid='.$Comment->CommentID, 'Class' => 'Popup');
   }

   public function Base_DiscussionOptions_Handler($Sender, $Args) {
      $Discussion = $Args['Discussion'];
      if (!Gdn::Session()->CheckPermission('Vanilla.Discussions.Edit', TRUE, 'Category', $Discussion->PermissionCategoryID))
         return;

      if (isset($Args['DiscussionOptions'])) {
         $Args['DiscussionOptions']['QnA'] = array('Label' => T('Q&A').'...', 'Url' => '/discussion/qnaoptions?discussionid='.$Discussion->DiscussionID, 'Class' => 'Popup');
      } elseif (isset($Sender->Options)) {
         $Sender->Options .= '<li>'.Anchor(T('Q&A').'...', '/discussion/qnaoptions?discussionid='.$Discussion->DiscussionID, 'Popup QnAOptions') . '</li>';
      }
   }

   public function CommentModel_BeforeNotification_Handler($Sender, $Args) {
      $ActivityModel = $Args['ActivityModel'];
      $Comment = (array)$Args['Comment'];
      $CommentID = $Comment['CommentID'];
      $Discussion = (array)$Args['Discussion'];

      if ($Comment['InsertUserID'] == $Discussion['InsertUserID'])
         return;
      if (strtolower($Discussion['Type']) != 'question')
         return;

      $ActivityID = $ActivityModel->Add(
         $Comment['InsertUserID'],
         'QuestionAnswer',
         Anchor(Gdn_Format::Text($Discussion['Name']), "discussion/comment/$CommentID/#Comment_$CommentID"),
         $Discussion['InsertUserID'],
         '',
         "/discussion/comment/$CommentID/#Comment_$CommentID");
      $ActivityModel->QueueNotification($ActivityID, '', 'first');
   }

   /**
    * @param CommentModel $Sender
    * @param array $Args
    */
   public function CommentModel_BeforeUpdateCommentCount_Handler($Sender, $Args) {
      $Discussion =& $Args['Discussion'];

      // Mark the question as answered.
      if (strtolower($Discussion['Type']) == 'question' && !$Discussion['Sink'] && !in_array($Discussion['QnA'], array('Answered', 'Accepted')) && $Discussion['InsertUserID'] != Gdn::Session()->UserID) {
         $Sender->SQL->Set('QnA', 'Answered');
      }
   }

   public function DiscussionController_BeforeDiscussionRender_Handler($Sender, $Args) {
      if (strcasecmp($Sender->Data('Discussion.QnA'), 'Accepted') != 0)
         return;

      // Find the accepted answer(s) to the question.
      $CommentModel = new CommentModel();
      $Answers = $CommentModel->GetWhere(array('DiscussionID' => $Sender->Data('Discussion.DiscussionID'), 'Qna' => 'Accepted'))->Result();

      $Sender->SetData('Answers', $Answers);
   }

   /**
    * Write the accept/reject buttons.
    * @staticvar null $DiscussionModel
    * @staticvar boolean $InformMessage
    * @param type $Sender
    * @param type $Args
    * @return type
    */
   public function DiscussionController_AfterCommentBody_Handler($Sender, $Args) {
      $Discussion = GetValue('Discussion', $Args);
      $Comment = GetValue('Comment', $Args);

      if (!$Comment)
         return;

      $CommentID = GetValue('CommentID', $Comment);
      if (!is_numeric($CommentID))
         return;

      if (!$Discussion) {
         static $DiscussionModel = NULL;
         if ($DiscussionModel === NULL)
            $DiscussionModel = new DiscussionModel();
         $Discussion = $DiscussionModel->GetID(GetValue('DiscussionID', $Comment));
      }

      if (!$Discussion || strtolower(GetValue('Type', $Discussion)) != 'question')
         return;

      // Check permissions.
      $CanAccept = Gdn::Session()->CheckPermission('Garden.Moderation.Manage');
      $CanAccept |= Gdn::Session()->UserID == GetValue('InsertUserID', $Discussion) && Gdn::Session()->UserID != GetValue('InsertUserID', $Comment);

      if (!$CanAccept)
         return;

      $QnA = GetValue('QnA', $Comment);
      if ($QnA)
         return;

      // Write the links.
//      $Types = GetValue('ReactionTypes', $Sender->EventArguments);
//      if ($Types)
//         echo Bullet();

      $Query = http_build_query(array('commentid' => $CommentID, 'tkey' => Gdn::Session()->TransientKey()));

      echo '<div class="ActionBlock QnA-Feedback">';

//      echo '<span class="FeedbackLabel">'.T('Feedback').'</span>';

      echo '<span class="DidThisAnswer">'.T('Did this answer the question?').'</span> ';

      echo '<span class="QnA-YesNo">';

      echo Anchor(T('Yes'), '/discussion/qna/accept?'.$Query, array('class' => 'React QnA-Yes', 'title' => T('Accept this answer.')));
      //echo ' '.Bullet().' ';
      echo Anchor(T('No'), '/discussion/qna/reject?'.$Query, array('class' => 'React QnA-No', 'title' => T('Reject this answer.')));

      echo '</span>';

      echo '</div>';

//      static $InformMessage = TRUE;
//
//      if ($InformMessage && Gdn::Session()->UserID == GetValue('InsertUserID', $Discussion) && in_array(GetValue('QnA', $Discussion), array('', 'Answered'))) {
//         $Sender->InformMessage(T('Click accept or reject beside an answer.'), 'Dismissable');
//         $InformMessage = FALSE;
//      }
   }

   /**
    *
    * @param DiscussionController $Sender
    * @param type $Args
    * @return type
    */
   public function DiscussionController_AfterDiscussion_Handler($Sender, $Args) {
      if ($Sender->Data('Answers'))
         include $Sender->FetchViewLocation('Answers', '', 'plugins/QnA');
   }


   /**
    *
    * @param DiscussionController $Sender
    * @param array $Args
    */
   public function DiscussionController_QnA_Create($Sender, $Args = array()) {
      $Comment = Gdn::SQL()->GetWhere('Comment', array('CommentID' => $Sender->Request->Get('commentid')))->FirstRow(DATASET_TYPE_ARRAY);
      if (!$Comment)
         throw NotFoundException('Comment');

      $Discussion = Gdn::SQL()->GetWhere('Discussion', array('DiscussionID' => $Comment['DiscussionID']))->FirstRow(DATASET_TYPE_ARRAY);

      // Check for permission.
      if (!(Gdn::Session()->UserID == GetValue('InsertUserID', $Discussion) || Gdn::Session()->CheckPermission('Garden.Moderation.Manage'))) {
         throw PermissionException('Garden.Moderation.Manage');
      }
      if (!Gdn::Session()->ValidateTransientKey($Sender->Request->Get('tkey')))
         throw PermissionException();

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
            $CommentSet['DateAccepted'] = Gdn_Format::ToDateTime();
            $CommentSet['AcceptedUserID'] = Gdn::Session()->UserID;

            if (!$Discussion['DateAccepted']) {
               $DiscussionSet['DateAccepted'] = Gdn_Format::ToDateTime();
               $DiscussionSet['DateOfAnswer'] = $Comment['DateInserted'];
            }
         }

         // Update the comment.
         Gdn::SQL()->Put('Comment', $CommentSet, array('CommentID' => $Comment['CommentID']));

         // Update the discussion.
         if ($Discussion['QnA'] != $QnA && (!$Discussion['QnA'] || in_array($Discussion['QnA'], array('Unanswered', 'Answered', 'Rejected'))))
            Gdn::SQL()->Put(
               'Discussion',
               $DiscussionSet,
               array('DiscussionID' => $Comment['DiscussionID']));

         // Record the activity.
         AddActivity(Gdn::Session()->UserID,
                     'Answer' . $QnA, // Build "AnswerAccepted" or "AnswerRejected" string
		                 Anchor(Gdn_Format::Text($Discussion['Name']), "/discussion/{$Discussion['DiscussionID']}/".Gdn_Format::Url($Discussion['Name'])),
		                 $Comment['InsertUserID'],
		                 "/discussion/comment/{$Comment['CommentID']}/#Comment_{$Comment['CommentID']}"
		                );
      }
      Redirect("/discussion/comment/{$Comment['CommentID']}#Comment_{$Comment['CommentID']}");
   }

   public function DiscussionController_QnAOptions_Create($Sender, $DiscussionID = '', $CommentID = '') {
      if ($DiscussionID)
         $this->_DiscussionOptions($Sender, $DiscussionID);
      elseif ($CommentID)
         $this->_CommentOptions($Sender, $CommentID);

   }

   public function RecalculateDiscussionQnA($Discussion) {
      // Find comments in this discussion with a QnA value.
      $Set = array();

      $Row = Gdn::SQL()->GetWhere('Comment',
         array('DiscussionID' => GetValue('DiscussionID', $Discussion), 'QnA is not null' => ''), 'QnA, DateAccepted', 'asc', 1)->FirstRow(DATASET_TYPE_ARRAY);

      if (!$Row) {
         if (GetValue('CountComments', $Discussion) > 0)
            $Set['QnA'] = 'Unanswered';
         else
            $Set['QnA'] = 'Answered';

         $Set['DateAccepted'] = NULL;
         $Set['DateOfAnswer'] = NULL;
      } elseif ($Row['QnA'] == 'Accepted') {
         $Set['QnA'] = 'Accepted';
         $Set['DateAccepted'] = $Row['DateAccepted'];
         $Set['DateOfAnswer'] = $Row['DateInserted'];
      } elseif ($Row['QnA'] == 'Rejected') {
         $Set['QnA'] = 'Rejected';
         $Set['DateAccepted'] = NULL;
         $Set['DateOfAnswer'] = NULL;
      }

      Gdn::Controller()->DiscussionModel->SetField(GetValue('DiscussionID', $Discussion), $Set);
   }

   public function _CommentOptions($Sender, $CommentID) {
      $Sender->Form = new Gdn_Form();

      $Comment = $Sender->CommentModel->GetID($CommentID);

      if (!$Comment)
         throw NotFoundException('Comment');

      $Discussion = $Sender->DiscussionModel->GetID(GetValue('DiscussionID', $Comment));

      $Sender->Permission('Vanilla.Discussions.Edit', TRUE, 'Category', GetValue('PermissionCategoryID', $Discussion));

      if ($Sender->Form->IsPostBack()) {
         $QnA = $Sender->Form->GetFormValue('QnA');
         if (!$QnA)
            $QnA = NULL;

         $CurrentQnA = GetValue('QnA', $Comment);


//         ->Column('DateAccepted', 'datetime', TRUE)
//         ->Column('AcceptedUserID', 'int', TRUE)

         if ($CurrentQnA != $QnA) {
            $Set = array('QnA' => $QnA);

            if ($QnA == 'Accepted') {
               $Set['DateAccepted'] = Gdn_Format::ToDateTime();
               $Set['AcceptedUserID'] = Gdn::Session()->UserID;
            } else {
               $Set['DateAccepted'] = NULL;
               $Set['AcceptedUserID'] = NULL;
            }

            $Sender->CommentModel->SetField($CommentID, $Set);
            $Sender->Form->SetValidationResults($Sender->CommentModel->ValidationResults());
         }

         // Recalculate the Q&A status of the discussion.
         $this->RecalculateDiscussionQnA($Discussion);

         Gdn::Controller()->JsonTarget('', '', 'Refresh');
      } else {
         $Sender->Form->SetData($Comment);
      }

      $Sender->SetData('Comment', $Comment);
      $Sender->SetData('Discussion', $Discussion);
      $Sender->SetData('_QnAs', array('Accepted' => T('Yes'), 'Rejected' => T('No'), '' => T("Don't know")));
      $Sender->SetData('Title', T('Q&A Options'));
      $Sender->Render('CommentOptions', '', 'plugins/QnA');
   }

   protected function _DiscussionOptions($Sender, $DiscussionID) {
      $Sender->Form = new Gdn_Form();

      $Discussion = $Sender->DiscussionModel->GetID($DiscussionID);

      if (!$Discussion)
         throw NotFoundException('Discussion');



      // Both '' and 'Discussion' denote a discussion type of discussion.
      if (!GetValue('Type', $Discussion))
         SetValue('Type', $Discussion, 'Discussion');

      if ($Sender->Form->IsPostBack()) {
         $Sender->DiscussionModel->SetField($DiscussionID, 'Type', $Sender->Form->GetFormValue('Type'));
//         $Form = new Gdn_Form();
         $Sender->Form->SetValidationResults($Sender->DiscussionModel->ValidationResults());

//         if ($Sender->DeliveryType() == DELIVERY_TYPE_ALL || $Redirect)
//            $Sender->RedirectUrl = Gdn::Controller()->Request->PathAndQuery();
         Gdn::Controller()->JsonTarget('', '', 'Refresh');
      } else {
         $Sender->Form->SetData($Discussion);
      }

      $Sender->SetData('Discussion', $Discussion);
      $Sender->SetData('_Types', array('Question' => T('Question'), 'Discussion' => T('Discussion')));
      $Sender->SetData('Title', T('Q&A Options'));
      $Sender->Render('DiscussionOptions', '', 'plugins/QnA');
   }

   public function DiscussionModel_BeforeGet_Handler($Sender, $Args) {
      $Unanswered = Gdn::Controller()->ClassName == 'DiscussionsController' && Gdn::Controller()->RequestMethod == 'unanswered';

      if ($Unanswered) {
         $Args['Wheres']['Type'] = 'Question';
         $Sender->SQL->WhereIn('d.QnA', array('Unanswered', 'Rejected'));
      } elseif ($QnA = Gdn::Request()->Get('qna')) {
         $Args['Wheres']['QnA'] = $QnA;
      }
   }

   /**
    *
    * @param DiscussionModel $Sender
    * @param array $Args
    */
   public function DiscussionModel_BeforeSaveDiscussion_Handler($Sender, $Args) {
//      $Sender->Validation->ApplyRule('Type', 'Required', T('Choose whether you want to ask a question or start a discussion.'));

      $Post =& $Args['FormPostValues'];
      if ($Args['Insert'] && GetValue('Type', $Post) == 'Question') {
         $Post['QnA'] = 'Unanswered';
      }
   }

   /* New Html method of adding to discussion filters */
   public function DiscussionsController_AfterDiscussionFilters_Handler($Sender) {
      $Count = Gdn::Cache()->Get('QnA-UnansweredCount');
      if ($Count === Gdn_Cache::CACHEOP_FAILURE)
         $Count = ' <span class="Aside"><span class="Popin Count" rel="/discussions/unansweredcount"></span>';
      else
         $Count = ' <span class="Aside"><span class="Count">'.$Count.'</span></span>';

      echo '<li class="QnA-UnansweredQuestions '.($Sender->RequestMethod == 'unanswered' ? ' Active' : '').'">'
			.Anchor(Sprite('SpUnansweredQuestions').T('Unanswered'), '/discussions/unanswered', 'UnansweredQuestions')
         .$Count
		.'</li>';
   }

   /* Old Html method of adding to discussion filters */
   public function DiscussionsController_AfterDiscussionTabs_Handler($Sender, $Args) {
      if (StringEndsWith(Gdn::Request()->Path(), '/unanswered', TRUE))
         $CssClass = ' class="Active"';
      else
         $CssClass = '';

      $Count = Gdn::Cache()->Get('QnA-UnansweredCount');
      if ($Count === Gdn_Cache::CACHEOP_FAILURE)
         $Count = ' <span class="Popin Count" rel="/discussions/unansweredcount">';
      else
         $Count = ' <span class="Count">'.$Count.'</span>';

      echo '<li'.$CssClass.'><a class="TabLink QnA-UnansweredQuestions" href="'.Url('/discussions/unanswered').'">'.T('Unanswered Questions', 'Unanswered').$Count.'</span></a></li>';
   }

   /**
    * @param DiscussionsController $Sender
    * @param array $Args
    */
   public function DiscussionsController_Unanswered_Create($Sender, $Args = array()) {
      $Sender->View = 'Index';
      $Sender->SetData('_PagerUrl', 'discussions/unanswered/{Page}');
      $Sender->Index(GetValue(0, $Args, 'p1'));
      $this->InUnanswered = TRUE;
   }

   /**
    *
    * @param DiscussionsController $Sender
    * @param type $Args
    */
   public function DiscussionsController_Render_Before($Sender, $Args) {
      if (strcasecmp($Sender->RequestMethod, 'unanswered') == 0) {
         $Sender->SetData('CountDiscussions', FALSE);
      }
      // Add 'Ask a Question' button if using BigButtons.
      if (C('Plugins.QnA.UseBigButtons')) {
         $QuestionModule = new NewQuestionModule($Sender, 'plugins/QnA');
         $Sender->AddModule($QuestionModule);
      }

      if (isset($this->InUnanswered)) {
         // Remove announcements that aren't questions...
         $Announcements = $Sender->Data('Announcements');
         foreach ($Announcements as $i => $Row) {
            if (GetValue('Type', $Row) != 'Questions')
               unset($Announcements[$i]);
         }
         $Sender->SetData('Announcements', array_values($Announcements));
      }
   }

    /**
    * @param DiscussionsController $Sender
    * @param array $Args
    */
   public function DiscussionsController_UnansweredCount_Create($Sender, $Args = array()) {
      Gdn::SQL()->WhereIn('QnA', array('Unanswered', 'Rejected'));
      $Count = Gdn::SQL()->GetCount('Discussion', array('Type' => 'Question'));
      Gdn::Cache()->Store('QnA-UnansweredCount', $Count, array(Gdn_Cache::FEATURE_EXPIRY => 15 * 60));

      $Sender->SetData('UnansweredCount', $Count);
      $Sender->SetData('_Value', $Count);
      $Sender->Render('Value', 'Utility', 'Dashboard');
   }

   public function Base_BeforeDiscussionMeta_Handler($Sender, $Args) {
      $Discussion = $Args['Discussion'];

      if (strtolower(GetValue('Type', $Discussion)) != 'question')
         return;

      $QnA = GetValue('QnA', $Discussion);
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
            if (GetValue('InsertUserID', $Discussion) == Gdn::Session()->UserID) {
               $QnA = 'Answered';
               $Title = ' title="'.T("Someone's answered your question. You need to accept/reject the answer.").'"';
            }
            break;
         case 'Accepted':
            $Text = 'Answered';
            $Title = ' title="'.T("This question's answer has been accepted.").'"';
            break;
         default:
            $QnA = FALSE;
      }
      if ($QnA) {
         echo ' <span class="Tag QnA-Tag-'.$QnA.'"'.$Title.'>'.T("Q&A $QnA", $Text).'</span> ';
      }
   }

   /**
    * @param Gdn_Controller $Sender
    * @param array $Args
    */
   public function NotificationsController_BeforeInformNotifications_Handler($Sender, $Args) {
      $Path = trim($Sender->Request->GetValue('Path'), '/');
      if (preg_match('`^(vanilla/)?discussion[^s]`i', $Path))
         return;

      // Check to see if the user has answered questions.
      $Count = Gdn::SQL()->GetCount('Discussion', array('Type' => 'Question', 'InsertUserID' => Gdn::Session()->UserID, 'QnA' => 'Answered'));
      if ($Count > 0) {
         $Sender->InformMessage(FormatString(T("You've asked questions that have now been answered", "<a href=\"{/discussions/mine?qna=Answered,url}\">You've asked questions that now have answers</a>. Make sure you accept/reject the answers.")), 'Dismissable');
      }
   }

   /**
    * Add 'Ask a Question' button if using BigButtons.
    */
   public function CategoriesController_Render_Before($Sender) {
      if (C('Plugins.QnA.UseBigButtons')) {
         $QuestionModule = new NewQuestionModule($Sender, 'plugins/QnA');
         $Sender->AddModule($QuestionModule);
      }
   }

   /**
    * Add 'Ask a Question' button if using BigButtons.
    */
   public function DiscussionController_Render_Before($Sender) {
      if (C('Plugins.QnA.UseBigButtons')) {
         $QuestionModule = new NewQuestionModule($Sender, 'plugins/QnA');
         $Sender->AddModule($QuestionModule);
      }

      if ($Sender->Data('Discussion.Type') == 'Question') {
         $Sender->SetData('_CommentsHeader', T('Answers'));
      }
   }


   /**
    * Add the "new question" option to the new discussion button group dropdown.
    */
   public function Base_BeforeNewDiscussionButton_Handler($Sender) {
      $NewDiscussionModule = &$Sender->EventArguments['NewDiscussionModule'];
      $NewDiscussionModule->AddButton(T('Ask a Question'), 'post/question');
   }

   /**
    * Add the question form to vanilla's post page.
    */
   public function PostController_AfterForms_Handler($Sender) {
      $Forms = $Sender->Data('Forms');
      $Forms[] = array('Name' => 'Question', 'Label' => Sprite('SpQuestion').T('Ask a Question'), 'Url' => 'post/question');
		$Sender->SetData('Forms', $Forms);
   }

   /**
    * Create the new question method on post controller.
    */
   public function PostController_Question_Create($Sender) {
      // Create & call PostController->Discussion()
      $Sender->View = PATH_PLUGINS.'/QnA/views/post.php';
      $Sender->Discussion(GetValue(0, $Sender->RequestArgs, ''));
   }

   /**
    * Override the PostController->Discussion() method before render to use our view instead.
    */
   public function PostController_BeforeDiscussionRender_Handler($Sender) {
      // Override if we are looking at the question url.
      if ($Sender->RequestMethod == 'question') {
         $Sender->Form->AddHidden('Type', 'Question');
         $Sender->Title(T('Ask a Question'));
         $Sender->SetData('Breadcrumbs', array(array('Name' => $Sender->Data('Title'), 'Url' => '/post/question')));
      }
   }

   /**
    * Adds email notification options to profiles.
    *
    * @package QnA
    *
    * @param object $Sender ProfileController.
    */
   public function ProfileController_AfterPreferencesDefined_Handler($Sender) {
		  //Notification options for Answer Accepted
      $Sender->Preferences['Notifications']['Popup.AnswerAccepted'] = T('Notify me when people accept my answers.');
      $Sender->Preferences['Notifications']['Email.AnswerAccepted'] = T('Notify me when people accept my answers.');

			//Notification options for Answer Rejected
      $Sender->Preferences['Notifications']['Popup.AnswerRejected'] = T('Notify me when people reject my answers.');
      $Sender->Preferences['Notifications']['Email.AnswerRejected'] = T('Notify me when people reject my answers.');
   }
}
