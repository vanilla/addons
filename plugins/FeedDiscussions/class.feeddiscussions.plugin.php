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
$PluginInfo['FeedDiscussions'] = array(
   'Name' => 'Feed Discussions',
   'Description' => "Automatically creates new discussions based on content imported from supplied RSS feeds.",
   'Version' => '0.9.1',
   'RequiredApplications' => FALSE,
   'RequiredTheme' => FALSE, 
   'RequiredPlugins' => FALSE,
   'HasLocale' => TRUE,
   'RegisterPermissions' => FALSE,
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com'
);

class FeedDiscussionsPlugin extends Gdn_Plugin {

   protected $FeedList = NULL;
   protected $RawFeedList = NULL;
   
   /**
    * Set up appmenu link
    */
   public function Base_GetAppSettingsMenuItems_Handler(&$Sender) {
      $Menu = &$Sender->EventArguments['SideMenu'];
      $Menu->AddItem('Forum', T('Forum'));
      $Menu->AddLink('Forum', T('Feed Discussions'), 'plugin/feeddiscussions', 'Garden.Settings.Manage');
   }
   
   public function DiscussionsController_AfterInitialize_Handler($Sender) {
      if ($this->IsEnabled()) {
         if ($this->CheckFeeds(FALSE))
            $Sender->AddJsFile($this->GetResource('js/feeddiscussions.js', FALSE, FALSE));
      }
   }
   
   public function DiscussionController_BeforeDiscussionRender_Handler($Sender) {
      if ($this->IsEnabled())
         $Sender->AddCssFile($this->GetResource('css/feeddiscussions.css', FALSE, FALSE));
   }
   
   /**
    * Act as a mini dispatcher for API requests to the plugin app
    */
   public function PluginController_FeedDiscussions_Create($Sender) {
		$this->Dispatch($Sender, $Sender->RequestArgs);
   }
   
   /**
    * Handle toggling of the FeedDiscussions.Enabled setting
    *
    * This method handles the internally re-dispatched call generated when a user clicks
    * the 'Enable' or 'Disable' button within the dashboard settings page for Feed Discussions.
    */
   public function Controller_Toggle($Sender) {
      $Sender->Permission('Garden.Settings.Manage');
      
      // Handle Enabled/Disabled toggling
      $this->AutoToggle($Sender);
   }
   
   public function Controller_CheckFeeds($Sender) {
      $this->CheckFeeds();
      exit();
   }
   
   public function CheckFeeds($AutoImport = TRUE) {
      $NeedToPoll = 0;
      foreach ($this->GetFeeds() as $FeedURL => $FeedData) {
         // Check feed here
         $LastImport = GetValue('LastImport', $FeedData) == 'never' ? 0 : strtotime(GetValue('LastImport', $FeedData));
         $Historical = (bool)GetValue('Historical', $FeedData, FALSE);
         $Delay = GetValue('Refresh', $FeedData);
         $DelayStr = '+'.str_replace(array(
            'h',
            'd',
            'w'
         ),array(
            'hours',
            'days',
            'weeks'
         ),$Delay);
         $DelayMinTime = strtotime($DelayStr, $LastImport);
         if (
            ($LastImport && time() > $DelayMinTime) ||                  // We've imported before, and this article was published since then
            
            (!$LastImport && (time() > $DelayMinTime || $Historical))   // We've not imported before, and this is either a new article,
                                                                        // or its old and we're allowed to import old articles
         ) {
            if ($AutoImport) {
               $NeedToPoll = $NeedToPoll | 1;
               $this->PollFeed($FeedURL, $LastImport);
            } else {
               return TRUE;
            }
         }
      }
      return (bool)$NeedToPoll;
   }
   
   public function Controller_Index($Sender) {
      $Sender->Title($this->GetPluginKey('Name'));
      $Sender->AddSideMenu('plugin/feeddiscussions');
      $Sender->SetData('Description', $this->GetPluginKey('Description'));
      $Sender->AddCssFile($this->GetResource('css/feeddiscussions.css',FALSE,FALSE));
      
      $Sender->SetData('Feeds', $this->GetFeeds());
      
      $Sender->Render($this->GetView('feeddiscussions.php'));
   }
   
