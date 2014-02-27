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
$PluginInfo['Block'] = array(
   'Description' => 'This plugin lets user ignore other users, filtering their posts out of discussions.',
   'Version' => '1.0.1',
   'RequiredApplications' => array('Vanilla' => '2.0.10a'),
   'RequiredTheme' => FALSE,
   'RequiredPlugins' => FALSE,
   'HasLocale' => FALSE,
   'SettingsUrl' => FALSE,
   'SettingsPermission' => 'Garden.Settings.Manage',
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com'
);

class BlockPlugin extends Gdn_Plugin {

   public function __construct() {

   }

   public function DiscussionController_BeforeDiscussionRender_Handler($Sender) {
      $Sender->AddJsFile('block.js', 'plugins/Block');
      $Sender->AddCssFile('block.css', 'plugins/Block');
   }

   public function DiscussionController_BeforeCommentDisplay_Handler($Sender) {
      $UserID = GetValue('InsertUserID',$Sender->EventArguments['Object']);
      if ($this->Blocked($UserID)) {
         $Classes = explode(" ",$Sender->EventArguments['CssClass']);
         $Classes[] = 'UserBlocked';
         $Classes = array_fill_keys($Classes, NULL);
         $Classes = implode(' ',array_keys($Classes));
         $Sender->EventArguments['CssClass'] = $Classes;
      }
   }

   public function ProfileController_Block_Create($Sender) {
      $Sender->DeliveryType(DELIVERY_TYPE_VIEW);
      $Sender->DeliveryMethod(DELIVERY_METHOD_JSON);
      $Sender->SetJson('Status',200);

      try {
         $User = call_user_func_array(array($this, 'GetUserInfo'), $Sender->RequestArgs);
         $BlockUserID = GetValue('UserID', $User);
         $this->SetUserMeta(Gdn::Session()->UserID, "Blocked.User.{$BlockUserID}", $BlockUserID);
         $Sender->InformMessage(T("User has been blocked."));
      } catch (Exception $e) {
         $Sender->InformMessage(T("Could not find that person!"));
         $Sender->SetJson('Status',404);
      }

      $Sender->Render('blank','utility','dashboard');
   }

   public function ProfileController_Unblock_Create($Sender) {
      $Sender->DeliveryType(DELIVERY_TYPE_VIEW);
      $Sender->DeliveryMethod(DELIVERY_METHOD_JSON);
      $Sender->SetJson('Status',200);

      try {
         $User = call_user_func_array(array($this, 'GetUserInfo'), $Sender->RequestArgs);
         $BlockUserID = GetValue('UserID', $User);
         $this->SetUserMeta(Gdn::Session()->UserID, "Blocked.User.{$BlockUserID}", NULL);
         $Sender->InformMessage(T("User has been unblocked."));
      } catch (Exception $e) {
         echo $e->getMessage()."\n";
         print_r($User);die();
         $Sender->InformMessage(T("Could not find that person! ({$BlockUserID})"));
         $Sender->SetJson('Status',404);
      }

      $Sender->Render('blank','utility','dashboard');
   }

   protected function GetUserInfo($UserReference = '', $Username = '', $UserID = '') {
      // If a UserID was provided as a querystring parameter, use it over anything else:
		if ($UserID) {
			$UserReference = $UserID;
			$Username = 'Unknown'; // Fill this with a value so the $UserReference is assumed to be an integer/userid.
		}

      if ($UserReference == '') {
         $User = Gdn::UserModel()->Get(Gdn::Session()->UserID);
      } else if (is_numeric($UserReference) && $Username != '') {
         $User = Gdn::UserModel()->Get($UserReference);
      } else {
         $User = Gdn::UserModel()->GetByUsername($UserReference);
      }

      if ($User === FALSE) {
         throw NotFoundException();
      } else if ($this->User->Deleted == 1) {
         throw NotFoundException();
      } else if (GetValue('UserID', $User) == Gdn::Session()->UserID) {
         throw NotFoundException();
      } else {
         return $User;
      }
   }

   public function Blocked($UserID) {
      static $BlockedUsers = NULL;
      if (is_null($BlockedUsers)) {
         $Blocked = $this->GetUserMeta(Gdn::Session()->UserID, 'Blocked.User.%');
         $BlockedUsers = array_values($Blocked);
      }

      if (in_array($UserID, $BlockedUsers))
         return TRUE;

      return FALSE;
   }

}