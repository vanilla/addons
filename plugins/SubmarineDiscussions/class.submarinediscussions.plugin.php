<?php if (!defined('APPLICATION')) exit();

class SubmarineDiscussionsPlugin extends Gdn_Plugin {
   /**
	 * Add 'sink' option to new discussion form.
	 */
   public function PostController_DiscussionFormOptions_Handler($sender) {
      $session = Gdn::Session();
      if ($session->CheckPermission('Vanilla.Discussions.Sink'))
         $sender->EventArguments['Options'] .= '<li>'.$sender->Form->CheckBox('Sink', T('Sink'), ['value' => '1']).'</li>';
   }
   
   /**
	 * Set DateLastComment to null if this is an insert and 'sink' was selected.
	 */
   public function DiscussionModel_BeforeSaveDiscussion_Handler($sender) {
      if ($sender->EventArguments['Insert'] && $sender->EventArguments['FormPostValues']['Sink'] == 1)
         $sender->EventArguments['FormPostValues']['DateLastComment'] = NULL;
   }
}
