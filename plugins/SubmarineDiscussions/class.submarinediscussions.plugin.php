<?php if (!defined('APPLICATION')) exit();

class SubmarineDiscussionsPlugin extends Gdn_Plugin {
   /**
	 * Add 'sink' option to new discussion form.
	 */
   public function PostController_DiscussionFormOptions_Handler($Sender) {
      $Session = Gdn::Session();
      if ($Session->CheckPermission('Vanilla.Discussions.Sink'))
         $Sender->EventArguments['Options'] .= '<li>'.$Sender->Form->CheckBox('Sink', T('Sink'), ['value' => '1']).'</li>';
   }
   
   /**
	 * Set DateLastComment to null if this is an insert and 'sink' was selected.
	 */
   public function DiscussionModel_BeforeSaveDiscussion_Handler($Sender) {
      if ($Sender->EventArguments['Insert'] && $Sender->EventArguments['FormPostValues']['Sink'] == 1)
         $Sender->EventArguments['FormPostValues']['DateLastComment'] = NULL;
   }
}
