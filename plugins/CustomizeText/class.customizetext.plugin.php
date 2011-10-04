<?php if (!defined('APPLICATION')) exit();

/**
 * TODO:
 * Create a method to edit a translation. Drop it into the page (if not admin master). Ajax on submit.
 * Fill form with values on hover.
 * Auto-focus form when form values are filled.
 * Alternatively: could put the form into an inform with a custom target that always gets replaced.
 * 
 * Blacklist known "problem translations" that are in buttons or wreck page layout.
 */

$PluginInfo['CustomizeText'] = array(
   'Name' => 'Customize Text',
   'Description' => "Allows administrators to edit the text throughout their forum.",
   'Version' => '1',
   'RequiredApplications' => array('Vanilla' => '2.0.17'),
   'Author' => "Mark O'Sullivan",
   'AuthorEmail' => 'mark@vanillaforums.com',
   'AuthorUrl' => 'http://vanillaforums.com',
	'SettingsUrl' => 'settings/customizetext'
);

$CustomizeTextTranslations = array();
$CustomizeTextMasterView = '';

class CustomizeTextLocale extends Gdn_Locale {

	protected $_UnsavedDefinitions = array();
	
   public function __construct($LocaleName = '', $ApplicationWhiteList = '', $PluginWhiteList = '', $ForceRemapping = FALSE) {
      parent::__construct($LocaleName, $ApplicationWhiteList, $PluginWhiteList, $ForceRemapping);
   }	

	/**
	 * Override the core method to save translations b/c it doesn't save to the file I want to use.
	 */
   public function SaveTranslations($Translations) {
		// Load existing definitions
      $Path = PATH_CONF . "/locale.php";
		$Definition = array();
      if (file_exists($Path))
         require($Path);
		
		// Merge existing translations with new ones.
		$Definition = array_merge($Definition, $Translations);
		// Make sure the most common change request definitions are in there:
		if (!array_key_exists('GuestModule.Message', $Definition))
			$Definition['GuestModule.Message'] = "It looks like you're new here. If you want to get involved, click one of these buttons!";
		if (!array_key_exists('Howdy, Stranger!', $Definition))
			$Definition['Howdy, Stranger!'] = 'Howdy, Stranger!';
		if (!array_key_exists('Apply for Membership', $Definition))
			$Definition['Apply for Membership'] = 'Apply for Membership';
		
		$FileContents = "<?php if (!defined('APPLICATION')) exit();\r\n";
		$FileContents .= '$NewDefinitions = '.var_export($Definition, TRUE).";\r\n";
		$FileContents .= 'if (isset($Definition)) $Definition = array_merge($Definition, $NewDefinitions);';
		
		// Save the definitions
      if (PATH_LOCAL_CONF != PATH_CONF) {
			// Infrastructure deployment. Use old method.
         $Result = Gdn_FileSystem::SaveFile($Path, $FileContents, LOCK_EX);
      } else {
         $TmpFile = tempnam(PATH_CONF, 'locale');
         $Result = FALSE;
         if (file_put_contents($TmpFile, $FileContents) !== FALSE) {
            chmod($TmpFile, 0775);
            $Result = rename($TmpFile, $Path);
         }
      }

      if ($Result && function_exists('apc_delete_file')) {
         // This fixes a bug with some configurations of apc.
         @apc_delete_file($Path);
      }
		
		return $Result;
   }

   /**
    * Translates a code into the selected locale's definition.
    *
    * @param string $Code The code related to the language-specific definition.
    *   Codes thst begin with an '@' symbol are treated as literals and not translated.
    * @param string $Default The default value to be displayed if the translation code is not found.
    * @return string
    */
   public function Translate($Code, $Default = FALSE) {
      if ($Default === FALSE)
         $Default = $Code;

      // Codes that begin with @ are considered literals.
      if(substr_compare('@', $Code, 0, 1) == 0)
         return substr($Code, 1);

      if (array_key_exists($Code, $this->_Definition)) {
         return $this->_Definition[$Code];
      } else {
			// Assign it to my "unsaved" collection.
			$this->_UnsavedDefinitions[$Code] = $Default;
         return $Default;
      }
   }
	
