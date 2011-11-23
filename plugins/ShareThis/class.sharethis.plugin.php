<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

// Define the plugin:
$PluginInfo['ShareThis'] = array(
   'Name' => 'ShareThis',
   'Description' => 'This plugin adds ShareThis (http://sharethis.com) buttons to the bottom of each post.',
   'Version' => '1.0',
   'RequiredApplications' => FALSE,
   'RequiredTheme' => FALSE, 
   'RequiredPlugins' => FALSE,
   'SettingsUrl' => '/dashboard/plugin/sharethis',
   'SettingsPermission' => 'Garden.AdminUser.Only',
   'HasLocale' => TRUE,
   'RegisterPermissions' => FALSE,
   'Author' => "Brendan Sera-Shriar a.k.a digibomb",
   'AuthorEmail' => 'brendan@vanillaforums.com',
   'AuthorUrl' => 'http://www.dropthedigibomb.com'
);


class ShareThisPlugin extends Gdn_Plugin {

	public function DiscussionController_AfterComment_Handler($Sender) {
	
		/* Add CSS  for share div */
		
		$Sender->AddCSSFile('plugins/ShareThis/design/sharethis.css');
	
		/* Get the sharethis publisher number. */
      $PublisherNumber = C('Plugin.ShareThis.PublisherNumber', 'Publisher Number');
      
      $ShareThisCode = '<div class="Share"><span class="st_twitter_hcount" displayText="Tweet"></span><span class="st_facebook_hcount" displayText="Share"></span><span class="st_email_hcount" displayText="Email"></span><span class="st_sharethis_hcount" displayText="Share"></span></div>';
	
	
	   /* Add javascript for ShareThis */

		$Sender->Head->AddString("<script type=\"text/javascript\" src=\"http://w.sharethis.com/button/buttons.js\"></script><script type=\"text/javascript\">stLight.options({publisher:'{$PublisherNumber}'});</script>");
		
		/* Display ShareThis buttons. */
		
		if (GetValue('Type', $Sender->EventArguments) != 'Comment') {
			echo $ShareThisCode;
		}
	}		

   public function Setup() {
      // Nothing to do here!
   }
   
   public function Structure() {
      // Nothing to do here!
   }
   
  /*Add to dashboard side menu. */ 
  
   public function Base_GetAppSettingsMenuItems_Handler($Sender) {
      $Menu = $Sender->EventArguments['SideMenu'];
      $Menu->AddLink('Add-ons', T('ShareThis'), 'plugin/sharethis', 'Garden.Settings.Manage');
   }

   public function PluginController_ShareThis_Create($Sender) {
   	$Sender->Permission('Garden.AdminUser.Only');
   	$Sender->Title('ShareThis');
      $Sender->AddSideMenu('plugin/ShareThis');
      $Sender->Form = new Gdn_Form();
      $this->Dispatch($Sender, $Sender->RequestArgs);
   }
   
   /* Define variables for settings page. */
   
   public function Controller_Index($Sender) {
      $PublisherNumber = C('Vanilla.Plugin.PublisherNumber', 'Publisher Number');
      
      $Validation = new Gdn_Validation();
      $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
      
      $ConfigArray = array(
         'Plugin.ShareThis.PublisherNumber'
      );
      if ($Sender->Form->AuthenticatedPostBack() === FALSE)
         $ConfigArray['Plugin.ShareThis.PublisherNumber'] = $PublisherNumber;
      
      $ConfigurationModel->SetField($ConfigArray);
      
      // Set the model on the form.
      $Sender->Form->SetModel($ConfigurationModel);
      
      // If seeing the form for the first time...
      if ($Sender->Form->AuthenticatedPostBack() === FALSE) {
         // Apply the config settings to the form.
         $Sender->Form->SetData($ConfigurationModel->Data);
      } else {
         // Define some validation rules for the fields being saved
         $ConfigurationModel->Validation->ApplyRule('Plugin.ShareThis.PublisherNumber', 'Required');
         
         if ($Sender->Form->Save() !== FALSE) {
            $Sender->InformMessage(T("Your changes have been saved."));
         }
      }
      
      $Sender->Render($this->GetView('sharethis.php'));
   }
      
}