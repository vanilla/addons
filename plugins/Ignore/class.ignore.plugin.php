<?php if (!defined('APPLICATION')) exit();

/**
 * Ignore Plugin
 *
 * This plugin allows users to maintain an ignore list that filters out other
 * users' comments.
 *
 * Changes:
 *  1.0     Initial release
 *  1.0.1   Fix guest mode bug
 *  1.0.2   Change Plugin.Ignore.MaxIgnores to Plugins.Ignore.MaxIgnores
 *  1.0.3   Fix usage of t() (or lack of usage in some cases)
 *  1.1     Add SimpleAPI hooks
 *  1.2     Hook into conversations application and block ignored PMs
 *  1.3     Mobile Friendly and improved CSS
 *  1.3.2   Enable revoke JS
 *  1.4     Change revoke to use hijack.  Prevent forum admins from being ignored
 *          Added optional setting to prevent moderators from being ignored
 *          Added check to username when adding
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2003 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Addons
 */

class IgnorePlugin extends Gdn_Plugin {

   const IGNORE_SELF = 'self';
   const IGNORE_GOD = 'god';
   const IGNORE_LIMIT = 'limit';
   const IGNORE_RESTRICTED = 'restricted';
   const IGNORE_FORUM_ADMIN = 'forumadmin';
   const IGNORE_FORUM_MOD = 'forummods';

   public $allowModeratorIgnore;

   public function __construct() {
      parent::__construct();
      $this->allowModeratorIgnore = c('Plugins.Ignore.AllowModeratorIgnore', TRUE);
      $this->fireEvent('Init');
   }

   /**
    * Add mapper methods
    *
    * @param SimpleApiPlugin $sender
    */
   public function simpleApiPlugin_mapper_handler($sender) {
      switch ($sender->Mapper->Version) {
         case '1.0':
            $sender->Mapper->addMap([
               'ignore/list'           => 'profile/ignore',
               'ignore/add'            => 'profile/ignore/add',
               'ignore/remove'         => 'profile/ignore/remove',
               'ignore/restrict'       => 'profile/ignore/restrict'
            ], NULL, [
               'ignore/list'           => ['IgnoreList', 'IgnoreLimit', 'IgnoreRestricted'],
               'ignore/add'            => ['Success'],
               'ignore/remove'         => ['Success'],
               'ignore/restrict'       => ['Success']
            ]);
            break;
      }
   }

   public function profileController_afterAddSideMenu_handler($sender) {
      if (!Gdn::session()->checkPermission('Garden.SignIn.Allow'))
         return;

      $sideMenu = $sender->EventArguments['SideMenu'];
      $viewingUserID = Gdn::session()->UserID;

      if ($sender->User->UserID == $viewingUserID) {
         $sideMenu->addLink('Options', sprite('SpIgnoreList').' '.t('Ignore List'), '/profile/ignore', FALSE, ['class' => 'Popup']);
      } else {
         $sideMenu->addLink('Options', sprite('SpIgnoreList').' '.t('Ignore List'), "/profile/ignore/{$sender->User->UserID}/".Gdn_Format::url($sender->User->Name), 'Garden.Users.Edit', ['class' => 'Popup']);
      }
   }

   /**
    * Profile settings
    *
    * @param ProfileController $sender
    */
   public function profileController_ignore_create($sender) {
      $sender->permission('Garden.SignIn.Allow');
      $sender->title(t('Ignore List'));

      $this->dispatch($sender);
   }

