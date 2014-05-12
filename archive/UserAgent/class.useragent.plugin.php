<?php if (!defined('APPLICATION')) exit();

$PluginInfo['UserAgent'] = array(
   'Name' => 'User Agent',
   'Description' => "Record user platform data (browser, operating system) and show under comments. Requires browsecap.ini is setup in your PHP install.",
   'Version' => '1.0',
   'MobileFriendly' => TRUE,
   'Author' => "Lincoln Russell",
   'AuthorEmail' => 'lincoln@vanillaforums.com',
   'AuthorUrl' => 'http://lincolnwebs.com'
);

class UserAgentPlugin extends Gdn_Plugin {

   public function DiscussionController_AfterCommentBody_Handler($Sender, $Args) {
      $Attributes = unserialize(GetValue('Attributes', GetValue('Comment', $Args)));
      $this->AttachInfo($Sender, $Attributes);
   }
   
   public function DiscussionController_AfterDiscussionBody_Handler($Sender, $Args) {
      $Attributes = unserialize(GetValue('Attributes', GetValue('Discussion', $Args)));
      $this->AttachInfo($Sender, $Attributes);
   }
   
   public function CommentModel_BeforeSaveComment_Handler($Sender, &$Args) {
      if ($Args['FormPostValues']['InsertUserID'] != Gdn::Session()->UserID)
         return;
      $this->SetAttributes($Sender, $Args);
   }
   
   public function DiscussionModel_BeforeSaveDiscussion_Handler($Sender, &$Args) {
      if ($Args['FormPostValues']['InsertUserID'] != Gdn::Session()->UserID)
         return;
      $this->SetAttributes($Sender, $Args);
   }
   
   /**
    * Collect user agent data and save in Attributes array.
    */
   protected function SetAttributes($Sender, &$Args) {
      if (!isset($Args['FormPostValues']['Attributes']))
         $Args['FormPostValues']['Attributes'] = array();
      
      // Add user agent data to Attributes
      $Data = @get_browser(GetValue('HTTP_USER_AGENT', $_SERVER)); // requires browsecap.ini or throws error
      $Args['FormPostValues']['Attributes']['Platform'] = GetValue('platform', $Data);
      $Args['FormPostValues']['Attributes']['Browser'] = trim(GetValue('browser', $Data) . ' ' . GetValue('version', $Data));
      $Args['FormPostValues']['Attributes'] = serialize($Args['FormPostValues']['Attributes']);
   }
   
   /**
    * Output user agent information.
    */
   protected function AttachInfo($Sender, $Attributes) {
      if (!CheckPermission('Garden.Moderation.Manage'))
         return;
      
      $Info = '';
      if ($Value = GetValue('Browser', $Attributes))
         $Info .= Wrap('Browser', 'dt').' '.Wrap($Value, 'dd');
      if ($Value = GetValue('Platform', $Attributes))
         $Info .= Wrap('OS', 'dt').' '.Wrap($Value, 'dd');
      
      echo Wrap($Info, 'dl', array('class' => "About UserAgentInfo"));
   }

   public function Setup() { }
}