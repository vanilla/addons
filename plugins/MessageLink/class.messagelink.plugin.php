<?php if (!defined('APPLICATION')) exit();

class MessageLinkPlugin extends Gdn_Plugin {
   /**
    * Add 'Send Message' option to Discussion.
    */
   public function base_afterFlag_handler($sender, $args) {
      if (checkPermission('Conversations.Conversations.Add'))
         $this->addSendMessageButton($sender, $args);
   }

   /**
    * Output Send Message link.
    */
   protected function addSendMessageButton($sender, $args) {
      if (!Gdn::session()->UserID) return;
      if (isset($args['Comment'])) {
         $object = $args['Comment'];
         $objectID = 'Comment_'.$args['Comment']->CommentID;
      } else if (isset($args['Discussion'])) {
         $object = $args['Discussion'];
         $objectID = 'Discussion_'.$args['Discussion']->DiscussionID;
      } else return;

      echo anchor(sprite('ReactMessage', 'ReactSprite').t('Send Message'), url("/messages/add/{$object->InsertName}",TRUE), 'ReactButton Visible SendMessage').' ';
   }
}
