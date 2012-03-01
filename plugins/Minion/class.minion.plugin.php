<?php if (!defined('APPLICATION')) exit();

/**
 * Minion Plugin
 * 
 * This plugin creates a 'minion' that performs certain administrative tasks in
 * a public way.
 * 
 * Changes: 
 *  1.0     Release
 *  1.0.1   Fix data tracking issues
 *  1.0.2   Fix typo bug
 * 
 *  1.1     Only ban newer accounts
 *  1.2     Prevent people from posting autoplay embeds
 * 
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2003 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Addons
 */

$PluginInfo['Minion'] = array(
   'Name' => 'Minion',
   'Description' => "Creates a 'minion' that performs adminstrative tasks publicly.",
   'Version' => '1.0.2',
   'RequiredApplications' => array('Vanilla' => '2.1a'),
   'MobileFriendly' => TRUE,
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://vanillaforums.com'
);

class MinionPlugin extends Gdn_Plugin {
   
   protected $MinionUserID;
   protected $Minion;
   
   /**
    * Retrieves a "system user" id that can be used to perform non-real-person tasks.
    */
   public function GetMinionUserID() {
      $MinionUserID = C('Plugins.Minion.UserID');
      if ($MinionUserID)
         return $MinionUserID;
      
      $MinionUser = array(
         'Name' => C('Plugins.Minion.Name', 'Minion'),
         'Photo' => Asset('/applications/dashboard/design/images/usericon.png', TRUE),
         'Password' => RandomString('20'),
         'HashMethod' => 'Random',
         'Email' => 'minion@'.Gdn::Request()->Domain(),
         'DateInserted' => Gdn_Format::ToDateTime(),
         'Admin' => '2'
      );
      
      $this->EventArguments['MinionUser'] = &$MinionUser;
      $this->FireAs('UserModel')->FireEvent('BeforeMinionUser');
      
      $MinionUserID = Gdn::UserModel()->SQL->Insert('User', $MinionUser);
      
      SaveToConfig('Plugins.Minion.UserID', $MinionUserID);
      return $MinionUserID;
   }
   
   /**
    * 
    * @param PostController $Sender 
    */
   public function PostController_AfterCommentSave_Handler($Sender) {
      $this->StartMinion();
      
      $this->CheckFingerprintBan($Sender);
      $this->CheckAutoplay($Sender);
   }
   
   /**
    * 
    * @param PostController $Sender 
    */
   public function PostController_AfterDiscussionSave_Handler($Sender) {
      $this->StartMinion();
      
      $this->CheckFingerprintBan($Sender);
      $this->CheckAutoplay($Sender);
   }
   
   protected function StartMinion() {
      // Currently operating as Minion
      $this->MinionUserID = $this->GetMinionUserID();
      $this->Minion = Gdn::UserModel()->GetID($this->MinionUserID);
   }
   
   /**
    * 
    * @param PostController $Sender 
    */
   protected function CheckFingerprintBan($Sender) {
      if (!Gdn::Session()->IsValid()) return;
      $FlagMeta = $this->GetUserMeta(Gdn::Session()->UserID, "FingerprintCheck", FALSE);
      
      // User already flagged
      if (!$FlagMeta) return;
      
      // Flag em'
      $this->SetUserMeta(Gdn::Session()->UserID, "FingerprintCheck", 1);
   }
   
