<?php if (!defined('APPLICATION')) exit();

class NoBumpPlugin extends Gdn_Plugin {
   /**
    * Add 'No Bump' option to new discussion form.
    */
   public function discussionController_afterBodyField_handler($sender) {
      if (Gdn::session()->checkPermission('Garden.Moderation.Manage'))
         echo $sender->Form->checkBox('NoBump', t('No Bump'), ['value' => '1']);
   }

   /**
    * Set Comment's DateInserted to Discussion's DateLastComment so there's no change.
    */
   public function commentModel_beforeUpdateCommentCount_handler($sender) {
      if (Gdn::session()->checkPermission('Garden.Moderation.Manage')) {
         if (Gdn::controller()->Form->getFormValue('NoBump'))
            $sender->EventArguments['Discussion']['Sink'] = 1;
      }
   }

   /** No setup. */
   public function setup() { }
}
