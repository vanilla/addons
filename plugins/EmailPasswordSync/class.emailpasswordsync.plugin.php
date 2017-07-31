<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */

class MultipleEmailsPlugin extends Gdn_Plugin {
   /// Properties
   protected $_OldPasswordHash;

   /// Methods.
   public function Setup() {
      // Allow multiple emails.
      SaveToConfig('Garden.Registration.EmailUnique', FALSE);
   }

   /**
    *
    * @param UserModel $userModel
    * @param int $userID
    * @param bool $checkPasswords
    */
   protected function _SyncPasswords($userModel, $userID, $checkPasswords = TRUE) {
      $user = $userModel->GetID($userID, DATASET_TYPE_ARRAY);

      if ($checkPasswords) {
         if (is_array($this->_OldPasswordHash)) {
            $userModel->SQL
               ->Where('Password', $this->_OldPasswordHash[0])
               ->Where('HashMethod', $this->_OldPasswordHash[1]);

            $this->_OldPasswordHash = NULL;
         } else {
            return;
         }
      }

      $userModel->SQL
         ->Update('User')
         ->Set('Password', $user['Password'])
         ->Set('HashMethod', $user['HashMethod'])
         ->Where('Email', $user['Email'])
         ->Put();
   }

   /**
    * @param Gdn_Controller $sender
    * @param array $args
    */
   public function EntryController_Render_Before($sender, $args) {
      if ($sender->RequestMethod != 'passwordreset')
         return;

      if (isset($sender->Data['User'])) {
         // Get all of the users with the same email.
         $email = $sender->Data('User.Email');
         $users = Gdn::SQL()->Select('Name')->From('User')->Where('Email', $email)->Get()->ResultArray();
         $names = array_column($users, 'Name');

         SetValue('Name', $sender->Data['User'], implode(', ', $names));
      }
   }

   /**
    * @param UserModel $userModel
    * @param array $args
    */
   public function UserModel_AfterInsertUser_Handler($userModel, $args) {
      $password = GetValue('User/Password', $_POST);
      if (!$password)
         return;

      // See if there is a user with the same email/password.
      $users = $userModel->GetWhere(['Email' => GetValueR('InsertFields.Email', $args)])->ResultArray();
      $hasher = new Gdn_PasswordHash();

      foreach ($users as $user) {
         if ($hasher->CheckPassword($password, $user['Password'], $user['HashMethod'])) {
            $userModel->SQL->Put(
               'User',
               ['Password' => $user['Password'], 'HashMethod' => $user['HashMethod']],
               ['UserID' => GetValue('InsertUserID', $args)]);
            return;
         }
      }
   }

   /**
    * @param UserModel $userModel
    * @param array $args
    */
   public function UserModel_AfterPasswordReset_Handler($userModel, $args) {
      $userID = GetValue('UserID', $args);

      $this->_SyncPasswords($userModel, $userID, FALSE);
   }

   /**
    * @param UserModel $userModel
    * @param array $args
    */
   public function UserModel_BeforeSave_Handler($userModel, $args) {
      if (isset($args['Fields']) && !isset($args['Fields']['Password']))
         return;

      // Grab the current passwordhash for comparison.
      $userID = GetValueR('FormPostValues.UserID', $args);
      if ($userID) {
         $currentUser = $userModel->GetID($userID, DATASET_TYPE_ARRAY);
         $this->_OldPasswordHash = [$currentUser['Password'], $currentUser['HashMethod']];
      }
   }

   /**
    * @param UserModel $userModel
    * @param array $args
    */
   public function UserModel_AfterSave_Handler($userModel, $args) {
      if (isset($args['Fields']) && !isset($args['Fields']['Password']))
         return;

      $userID = GetValue('UserID', $args);

      $this->_SyncPasswords($userModel, $userID);
   }

   /**
    * Consolidates users with the same email into one user so only one password request email is sent.
    *
    * @param UserModel $userModel
    * @param array $args
    */
   public function UserModel_BeforePasswordRequest_Handler($userModel, $args) {
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
      $this->FireEvent('PasswordRequestBefore');
   }
}
