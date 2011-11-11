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
$PluginInfo['LastEdited'] = array(
   'Name' => 'Last Edited',
   'Description' => 'This plugin appends a "post last edited by X at Y" line to the end of edited posts.',
   'Version' => '1.0.2',
   'MobileFriendly' => TRUE,
   'RequiredApplications' => array('Vanilla' => '2.0'),
   'RequiredTheme' => FALSE, 
   'RequiredPlugins' => FALSE,
   'HasLocale' => TRUE,
   'RegisterPermissions' => FALSE,
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com'
);

class LastEditedPlugin extends Gdn_Plugin {

   public function DiscussionController_BeforeDiscussionRender_Handler(&$Sender) {
      $this->PrepareEdited($Sender);
   }
   
   public function PostController_BeforeCommentRender_Handler(&$Sender) {
      $this->PrepareEdited($Sender);
   }
   
   public function PrepareEdited($Sender) {
      $Sender->AddCssFile($this->GetResource('design/lastedited.css', FALSE, FALSE));
   }
   
   public function DiscussionController_AfterCommentBody_Handler(&$Sender) {
      $this->DrawEdited($Sender);
   }
   
   public function PostController_AfterCommentBody_Handler(&$Sender) {
      $this->DrawEdited($Sender);
   }
   
   protected function DrawEdited(&$Sender) {
      if (isset($Sender->EventArguments['Discussion']))
         $Data = $Sender->EventArguments['Discussion'];
         
      if (isset($Sender->EventArguments['Comment']))
         $Data = $Sender->EventArguments['Comment'];
      
      if (is_null($Data->DateUpdated)) return;
      if ($Data->DateUpdated == $Data->DateInserted) return;
      
      $SourceUserID = $Data->InsertUserID;
      $UpdatedUserID = $Data->UpdateUserID;
      
      $UserData = Gdn::UserModel()->Get($UpdatedUserID);
      $Sender->Edited = array(
         'Date'      => Gdn_Format::ToDateTime(Gdn_Format::ToTimestamp($Data->DateUpdated)),
         'User'      => GetValue('Name', $UserData, T('Unknown User'))
      );
      
      $Display = $Sender->FetchView($this->GetView('edited.php'));
      unset($Sender->Edited);
      echo $Display;
      
   }
   
   public function Setup() {
      // Nothing to do!
   }
   
}