   public function controller_Index($sender) {

      $args = $sender->RequestArgs;
      if (sizeof($args) < 2)
         $args = array_merge($args, [0,0]);
      elseif (sizeof($args) > 2)
         $args = array_slice($args, 0, 2);

      list($userReference, $username) = $args;

      $sender->getUserInfo($userReference, $username);
      $sender->_SetBreadcrumbs(t('Ignore List'), '/profile/ignore');

      $userID = $viewingUserID = Gdn::session()->UserID;
      if ($sender->User->UserID != $viewingUserID) {
         $sender->permission('Garden.Users.Edit');
         $userID = $sender->User->UserID;
      }

      $sender->setData('ForceEditing', ($userID == Gdn::session()->UserID) ? FALSE : $sender->User->Name);

      if ($sender->Form->isMyPostBack()) {
         $ignoreUsername = $sender->Form->getFormValue('AddIgnore');
         try {
            $addIgnoreUser = Gdn::userModel()->getByUsername($ignoreUsername);
            $addRestricted = $this->ignoreRestricted($addIgnoreUser->UserID);
            if (empty($ignoreUsername)) {
               throw new Exception(t("You must enter a username to ignore."));
            }
            if ($addIgnoreUser === FALSE) {
               throw new Exception(sprintf(t("User '%s' can not be found."), $ignoreUsername));
            }
            switch ($addRestricted) {

               case self::IGNORE_LIMIT:
                  throw new Exception(t("You have reached the maximum number of ignores."));

               case self::IGNORE_RESTRICTED:
                  throw new Exception(t("Your ignore privileges have been revoked."));

               case self::IGNORE_SELF:
                  throw new Exception(t("You can't put yourself on ignore."));

               case self::IGNORE_GOD:
               case self::IGNORE_FORUM_ADMIN:
               case self::IGNORE_FORUM_MOD:
                  throw new Exception(t("You can't ignore that person."));

               default:
                  $this->addIgnore($userID, $addIgnoreUser->UserID);
                  $sender->informMessage(
                     '<span class="InformSprite Contrast"></span>'.sprintf(t("%s is now on ignore."), $addIgnoreUser->Name),
                     'AutoDismiss HasSprite'
                  );
                  $sender->Form->setFormValue('AddIgnore', '');
                  break;
            }
         } catch (Exception $ex) {
            $sender->Form->addError($ex);
         }
      }

      $ignoredUsersRaw = $this->getUserMeta($userID, 'Blocked.User.%');
      $ignoredUsersIDs = [];
      foreach ($ignoredUsersRaw as $ignoredUsersKey => $ignoredUsersIgnoreDate) {
         $ignoredUsersKeyArray = explode('.', $ignoredUsersKey);
         $ignoredUsersID = array_pop($ignoredUsersKeyArray);
         $ignoredUsersIDs[$ignoredUsersID] = $ignoredUsersIgnoreDate;
      }

      $ignoredUsers = Gdn::userModel()->getIDs(array_keys($ignoredUsersIDs));

      // Add ignore date to each user
      foreach ($ignoredUsers as $ignoredUsersID => &$ignoredUser)
         $ignoredUser['IgnoreDate'] = $ignoredUsersIDs[$ignoredUsersID];

      $ignoredUsers = array_values($ignoredUsers);
      $sender->setData('IgnoreList', $ignoredUsers);

      $maxIgnores = c('Plugins.Ignore.MaxIgnores', 5);
      $sender->setData('IgnoreLimit', ($sender->User->Admin) ? 'infinite' : $maxIgnores);

      $ignoreIsRestricted = $this->ignoreIsRestricted($userID);
      $sender->setData('IgnoreRestricted', $ignoreIsRestricted);

      $sender->render('ignore','','plugins/Ignore');
   }

   /*
    * API METHODS
    */

   public function controller_Add($sender) {
      $sender->deliveryMethod(DELIVERY_METHOD_JSON);
      $sender->deliveryType(DELIVERY_TYPE_DATA);

      if (!$sender->Form->authenticatedPostBack())
         throw new Exception(405);

      $userID = Gdn::request()->get('UserID');
      if ($userID != Gdn::session()->UserID)
         $sender->permission('Garden.Users.Edit');

      $user = Gdn::userModel()->getID($userID);
      if (!$user)
         throw new Exception(sprintf(t("No such user '%s'"), $userID), 404);

      $ignoreUserID = Gdn::request()->get('IgnoreUserID');
      $ignoreUser = Gdn::userModel()->getID($ignoreUserID);
      if (!$ignoreUser)
         throw new Exception(sprintf(t("No such user '%s'"), $ignoreUserID), 404);

      $addRestricted = $this->ignoreRestricted($ignoreUserID, $userID);

      switch ($addRestricted) {
         case self::IGNORE_GOD:
            throw new Exception(t("You can't ignore that person."), 403);

         case self::IGNORE_LIMIT:
            throw new Exception(t("You have reached the maximum number of ignores."), 406);

         case self::IGNORE_RESTRICTED:
            throw new Exception(t("Your ignore privileges have been revoked."), 403);

         case self::IGNORE_SELF:
            throw new Exception(t("You can't put yourself on ignore."), 406);

         default:
            $this->addIgnore($userID, $ignoreUserID);
            $this->setData('Success', sprintf(t("Added %s to ignore list."), $ignoreUser->Name));
            break;
      }

      $sender->render();
   }

