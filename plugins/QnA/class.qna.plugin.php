<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */

// Define the plugin:
$PluginInfo['QnA'] = array(
   'Name' => 'Q&A',
   'Description' => "Allows users to designate a discussion as a question and then accept one or more of the comments as an answer.",
   'Version' => '1.0.8b',
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
   public function Base_CommentOptions_Handler($Sender, $Args) {
      $Discussion = GetValue('Discussion', $Args);
      $Comment = GetValue('Comment', $Args);
      
      if (!$Comment)
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
      $Query = http_build_query(array('commentid' => GetValue('CommentID', $Comment), 'tkey' => Gdn::Session()->TransientKey()));

      echo ' <span class="MItem">'.Anchor(T('Accept', 'Accept'), '/discussion/qna/accept?'.$Query, array('class' => 'QnA-Yes', 'title' => T('Accept this answer.'))).'</span> '.
         ' <span class="MItem">'.Anchor(T('Reject', 'Reject'), '/discussion/qna/reject?'.$Query, array('class' => 'QnA-No', 'title' => T('Reject this answer.'))).'</span> ';

      static $InformMessage = TRUE;

      if ($InformMessage && Gdn::Session()->UserID == GetValue('InsertUserID', $Discussion) && in_array(GetValue('QnA', $Discussion), array('', 'Answered'))) {
         $Sender->InformMessage(T('Click accept or reject beside an answer.'), 'Dismissable');
         $InformMessage = FALSE;
      }
   }

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
         if ($QnA == 'Accepted') {
            AddActivity(
               Gdn::Session()->UserID,
               'AnswerAccepted',
               Anchor(Gdn_Format::Text($Discussion['Name']), "/discussion/{$Discussion['DiscussionID']}/".Gdn_Format::Url($Discussion['Name'])),
               $Comment['InsertUserID'],
               "/discussion/comment/{$Comment['CommentID']}/#Comment_{$Comment['CommentID']}",
               TRUE
            );
         }
      }

      Redirect("/discussion/comment/{$Comment['CommentID']}#Comment_{$Comment['CommentID']}");
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
    * @param Gdn_Controller $Sender
    * @param array $Args
    */
   public function PostController_BeforeFormInputs_Handler($Sender, $Args) {
      $Sender->AddDefinition('QuestionTitle', T('Question Title'));
      $Sender->AddDefinition('DiscussionTitle', T('Discussion Title'));
      $Sender->AddDefinition('QuestionButton', T('Ask Question'));
      $Sender->AddDefinition('DiscussionButton', T('Post Discussion'));
      $Sender->AddJsFile('qna.js', 'plugins/QnA');

      $Form = $Sender->Form;
      $QuestionButton = !C('Plugins.QnA.UseBigButtons') || GetValue('Type', $_GET) == 'Question';
      if ($Sender->Form->GetValue('Type') == 'Question' && $QuestionButton) {
         Gdn::Locale()->SetTranslation('Discussion Title', T('Question Title'));
         Gdn::Locale()->SetTranslation('Post Discussion', T('Ask Question'));
      }
      
      if (!C('Plugins.QnA.UseBigButtons'))
         include $Sender->FetchViewLocation('QnAPost', '', 'plugins/QnA');
   }

   public function PostController_Render_Before($Sender, $Args) {
      $Form = $Sender->Form; //new Gdn_Form();
      $QuestionButton = !C('Plugins.QnA.UseBigButtons') || GetValue('Type', $_GET) == 'Question';
      if (!$Form->IsPostBack()) {
         if (!property_exists($Sender, 'Discussion')) {
            $Form->SetValue('Type', 'Question');
         } elseif (!$Form->GetValue('Type')) {
            $Form->SetValue('Type', 'Discussion');
         }
      }

      if ($Form->GetValue('Type') == 'Question' && $QuestionButton) {
         $Sender->SetData('Title', T('Ask a Question'));
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
   }
}