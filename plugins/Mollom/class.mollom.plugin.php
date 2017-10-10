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
   public static function mollom() {
      static $mollom;
      if (!$mollom)
         $mollom = new MollomVanilla();

      return $mollom;
   }

   public function checkMollom($recordType, $data) {
      $userID = $this->userID();
      if (!$userID)
         return FALSE;

      $mollom = self::mollom();

      if (!$mollom)
         return FALSE;

      $result = $mollom->checkContent([
         'checks' => ['spam'],
         'postTitle' => getValue('Name', $data),
         'postBody' => concatSep("\n\n", getValue('Body', $data),getValue('Story', $data)),
         'authorName' => $data['Username'],
         'authorEmail' => $data['Email'],
         'authorIp' => $data['IPAddress']
      ]);
      return ($result['spamClassification'] == 'spam') ? true : false;
   }

   public function setup() {
      $this->structure();
   }

   public function structure() {
      // Get a user for operations.
      $userID = Gdn::sql()->getWhere('User', ['Name' => 'Mollom', 'Admin' => 2])->value('UserID');

      if (!$userID) {
         $userID = Gdn::sql()->insert('User', [
            'Name' => 'Mollom',
            'Password' => randomString('20'),
            'HashMethod' => 'Random',
            'Email' => 'mollom@domain.com',
            'DateInserted' => Gdn_Format::toDateTime(),
            'Admin' => '2'
         ]);
      }
      saveToConfig('Plugins.Mollom.UserID', $userID);
   }

   public function userID() {
      return c('Plugins.Mollom.UserID', NULL);
   }

   /// EVENT HANDLERS ///

   public function base_checkSpam_handler($sender, $args) {
      if ($args['IsSpam'])
         return; // don't double check

      $recordType = $args['RecordType'];
      $data =& $args['Data'];

      $result = FALSE;
      switch ($recordType) {
         case 'Registration':
            $data['Name'] = '';
            $data['Body'] = getValue('DiscoveryText', $data);
            if ($data['Body']) {
               // Only check for spam if there is discovery text.
               $result = $this->checkMollom($recordType, $data);
               if ($result)
                  $data['Log_InsertUserID'] = $this->userID();
            }
            break;
         case 'Comment':
         case 'Discussion':
         case 'Activity':
         case 'ActivityComment':
            $result = $this->checkMollom($recordType, $data);
            if ($result)
               $data['Log_InsertUserID'] = $this->userID();
            break;
         default:
            $result = FALSE;
      }
      $sender->EventArguments['IsSpam'] = $result;
   }

   public function settingsController_mollom_create($sender, $args = []) {
      $sender->permission('Garden.Settings.Manage');
      $sender->setData('Title', t('Mollom Settings'));

      $cf = new ConfigurationModule($sender);
      $cf->initialize([
          'Plugins.Mollom.publicKey' => [],
          'Plugins.Mollom.privateKey' => []
          ]);

      $sender->addSideMenu('settings/plugins');
      $cf->renderAll();
   }
}
