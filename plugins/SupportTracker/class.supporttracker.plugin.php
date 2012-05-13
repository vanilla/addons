<?php if (!defined('APPLICATION')) exit();

$PluginInfo['SupportTracker'] = array(
   'Name' => 'Support Tracker',
   'Description' => "Use Vanilla as a support ticket system.",
   'Version' => '1.0',
   'MobileFriendly' => TRUE,
   'RegisterPermissions' => array('Plugins.SupportTracker.Manage'),
   'Author' => "Lincoln Russell",
   'AuthorEmail' => 'lincoln@vanillaforums.com',
   'AuthorUrl' => 'http://lincolnwebs.com'
);

// Anyone who starts a discussion via VanillaPop needs Preferences.Email.DiscussionComment
   
// "Claim" tickets?

// Account for users emailing from different address

class SupportTrackerPlugin extends Gdn_Plugin {
   /**
    * Suffix discussion name with DiscussionID in single view.
    */
   public function DiscussionController_BeforeDiscussionOptions_Handler($Sender, $Args) {
      $Discussion = $Sender->Data('Discussion');
      $NewName = GetValue('Name', $Discussion).' ['.GetValue('DiscussionID', $Discussion).']';
      SetValue('Name', $Discussion, $NewName);
      $Sender->SetData('Discussion', $Discussion);
   }
   
   /**
    * Suffix discussion names with DiscussionID in list.
    */
   public function DiscussionsController_AfterDiscussionTitle_Handler($Sender, $Args) {
      echo ' ['.GetValue('DiscussionID', GetValue('Discussion', $Args)).']';
   }
   
   /**
    * Deny access to private support discussions.
    */
   public function DiscussionController_Render_Before($Sender, $Args) {
      $Sender->Data('CategoryID');
      
      // Get category data
      // If private category, deny if not own discussion or CheckPermission('Plugins.SupportTracker.Manage')
   }
   
   /**
    * Do not show private support discussions.
    */
   public function DiscussionsModel_BeforeGet_Handler($Sender, $Args) {
      $Conditions = GetValue('Wheres', $Args);
		// if category condition in wheres, strip not-own discussions if not CheckPermission('Plugins.SupportTracker.Manage')
   }
   
   /**
    * Toggle discussion answered manually.
    */
   public function DiscussionController_Answered_Create($Sender, $Args) {
      
   }
   
   /**
    * Toggle discussion answered automatically.
    */
   public function PostController_X_Handler($Sender, $Args) {
      // If commenter !permission, unset Answered
      
      // If commenter permission + checkbox, set Answered
      
   }
   
   /**
    * Show discussion answered state.
    */
   public function DiscussionsController_X_Handler($Sender, $Args) {
      
   }
   
   /**
    * Show unanswered discussions.
    */
   public function DiscussionsController_Unanswered_Create($Sender, $Args) {
      
   }
   
   /**
    * Add 'Unanswered' option to discussion filters.
    */
   public function DiscussionsController_XX_Handler($Sender, $Args) {
      
   }

   public function Setup() {
      // Add Discussion.Answered toggle
      
      
      // Set default pref
      SaveToConfig('Preferences.Email.DiscussionComment', '1');
   }
}