   public function controller_Remove($sender) {
      $sender->deliveryMethod(DELIVERY_METHOD_JSON);
      $sender->deliveryType(DELIVERY_TYPE_DATA);

      $userID = Gdn::request()->get('UserID');
      if ($userID != Gdn::session()->UserID)
         $sender->permission('Garden.Users.Edit');

      $user = Gdn::userModel()->getID($userID);
      if (!$user)
         throw new Exception(sprintf(t("No such user '%s'"), $userID), 404);

      $ignoreUserID = Gdn::request()->get('IgnoreUserID');
      $ignoreUser = Gdn::userModel()->getID($ignoreUserID);
      if (!$ignoreUser)
         throw new Exception(sprintf(t("No such user '%s'"), $ignoreUserID), 404);

      $this->removeIgnore($userID, $ignoreUserID);
      $sender->setData('Success', sprintf(t("Removed %s from ignore list."), $ignoreUser->Name));

      $sender->render();
   }

   public function controller_Restrict($sender) {
      $sender->deliveryMethod(DELIVERY_METHOD_JSON);
      $sender->deliveryType(DELIVERY_TYPE_DATA);

      $userID = Gdn::request()->get('UserID');
      if ($userID != Gdn::session()->UserID)
         $sender->permission('Garden.Users.Edit');

      $user = Gdn::userModel()->getID($userID);
      if (!$user)
         throw new Exception("No such user '{$userID}'", 404);

      $restricted = strtolower(Gdn::request()->get('Restricted', 'no'));
      $restricted = in_array($restricted, ['yes', 'true', 'on', TRUE]) ? TRUE : NULL;
      $this->setUserMeta($userID, 'Forbidden', $restricted);

      $sender->setData('Success', sprintf(t($restricted ?
         "%s's ignore privileges have been disabled." :
         "%s's ignore privileges have been enabled."
      ), $user->Name));

      $sender->render();
   }

   public function profileController_render_before($sender) {
      $sender->addJsFile('ignore.js', 'plugins/Ignore');
   }

   public function assetModel_styleCss_handler($sender) {
      $sender->addCssFile('ignore.css', 'plugins/Ignore');
   }

   /**
    * Add "Ignore" option to profile options.
    */
   public function profileController_beforeProfileOptions_handler($sender, $args) {
      if (!$sender->EditMode && Gdn::session()->isValid()) {
         // Only show option if allowed
         $ignoreRestricted = $this->ignoreRestricted($sender->User->UserID);
         if ($ignoreRestricted && $ignoreRestricted != self::IGNORE_LIMIT)
            return;

         // Add to dropdown
         $userIgnored = $this->ignored($sender->User->UserID);
         $label = ($userIgnored) ? sprite('SpUnignore').' '.t('Unignore') : sprite('SpIgnore').' '.t('Ignore');
         $args['ProfileOptions'][] = ['Text' => $label,
            'Url' => "/user/ignore/toggle/{$sender->User->UserID}/".Gdn_Format::url($sender->User->Name),
            'CssClass' => 'Popup'];
      }
   }

   public function discussionController_beforeDiscussionRender_handler($sender) {
      $sender->addJsFile('ignore.js', 'plugins/Ignore');
   }

   public function discussionController_beforeCommentDisplay_handler($sender) {
      if ($this->ignoreIsRestricted()) return;
      $userID = getValue('InsertUserID',$sender->EventArguments['Object']);
      if ($this->ignored($userID)) {
         $classes = explode(" ",$sender->EventArguments['CssClass']);
         $classes[] = 'Ignored';
         $classes = array_fill_keys($classes, NULL);
         $classes = implode(' ',array_keys($classes));
         $sender->EventArguments['CssClass'] = $classes;
      }
   }

   /**
    *
    *
    * @param MessageController $sender
    */
   public function messagesController_beforeAddConversation_handler($sender) {

      $recipients = $sender->EventArguments['Recipients'];
      if (!is_array($recipients) || !sizeof($recipients)) return;

      $userID = Gdn::session()->UserID;
      foreach ($recipients as $recipientID) {
         if ($this->ignored($userID, $recipientID)) {
            $user = Gdn::userModel()->getID($recipientID, DATASET_TYPE_ARRAY);
            $sender->Form->addError(sprintf(t("Unable to create conversation, %s is ignoring you."), htmlspecialchars($user['Name'])));
         }
      }
   }

