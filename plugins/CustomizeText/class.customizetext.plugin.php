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

class CustomizeTextPlugin extends Gdn_Plugin {
   
   public function __construct() {
      parent::__construct();
      if (!C('Garden.Locales.DeveloperMode', FALSE))
         SaveToConfig('Garden.Locales.DeveloperMode', TRUE);
   }

   /**
	 * Add the customize text menu option.
	 */
   public function Base_GetAppSettingsMenuItems_Handler($sender) {
		$menu = &$sender->EventArguments['SideMenu'];
      $menu->AddLink('Appearance', 'Customize Text', 'settings/customizetext', 'Garden.Settings.Manage');
	}

	/**
	 * Add the customize text page to the dashboard.
    * 
    * @param Gdn_Controller $sender
	 */
   public function SettingsController_CustomizeText_Create($sender) {
      $sender->Permission('Garden.Settings.Manage');
      
      $sender->AddSideMenu('settings/customizetext');
		$sender->AddJsFile('jquery.autogrow.js');
      
      $sender->Title('Customize Text');

		$directive = GetValue(0, $sender->RequestArgs, '');
		$view = 'customizetext';
		if ($directive == 'rebuild')
			$view = 'rebuild';
		elseif ($directive == 'rebuildcomplete')
			$view = 'rebuildcomplete';
      
      $method = 'none';
      
      if ($sender->Form->IsPostback()) {
         $method = 'search';
      
         if ($sender->Form->GetValue('Save_All'))
            $method = 'save';
      }
      
      $matches = [];
      $keywords = NULL;
      switch ($method) {
         case 'none':
            break;
         
         case 'search':
         case 'save':
            
            $keywords = strtolower($sender->Form->GetValue('Keywords'));
            
            if ($method == 'search') {
               $sender->Form->ClearInputs();
               $sender->Form->SetFormValue('Keywords', $keywords);
            }
            
            $definitions = Gdn::Locale()->GetDeveloperDefinitions();
            $countDefinitions = sizeof($definitions);
            $sender->SetData('CountDefinitions', $countDefinitions);
            
            $changed = FALSE;
            foreach ($definitions as $key => $baseDefinition) {
               $keyHash = md5($key);
               $elementName = "def_{$keyHash}";

               // Look for matches
               $k = strtolower($key);
               $d = strtolower($baseDefinition);
               
               // If this key doesn't match, skip it
               if ($keywords != '*' && !(strlen($keywords) > 0 && (strpos($k, $keywords) !== FALSE || strpos($d, $keywords) !== FALSE)))
                  continue;
               
               $modified = FALSE;

               // Found a definition, look it up in the real locale first, to see if it has been overridden
               $currentDefinition = Gdn::Locale()->Translate($key, FALSE);
               if ($currentDefinition !== FALSE && $currentDefinition != $baseDefinition)
                  $modified = TRUE;
               else
                  $currentDefinition = $baseDefinition;

               $matches[$key] = ['def' => $currentDefinition, 'mod' => $modified];
               if ($currentDefinition[0] == "\r\n")
                  $currentDefinition = "\r\n{$currentDefinition}";
               else if ($currentDefinition[0] == "\r")
                  $currentDefinition = "\r{$currentDefinition}";
               else if ($currentDefinition[0] == "\n")
                  $currentDefinition = "\n{$currentDefinition}";
               
               if ($method == 'save') {
                  $suppliedDefinition = $sender->Form->GetValue($elementName);

                  // Has this field been changed?
                  if ($suppliedDefinition != FALSE && $suppliedDefinition != $currentDefinition) {

                     // Changed from what it was, but is it a change from the *base* value?
                     $saveDefinition = ($suppliedDefinition != $baseDefinition) ? $suppliedDefinition : NULL;
                     if (!is_null($saveDefinition)) {
                        $currentDefinition = $saveDefinition;
                        $saveDefinition = str_replace("\r\n", "\n", $saveDefinition);
                     }
                     
                     Gdn::Locale()->SetTranslation($key, $saveDefinition, [
                        'Save'         => TRUE,
                        'RemoveEmpty'  => TRUE
                     ]);
                     $matches[$key] = ['def' => $suppliedDefinition, 'mod' => !is_null($saveDefinition)];
                     $changed = TRUE;
                  }
               }
               
               $sender->Form->SetFormValue($elementName, $currentDefinition);
            }

            if ($changed) {
               $sender->InformMessage("Locale changes have been saved!");
            }
            
            break;
      }
      
      $sender->SetData('Matches', $matches);
      $countMatches = sizeof($matches);
      $sender->SetData('CountMatches', $countMatches);
      
      $sender->Render($view, '', 'plugins/CustomizeText');
   }
}
