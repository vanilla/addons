<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */

class MultipleEmailsPlugin extends Gdn_Plugin {
   /// Properties
   protected $_OldPasswordHash;

   /// Methods.
   public function setup() {
      // Allow multiple emails.
      saveToConfig('Garden.Registration.EmailUnique', FALSE);
   }

   /**
    *
    * @param UserModel $userModel
    * @param int $userID
    * @param bool $checkPasswords
    */
   protected function _SyncPasswords($userModel, $userID, $checkPasswords = TRUE) {
      $user = $userModel->getID($userID, DATASET_TYPE_ARRAY);

      if ($checkPasswords) {
         if (is_array($this->_OldPasswordHash)) {
            $userModel->SQL
               ->where('Password', $this->_OldPasswordHash[0])
               ->where('HashMethod', $this->_OldPasswordHash[1]);

            $this->_OldPasswordHash = NULL;
         } else {
            return;
         }
      }

      $userModel->SQL
         ->update('User')
         ->set('Password', $user['Password'])
         ->set('HashMethod', $user['HashMethod'])
         ->where('Email', $user['Email'])
         ->put();
   }

   /**
    * @param Gdn_Controller $sender
    * @param array $args
    */
   public function entryController_render_before($sender, $args) {
      if ($sender->RequestMethod != 'passwordreset')
         return;

      if (isset($sender->Data['User'])) {
         // Get all of the users with the same email.
         $email = $sender->data('User.Email');
         $users = Gdn::sql()->select('Name')->from('User')->where('Email', $email)->get()->resultArray();
         $names = array_column($users, 'Name');

         setValue('Name', $sender->Data['User'], implode(', ', $names));
      }
   }

   /**
    * @param UserModel $userModel
    * @param array $args
    */
   public function userModel_afterInsertUser_handler($userModel, $args) {
      $password = getValue('User/Password', $_POST);
      if (!$password)
         return;

      // See if there is a user with the same email/password.
      $users = $userModel->getWhere(['Email' => getValueR('InsertFields.Email', $args)])->resultArray();
      $hasher = new Gdn_PasswordHash();

      foreach ($users as $user) {
         if ($hasher->checkPassword($password, $user['Password'], $user['HashMethod'])) {
            $userModel->SQL->put(
               'User',
               ['Password' => $user['Password'], 'HashMethod' => $user['HashMethod']],
               ['UserID' => getValue('InsertUserID', $args)]);
            return;
         }
      }
   }

   /**
    * @param UserModel $userModel
    * @param array $args
    */
   public function userModel_afterPasswordReset_handler($userModel, $args) {
      $userID = getValue('UserID', $args);

      $this->_SyncPasswords($userModel, $userID, FALSE);
   }

   /**
    * @param UserModel $userModel
    * @param array $args
    */
   public function userModel_beforeSave_handler($userModel, $args) {
      if (isset($args['Fields']) && !isset($args['Fields']['Password']))
         return;

      // Grab the current passwordhash for comparison.
      $userID = getValueR('FormPostValues.UserID', $args);
      if ($userID) {
         $currentUser = $userModel->getID($userID, DATASET_TYPE_ARRAY);
         $this->_OldPasswordHash = [$currentUser['Password'], $currentUser['HashMethod']];
      }
   }

   /**
    * @param UserModel $userModel
    * @param array $args
    */
   public function userModel_afterSave_handler($userModel, $args) {
      if (isset($args['Fields']) && !isset($args['Fields']['Password']))
         return;

      $userID = getValue('UserID', $args);

      $this->_SyncPasswords($userModel, $userID);
   }

   /**
    * Consolidates users with the same email into one user so only one password request email is sent.
    *
    * @param UserModel $userModel
    * @param array $args
    */
   public function userModel_beforePasswordRequest_handler($userModel, $args) {
      $email = $args['Email'];
      $users =& $args['Users'];

      $names = [];

      foreach ($users as $index => $user) {
         if ($user->Email == $email) {
            if (!isset($emailUser)) {
               $emailUser = $user;
            }

            $names[] = $user->Name;

            if ($user->UserID <> $emailUser->UserID)
               unset($users[$index]);
         }
      }
      if (isset($emailUser)) {
         sort($names);
         $emailUser->Name = implode(', ', $names);
      }

      $this->EventArguments['Users'] = $users;
      $this->EventArguments['Email'] = $email;
      $this->fireEvent('PasswordRequestBefore');
   }
}
