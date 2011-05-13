<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */

// Define the plugin:
$PluginInfo['Whispers'] = array(
   'Name' => 'Whispers',
   'Description' => "This plugin brings back functionality similar to the popular Vanilla 1 whispers.",
   'Version' => '1.0a',
   'RequiredApplications' => array('Vanilla' => '2.0.18a1', 'Conversations' => '2.0.18a1'),
   'Author' => 'Todd Burry',
   'AuthorEmail' => 'todd@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.org/profile/todd'
);

class WhispersPlugin extends Gdn_Plugin {
   /// Properties ///
   public $Conversations = NULL;


   /// Methods ///

   public function GetWhispers($DiscussionID, $Comments) {
      $FirstDate = NULL;
      $LastDate = NULL;

      if (count($Comments) > 0) {
         $FirstComment = array_shift($Comments);
         $FirstDate = GetValue('DateInserted', $FirstComment);
         array_unshift($Comments, $FirstComment);
         

         $LastComment = array_pop($Comments);
         array_push($Comments, $LastComment);

         $LastCommentID = GetValue('CommentID', $LastComment);

         // We need to grab the comment that is one after the last comment.
         $LastComment = Gdn::SQL()->Limit(1)->GetWhere('Comment', array('DiscussionID' => $DiscussionID, 'CommentID >' => $LastCommentID))->FirstRow();
         if ($LastComment)
            $LastDate = GetValue('DateInserted', $LastComment);
      }

      // Grab the conversations that are associated with this discussion.
      $Sql = Gdn::SQL()
         ->Select('c.ConversationID')
         ->From('Conversation c')
         ->Where('c.DiscussionID', $DiscussionID);

      if (!Gdn::Session()->CheckPermission('Conversations.Moderation.Manage')) {
         $Sql->Join('UserConversation uc', 'c.ConversationID = uc.ConversationID')
            ->Where('uc.UserID', Gdn::Session()->UserID);
      }

      $Conversations = $Sql->Get()->ResultArray();
      $Conversations = Gdn_DataSet::Index($Conversations, 'ConversationID');

      // Join the participants into the conversations.
      $ConversationModel = new ConversationModel();
      $ConversationModel->JoinParticipants($Conversations);
      $this->Conversations = $Conversations;

      $ConversationIDs = array_keys($Conversations);

      // Grab all messages that are between the first and last dates.
      $Sql = Gdn::SQL()
         ->Select('cm.*')
         ->Select('iu.Name as InsertName, iu.Photo as InsertPhoto')
         ->From('ConversationMessage cm')
         ->Join('User iu', 'cm.InsertUserID = iu.UserID')
         ->WhereIn('cm.ConversationID', $ConversationIDs)
         ->OrderBy('cm.DateInserted');

      if ($FirstDate)
         $Sql->Where('cm.DateInserted >=', $FirstDate);
      if ($LastDate)
         $Sql->Where('cm.DateInserted <', $LastDate);

      $Whispers = $Sql->Get();

      // Add dummy comment fields to the whispers.
      $WhispersResult =& $Whispers->Result();
      foreach ($WhispersResult as &$Whisper) {
         SetValue('DiscussionID', $Whisper, $DiscussionID);
         SetValue('CommentID', $Whisper, 'w'.GetValue('MessageID', $Whisper));
         SetValue('Type', $Whisper, 'Whisper');

         $Participants = GetValueR(GetValue('ConversationID', $Whisper).'.Participants', $Conversations);
         SetValue('Participants', $Whisper, $Participants);
      }

      return $Whispers;
   }

   public function MergeWhispers($Comments, $Whispers) {
      $Result = array_merge($Comments, $Whispers);
      usort($Result, array('WhispersPlugin', '_MergeWhispersSort'));
      return $Result;
   }

   protected static function _MergeWhispersSort($A, $B) {
      $DateA = Gdn_Format::ToTimestamp(GetValue('DateInserted', $A));
      $DateB = Gdn_Format::ToTimestamp(GetValue('DateInserted', $B));

      if ($DateA > $DateB)
         return 1;
      elseif ($DateB < $DateB)
         return -1;
      else
         0;
   }

   public function Setup() {
      $this->Structure();
   }

   public function Structure() {
      Gdn::Structure()
         ->Table('Conversation')
         ->Column('DiscussionID', 'int', NULL, 'index')
         ->Set();
   }

   /// Event Handlers ///