   public function Controller_AddFeed($Sender) {
      
      // Do addfeed stuff here;
      if ($Sender->Form->AuthenticatedPostback()) {
         
         // Grab posted values and merge with defaults
         $FormPostValues = $Sender->Form->FormValues();
         $Defaults = array(
            'FeedDiscussions.FeedOption.Historical'   => 1,
            'FeedDiscussions.FeedOption.Refresh'      => '1d'
         );
         $FormPostValues = array_merge($Defaults, $FormPostValues);
         
         try {
            $FeedURL = GetValue('FeedDiscussions.FeedURL', $FormPostValues, NULL);
            if (empty($FeedURL))
               throw new Exception("You must supply a valid Feed URL");
         
            if ($this->HaveFeed($FeedURL))
               throw new Exception("The Feed URL you supplied is already part of an Active Feed");
               
            // Check feed is valid RSS:
            $FeedRSS = ProxyRequest($FeedURL, FALSE, TRUE);
            if (!$FeedRSS)
               throw new Exception("The Feed URL you supplied is not available");
            
            $RSSData = simplexml_load_string($FeedRSS);
            if (!$RSSData)
               throw new Exception("The Feed URL you supplied is not valid XML");
               
            $Channel = GetValue('channel', $RSSData, FALSE);
            if (!$Channel)
               throw new Exception("The Feed URL you supplied is not an RSS stream");
               
            $this->AddFeed($FeedURL, array(
               'Historical'   => $FormPostValues['FeedDiscussions.FeedOption.Historical'],
               'Refresh'      => $FormPostValues['FeedDiscussions.FeedOption.Refresh'],
               'LastImport'   => "never"
            ));
            $Sender->InformMessage(sprintf(T("Feed has been added"),$FeedURL));
            $Sender->Form->ClearInputs();
               
         } catch(Exception $e) {
            $Sender->Form->AddError(T($e->getMessage()));
         }
      }
      
      // Redirect('/plugin/feeddiscussions/');
      $this->Controller_Index($Sender);
   }
   
   public function Controller_DeleteFeed($Sender) {
      $ChosenFeed = GetValue(1, $Sender->RequestArgs, NULL);
      if (!is_null($ChosenFeed)) {
         $FeedURL = self::DecodeFeedKey($ChosenFeed);
         if ($this->HaveFeed($FeedURL)) {
            $this->RemoveFeed($FeedURL);
            $Sender->InformMessage(sprintf(T("Feed has been removed"),$FeedURL));
         }
      }
      
      // Redirect('/plugin/feeddiscussions/');
      $this->Controller_Index($Sender);
   }
   
   protected function GetFeeds($Raw = FALSE, $Regen = FALSE) {
      if (is_null($this->FeedList) || $Regen) {
         $FeedArray = C('Plugin.FeedDiscussions.Feeds', array());
         $this->FeedList = array();
         $this->RawFeedList = array();
         
         foreach ($FeedArray as $FeedKey => $FeedItem) {
            $RealFeedURL = self::DecodeFeedKey($FeedKey);
            $this->RawFeedList[$FeedKey] = $this->FeedList[$RealFeedURL] = $FeedItem;
         }
      }
      
      return ($Raw) ? $this->RawFeedList : $this->FeedList;
   }
   
