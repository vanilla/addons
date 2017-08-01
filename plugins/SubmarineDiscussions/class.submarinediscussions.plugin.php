<?php if (!defined('APPLICATION')) exit();

class SubmarineDiscussionsPlugin extends Gdn_Plugin {
   /**
	 * Add 'sink' option to new discussion form.
	 */
   public function postController_discussionFormOptions_handler($sender) {
      $session = Gdn::session();
      if ($session->checkPermission('Vanilla.Discussions.Sink'))
         $sender->EventArguments['Options'] .= '<li>'.$sender->Form->checkBox('Sink', t('Sink'), ['value' => '1']).'</li>';
   }
   
   /**
	 * Set DateLastComment to null if this is an insert and 'sink' was selected.
	 */
   public function discussionModel_beforeSaveDiscussion_handler($sender) {
      if ($sender->EventArguments['Insert'] && $sender->EventArguments['FormPostValues']['Sink'] == 1)
         $sender->EventArguments['FormPostValues']['DateLastComment'] = NULL;
   }
}