   public function CommentModel_AfterGet_Handler($Sender, $Args) {
      // Grab the whispers associated with this discussion.
      $DiscussionID = $Args['DiscussionID'];
      $Comments =& $Args['Comments'];
      $CommentsResult =& $Comments->Result();
      $Whispers = $this->GetWhispers($DiscussionID, $CommentsResult);
      $Whispers->DatasetType($Comments->DatasetType());
      
      $CommentsResult = $this->MergeWhispers($CommentsResult, $Whispers->Result());
   }

   /**
    * @param Gdn_Controller $Sender
    * @param args $Args
    */
   public function DiscussionController_AfterBodyField_Handler($Sender, $Args) {
      $Sender->AddJsFile('whispers.js', 'plugins/Whispers'); //, array('hint' => 'inline'));
      $Sender->AddJsFile('jquery.autogrow.js');
      $Sender->AddJsFile('jquery.autocomplete.js');

      $this->Form = $Sender->Form;
      include $Sender->FetchViewLocation('WhisperForm', '', 'plugins/Whispers');
   }

   public function DiscussionController_CommentInfo_Handler($Sender, $Args) {
      if (!isset($Args['Comment']))
         return;
      $Comment = $Args['Comment'];
      if (!GetValue('Type', $Comment) == 'Whisper')
         return;

      $Participants = GetValue('Participants', $Comment);
      $ConversationID = GetValue('ConversationID', $Comment);
      $MessageID = GetValue('MessageID', $Comment);
      $MessageUrl = "/messages/$ConversationID#Message_$MessageID";

      echo '<div class="Whisper-Info"><b>'.Anchor(T('Whispered To'), $MessageUrl).'</b>: ';
      $First = TRUE;
      foreach ($Participants as $UserID => $User) {
         if (GetValue('UserID', $User) == GetValue('InsertUserID', $Comment))
            continue;

         if ($First)
            $First = FALSE;
         else
            echo ', ';
         
         echo UserAnchor($User);
      }
      echo '</div>';
   }

   public function DiscussionController_BeforeCommentDisplay_Handler($Sender, $Args) {
      if (!isset($Args['Comment']))
         return;
      $Comment = $Args['Comment'];
      if (!GetValue('Type', $Comment) == 'Whisper')
         return;

      $Args['CssClass'] = ConcatSep(' ', $Args['CssClass'], 'Whisper');
   }

   public function DiscussionController_CommentOptions_Handler($Sender, $Args) {
      if (!isset($Args['Comment']))
         return;
      $Comment = $Args['Comment'];
      if (!GetValue('Type', $Comment) == 'Whisper')
         return;

      $Sender->Options = '';
   }

   public function MessagesController_BeforeConversationMeta_Handler($Sender, $Args) {
      $DiscussionID = GetValueR('Conversation.DiscussionID', $Args);

      if ($DiscussionID) {
         echo '<span class="MetaItem Tag Whispers-Tag">'.Anchor(T('Whisper'), "/discussion/$DiscussionID/x").'</span>';
      }
   }

   /**
    * @param PostController $Sender
    * @param array $Args
    * @return mixed
    */
   public function PostController_Comment_Create($Sender, $Args) {
      if ($Sender->Form->IsPostBack()) {
         $Sender->Form->InputPrefix = 'Comment';

         $Whisper = $Sender->Form->GetFormValue('Whisper');
         $WhisperTo = trim($Sender->Form->GetFormValue('To'));
         $ConversationID = $Sender->Form->GetFormValue('ConversationID');

         if (!$Whisper)
            return call_user_func_array(array($Sender, 'Comment'), $Args);

         $ConversationModel = new ConversationModel();
         $ConversationMessageModel = new ConversationMessageModel();

         if ($ConversationID > 0) {
            $Sender->Form->SetModel($ConversationMessageModel);
         } else {
            // We have to remove the blank conversation ID or else the model won't validate.
            $FormValues = $Sender->Form->FormValues();
            unset($FormValues['ConversationID']);
            $Sender->Form->FormValues($FormValues);

            $Sender->Form->SetModel($ConversationModel);
            $ConversationModel->Validation->ApplyRule('DiscussionID', 'Required');
         }
         
         $Sender->Form->InputPrefix = 'Comment';

         $ID = $Sender->Form->Save($ConversationMessageModel);

         if ($Sender->Form->ErrorCount() > 0) {
            $Sender->ErrorMessage($Sender->Form->Errors());
         } else {
            // Grab the last comment in the discussion.
            $Sender->RedirectUrl = Url('/'); //Url("discussion/comment/$CommentID/#Comment_$CommentID", TRUE);
         }
         $Sender->Render();
      } else {
         return call_user_func_array(array($Sender, 'Comment'), $Args);
      }
   }
}