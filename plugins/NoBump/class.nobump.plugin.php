<?php if (!defined('APPLICATION')) exit();

class NoBumpPlugin extends Gdn_Plugin {
   /**
    * Add 'No Bump' option to new discussion form.
    */
   public function DiscussionController_AfterBodyField_Handler($sender) {
      if (Gdn::Session()->CheckPermission('Garden.Moderation.Manage'))
         echo $sender->Form->CheckBox('NoBump', T('No Bump'), ['value' => '1']);
   }

   /**
    * Set Comment's DateInserted to Discussion's DateLastComment so there's no change.
    */
   public function CommentModel_BeforeUpdateCommentCount_Handler($sender) {
      if (Gdn::Session()->CheckPermission('Garden.Moderation.Manage')) {
         if (Gdn::Controller()->Form->GetFormValue('NoBump'))
            $sender->EventArguments['Discussion']['Sink'] = 1;
      }
   }

   /** No setup. */
   public function Setup() { }
}
