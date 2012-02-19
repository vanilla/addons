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
   'Version' => '1.1',
   'MobileFriendly' => TRUE,
   'RequiredApplications' => array('Vanilla' => '2.1a'),
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
      
      $Discussion = $Sender->Discussion;
      $PermissionCategoryID = $Discussion->PermissionCategoryID;
      
      // Assume discussion
      $Data = $Discussion;
      $RecordType = 'discussion';
      $RecordID = GetValue('DiscussionID', $Data);
      
      // But override if comment
      if (isset($Sender->EventArguments['Comment'])) {
         $Data = $Sender->EventArguments['Comment'];
         $RecordType = 'comment';
         $RecordID = GetValue('CommentID', $Data);
      }
      
      $UserCanEdit = Gdn::Session()->CheckPermission('Vanilla.'.ucfirst($RecordType).'s.Edit', TRUE, 'Category', $PermissionCategoryID);
      
      if (is_null($Data->DateUpdated)) return;
      if ($Data->DateUpdated == $Data->DateInserted) return;
      
      $SourceUserID = $Data->InsertUserID;
      $UpdatedUserID = $Data->UpdateUserID;
      
      $UserData = Gdn::UserModel()->GetID($UpdatedUserID);
      $Edited = array(
         'EditUser'     => GetValue('Name', $UserData, T('Unknown User')),
         'EditDate'     => Gdn_Format::ToDateTime(Gdn_Format::ToTimestamp($Data->DateUpdated)),
         'EditLogUrl'   => Url("/log/record/{$RecordType}/{$RecordID}")
      );
      
      $Format = T('PostEdited.Plain', 'Post edited by {EditUser} at {EditDate}');
      if ($UserCanEdit)
         $Format = T('PostEdited.Log', 'Post edited by {EditUser} at {EditDate} (<a href="{EditLogUrl}">log</a>)');
      
      $Display = '<div class="PostEdited">'.FormatString($Format, $Edited).'</div>';
      echo $Display;
      
   }
   
   public function Setup() {
      // Nothing to do!
   }
   
}