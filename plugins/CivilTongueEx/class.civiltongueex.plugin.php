<?php if (!defined('APPLICATION')) exit();

// Define the plugin:
$PluginInfo['CivilTongueEx'] = array(
   'Name' => 'Civil Tongue Ex',
   'Description' => 'A swear word filter for your forum. Making your forum safer for younger audiences. This version of the plugin is based on the Civil Tongue plugin.',
   'Version' => '1.0b',
   'MobileFriendly' => TRUE,
   'Author' => "Todd Burry",
   'AuthorEmail' => 'todd@vanillaforums.com',
   'AuthorUrl' => 'http://vanillaforums.org/profile/todd',
   'SettingsUrl' => '/dashboard/plugin/tongue',
	'SettingsPermission' => 'Plugins.CivilTongue.Manage',
	'RegisterPermissions' => array('Plugins.CivilTongue.Manage')
);

class CivilTonguePlugin extends Gdn_Plugin {
   public $Replacement;

   public function  __construct() {
      parent::__construct();
      $this->Replacement = C('Plugins.CivilTongue.Replacement', '');
   }

	
	public function Base_GetAppSettingsMenuItems_Handler(&$Sender) {
      $Menu = $Sender->EventArguments['SideMenu'];
      $Menu->AddLink('Forum', T('Civil Tongue'), 'plugin/tongue', 'Plugins.CivilTongue.Manage');
   }

	public function PluginController_Tongue_Create($Sender, $Args = array()) {
		$Sender->Permission('Plugins.CivilTongue.Manage');	
		$Sender->Form = new Gdn_Form();
		$Validation = new Gdn_Validation();
      $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
		$ConfigurationModel->SetField(array('Plugins.CivilTongue.Words', 'Plugins.CivilTongue.Replacement'));
		$Sender->Form->SetModel($ConfigurationModel);
		
		
		if ($Sender->Form->AuthenticatedPostBack() === FALSE) { 
			
         $Sender->Form->SetData($ConfigurationModel->Data);    
      } else {
         $Data = $Sender->Form->FormValues();
			
         if ($Sender->Form->Save() !== FALSE)
            $Sender->StatusMessage = T("Your settings have been saved.");
      }

		$Sender->AddSideMenu('plugin/tongue');		
		$Sender->SetData('Title', T('Civil Tongue'));
		$Sender->Render($this->GetView('index.php'));
	
	}

   public function ProfileController_Render_Before($Sender, $Args) {
      $this->ActivityController_Render_Before($Sender, $Args);
   }

   public function ActivityController_Render_Before($Sender, $Args) {
      $User = GetValue('User', $Sender);
      if ($User)
         SetValue('About', $User, $this->Replace(GetValue('About', $User)));

      $ActivityData = GetValue('ActivityData', $Sender);
      if ($ActivityData) {
         $Result =& $ActivityData->Result();
         foreach ($Result as &$Row) {
            SetValue('Story', $Row, $this->Replace(GetValue('Story', $Row)));
         }
      }

      $CommentData = GetValue('CommentData', $Sender);
      if ($CommentData) {
         $Result =& $CommentData->Result();
         foreach ($Result as &$Row) {
            $Value = $this->Replace(GetValue('Story', $Row));
            SetValue('Story', $Row, $Value);

            $Value = $this->Replace(GetValue('DiscussionName', $Row));
            SetValue('DiscussionName', $Row, $Value);

            $Value = $this->Replace(GetValue('Body', $Row));
            SetValue('Body', $Row, $Value);

         }
      }

   }

   public function DiscussionController_Render_Before($Sender, $Args) {
      $Discussion = GetValue('Discussion', $Sender);
      if ($Discussion) {
         $Discussion->Name = $this->Replace($Discussion->Name);
         if (isset($Discussion->Body)) {
            $Discussion->Body = $this->Replace($Discussion->Body);
         }
      }

      if (isset($Sender->CommentData)) {
         $CommentData = $Sender->CommentData->Result();
         foreach ($CommentData as $Comment) {
            $Comment->Body = $this->Replace($Comment->Body);
         }
      }
   }

   public function Base_BeforeDiscussionName_Handler($Sender, $Args) {
      $Discussion = GetValue('Discussion', $Args);
      if ($Discussion) {
         $Discussion->Name = $this->Replace($Discussion->Name);
         if (isset($Discussion->Body)) {
            $Discussion->Body = $this->Replace($Discussion->Body);
         }
      }
   }

   public function Replace($Text) {
      $Patterns = $this->GetPatterns();
      $Result = preg_replace($Patterns, $this->Replacement, $Text);
      return $Result;
   }
	
	public function GetPatterns() {
		// Get config.
		static $Patterns = NULL;

      if ($Patterns === NULL) {
         $Patterns = array();
         $Words = C('Plugins.CivilTongue.Words', null);
         if($Words !== null) {
            $ExplodedWords = explode(';', $Words);
            foreach($ExplodedWords as $Word) {
               $Patterns[] = '/\b' . preg_quote(ltrim(rtrim($Word))) . '\b/is';
            }
         }
      }
		return $Patterns;
	}
	
	
   public function Setup() {
      // Set default configuration
		SaveToConfig('Plugins.CivilTongue.Replacement', '****');
		
   }
}

