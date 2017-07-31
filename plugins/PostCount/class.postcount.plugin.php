<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

class PostCountPlugin extends Gdn_Plugin {
   
   public function UserInfoModule_OnBasicInfo_Handler($sender) {
      $user = Gdn::UserModel()->GetID($sender->User->UserID);
      if ($user) {
         $postCount = GetValue('CountComments', $user, 0) + GetValue('CountDiscussions', $user, 0);
         echo "<dt class=\"Posts\">".T('Posts')."</dt>\n";
         echo "<dd class=\"Posts\">".number_format($postCount)."</dd>";
      }
   }
   
   public function DiscussionController_AuthorInfo_Handler($sender) {
      $this->_AttachPostCount($sender);
   }
   
   public function PostController_AuthorInfo_Handler($sender) {
      $this->_AttachPostCount($sender);
   }
   
   protected function _AttachPostCount($sender) {
      $user = Gdn::UserModel()->GetID($sender->EventArguments['Author']->UserID);
      if ($user) {
         $posts = GetValue('CountComments', $user, 0) + GetValue('CountDiscussions', $user, 0);
         echo '<span class="MItem PostCount">'.Plural(number_format($posts), '@'.T('Posts.Singular: %s', 'Posts: <b>%s</b>'), '@'.T('Posts.Plural: %s', 'Posts: <b>%s</b>')).'</span>';
      }
   }

   public function Setup() {
      // Nothing to do here!
   }
   
   public function Structure() {
      // Nothing to do here!
   }
         
}