   /**
    * Add a new message to a conversation.
    *
    * @param MessageController $sender
    */
   public function messagesController_beforeAddMessage_handler($sender) {

      $conversationID = $sender->EventArguments['ConversationID'];
      $conversationModel = new ConversationModel();
      $recipients = $conversationModel->getRecipients($conversationID);
      if (!$recipients->numRows()) return;

      $recipients = $recipients->resultArray();
      $recipients = array_column($recipients, 'UserID');

      $userID = Gdn::session()->UserID;
      foreach ($recipients as $recipientID => $recipient) {
         if ($this->ignored($userID, $recipientID)) {
            $sender->Form->addError(sprintf(t('Unable to send message, %s is ignoring you.'), htmlspecialchars($userID['Name'])));
         }
      }

   }

   /**
    *
    * @param UserController $sender
    */
   public function userController_ignore_create($sender) {
      $sender->permission('Garden.SignIn.Allow');

      $args = $sender->RequestArgs;
      if (sizeof($args) < 3)
         $args = array_merge($args, [0,0]);
      elseif (sizeof($args) > 2)
         $args = array_slice($args, 1, 3);

      list($userReference, $username) = $args;

      // Set user
      $user = $this->getUserInfo($userReference, $username);
      $sender->setData('User', $user);
      $userID = getValue('UserID', $user);

      // Set title and mode
      $ignoreRestricted = $this->ignoreIsRestricted();
      $userIgnored = $this->ignored($userID);
      $mode = $userIgnored ? 'unset' : 'set';
      $actionText = t($mode == 'set' ? 'Ignore' : 'Unignore');
      $sender->title($actionText);
      $sender->setData('Mode', $mode);
      if ($mode == 'set') {
         // Check is Ignore is allowed.
         $ignoreRestricted = $this->ignoreRestricted($userID);
      }
      try {
         // Check for prevented states
         switch ($ignoreRestricted) {
            case self::IGNORE_GOD:
               $sender->informMessage('<span class="InformSprite Lightbulb"></span>'.t("You can't ignore that person."),
                  'AutoDismiss HasSprite'
               );
               break;

            case self::IGNORE_LIMIT:
               $sender->informMessage('<span class="InformSprite Lightbulb"></span>'.t("You have reached the maximum number of ignores."),
                  'AutoDismiss HasSprite'
               );
               break;

            case self::IGNORE_RESTRICTED:
               $sender->informMessage('<span class="InformSprite Lightbulb"></span>'.t("Your ignore privileges have been revoked."),
                  'AutoDismiss HasSprite'
               );
               break;

            case self::IGNORE_SELF:
               $sender->informMessage('<span class="InformSprite Lightbulb"></span>'.t("You can't put yourself on ignore."),
                  'AutoDismiss HasSprite'
               );
               break;
         }

         // Get conversation intersects
         $conversations = $this->ignoreConversations($userID);
         $sender->setData('Conversations', $conversations);

         if ($sender->Form->authenticatedPostBack()) {
            switch ($mode) {
               case 'set':

                  if (!$ignoreRestricted) {
                     $sender->jsonTarget('a.IgnoreButton', t('Unignore'), 'Text');
                     $this->addIgnore(Gdn::session()->UserID, $userID);
                     $sender->informMessage(
                        '<span class="InformSprite Contrast"></span>'.sprintf(t("%s is now on ignore."), $user->Name),
                        'AutoDismiss HasSprite'
                     );
                  }

                  break;

               case 'unset':

                  if (!$ignoreRestricted) {
                     $sender->jsonTarget('a.IgnoreButton', t('Ignore'), 'Text');
                     $this->removeIgnore(Gdn::session()->UserID, $userID);
                     $sender->informMessage(
                        '<span class="InformSprite Brightness"></span>'.sprintf(t("%s is no longer on ignore."), $user->Name),
                        'AutoDismiss HasSprite'
                     );
                     $sender->setRedirectTo('/profile/ignore');
                  }

                  break;

               default:
                  $sender->informMessage(t("Unsupported operation."));
                  $sender->setJson('Status',400);
                  break;
            }
         }

      } catch (Exception $ex) {
         $sender->informMessage(t("Could not find that person! - ".$ex->getMessage()));
         $sender->setJson('Status', 404);
      }

      $sender->render('confirm', '', 'plugins/Ignore');
   }

