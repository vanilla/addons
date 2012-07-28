<?php if (!defined('APPLICATION')) exit();

$PluginInfo['MessageLink'] = array(
   'Name' => 'Message Link',
   'Description' => "Adds a link to message the author of each discussion and comment.",
   'Version' => '1.0',
   'RequiredApplications' => array('Vanilla' => '2.0.18', 'Conversations' => '2.0.18'),
   'Author' => "Lincoln Russell",
   'AuthorEmail' => 'lincoln@vanillaforums.com',
   'AuthorUrl' => 'http://lincolnwebs.com'
);

class MessageLinkPlugin extends Gdn_Plugin {
   /**
    * Add 'Send Message' option to Discussion.
    */
   public function Base_AfterReactions_Handler($Sender, $Args) {
      $this->AddSendMessageButton($Sender, $Args);
   }
   
   /**
    * Output Send Message link.
    */
   protected function AddSendMessageButton($Sender, $Args) {
      if (!Gdn::Session()->UserID) return;
      if (isset($Args['Comment'])) {
         $Object = $Args['Comment'];
         $ObjectID = 'Comment_'.$Args['Comment']->CommentID;
      } else if (isset($Args['Discussion'])) {
         $Object = $Args['Discussion'];
         $ObjectID = 'Discussion_'.$Args['Discussion']->DiscussionID;
      } else return;
      
      $Types = GetValue('ReactionTypes', $Sender->EventArguments);
      if ($Types)
         echo Bullet();
      
      echo Anchor(Sprite('ReactSendMessage', 'ReactSprite').T('Send Message'), Url("/messages/add/{$Object->InsertName}",TRUE), 'React SendMessage');
   }
}