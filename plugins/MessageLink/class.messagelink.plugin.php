<?php if (!defined('APPLICATION')) exit();

class MessageLinkPlugin extends Gdn_Plugin {
   /**
    * Add 'Send Message' option to Discussion.
    */
   public function Base_AfterFlag_Handler($sender, $args) {
      if (CheckPermission('Conversations.Conversations.Add'))
         $this->AddSendMessageButton($sender, $args);
   }

   /**
    * Output Send Message link.
    */
   protected function AddSendMessageButton($sender, $args) {
      if (!Gdn::Session()->UserID) return;
      if (isset($args['Comment'])) {
         $object = $args['Comment'];
         $objectID = 'Comment_'.$args['Comment']->CommentID;
      } else if (isset($args['Discussion'])) {
         $object = $args['Discussion'];
         $objectID = 'Discussion_'.$args['Discussion']->DiscussionID;
      } else return;

      echo Anchor(Sprite('ReactMessage', 'ReactSprite').T('Send Message'), Url("/messages/add/{$object->InsertName}",TRUE), 'ReactButton Visible SendMessage').' ';
   }
}
