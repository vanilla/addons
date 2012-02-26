<?php if (!defined('APPLICATION')) exit();

/**
 * Minion Plugin
 * 
 * This plugin creates a 'minion' that performs certain administrative tasks in
 * a public way.
 * 
 * Changes: 
 *  1.0     Release
 * 
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2003 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Addons
 */

$PluginInfo['Minion'] = array(
   'Name' => 'Minion',
   'Description' => "Creates a 'minion' that performs adminstrative tasks publicly.",
   'Version' => '1.0',
   'RequiredApplications' => array('Vanilla' => '2.1a'),
   'MobileFriendly' => TRUE,
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://vanillaforums.com'
);

class MinionPlugin extends Gdn_Plugin {
   
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
      $this->CheckFingerprintBan();
   }
   
   /**
    * 
    * @param PostController $Sender 
    */
   public function PostController_AfterDiscussionSave_Handler($Sender) {
      $this->CheckFingerprintBan();
   }
   
   protected function CheckFingerprintBan() {
      if (!Gdn::Session()->IsValid()) return;
      $FlagMeta = $this->GetUserMeta(Gdn::Session()->UserID, "FingerprintCheck", FALSE);
      
      // User already flagged
      if (!$FlagMeta) return;
      
      // Flag em'
      $this->SetUserMeta(Gdn::Session()->UserID, "FingerprintCheck", 1);
   }
   
   /**
    *
    * @param PluginController $Sender
    */
   public function PluginController_Minion_Create($Sender) {
      $Sender->DeliveryMethod(DELIVERY_METHOD_JSON);
      $Sender->DeliveryType(DELIVERY_TYPE_DATA);
      
      $LastMinionDate = Gdn::Get('Plugin.Minion.LastRun', FALSE);
      $LastMinionTime = $LastMinionDate ? strtotime($LastMinionDate) : 0;
      
      if (!$LastMinionTime) 
         $LastMinionTime = 0;
      
      $Sender->SetData('Run', FALSE);
      
      $Elapsed = time() - $LastMinionTime;
      $ElapsedMinimum = C('Plugins.Minion.MinFrequency', 5*60);
      if ($Elapsed < $ElapsedMinimum) 
         return $Sender->Render();
      
      // Remember when we last ran
      Gdn::Set('Plugin.Minion.LastRun', date('Y-m-d H:i:s'));
      
      // Currently operating as Minion
      $MinionUserID = $this->GetMinionUserID();
      $Minion = Gdn::UserModel()->GetID($MinionUserID);
      Gdn::Session()->User = $Minion;
      Gdn::Session()->UserID = $Minion->UserID;
      
      $Sender->SetData('Run', TRUE);
      $Sender->SetData('MinionUserID', $MinionUserID);
      $Sender->SetData('Minion', $Minion->Name);
      
      // Get all flagged users
      $UserData = Gdn::UserMetaModel()->SQL->Select('*')
         ->From('UserMeta')
         ->Where('Name', 'Plugin.Minion.FingerprintCheck')
         ->Get();

      while ($UserRow = $UserData->NextRow(DATASET_TYPE_ARRAY)) {
         $UserID = $UserRow['UserID'];
         $User = Gdn::UserModel()->GetID($UserID);
         if ($User->Banned) continue;
         
         $UserFingerprint = GetValue('Fingerprint', $User, FALSE);

         // Unknown user fingerprint
         if (empty($UserFingerprint)) continue;

         $RelatedUsers = Gdn::UserModel()->GetWhere(array(
            'Fingerprint'  => $UserFingerprint
         ));

         // Check if any users matching this fingerprint are banned
         $ShouldBan = FALSE; $BanTriggerUsers = array();
         while ($RelatedUser = $RelatedUsers->NextRow(DATASET_TYPE_ARRAY)) {
            if ($RelatedUser['Banned']) {
               $RelatedUserName = $RelatedUser['Name'];
               $ShouldBan = TRUE;
               $BanTriggerUsers[$RelatedUserName] = $RelatedUser;
            }
         }
         
         $Sender->SetData('ShouldBan', $ShouldBan);

         // If the user triggered a ban
         if ($ShouldBan) {
            
            $Sender->SetData('BanMatches', array_keys($BanTriggerUsers));
            $Sender->SetData('BanUser', $User);
            
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
               $Sender->SetData('NotificationDiscussionID', $LastDiscussionID);
               
               $MinionReportText = T("{Minion Name} DETECTED BANNED ALIAS

USER BANNED
{Ban Target}");
               $MinionReportText = FormatString($MinionReportText, array(
                  'Minion Name'  => C('Plugins.Minion.Name'),
                  'Ban Target'   => $User->Name
               ));
               
               $MinionCommentID = $CommentModel->Save(array(
                  'DiscussionID' => $LastDiscussionID,
                  'Body'         => $MinionReportText,
                  'InsertUserID' => $MinionUserID
               ));

               $CommentModel->Save2($MinionCommentID, TRUE);
               $Sender->SetData('NotificationCommentID', $MinionCommentID);
            }
            
         }
         
      }
      
      // Delete all flags
      Gdn::UserMetaModel()->SQL->Delete('UserMeta', array(
         'Name' => 'Plugin.Minion.FingerprintCheck'
      ));
      
      $Sender->Render();
   }
   
}