   /**
    * @param UserController $sender
    */
   public function userController_ignoreList_create($sender) {
      $sender->deliveryType(DELIVERY_TYPE_VIEW);
      $sender->deliveryMethod(DELIVERY_METHOD_JSON);

      if (!Gdn::session()->checkPermission('Garden.Users.Edit')) {
         $sender->setJson('Status', 401);
         $sender->render('blank', 'utility', 'dashboard');
      }

      $sender->setJson('Status',200);

      $args = $sender->RequestArgs;
      if (sizeof($args) < 3)
         $args = array_merge($args, [0,0]);
      elseif (sizeof($args) > 2)
         $args = array_slice($args, 1, 3);

      list($userReference, $username) = $args;

      $user = $this->getUserInfo($userReference, $username);
      $userID = getValue('UserID', $user);

      if ($user->Admin) {
         $sender->informMessage(sprintf(t("You can't do that to %s!", $user->Name)));
         $sender->setJson('Status', 401);
         $sender->render('blank', 'utility', 'dashboard');
      }

      $mode = $sender->RequestArgs[0];

      try {

         switch ($mode) {
            case 'allow':
               $this->setUserMeta($userID, 'Forbidden', NULL);
               $sender->jsonTarget('#revoke', t('Restored'));
               $sender->jsonTarget('', '', 'Refresh');
               break;

            case 'revoke':
               $this->setUserMeta($userID, 'Forbidden', TRUE);
               $sender->jsonTarget('#revoke', t('Revoked'));
               $sender->jsonTarget('', '', 'Refresh');
               break;

            default:
               $sender->informMessage(t("Unsupported operation."));
               $sender->setJson('Status',400);
               break;
         }

      } catch (Exception $ex) {
         $sender->informMessage(t("Could not find that person! - ".$ex->getMessage()));
         $sender->setJson('Status', 404);
      }
      $sender->render('blank', 'utility', 'dashboard');
   }

   protected function getUserInfo($userReference = '', $username = '', $userID = '') {
      // If a UserID was provided as a querystring parameter, use it over anything else:
		if ($userID) {
			$userReference = $userID;
			$username = 'Unknown'; // Fill this with a value so the $UserReference is assumed to be an integer/userid.
		}

      if ($userReference == '') {
         $user = Gdn::userModel()->getID(Gdn::session()->UserID);
      } else if (is_numeric($userReference) && $username != '') {
         $user = Gdn::userModel()->getID($userReference);
      } else {
         $user = Gdn::userModel()->getByUsername($userReference);
      }

      if ($user === FALSE) {
         throw notFoundException();
      } else if ($user->Deleted == 1) {
         throw notFoundException();
      } else if (getValue('UserID', $user) == Gdn::session()->UserID) {
         throw notFoundException();
      } else {
         return $user;
      }
   }

   protected function addIgnore($forUserID, $ignoreUserID) {
      $this->setUserMeta($forUserID, "Blocked.User.{$ignoreUserID}", date('Y-m-d H:i:s'));

      // Since the Conversation application can be turned off, check first if the ConversationModel is present.
      if (class_exists('ConversationModel')) {
         // Remove from conversations
         $conversations = $this->ignoreConversations($ignoreUserID, $forUserID);
         Gdn::sql()->delete('UserConversation', [
             'UserID' => $forUserID,
             'ConversationID' => $conversations
         ]);
         $conversationModel = new ConversationModel();
         $conversationModel->countUnread($forUserID, true);
      }
   }

   protected function removeIgnore($forUserID, $ignoreUserID) {
      $this->setUserMeta($forUserID, "Blocked.User.{$ignoreUserID}", NULL);
   }

   public function ignored($userID = NULL, $sessionUserID = NULL) {
      static $blockedUsers = NULL;

      if (is_null($sessionUserID))
         $sessionUserID = Gdn::session()->UserID;

      if (is_null($blockedUsers))
         $blockedUsers = $this->getUserMeta($sessionUserID, 'Blocked.User.%');

      if (is_null($userID)) return $blockedUsers;

      $blockKey = $this->makeMetaKey("Blocked.User.{$userID}");
      if (array_key_exists($blockKey, $blockedUsers))
         return TRUE;

      return FALSE;
   }

