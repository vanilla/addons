<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */

// Define the plugin:
$PluginInfo['QnA'] = array(
   'Name' => 'Q&A (Question & Answer)',
   'Description' => "Allows users to designate a discussion as a question and then accept one or more of the comments as an answer.",
   'Version' => '1.0b',
   'RequiredApplications' => array('Vanilla' => '2.0.18a1'),
   'Author' => 'Todd Burry',
   'AuthorEmail' => 'todd@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.org/profile/todd'
);

class QnAPlugin extends Gdn_Plugin {
   /// PROPERTIES ///


   /// METHODS ///

   public function Structure() {
      Gdn::Structure()
         ->Table('Discussion')
         ->Column('QnA', array('Answered', 'Accepted', 'Rejected'), NULL)
         ->Set();

      Gdn::Structure()
         ->Table('Comment')
         ->Column('QnA', array('Accepted', 'Rejected'), NULL)
         ->Set();
   }


   /// EVENTS ///

   public function DiscussionController_AfterCommentBody_Handler($Sender, $Args) {
      $Discussion = GetValue('Discussion', $Args);
      $Comment = GetValue('Comment', $Args);

      if (!$Discussion || !$Comment)
         return;

      // Check permissions.
      if (!(Gdn::Session()->UserID == GetValue('InsertUserID', $Discussion) || Gdn::Session()->CheckPermission('Garden.Moderation.Manage')))
         return;

      $QnA = GetValue('QnA', $Comment);
      if ($QnA)
         return;

      // Write the links.
      $Query = http_build_query(array('commentid' => GetValue('CommentID', $Comment), 'tkey' => Gdn::Session()->TransientKey()));

      echo '<div class="Inset QnA-Form">'.
            T('Did this answer the question?').
            ' '.Anchor(T('Yes'), '/discussion/qna/accept?'.$Query, array('class' => 'Button QnA-Yes')).
            ' '.Anchor(T('No'), '/discussion/qna/reject?'.$Query, array('class' => 'Button QnA-No')).
         '</div>';
   }

   public function Base_CommentInfo_Handler($Sender, $Args) {
      $Type = GetValue('Type', $Args);
      if ($Type != 'Comment')
         return;

      $QnA = GetValueR('Comment.QnA', $Args);

      if ($QnA) {
         $Title = sprintf(T('This answer was %s.'), T('Q&A '.$QnA, $QnA));
         echo '<div class="QnA-Box QnA-'.$QnA.'" title="'.htmlspecialchars($Title).'"><div>'.$Title.'</div></div>';
      }
   }

   /**
    *
    * @param DiscussionController $Sender
    * @param array $Args
    */
   public function DiscussionController_QnA_Create($Sender, $Args) {
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
         // Update the comment.
         Gdn::SQL()->Put('Comment', array('QnA' => $QnA), array('CommentID' => $Comment['CommentID']));

         // Update the discussion.
         if (!$Discussion['QnA'] || $Discussion['QnA'] = 'Answered')
            Gdn::SQL()->Put('Discussion', array('QnA' => $QnA), array('DiscussionID' => $Comment['DiscussionID']));
      }

      Redirect("/discussion/comment/{$Comment['CommentID']}#Comment_{$Comment['CommentID']}");
   }

   /**
    *
    * @param DiscussionModel $Sender
    * @param array $Args
    */
   public function DiscussionModel_BeforeSaveDiscussion_Handler($Sender, $Args) {
      $Sender->Validation->ApplyRule('Type', 'Required', T('Choose either whether you want to ask a question or start a discussion.'));
   }

   public function Base_BeforeDiscussionMeta_Handler($Sender, $Args) {
      $Discussion = $Args['Discussion'];

      if (!GetValue('Type', $Discussion) == 'question')
         return;

      $QnA = GetValue('QnA', $Discussion);
      switch ($QnA) {
         case '':
         case 'Rejected':
            $QnA = 'Question';
            break;
         case 'Answered':
         case 'Accepted':
            $QnA = 'Answered';
            break;
         default:
            $QnA = FALSE;
      }
      if ($QnA) {
         echo '<span class="Tag Tag-'.$QnA.'">'.T("Q&A $QnA", $QnA).'</span> ';
      }
   }

   public function PostController_BeforeBodyInput_Handler($Sender, $Args) {
      $Form = $Sender->Form;

      echo '<p>',
         T('You can either as a question or start a regular discussion.', 'You can either as a question or start a regular discussion. Choose what you want to do below.'),
         '</p>';

      echo '<div class="P">',
         $Form->RadioList('Type', array('Question' => 'Question', 'Discussion' => 'Discussion')),
      '</div>';
   }
}