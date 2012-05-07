<?php if (!defined('APPLICATION')) exit();

/**
 * Feed Discussions
 * 
 * Automatically creates new discussions based on content imported from supplied RSS feeds.
 * 
 * Changes: 
 *  1.0     Initial release/rewrite
 *  1.0.1   Minor fixes for logic
 *  1.0.2   Fix repeat posting bug
 * 
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2003 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Addons
 */

// Define the plugin:
$PluginInfo['FeedDiscussions'] = array(
   'Name' => 'Feed Discussions',
   'Description' => "Automatically creates new discussions based on content imported from supplied RSS feeds.",
   'Version' => '1.0.2',
   'RequiredApplications' => array('Vanilla' => '2.1a'),
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
   
   public function DiscussionController_BeforeDiscussionRender_Handler($Sender) {
      if ($this->IsEnabled()) {
         if ($this->CheckFeeds(FALSE))
            $Sender->AddJsFile($this->GetResource('js/feeddiscussions.js', FALSE, FALSE));
         
         $Sender->AddCssFile($this->GetResource('css/feeddiscussions.css', FALSE, FALSE));
      }
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
         $LastImport = GetValue('LastImport', $FeedData) == 'never' ? NULL : strtotime(GetValue('LastImport', $FeedData));
         if (is_null($LastImport))
            $LastImport = strtotime(GetValue('Added', $FeedData, 0));
         
         $Historical = (bool)GetValue('Historical', $FeedData, FALSE);
         $Delay = GetValue('Refresh', $FeedData);
         $DelayStr = '+'.str_replace(array(
            'm',
            'h',
            'd',
            'w'
         ),array(
            'minutes',
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
      
      $Categories = CategoryModel::Categories();
      $Sender->SetData('Categories', $Categories);
      $Sender->SetData('Feeds', $this->GetFeeds());
      
      $Sender->Render($this->GetView('feeddiscussions.php'));
   }
   
   public function Controller_AddFeed($Sender) {
      
      $Categories = CategoryModel::Categories();
      $Sender->SetData('Categories', $Categories);
      
      // Do addfeed stuff here;
      if ($Sender->Form->AuthenticatedPostback()) {
         
         // Grab posted values and merge with defaults
         $FormPostValues = $Sender->Form->FormValues();
         $Defaults = array(
            'Historical'   => 1,
            'Refresh'      => '1d',
            'Category'     => -1
         );
         $FormPostValues = array_merge($Defaults, $FormPostValues);
         
         try {
            $FeedURL = GetValue('FeedURL', $FormPostValues, NULL);
            if (empty($FeedURL))
               throw new Exception("You must supply a valid Feed URL");
         
            if ($this->HaveFeed($FeedURL, FALSE))
               throw new Exception("The Feed URL you supplied is already part of an Active Feed");
            
            $FeedCategoryID = GetValue('Category', $FormPostValues);
            if (!array_key_exists($FeedCategoryID, $Categories))
               throw new Exception("You need to select a Category");
               
            // Check feed is valid RSS:
            $Pr = new ProxyRequest();
            $FeedRSS = $Pr->Request(array(
               'URL' => $FeedURL
            ));
            
            if (!$FeedRSS)
               throw new Exception("The Feed URL you supplied is not available");
            
            $RSSData = simplexml_load_string($FeedRSS);
            if (!$RSSData)
               throw new Exception("The Feed URL you supplied is not valid XML");
               
            $Channel = GetValue('channel', $RSSData, FALSE);
            if (!$Channel)
               throw new Exception("The Feed URL you supplied is not an RSS stream");
               
            $this->AddFeed($FeedURL, array(
               'Historical'   => $FormPostValues['Historical'],
               'Refresh'      => $FormPostValues['Refresh'],
               'Category'     => $FeedCategoryID,
               'Added'        => date('Y-m-d H:i:s'),
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
      $FeedKey = GetValue(1, $Sender->RequestArgs, NULL);
      if (!is_null($FeedKey) && $this->HaveFeed($FeedKey)) {
         $Feed = $this->GetFeed($FeedKey, TRUE);
         $FeedURL = $Feed['URL'];
         
         $this->RemoveFeed($FeedKey);
         $Sender->InformMessage(sprintf(T("Feed has been removed"),$FeedURL));
      }
      
      // Redirect('/plugin/feeddiscussions/');
      $this->Controller_Index($Sender);
   }
   
   protected function GetFeeds($Raw = FALSE, $Regen = FALSE) {
      if (is_null($this->FeedList) || $Regen) {
         $FeedArray = $this->GetUserMeta(0, "Feed.%");
         
         //die('feeds');
         $this->FeedList = array();
         $this->RawFeedList = array();
         
         foreach ($FeedArray as $FeedMetaKey => $FeedItem) {
            $DecodedFeedItem = json_decode($FeedItem, TRUE);
            $FeedURL = GetValue('URL', $DecodedFeedItem, NULL);
            $FeedKey = self::EncodeFeedKey($FeedURL);
            
            if (is_null($FeedURL)) {
               //$this->RemoveFeed($FeedKey);
               continue;
            }
            
            $this->RawFeedList[$FeedKey] = $this->FeedList[$FeedURL] = $DecodedFeedItem;
         }
      }
      
      return ($Raw) ? $this->RawFeedList : $this->FeedList;
   }
   
   protected function PollFeed($FeedURL, $LastImportDate) {
      $Pr = new ProxyRequest();
      $FeedRSS = $Pr->Request(array(
         'URL' => $FeedURL
      ));
            
      if (!$FeedRSS) return FALSE;
      
      $RSSData = simplexml_load_string($FeedRSS);
      if (!$RSSData) return FALSE;
      
      $Channel = GetValue('channel', $RSSData, FALSE);
      if (!$Channel) return FALSE;
      
      if (!array_key_exists('item', $Channel)) return FALSE;
      
      $Feed = $this->GetFeed($FeedURL, FALSE);
      
      $DiscussionModel = new DiscussionModel();
      $DiscussionModel->SpamCheck = FALSE;
      
      $LastPublishDate = GetValue('LastPublishDate', $Feed, date('c'));
      $LastPublishTime = strtotime($LastPublishDate);
      
      $FeedLastPublishTime = 0;
      foreach (GetValue('item', $Channel) as $Item) {
         $FeedItemGUID = trim((string)GetValue('guid', $Item));
         $FeedItemID = substr(md5($FeedItemGUID), 0, 30);
         
         $ItemPubDate = (string)GetValue('pubDate', $Item, NULL);
         if (is_null($ItemPubDate))
            $ItemPubTime = time();
         else
            $ItemPubTime = strtotime($ItemPubDate);
         
         if ($ItemPubTime > $FeedLastPublishTime)
            $FeedLastPublishTime = $ItemPubTime;
         
         if ($ItemPubTime < $LastPublishTime && !$Feed['Historical'])
            continue;
         
         $ExistingDiscussion = $DiscussionModel->GetWhere(array(
            'ForeignID' => $FeedItemID
         ));
         
         if ($ExistingDiscussion && $ExistingDiscussion->NumRows())
            continue;
         
         $this->EventArguments['Publish'] = TRUE;

         $this->EventArguments['FeedURL'] = $FeedURL;
         $this->EventArguments['Feed'] = &$Feed;
         $this->EventArguments['Item'] = &$Item;
         $this->FireEvent('FeedItem');

         if (!$this->EventArguments['Publish']) continue;

         $StoryTitle = array_shift($Trash = explode("\n",(string)GetValue('title', $Item)));
         $StoryBody = (string)GetValue('description', $Item);
         $StoryPublished = date("Y-m-d H:i:s", $ItemPubTime);

         $ParsedStoryBody = $StoryBody;
         $ParsedStoryBody = '<div class="AutoFeedDiscussion">'.$ParsedStoryBody.'</div>';

         $DiscussionData = array(
               'Name'            => $StoryTitle,
               'Format'          => 'Html',
               'CategoryID'      => $Feed['Category'],
               'ForeignID'       => substr($FeedItemID, 0, 30),
               'Body'            => $ParsedStoryBody
            );

         $Minion = Gdn::PluginManager()->GetPluginInstance('MinionPlugin');
         $InsertUserID = $Minion->GetMinionUserID();

         $DiscussionData[$DiscussionModel->DateInserted] = $StoryPublished;
         $DiscussionData[$DiscussionModel->InsertUserID] = $InsertUserID;

         $DiscussionData[$DiscussionModel->DateUpdated] = $StoryPublished;
         $DiscussionData[$DiscussionModel->UpdateUserID] = $InsertUserID;

         $this->EventArguments['FeedDiscussion'] = &$DiscussionData;
         $this->FireEvent('Publish');

         if (!$this->EventArguments['Publish']) continue;

         $InsertID = $DiscussionModel->Save($DiscussionData);

         $DiscussionModel->Validation->Results(TRUE);
      }
      
      $FeedKey = self::EncodeFeedKey($FeedURL);
      $this->UpdateFeed($FeedKey, array(
         'LastImport'      => date('Y-m-d H:i:s'),
         'LastPublishDate' => date('c', $FeedLastPublishTime)
      ));
   }
   
   public function ReplaceBadURLs($Matches) {
      $MatchedURL = $Matches[0];
      $FixedURL = array_pop($Trash = explode("/*", $MatchedURL));
      return 'href="'.$FixedURL.'"';
   }
   
   protected function AddFeed($FeedURL, $Feed) {
      $FeedKey = self::EncodeFeedKey($FeedURL);
      
      $Feed['URL'] = $FeedURL;
      $EncodedFeed = json_encode($Feed);
      $this->SetUserMeta(0, "Feed.{$FeedKey}", $EncodedFeed);
      
      // regenerate the internal feed list
      $this->GetFeeds(TRUE, TRUE);
   }
   
   protected function UpdateFeed($FeedKey, $FeedOptionKey, $FeedOptionValue = NULL) {
      $Feed = $this->GetFeed($FeedKey);
      
      if (!is_array($FeedOptionKey))
         $FeedOptionKey = array($FeedOptionKey => $FeedOptionValue);
      
      $Feed = array_merge($Feed, $FeedOptionKey);
      
      $EncodedFeed = json_encode($Feed);
      $this->SetUserMeta(0, "Feed.{$FeedKey}", $EncodedFeed);
      
      // regenerate the internal feed list
      $this->GetFeeds(TRUE, TRUE);
   }
   
   protected function RemoveFeed($FeedKey, $PreEncoded = TRUE) {
      $FeedKey = (!$PreEncoded) ? self::EncodeFeedKey($FeedKey) : $FeedKey;
      $this->SetUserMeta(0, "Feed.{$FeedKey}", NULL);
      
      // regenerate the internal feed list
      $this->GetFeeds(TRUE, TRUE);
   }
   
   protected function GetFeed($FeedKey, $PreEncoded = TRUE) {
      $FeedKey = (!$PreEncoded) ? self::EncodeFeedKey($FeedKey) : $FeedKey;
      $Feeds = $this->GetFeeds(TRUE);
      
      if (array_key_exists($FeedKey, $Feeds))
         return $Feeds[$FeedKey];
         
      return NULL;
   }
   
   protected function HaveFeed($FeedKey, $PreEncoded = TRUE) {
      $FeedKey = (!$PreEncoded) ? self::EncodeFeedKey($FeedKey) : $FeedKey;
      $Feed = $this->GetFeed($FeedKey);
      if (!empty($Feed)) return TRUE;
      return FALSE;
   }
   
   public static function EncodeFeedKey($Key) {
      return md5($Key);
   }
   
   public function Setup() {
      // Nothing to do here!
   }
   
   public function Structure() {
      // Nothing to do here!
   }
         
}