   public function ignoreRestricted($userID, $sessionUserID = NULL) {
      if (is_null($sessionUserID))
         $sessionUserID = Gdn::session()->UserID;

      // Noone can ignore themselves
      if ($userID == $sessionUserID) return self::IGNORE_SELF;

      // Admins can't be ignored
      $ignoreUser = Gdn::userModel()->getID($userID);
      if ($ignoreUser->Admin) return self::IGNORE_GOD;

      // Forum admins can;t be ignored.
      if (Gdn::userModel()->checkPermission($ignoreUser, 'Garden.Settings.Manage')) {
         return self::IGNORE_FORUM_ADMIN;
      }

      if (!$this->allowModeratorIgnore && Gdn::userModel()->checkPermission($ignoreUser, 'Garden.Moderation.Manage')) {
         return self::IGNORE_FORUM_MOD;
      }

      // Admins can ignore anyone
      if (Gdn::session()->checkPermission('Garden.Settings.Manage')) return FALSE;

      // Ignore has been restricted for you
      $ignoreRestricted = $this->getUserMeta($sessionUserID, 'Plugin.Ignore.Forbidden');
      $ignoreRestricted = getValue('Plugin.Ignore.Forbidden', $ignoreRestricted, FALSE);
      if ($ignoreRestricted) return self::IGNORE_RESTRICTED;

      $ignoredUsers = $this->getUserMeta($sessionUserID, 'Blocked.User.%');
      $numIgnoredUsers = sizeof($ignoredUsers);
      $maxIgnores = c('Plugins.Ignore.MaxIgnores', 5);
      if ($numIgnoredUsers >= $maxIgnores) return self::IGNORE_LIMIT;

      return FALSE;
   }

   /**
    * Is this user forbidden from using ignore?
    *
    * @param int|null $userID ID for the user to verify ignore permissions for. Current user if none specified.
    * @return bool|string IgnorePlugin::IGNORE_RESTRICTED if user cannot ignore, otherwise false.
    */
   public function ignoreIsRestricted($userID = NULL) {
      // Guests cant ignore
      if (!Gdn::session()->isValid()) {
         return self::IGNORE_RESTRICTED;
      }

      if (is_null($userID)) {
         $userID = Gdn::session()->UserID;
      }

      if (is_null($userID)) {
         return self::IGNORE_RESTRICTED;
      }

      $isRestricted = $this->getUserMeta($userID, 'Plugin.Ignore.Forbidden');
      $isRestricted = val('Plugin.Ignore.Forbidden', $isRestricted, FALSE);
      if ($isRestricted) {
         return self::IGNORE_RESTRICTED;
      }

      return FALSE;
   }

   public function ignoreConversations($ignoreUserID, $sessionUserID = NULL) {
      // Guests cant ignore
      if (!Gdn::session()->isValid()) return FALSE;

      if (is_null($sessionUserID))
         $sessionUserID = Gdn::session()->UserID;

      // Noone can ignore themselves
      if ($ignoreUserID == $sessionUserID) return self::IGNORE_SELF;

      // Avoid a call to the database if the Conversation application is turned off.
      if (!class_exists('ConversationModel')) {
         return [];
      }

      // Get ignore user's conversation IDs
      $ignoreConversations = Gdn::sql()
         ->select('ConversationID')
         ->from('UserConversation')
         ->where('UserID', $ignoreUserID)
         ->where('Deleted', 0)
         ->get()->resultArray();
      $ignoreConversationIDs = array_column($ignoreConversations, 'ConversationID', 'ConversationID');
      unset($ignoreConversations);

      // Get session user's conversation IDs
      $sessionConversations = Gdn::sql()
         ->select('ConversationID')
         ->from('UserConversation')
         ->where('UserID', $sessionUserID)
         ->where('Deleted', 0)
         ->get()->resultArray();
      $sessionConversationIDs = array_column($sessionConversations, 'ConversationID', 'ConversationID');
      unset($sessionConversations);

      $commonConversations = array_intersect($ignoreConversationIDs, $sessionConversationIDs);
      $commonConversationIDs = array_values($commonConversations);
      $commonConversationIDs = array_unique($commonConversationIDs);

      return $commonConversationIDs;
   }

}
