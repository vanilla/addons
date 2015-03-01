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
$PluginInfo['PostCount'] = array(
   'Name' => 'Post Count',
   'Description' => "Shows each user's comment total by their name in each comment.",
   'Version' => '1.0.3',
   'MobileFriendly' => TRUE,
   'RequiredApplications' => FALSE,
   'RequiredTheme' => FALSE, 
   'RequiredPlugins' => FALSE,
   'HasLocale' => TRUE,
   'RegisterPermissions' => FALSE,
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com'
);

class PostCountPlugin extends Gdn_Plugin {
   
   public function UserInfoModule_OnBasicInfo_Handler(&$Sender) {
      $User = Gdn::UserModel()->GetID($Sender->User->UserID);
      if ($User) {
         $PostCount = GetValue('CountComments', $User, 0) + GetValue('CountDiscussions', $User, 0);
         echo "<dt class=\"Posts\">".T('Posts')."</dt>\n";
         echo "<dd class=\"Posts\">".number_format($PostCount)."</dd>";
      }
   }
   
   public function DiscussionController_AuthorInfo_Handler(&$Sender) {
      $this->_AttachPostCount($Sender);
   }
   
   public function PostController_AuthorInfo_Handler(&$Sender) {
      $this->_AttachPostCount($Sender);
   }
   
   protected function _AttachPostCount(&$Sender) {
      $User = Gdn::UserModel()->GetID($Sender->EventArguments['Author']->UserID);
      if ($User) {
         $Posts = GetValue('CountComments', $User, 0) + GetValue('CountDiscussions', $User, 0);
         echo '<span class="MItem PostCount">'.Plural(number_format($Posts), '@'.T('Posts.Singular: %s', 'Posts: <b>%s</b>'), '@'.T('Posts.Plural: %s', 'Posts: <b>%s</b>')).'</span>';
      }
   }

   public function Setup() {
      // Nothing to do here!
   }
   
   public function Structure() {
      // Nothing to do here!
   }
         
}