   protected function PollFeed($FeedURL, $LastImportDate) {
      $FeedRSS = ProxyRequest($FeedURL, FALSE, TRUE);
      if (!$FeedRSS) return FALSE;
      
      $RSSData = simplexml_load_string($FeedRSS);
      if (!$RSSData) return FALSE;
      
      $Channel = GetValue('channel', $RSSData, FALSE);
      if (!$Channel) return FALSE;
      
      if (!array_key_exists('item', $Channel)) return FALSE;
      
      $DiscussionModel = new DiscussionModel();
      $DiscussionModel->SpamCheck = FALSE;
      
      foreach (GetValue('item', $Channel) as $Item) {
         $StrPubDate = GetValue('pubDate', $Item, NULL);
         if (!is_null($StrPubDate)) {
            $PubDate = strtotime($StrPubDate);
            
            // Story is older than last import date. Do not import.
            if ($PubDate < $LastImportDate) continue;
         } else {
            $PubDate = time();
         }
         
         $StoryTitle = array_shift($Trash = explode("\n",GetValue('title', $Item)));
         $StoryBody = (string)GetValue('description', $Item);
         $StoryPublished = date("Y-m-d H:i:s", $PubDate);
         
         $ParsedStoryBody = $StoryBody;
         $ParsedStoryBody = '<div class="AutoFeedDiscussion">'.$ParsedStoryBody.'</div>';
         
         // Yahoo RSS Gayness
         // $ParsedStoryBody = preg_replace_callback('/href="(.*)"/', array($this, 'ReplaceBadURLs'), $ParsedStoryBody);
         
         $DiscussionData = array(
               'Name'            => $StoryTitle,
               'Body'            => $ParsedStoryBody
            );
            
         $InsertUserID = $DiscussionModel->SQL->Select('UserID')->From('User')->Where('Admin',1)->Get()->FirstRow()->UserID;
         $DiscussionData[$DiscussionModel->DateInserted] = $StoryPublished;
         $DiscussionData[$DiscussionModel->InsertUserID] = $InsertUserID;
         
         $DiscussionData[$DiscussionModel->DateUpdated] = $StoryPublished;
         $DiscussionData[$DiscussionModel->UpdateUserID] = $InsertUserID;
         
         $InsertID = $DiscussionModel->Save($DiscussionData);
            
         $DiscussionModel->Validation->Results(TRUE);
      }
      
      $this->UpdateFeed($FeedURL, 'LastImport', date('Y-m-d H:i:s', time()));
   }
   
   public function ReplaceBadURLs($Matches) {
      $MatchedURL = $Matches[0];
      $FixedURL = array_pop($Trash = explode("/*", $MatchedURL));
      return 'href="'.$FixedURL.'"';
   }
   
   protected function AddFeed($FeedURL, $FeedOptions) {
      $FeedKey = self::EncodeFeedKey($FeedURL);
      $Feeds = $this->GetFeeds(TRUE);
      $Feeds[$FeedKey] = $FeedOptions;
      SaveToConfig('Plugin.FeedDiscussions.Feeds', $Feeds);
      
      // regenerate the internal feed list
      $this->GetFeeds(TRUE, TRUE);
   }
   
   protected function UpdateFeed($FeedURL, $FeedOptionKey, $FeedOptionValue) {
      $FeedKey = self::EncodeFeedKey($FeedURL);
      $Feeds = $this->GetFeeds(TRUE);
      $Feeds[$FeedKey][$FeedOptionKey] = $FeedOptionValue;
      SaveToConfig('Plugin.FeedDiscussions.Feeds', $Feeds);
      
      // regenerate the internal feed list
      $this->GetFeeds(TRUE, TRUE);
   }
   
   protected function RemoveFeed($FeedURL) {
      $FeedKey = self::EncodeFeedKey($FeedURL);
      $Feeds = $this->GetFeeds(TRUE);
      unset($Feeds[$FeedKey]);
      SaveToConfig('Plugin.FeedDiscussions.Feeds', $Feeds);
      
      // regenerate the internal feed list
      $this->GetFeeds(TRUE, TRUE);
   }
   
   protected function GetFeed($FeedURL, $PreEncoded = FALSE) {
      $FeedKey = (!$PreEncoded) ? self::EncodeFeedKey($FeedURL) : $FeedURL;
      $Feeds = $this->GetFeeds(TRUE);
      
      if (array_key_exists($FeedKey, $Feeds))
         return $Feeds[$FeedKey];
         
      return NULL;
   }
   
   protected function HaveFeed($FeedURL) {
      $Feeds = $this->GetFeeds();
      if (array_key_exists($FeedURL, $Feeds)) return TRUE;
      return FALSE;
   }
   
   public static function EncodeFeedKey($Key) {
      return str_replace('=','_',base64_encode($Key));
   }
   
   public static function DecodeFeedKey($Key) {
      return base64_decode(str_replace('_','=',$Key));
   }
      
   public function Setup() {
      // Nothing to do here!
   }
   
   public function Structure() {
      // Nothing to do here!
   }
         
}