	/**
	 * All currently loaded definitions.
	 */
	public function GetDefinitions() {
		return $this->_Definition;
	}

	/**
	 * A collection of definitions that were not in the definition collection when the page started loading.
	 */
	public function GetUnsavedDefinitions() {
		return $this->_UnsavedDefinitions;
	}
}
$Codeset = Gdn::Config('Garden.LocaleCodeset', 'UTF8');
$CurrentLocale = Gdn::Config('Garden.Locale', 'en-CA');
$SetLocale = str_replace('-', '_', $CurrentLocale).'.'.$Codeset;
setlocale(LC_ALL, $SetLocale);
$CustomizeTextLocale = new CustomizeTextLocale($CurrentLocale, Gdn::ApplicationManager()->EnabledApplicationFolders(), Gdn::PluginManager()->EnabledPluginFolders());
Gdn::FactoryInstall(Gdn::AliasLocale, 'CustomizeTextLocale', NULL, Gdn::FactorySingleton, $CustomizeTextLocale);
unset($CustomizeTextLocale);

class CustomizeTextPlugin extends Gdn_Plugin {
	
	/**
	 * Add the customize text menu option.
	 */
   public function Base_GetAppSettingsMenuItems_Handler($Sender) {
		$Menu = &$Sender->EventArguments['SideMenu'];
      $Menu->AddLink('Appearance', 'Customize Text', 'settings/customizetext', 'Garden.Settings.Manage');
	}

	/**
	 * Add the customize text page to the dashboard.
	 */
   public function SettingsController_CustomizeText_Create($Sender) {
      $Sender->Permission('Garden.Settings.Manage');
      $Sender->Title('Customize Text');
      $Sender->AddSideMenu('settings/customizetext');
		$Sender->AddJsFile('/js/library/jquery.autogrow.js');

		$Directive = GetValue(0, $Sender->RequestArgs, '');
		$View = 'customizetext.php';
		if ($Directive == 'rebuild')
			$View = 'rebuild.php';
		elseif ($Directive == 'rebuildcomplete')
			$View = 'rebuildcomplete.php';

		$Session = Gdn::Session();
		$UserModel = Gdn::UserModel();
		$Sender->Matches = array();
		if ($Sender->Form->AuthenticatedPostback()) {
			// Loop/Save translation changes
			$Keywords = strtolower($Sender->Form->GetValue('Keywords'));
			$Locale = Gdn::Locale();
			$Definitions = $Locale->GetDefinitions();
			$Loop = 0;
			$Changes = FALSE;
			foreach ($Definitions as $Key => $Definition) {
				// Look for matches
				$k = strtolower($Key);
				$d = strtolower($Definition);
				if ($Keywords == '*' || (strlen($Keywords) > 0 && (strpos($k, $Keywords) !== FALSE || strpos($d, $Keywords) !== FALSE))) {
					$Sender->Matches[$Key] = $Definition;

					// Save changes in matches
					$NewDef = $Sender->Form->GetValue('def_'.$Loop);
					$OldCode = $Sender->Form->GetValue('code_'.$Loop);
					if ($OldCode == $Key && $NewDef != FALSE && $NewDef != $Definition) {
						$Definitions[$Key] = $NewDef;
						$Sender->Matches[$Key] = $NewDef;
						$Changes = TRUE;
					}
					
					$Loop++;
				}
			}
			if ($Changes) {
				$Locale->SaveTranslations($Definitions);
				$Sender->InformMessage('Your changes were saved successfully.');
			}
		}
      $Sender->Render(PATH_PLUGINS.'/CustomizeText/views/'.$View);
   }
	
	/**
	 * Add the inline text editor css to non-dashboard pages for admins.
	 */
	public function Base_AfterBody_Handler($Sender) {
		/**
		 * When the entire page is finished processing, check the locale to see if
		 * any new definitions have been found and save them if they have.
		 */
		$Locale = Gdn::Locale();
		if (method_exists($Locale, 'GetUnsavedDefinitions')) {
			$UnsavedDefinitions = $Locale->GetUnsavedDefinitions();
			if (count($UnsavedDefinitions))
				$Locale->SaveTranslations($UnsavedDefinitions);
		}
	}
}