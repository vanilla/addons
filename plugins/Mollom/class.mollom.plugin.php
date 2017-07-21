<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license GNU GPL2
 */

class MollomPlugin extends Gdn_Plugin {
   /// PROPERTIES ///

   /// METHODS ///

   /**
    * @return Akismet
    */
   public static function Mollom() {
      static $mollom;
      if (!$mollom)
         $mollom = new MollomVanilla();

      return $mollom;
   }

   public function CheckMollom($recordType, $data) {
      $userID = $this->UserID();
      if (!$userID)
         return FALSE;

      $mollom = self::Mollom();

      if (!$mollom)
         return FALSE;

      $result = $mollom->checkContent([
         'checks' => ['spam'],
         'postTitle' => GetValue('Name', $data),
         'postBody' => ConcatSep("\n\n", GetValue('Body', $data),GetValue('Story', $data)),
         'authorName' => $data['Username'],
         'authorEmail' => $data['Email'],
         'authorIp' => $data['IPAddress']
      ]);
      return ($result['spamClassification'] == 'spam') ? true : false;
   }

   public function Setup() {
      $this->Structure();
   }

   public function Structure() {
      // Get a user for operations.
      $userID = Gdn::SQL()->GetWhere('User', ['Name' => 'Mollom', 'Admin' => 2])->Value('UserID');

      if (!$userID) {
         $userID = Gdn::SQL()->Insert('User', [
            'Name' => 'Mollom',
            'Password' => RandomString('20'),
            'HashMethod' => 'Random',
            'Email' => 'mollom@domain.com',
            'DateInserted' => Gdn_Format::ToDateTime(),
            'Admin' => '2'
         ]);
      }
      SaveToConfig('Plugins.Mollom.UserID', $userID);
   }

   public function UserID() {
      return C('Plugins.Mollom.UserID', NULL);
   }

   /// EVENT HANDLERS ///

   public function Base_CheckSpam_Handler($sender, $args) {
      if ($args['IsSpam'])
         return; // don't double check

      $recordType = $args['RecordType'];
      $data =& $args['Data'];

      $result = FALSE;
      switch ($recordType) {
         case 'Registration':
            $data['Name'] = '';
            $data['Body'] = GetValue('DiscoveryText', $data);
            if ($data['Body']) {
               // Only check for spam if there is discovery text.
               $result = $this->CheckMollom($recordType, $data);
               if ($result)
                  $data['Log_InsertUserID'] = $this->UserID();
            }
            break;
         case 'Comment':
         case 'Discussion':
         case 'Activity':
         case 'ActivityComment':
            $result = $this->CheckMollom($recordType, $data);
            if ($result)
               $data['Log_InsertUserID'] = $this->UserID();
            break;
         default:
            $result = FALSE;
      }
      $sender->EventArguments['IsSpam'] = $result;
   }

   public function SettingsController_Mollom_Create($sender, $args = []) {
      $sender->Permission('Garden.Settings.Manage');
      $sender->SetData('Title', T('Mollom Settings'));

      $cf = new ConfigurationModule($sender);
      $cf->Initialize([
          'Plugins.Mollom.publicKey' => [],
          'Plugins.Mollom.privateKey' => []
          ]);

      $sender->AddSideMenu('settings/plugins');
      $cf->RenderAll();
   }
}
