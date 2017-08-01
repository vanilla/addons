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
   
   public function userInfoModule_onBasicInfo_handler($sender) {
      $user = Gdn::userModel()->getID($sender->User->UserID);
      if ($user) {
         $postCount = getValue('CountComments', $user, 0) + getValue('CountDiscussions', $user, 0);
         echo "<dt class=\"Posts\">".t('Posts')."</dt>\n";
         echo "<dd class=\"Posts\">".number_format($postCount)."</dd>";
      }
   }
   
   public function discussionController_authorInfo_handler($sender) {
      $this->_AttachPostCount($sender);
   }
   
   public function postController_authorInfo_handler($sender) {
      $this->_AttachPostCount($sender);
   }
   
   protected function _AttachPostCount($sender) {
      $user = Gdn::userModel()->getID($sender->EventArguments['Author']->UserID);
      if ($user) {
         $posts = getValue('CountComments', $user, 0) + getValue('CountDiscussions', $user, 0);
         echo '<span class="MItem PostCount">'.plural(number_format($posts), '@'.t('Posts.Singular: %s', 'Posts: <b>%s</b>'), '@'.t('Posts.Plural: %s', 'Posts: <b>%s</b>')).'</span>';
      }
   }

   public function setup() {
      // Nothing to do here!
   }
   
   public function structure() {
      // Nothing to do here!
   }
         
}
