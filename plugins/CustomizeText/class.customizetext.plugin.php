<?php if (!defined('APPLICATION')) exit();

/**
 * Allows forum adminis to modify text on their forum.
 * 
 * Requires infrastructure, and 2.1 locales (using Gdn_ConfigurationSource), as 
 * well as Garden.Locales.DeveloperMode
 * 
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
   'Version' => '1.1',
   'RequiredApplications' => array('Vanilla' => '2.1a2'),
   'Author' => "Mark O'Sullivan",
   'AuthorEmail' => 'mark@vanillaforums.com',
   'AuthorUrl' => 'http://vanillaforums.com',
	'SettingsUrl' => 'settings/customizetext'
);

class CustomizeTextPlugin extends Gdn_Plugin {
   
   public function __construct() {
      parent::__construct();
      if (!C('Garden.Locales.DeveloperMode', FALSE))
         SaveToConfig('Garden.Locales.DeveloperMode', TRUE);
   }

   /**
	 * Add the customize text menu option.
	 */
   public function Base_GetAppSettingsMenuItems_Handler($Sender) {
		$Menu = &$Sender->EventArguments['SideMenu'];
      $Menu->AddLink('Appearance', 'Customize Text', 'settings/customizetext', 'Garden.Settings.Manage');
	}

	/**
	 * Add the customize text page to the dashboard.
    * 
    * @param Gdn_Controller $Sender
	 */
   public function SettingsController_CustomizeText_Create($Sender) {
      $Sender->Permission('Garden.Settings.Manage');
      $Sender->Title('Customize Text');
      $Sender->AddSideMenu('settings/customizetext');
		$Sender->AddJsFile('/js/library/jquery.autogrow.js');

		$Directive = GetValue(0, $Sender->RequestArgs, '');
		$View = 'customizetext';
		if ($Directive == 'rebuild')
			$View = 'rebuild';
		elseif ($Directive == 'rebuildcomplete')
			$View = 'rebuildcomplete';
      
		$Sender->Matches = array();
		if ($Sender->Form->AuthenticatedPostback()) {
			// Loop/Save translation changes
			$Keywords = strtolower($Sender->Form->GetValue('Keywords'));
         
			$Definitions = Gdn::Locale()->GetDeveloperDefinitions();
			$Loop = 0;
			$Changed = FALSE;
			foreach ($Definitions as $Key => $Definition) {
				// Look for matches
				$k = strtolower($Key);
				$d = strtolower($Definition);
				if ($Keywords == '*' || (strlen($Keywords) > 0 && (strpos($k, $Keywords) !== FALSE || strpos($d, $Keywords) !== FALSE))) {
               
               // Found a definition, look it up in the real locale first, to see if it has been overridden
               $RealDefinition = Gdn::Locale()->Translate($Key, $Definition);
					$Sender->Matches[$Key] = $RealDefinition;
               
					// Save changes in matches
					$NewDef = $Sender->Form->GetValue('def_'.$Loop);
					$OldCode = $Sender->Form->GetValue('code_'.$Loop);
					if ($OldCode == $Key && $NewDef != FALSE && $NewDef != $RealDefinition) {
						Gdn::Locale()->SetTranslation($Key, $NewDef, TRUE);
						$Sender->Matches[$Key] = $NewDef;
                  $Changed = TRUE;
					}
					
					$Loop++;
				}
			}
         if ($Changed) {
            $Sender->InformMessage("Locale changes have been saved!");
         }
		}
      $Sender->Render($View, '', 'plugins/CustomizeText');
   }
}