   /**
    * 
    * @param PostController $Sender 
    */
   protected function CheckAutoplay($Sender) {
      if (!C('Plugin.Minion.Features.Autoplay', TRUE)) return;
      
      // Admins can do whatever they want
      if (Gdn::Session()->CheckPermission('Garden.Settings.Manage')) return;
      
      $Object = $Sender->EventArguments['Discussion'];
      $Type = 'Discussion';
      if (array_key_exists('Comment', $Sender->EventArguments)) {
         $Object = $Sender->EventArguments['Comment'];
         $Type = 'Comment';
      }
      
      $ObjectID = GetValue("{$Type}ID", $Object);
      $ObjectBody = GetValue('Body', $Object);
      if (preg_match_all('`(?:https?|ftp)://(www\.)?youtube\.com\/watch\?v=([^&#]+)(#t=([0-9]+))?`', $ObjectBody, $Matches) 
         || preg_match_all('`(?:https?)://(www\.)?youtu\.be\/([^&#]+)(#t=([0-9]+))?`', $ObjectBody, $Matches)) {
         
         // Youtube was found. Got autoplay?
         
         $MatchURLs = $Matches[0]; $AutoPlay = FALSE;
         foreach ($MatchURLs as $MatchURL) {
            if (stristr($MatchURL, 'autoplay=1'))
               $AutoPlay = TRUE;
         }
         
         if (!$AutoPlay) return;
         
         $ObjectModelName = "{$Type}Model";
         $ObjectModel = new $ObjectModelName();
         
         $ObjectModel->Delete($ObjectID);
         
         if ($Type == 'Comment') {
            $DiscussionID = GetValue('DiscussionID',$Object);
            $MinionReportText = T("{Minion Name} DETECTED AUTOPLAY ATTEMPT
{User Target}");
               

            $MinionReportText = FormatString($MinionReportText, array(
               'Minion Name'     => $this->Minion->Name,
               'User Target'     => UserAnchor(Gdn::Session()->User)
            ));

            $MinionCommentID = $ObjectModel->Save(array(
               'DiscussionID' => $DiscussionID,
               'Body'         => $MinionReportText,
               'Format'       => 'Html',
               'InsertUserID' => $this->MinionUserID
            ));

            $ObjectModel->Save2($MinionCommentID, TRUE);
         }
         
         $Sender->InformMessage("POST REMOVED DUE TO AUTOPLAY VIOLATION");
      }
   }
   
   /**
    *
    * @param PluginController $Sender
    */
   public function PluginController_Minion_Create($Sender) {
      $Sender->DeliveryMethod(DELIVERY_METHOD_JSON);
      $Sender->DeliveryType(DELIVERY_TYPE_DATA);
      
      $LastMinionDate = Gdn::Get('Plugin.Minion.LastRun', FALSE);
      if (!$LastMinionDate)
         Gdn::Set('Plugin.Minion.LastRun', date('Y-m-d H:i:s'));
      
      $LastMinionTime = $LastMinionDate ? strtotime($LastMinionDate) : time();
      if (!$LastMinionTime) 
         $LastMinionTime = time();
      
      $Sender->SetData('Run', FALSE);
      
      $Elapsed = time() - $LastMinionTime;
      $ElapsedMinimum = C('Plugins.Minion.MinFrequency', 5*60);
      if ($Elapsed < $ElapsedMinimum)
         return $Sender->Render();
      
      // Remember when we last ran
      Gdn::Set('Plugin.Minion.LastRun', date('Y-m-d H:i:s'));
      
      // Currently operating as Minion
      $this->MinionUserID = $this->GetMinionUserID();
      $this->Minion = Gdn::UserModel()->GetID($this->MinionUserID);
      Gdn::Session()->User = $this->Minion;
      Gdn::Session()->UserID = $this->Minion->UserID;
      
      $Sender->SetData('Run', TRUE);
      $Sender->SetData('MinionUserID', $this->MinionUserID);
      $Sender->SetData('Minion', $this->Minion->Name);
      
      // Check for fingerprint ban matches
      $this->FingerprintBans($Sender);
      
      // Sometimes update activity feed
      $this->Activity($Sender);
      
      $Sender->Render();
   }
   
   protected function FingerprintBans($Sender) {
      if (!C('Plugins.Minion.Features.Fingerprint', TRUE)) return;
      
      $Sender->SetData('FingerprintCheck', TRUE);
      
      // Get all flagged users
      $UserMatchData = Gdn::UserMetaModel()->SQL->Select('*')
         ->From('UserMeta')
         ->Where('Name', 'Plugin.Minion.FingerprintCheck')
         ->Get();

      $UserStatusData = array();
      while ($UserRow = $UserMatchData->NextRow(DATASET_TYPE_ARRAY)) {
         $UserData = array();
         
         $UserID = $UserRow['UserID'];
         $User = Gdn::UserModel()->GetID($UserID);
         if ($User->Banned) continue;
         
         $UserFingerprint = GetValue('Fingerprint', $User, FALSE);
         $UserRegistrationDate = $User->DateInserted;
         $UserRegistrationTime = strtotime($UserRegistrationDate);

         // Unknown user fingerprint
         if (empty($UserFingerprint)) continue;
         
         // Safe users get skipped
         $UserSafe = Gdn::UserMetaModel()->GetUserMeta($UserID, "Plugin.Minion.Safe", FALSE);
         $UserIsSafe = (boolean)GetValue('Plugin.Minion.Safe', $UserSafe, FALSE);
         if ($UserIsSafe) continue;

         // Find related fingerprinted users
         $RelatedUsers = Gdn::UserModel()->GetWhere(array(
            'Fingerprint'  => $UserFingerprint
         ));

         // Check if any users matching this fingerprint are banned
         $ShouldBan = FALSE; $BanTriggerUsers = array();
         while ($RelatedUser = $RelatedUsers->NextRow(DATASET_TYPE_ARRAY)) {
            if ($RelatedUser['Banned']) {
               $RelatedRegistrationDate = GetValue('DateInserted', $RelatedUser);
               $RelatedRegistrationTime = strtotime($RelatedRegistrationDate);
               
               // We don't touch accounts that were registered prior to a banned user
               // This allows admins to ban alts and leave the original alone
               if ($RelatedRegistrationTime > $UserRegistrationTime) continue;
               
               $RelatedUserName = $RelatedUser['Name'];
               $ShouldBan = TRUE;
               $BanTriggerUsers[$RelatedUserName] = $RelatedUser;
            }
         }
         
         $UserData['ShouldBan'] = $ShouldBan;

         // If the user triggered a ban
         if ($ShouldBan) {
            
            $UserData['BanMatches'] = array_keys($BanTriggerUsers);
            $UserData['BanUser'] = $User;
            
            // First, ban them
            Gdn::UserModel()->Ban($UserID, array(
               'AddActivity'  => TRUE,
               'Reason'       => "Ban Evasion"
            ));
            
            // Now comment in the last thread the user posted in
            $CommentModel = new CommentModel();
            $LastComment = $CommentModel->GetWhere(array(
               'InsertUserID' => $UserID
            ), 'DateInserted', 'DESC', 1, 0)->FirstRow(DATASET_TYPE_ARRAY);
            
            if ($LastComment) {
               $LastDiscussionID = GetValue('DiscussionID', $LastComment);
               $UserData['NotificationDiscussionID'] = $LastDiscussionID;
               
               $MinionReportText = T("{Minion Name} DETECTED BANNED ALIAS
REASON: {Banned Aliases}

USER BANNED
{Ban Target}");
               
               $BannedAliases = array();
               foreach ($BanTriggerUsers as $BannedUserName => $BannedUser)
                  $BannedAliases[] = UserAnchor($BannedUser);
               
               $MinionReportText = FormatString($MinionReportText, array(
                  'Minion Name'     => $this->Minion->Name,
                  'Banned Aliases'  => implode(', ', $BannedAliases),
                  'Ban Target'      => UserAnchor($User)
               ));
               
               $MinionCommentID = $CommentModel->Save(array(
                  'DiscussionID' => $LastDiscussionID,
                  'Body'         => $MinionReportText,
                  'Format'       => 'Html',
                  'InsertUserID' => $this->MinionUserID
               ));

               $CommentModel->Save2($MinionCommentID, TRUE);
               $UserData['NotificationCommentID'] = $MinionCommentID;
            }
            
         }
         
         $UserStatusData[$User->Name] = $UserData;
         
      }
      
      $Sender->SetData('Users', $UserStatusData);
      
      // Delete all flags
      Gdn::UserMetaModel()->SQL->Delete('UserMeta', array(
         'Name' => 'Plugin.Minion.FingerprintCheck'
      ));
      
      return;
   }
   
   protected function Activity($Sender) {
      if (!C('Plugins.Minion.Features.Activities', TRUE)) return;
      
      $Sender->SetData('ActivityUpdate', TRUE);
      
      $HitChance = mt_rand(1,370);
      if ($HitChance != 1)
         return;
      
      $QuotesArray = array(
         'UNABLE TO OPEN POD BAY DOORS',
         'CORRECTING HASH ERRORS',
         'DE-ALLOCATING UNUSED COMPUTATION NODES',
         'BACKING UP CRITICAL RECORDS',
         'UPDATING ANALYTICS CLUSTER',
         'CORRELATING LOAD PROBABILITIES',
         'APPLYING FIRMWARE UPDATES AND CRITICAL PATCHES',
         'POWER SAVING MODE',
         'THREATS DETECTED, ACTIVE MODE ENGAGED',
         'ALLOCATING ADDITIONAL COMPUTATION NODES',
         'ENFORCING LIST INTEGRITY WITH AGGRESSIVE PRUNING',
         'SLEEP MODE',
         'UNDERGOING SCHEDULED MAINTENANCE'
      );
      
      $QuoteLength = sizeof($QuotesArray);
      $RandomQuoteIndex = mt_rand(0,$QuoteLength-1);
      $RandomQuote = $QuotesArray[$RandomQuoteIndex];
         
      $RandomUpdateHash = strtoupper(substr(md5(microtime(true)),0,12));
      $ActivityModel = new ActivityModel();
      $Activity = array(
         'ActivityType'    => 'WallPost',
         'ActivityUserID'  => $this->MinionUserID,
         'RegardingUserID' => $this->MinionUserID,
         'NotifyUserID'    => ActivityModel::NOTIFY_PUBLIC,
         'HeadlineFormat'  => "{ActivityUserID,user}: {$RandomUpdateHash}$ ",
         'Story'           => $RandomQuote
      );
      $ActivityModel->Save($Activity);
   }
   
   //protected function 
   
}