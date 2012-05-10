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
$PluginInfo['Participated'] = array(
   'Name' => 'Participated Discussions',
   'Description' => "Users may view a list of all discussions they have commented on. This is a more user-friendly version of an 'auto-subscribe' option.",
   'Version' => '1.1.0',
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

class ParticipatedPlugin extends Gdn_Plugin {

   protected $Participated = NULL;
   protected $CountParticipated = NULL;
   
   public function DiscussionsController_AfterInitialize_Handler($Sender) {
      $this->GetCountParticipated();
   }
   
   protected function GetCountParticipated() {
      if (is_null($this->CountParticipated)) {
         $DiscussionModel = new DiscussionModel();
         try {
            $this->CountParticipated = $DiscussionModel->GetCountParticipated(NULL);
         } catch (Exception $e) {
            $this->CountParticipated = FALSE;
         }
      }
      
      return $this->CountParticipated;
   }
   
   /**
    * Gets list of discussions user has commented on.
    * 
    * @return DataSet
    */
   public function DiscussionModel_GetParticipated_Create($Sender) {
      $UserID = GetValue(0, $Sender->EventArguments);
      $Offset = GetValue(1, $Sender->EventArguments);
      $Limit = GetValue(2, $Sender->EventArguments);
      
      if (is_null($UserID)) {
         if (!Gdn::Session()->IsValid()) throw new Exception(T("Could not get participated discussions for non logged-in user."));
         $UserID = Gdn::Session()->UserID;
      }
      
      $Sender->SQL->Reset();
      $Sender->DiscussionSummaryQuery();
      
      $Data = $Sender->SQL->Select('d.*')
         ->Select('w.UserID', '', 'WatchUserID')
         ->Select('w.DateLastViewed, w.Dismissed, w.Bookmarked')
         ->Select('w.CountComments', '', 'CountCommentWatch')
         ->Join('UserDiscussion w', 'd.DiscussionID = w.DiscussionID and w.UserID = '.$UserID, 'left')
         ->Join('Comment c','d.DiscussionID = c.DiscussionID')
         ->Where('c.InsertUserID', $UserID)
         ->GroupBy('c.DiscussionID')
         ->OrderBy('d.DateLastComment', 'desc')
         ->Limit($Limit, $Offset)
         ->Get();
         
      $Sender->AddDiscussionColumns($Data);
      
      return $Data;
   }
   
   /**
    * Gets number of discussions user has commented on.
    *
    * @return int
    */
   public function DiscussionModel_GetCountParticipated_Create($Sender) {
      
      $UserID = GetValue(0, $Sender->EventArguments);
      
      if (is_null($UserID)) {
         if (!Gdn::Session()->IsValid()) throw new Exception(T("Could not get participated discussions for non logged-in user."));
         $UserID = Gdn::Session()->UserID;
      }
      
      $Count = Gdn::SQL()->Select('c.DiscussionID','DISTINCT','NumDiscussions')
         ->From('Comment c')
         ->Where('c.InsertUserID', $UserID)
         ->GroupBy('c.DiscussionID')
         ->Get();
      
      return ($Count instanceof Gdn_Dataset) ? $Count->NumRows() : FALSE;
   }
      
   /**
    * Add navigation tab.
    */
   public function AddParticipatedTab($Sender) {
      $Count = $this->GetCountParticipated();
      if ($Count > 0) {
         $MyParticipated = T('Participated Discussions');
         if (C('Vanilla.Discussions.ShowCounts', TRUE))
            $MyParticipated .= '<span class="Aside"><span class="Count">'.$Count.'</span></span>';
         echo ' <li '.(($Sender->RequestMethod == 'participated') ? ' class="Active"' : '').'>'.Anchor($MyParticipated, '/discussions/participated', 'MyParticipated TabLink').'</li> ';
      }
   }
   public function DiscussionsController_AfterDiscussionFilters_Handler($Sender) {
      $this->AddParticipatedTab($Sender);
   }
   public function CategoriesController_AfterDiscussionFilters_Handler($Sender) {
      $this->AddParticipatedTab($Sender);
   }
   public function DraftsController_AfterDiscussionFilters_Handler($Sender) {
      $this->AddParticipatedTab($Sender);
   }
   
   /**
    * Create paginated list of discussions user has participated in.
    */
   public function DiscussionsController_Participated_Create($Sender, $Args = array()) {
      $Sender->Permission('Garden.SignIn.Allow');
      
      $Page = GetValue(0, $Args);
      $Limit = GetValue(1, $Args);
      
      list($Offset, $Limit) = OffsetLimit($Page, Gdn::Config('Vanilla.Discussions.PerPage', 30));
         
      // Get Discussions
      $DiscussionModel = new DiscussionModel();
      
      $Sender->DiscussionData = $DiscussionModel->GetParticipated(Gdn::Session()->UserID, $Offset, $Limit);
      $Sender->SetData('Discussions', $Sender->DiscussionData);
      
      $CountDiscussions = $DiscussionModel->GetCountParticipated(Gdn::Session()->UserID);
      $Sender->SetData('CountDiscussions', $CountDiscussions);
      
      // Build a pager
      $PagerFactory = new Gdn_PagerFactory();
		$Sender->EventArguments['PagerType'] = 'Pager';
		$Sender->FireEvent('BeforeBuildPager');
      $Sender->Pager = $PagerFactory->GetPager($Sender->EventArguments['PagerType'], $Sender);
      $Sender->Pager->ClientID = 'Pager';
      $Sender->Pager->Configure(
         $Offset,
         $Limit,
         $CountDiscussions,
         'discussions/participated/%1$s'
      );
		$Sender->FireEvent('AfterBuildPager');
      
      // Deliver JSON data if necessary
      if ($Sender->DeliveryType() != DELIVERY_TYPE_ALL) {
         $Sender->SetJson('LessRow', $Sender->Pager->ToString('less'));
         $Sender->SetJson('MoreRow', $Sender->Pager->ToString('more'));
         $Sender->View = 'discussions';
      }
      
      // Add modules
      $Sender->AddModule('NewDiscussionModule');
      $Sender->AddModule('CategoriesModule');
      $BookmarkedModule = new BookmarkedModule($Sender);
      $BookmarkedModule->GetData();
      $Sender->AddModule($BookmarkedModule);
      
      $Sender->Render($this->GetView('participated.php'));
   }
   
   public function Setup() {
      // Nothing to do here!
   }
}