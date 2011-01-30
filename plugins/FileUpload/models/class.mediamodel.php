<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

class MediaModel extends VanillaModel {

   public function __construct() {
      parent::__construct('Media');
   }
   
   public function GetID($MediaID) {
      $this->FireEvent('BeforeGetID');
      $Data = $this->SQL
         ->Select('m.*')
         //->Select('iu.*')
         ->From('Media m')
         //->Join('User iu', 'm.InsertUserID = iu.UserID', 'left') // Insert user
         ->Where('m.MediaID', $MediaID)
         ->Get()
         ->FirstRow();
		
		return $Data;
   }
   
   public function PreloadDiscussionMedia($DiscussionID, $CommentIDList) {
      $this->FireEvent('BeforePreloadDiscussionMedia');
      
      $StartT = microtime(true);
      $Data = $this->SQL
         ->Select('m.*')
         ->From('Media m')
         ->BeginWhereGroup()
            ->Where('m.ForeignID', $DiscussionID)
            ->Where('m.ForeignTable', 'discussion')
         ->EndWhereGroup()
         ->OrOp()
         ->BeginWhereGroup()
            ->WhereIn('m.ForeignID', $CommentIDList)
            ->Where('m.ForeignTable', 'comment')
         ->EndWhereGroup()
         ->Get();
         
/*
      $DiscussionData = $this->SQL
         ->Select('m.*')
         ->From('Media m')
         ->Where('m.ForeignID', $DiscussionID)
         ->Where('m.ForeignTable', 'discussion')
         ->Get()->Result(DATASET_TYPE_ARRAY);

      $CommentData = $this->SQL
         ->Select('m.*')
         ->From('Media m')
         ->WhereIn('m.ForeignID', $CommentIDList)
         ->Where('m.ForeignTable', 'comment')
         ->Get()->Result(DATASET_TYPE_ARRAY);
      
      $Data = array_merge($DiscussionData, $CommentData);
*/

		return $Data;
   }
   
   public function Delete($Media, $DeleteFile = TRUE) {
      $MediaID = FALSE;
      if (is_a($Media, 'stdClass'))
         $Media = (array)$Media;
            
      if (is_numeric($Media)) 
         $MediaID = $Media;
      elseif (array_key_exists('MediaID', $Media))
         $MediaID = $Media['MediaID'];
      
      if ($MediaID) {
         $Media = $this->GetID($MediaID);
         $this->SQL->Delete($this->Name, array('MediaID' => $MediaID), FALSE);
         
         if ($DeleteFile) {
            $DirectPath = PATH_UPLOADS.DS.GetValue('Path',$Media);
            if (file_exists($DirectPath))
               @unlink($DirectPath);
         }

      } else {
         $this->SQL->Delete($this->Name, $Media, FALSE);
      }
      
   }
   
   public function DeleteParent($ParentTable, $ParentID) {
      $MediaItems = $this->SQL->Select('*')
         ->From($this->Name)
         ->Where('ForeignTable', strtolower($ParentTable))
         ->Where('ForeignID', $ParentID)
         ->Get()->Result(DATASET_TYPE_ARRAY);
         
      foreach ($MediaItems as $Media) {
         $this->Delete(GetValue('MediaID',$Media));
      }
   }
   
}