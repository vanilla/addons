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
 *  1.0.3   Fix usage of T() (or lack of usage in some cases)
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
      $this->allowModeratorIgnore = C('Plugins.Ignore.AllowModeratorIgnore', TRUE);
      $this->FireEvent('Init');
   }

   /**
    * Add mapper methods
    *
    * @param SimpleApiPlugin $sender
    */
   public function SimpleApiPlugin_Mapper_Handler($sender) {
      switch ($sender->Mapper->Version) {
         case '1.0':
            $sender->Mapper->AddMap([
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

   public function ProfileController_AfterAddSideMenu_Handler($sender) {
      if (!Gdn::Session()->CheckPermission('Garden.SignIn.Allow'))
         return;

      $sideMenu = $sender->EventArguments['SideMenu'];
      $viewingUserID = Gdn::Session()->UserID;

      if ($sender->User->UserID == $viewingUserID) {
         $sideMenu->AddLink('Options', Sprite('SpIgnoreList').' '.T('Ignore List'), '/profile/ignore', FALSE, ['class' => 'Popup']);
      } else {
         $sideMenu->AddLink('Options', Sprite('SpIgnoreList').' '.T('Ignore List'), "/profile/ignore/{$sender->User->UserID}/".Gdn_Format::Url($sender->User->Name), 'Garden.Users.Edit', ['class' => 'Popup']);
      }
   }

   /**
    * Profile settings
    *
    * @param ProfileController $sender
    */
   public function ProfileController_Ignore_Create($sender) {
      $sender->Permission('Garden.SignIn.Allow');
      $sender->Title(T('Ignore List'));

      $this->Dispatch($sender);
   }

   public function Controller_Index($sender) {

      $args = $sender->RequestArgs;
      if (sizeof($args) < 2)
         $args = array_merge($args, [0,0]);
      elseif (sizeof($args) > 2)
         $args = array_slice($args, 0, 2);

      list($userReference, $username) = $args;

      $sender->GetUserInfo($userReference, $username);
      $sender->_SetBreadcrumbs(T('Ignore List'), '/profile/ignore');

      $userID = $viewingUserID = Gdn::Session()->UserID;
      if ($sender->User->UserID != $viewingUserID) {
         $sender->Permission('Garden.Users.Edit');
         $userID = $sender->User->UserID;
      }

      $sender->SetData('ForceEditing', ($userID == Gdn::Session()->UserID) ? FALSE : $sender->User->Name);

      if ($sender->Form->IsMyPostBack()) {
         $ignoreUsername = $sender->Form->GetFormValue('AddIgnore');
         try {
            $addIgnoreUser = Gdn::UserModel()->GetByUsername($ignoreUsername);
            $addRestricted = $this->IgnoreRestricted($addIgnoreUser->UserID);
            if (empty($ignoreUsername)) {
               throw new Exception(T("You must enter a username to ignore."));
            }
            if ($addIgnoreUser === FALSE) {
               throw new Exception(sprintf(T("User '%s' can not be found."), $ignoreUsername));
            }
            switch ($addRestricted) {

               case self::IGNORE_LIMIT:
                  throw new Exception(T("You have reached the maximum number of ignores."));

               case self::IGNORE_RESTRICTED:
                  throw new Exception(T("Your ignore privileges have been revoked."));

               case self::IGNORE_SELF:
                  throw new Exception(T("You can't put yourself on ignore."));

               case self::IGNORE_GOD:
               case self::IGNORE_FORUM_ADMIN:
               case self::IGNORE_FORUM_MOD:
                  throw new Exception(T("You can't ignore that person."));

               default:
                  $this->AddIgnore($userID, $addIgnoreUser->UserID);
                  $sender->InformMessage(
                     '<span class="InformSprite Contrast"></span>'.sprintf(T("%s is now on ignore."), $addIgnoreUser->Name),
                     'AutoDismiss HasSprite'
                  );
                  $sender->Form->SetFormValue('AddIgnore', '');
                  break;
            }
         } catch (Exception $ex) {
            $sender->Form->AddError($ex);
         }
      }

      $ignoredUsersRaw = $this->GetUserMeta($userID, 'Blocked.User.%');
      $ignoredUsersIDs = [];
      foreach ($ignoredUsersRaw as $ignoredUsersKey => $ignoredUsersIgnoreDate) {
         $ignoredUsersKeyArray = explode('.', $ignoredUsersKey);
         $ignoredUsersID = array_pop($ignoredUsersKeyArray);
         $ignoredUsersIDs[$ignoredUsersID] = $ignoredUsersIgnoreDate;
      }

      $ignoredUsers = Gdn::UserModel()->GetIDs(array_keys($ignoredUsersIDs));

      // Add ignore date to each user
      foreach ($ignoredUsers as $ignoredUsersID => &$ignoredUser)
         $ignoredUser['IgnoreDate'] = $ignoredUsersIDs[$ignoredUsersID];

      $ignoredUsers = array_values($ignoredUsers);
      $sender->SetData('IgnoreList', $ignoredUsers);

      $maxIgnores = C('Plugins.Ignore.MaxIgnores', 5);
      $sender->SetData('IgnoreLimit', ($sender->User->Admin) ? 'infinite' : $maxIgnores);

      $ignoreIsRestricted = $this->IgnoreIsRestricted($userID);
      $sender->SetData('IgnoreRestricted', $ignoreIsRestricted);

      $sender->Render('ignore','','plugins/Ignore');
   }

   /*
    * API METHODS
    */

   public function Controller_Add($sender) {
      $sender->DeliveryMethod(DELIVERY_METHOD_JSON);
      $sender->DeliveryType(DELIVERY_TYPE_DATA);

      if (!$sender->Form->AuthenticatedPostBack())
         throw new Exception(405);

      $userID = Gdn::Request()->Get('UserID');
      if ($userID != Gdn::Session()->UserID)
         $sender->Permission('Garden.Users.Edit');

      $user = Gdn::UserModel()->GetID($userID);
      if (!$user)
         throw new Exception(sprintf(T("No such user '%s'"), $userID), 404);

      $ignoreUserID = Gdn::Request()->Get('IgnoreUserID');
      $ignoreUser = Gdn::UserModel()->GetID($ignoreUserID);
      if (!$ignoreUser)
         throw new Exception(sprintf(T("No such user '%s'"), $ignoreUserID), 404);

      $addRestricted = $this->IgnoreRestricted($ignoreUserID, $userID);

      switch ($addRestricted) {
         case self::IGNORE_GOD:
            throw new Exception(T("You can't ignore that person."), 403);

         case self::IGNORE_LIMIT:
            throw new Exception(T("You have reached the maximum number of ignores."), 406);

         case self::IGNORE_RESTRICTED:
            throw new Exception(T("Your ignore privileges have been revoked."), 403);

         case self::IGNORE_SELF:
            throw new Exception(T("You can't put yourself on ignore."), 406);

         default:
            $this->AddIgnore($userID, $ignoreUserID);
            $this->SetData('Success', sprintf(T("Added %s to ignore list."), $ignoreUser->Name));
            break;
      }

      $sender->Render();
   }

   public function Controller_Remove($sender) {
      $sender->DeliveryMethod(DELIVERY_METHOD_JSON);
      $sender->DeliveryType(DELIVERY_TYPE_DATA);

      $userID = Gdn::Request()->Get('UserID');
      if ($userID != Gdn::Session()->UserID)
         $sender->Permission('Garden.Users.Edit');

      $user = Gdn::UserModel()->GetID($userID);
      if (!$user)
         throw new Exception(sprintf(T("No such user '%s'"), $userID), 404);

      $ignoreUserID = Gdn::Request()->Get('IgnoreUserID');
      $ignoreUser = Gdn::UserModel()->GetID($ignoreUserID);
      if (!$ignoreUser)
         throw new Exception(sprintf(T("No such user '%s'"), $ignoreUserID), 404);

      $this->RemoveIgnore($userID, $ignoreUserID);
      $sender->SetData('Success', sprintf(T("Removed %s from ignore list."), $ignoreUser->Name));

      $sender->Render();
   }

   public function Controller_Restrict($sender) {
      $sender->DeliveryMethod(DELIVERY_METHOD_JSON);
      $sender->DeliveryType(DELIVERY_TYPE_DATA);

      $userID = Gdn::Request()->Get('UserID');
      if ($userID != Gdn::Session()->UserID)
         $sender->Permission('Garden.Users.Edit');

      $user = Gdn::UserModel()->GetID($userID);
      if (!$user)
         throw new Exception("No such user '{$userID}'", 404);

      $restricted = strtolower(Gdn::Request()->Get('Restricted', 'no'));
      $restricted = in_array($restricted, ['yes', 'true', 'on', TRUE]) ? TRUE : NULL;
      $this->SetUserMeta($userID, 'Forbidden', $restricted);

      $sender->SetData('Success', sprintf(T($restricted ?
         "%s's ignore privileges have been disabled." :
         "%s's ignore privileges have been enabled."
      ), $user->Name));

      $sender->Render();
   }

   public function ProfileController_Render_Before($sender) {
      $sender->AddJsFile('ignore.js', 'plugins/Ignore');
   }

   public function AssetModel_StyleCss_Handler($sender) {
      $sender->AddCssFile('ignore.css', 'plugins/Ignore');
   }

   /**
    * Add "Ignore" option to profile options.
    */
   public function ProfileController_BeforeProfileOptions_Handler($sender, $args) {
      if (!$sender->EditMode && Gdn::Session()->IsValid()) {
         // Only show option if allowed
         $ignoreRestricted = $this->IgnoreRestricted($sender->User->UserID);
         if ($ignoreRestricted && $ignoreRestricted != self::IGNORE_LIMIT)
            return;

         // Add to dropdown
         $userIgnored = $this->Ignored($sender->User->UserID);
         $label = ($userIgnored) ? Sprite('SpUnignore').' '.T('Unignore') : Sprite('SpIgnore').' '.T('Ignore');
         $args['ProfileOptions'][] = ['Text' => $label,
            'Url' => "/user/ignore/toggle/{$sender->User->UserID}/".Gdn_Format::Url($sender->User->Name),
            'CssClass' => 'Popup'];
      }
   }

   public function DiscussionController_BeforeDiscussionRender_Handler($sender) {
      $sender->AddJsFile('ignore.js', 'plugins/Ignore');
   }

   public function DiscussionController_BeforeCommentDisplay_Handler($sender) {
      if ($this->IgnoreIsRestricted()) return;
      $userID = GetValue('InsertUserID',$sender->EventArguments['Object']);
      if ($this->Ignored($userID)) {
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
   public function MessagesController_BeforeAddConversation_Handler($sender) {

      $recipients = $sender->EventArguments['Recipients'];
      if (!is_array($recipients) || !sizeof($recipients)) return;

      $userID = Gdn::Session()->UserID;
      foreach ($recipients as $recipientID) {
         if ($this->Ignored($userID, $recipientID)) {
            $user = Gdn::UserModel()->GetID($recipientID, DATASET_TYPE_ARRAY);
            $sender->Form->AddError(sprintf(T("Unable to create conversation, %s is ignoring you."), $user['Name']));
         }
      }
   }

   /**
    * Add a new message to a conversation.
    *
    * @param MessageController $sender
    */
   public function MessagesController_BeforeAddMessage_Handler($sender) {

      $conversationID = $sender->EventArguments['ConversationID'];
      $conversationModel = new ConversationModel();
      $recipients = $conversationModel->GetRecipients($conversationID);
      if (!$recipients->NumRows()) return;

      $recipients = $recipients->ResultArray();
      $recipients = array_column($recipients, 'UserID');

      $userID = Gdn::Session()->UserID;
      foreach ($recipients as $recipientID => $recipient) {
         if ($this->Ignored($userID, $recipientID)) {
            $sender->Form->AddError(sprintf(T('Unable to send message, %s is ignoring you.'), $user['Name']));
         }
      }

   }

   /**
    *
    * @param UserController $sender
    */
   public function UserController_Ignore_Create($sender) {
      $sender->Permission('Garden.SignIn.Allow');

      $args = $sender->RequestArgs;
      if (sizeof($args) < 3)
         $args = array_merge($args, [0,0]);
      elseif (sizeof($args) > 2)
         $args = array_slice($args, 1, 3);

      list($userReference, $username) = $args;

      // Set user
      $user = $this->GetUserInfo($userReference, $username);
      $sender->SetData('User', $user);
      $userID = GetValue('UserID', $user);

      // Set title and mode
      $ignoreRestricted = $this->IgnoreIsRestricted();
      $userIgnored = $this->Ignored($userID);
      $mode = $userIgnored ? 'unset' : 'set';
      $actionText = T($mode == 'set' ? 'Ignore' : 'Unignore');
      $sender->Title($actionText);
      $sender->SetData('Mode', $mode);
      if ($mode == 'set') {
         // Check is Ignore is allowed.
         $ignoreRestricted = $this->IgnoreRestricted($userID);
      }
      try {
         // Check for prevented states
         switch ($ignoreRestricted) {
            case self::IGNORE_GOD:
               $sender->InformMessage('<span class="InformSprite Lightbulb"></span>'.T("You can't ignore that person."),
                  'AutoDismiss HasSprite'
               );
               break;

            case self::IGNORE_LIMIT:
               $sender->InformMessage('<span class="InformSprite Lightbulb"></span>'.T("You have reached the maximum number of ignores."),
                  'AutoDismiss HasSprite'
               );
               break;

            case self::IGNORE_RESTRICTED:
               $sender->InformMessage('<span class="InformSprite Lightbulb"></span>'.T("Your ignore privileges have been revoked."),
                  'AutoDismiss HasSprite'
               );
               break;

            case self::IGNORE_SELF:
               $sender->InformMessage('<span class="InformSprite Lightbulb"></span>'.T("You can't put yourself on ignore."),
                  'AutoDismiss HasSprite'
               );
               break;
         }

         // Get conversation intersects
         $conversations = $this->IgnoreConversations($userID);
         $sender->SetData('Conversations', $conversations);

         if ($sender->Form->AuthenticatedPostBack()) {
            switch ($mode) {
               case 'set':

                  if (!$ignoreRestricted) {
                     $sender->JsonTarget('a.IgnoreButton', T('Unignore'), 'Text');
                     $this->AddIgnore(Gdn::Session()->UserID, $userID);
                     $sender->InformMessage(
                        '<span class="InformSprite Contrast"></span>'.sprintf(T("%s is now on ignore."), $user->Name),
                        'AutoDismiss HasSprite'
                     );
                  }

                  break;

               case 'unset':

                  if (!$ignoreRestricted) {
                     $sender->JsonTarget('a.IgnoreButton', T('Ignore'), 'Text');
                     $this->RemoveIgnore(Gdn::Session()->UserID, $userID);
                     $sender->InformMessage(
                        '<span class="InformSprite Brightness"></span>'.sprintf(T("%s is no longer on ignore."), $user->Name),
                        'AutoDismiss HasSprite'
                     );
                     $sender->setRedirectTo('/profile/ignore');
                  }

                  break;

               default:
                  $sender->InformMessage(T("Unsupported operation."));
                  $sender->SetJson('Status',400);
                  break;
            }
         }

      } catch (Exception $ex) {
         $sender->InformMessage(T("Could not find that person! - ".$ex->getMessage()));
         $sender->SetJson('Status', 404);
      }

      $sender->Render('confirm', '', 'plugins/Ignore');
   }

   /**
    * @param UserController $sender
    */
   public function UserController_IgnoreList_Create($sender) {
      $sender->DeliveryType(DELIVERY_TYPE_VIEW);
      $sender->DeliveryMethod(DELIVERY_METHOD_JSON);

      if (!Gdn::Session()->CheckPermission('Garden.Users.Edit')) {
         $sender->SetJson('Status', 401);
         $sender->Render('blank', 'utility', 'dashboard');
      }

      $sender->SetJson('Status',200);

      $args = $sender->RequestArgs;
      if (sizeof($args) < 3)
         $args = array_merge($args, [0,0]);
      elseif (sizeof($args) > 2)
         $args = array_slice($args, 1, 3);

      list($userReference, $username) = $args;

      $user = $this->GetUserInfo($userReference, $username);
      $userID = GetValue('UserID', $user);

      if ($user->Admin) {
         $sender->InformMessage(sprintf(T("You can't do that to %s!", $user->Name)));
         $sender->SetJson('Status', 401);
         $sender->Render('blank', 'utility', 'dashboard');
      }

      $mode = $sender->RequestArgs[0];

      try {

         switch ($mode) {
            case 'allow':
               $this->SetUserMeta($userID, 'Forbidden', NULL);
               $sender->JsonTarget('#revoke', T('Restored'));
               $sender->JsonTarget('', '', 'Refresh');
               break;

            case 'revoke':
               $this->SetUserMeta($userID, 'Forbidden', TRUE);
               $sender->JsonTarget('#revoke', T('Revoked'));
               $sender->JsonTarget('', '', 'Refresh');
               break;

            default:
               $sender->InformMessage(T("Unsupported operation."));
               $sender->SetJson('Status',400);
               break;
         }

      } catch (Exception $ex) {
         $sender->InformMessage(T("Could not find that person! - ".$ex->getMessage()));
         $sender->SetJson('Status', 404);
      }
      $sender->Render('blank', 'utility', 'dashboard');
   }

   protected function GetUserInfo($userReference = '', $username = '', $userID = '') {
      // If a UserID was provided as a querystring parameter, use it over anything else:
		if ($userID) {
			$userReference = $userID;
			$username = 'Unknown'; // Fill this with a value so the $UserReference is assumed to be an integer/userid.
		}

      if ($userReference == '') {
         $user = Gdn::UserModel()->GetID(Gdn::Session()->UserID);
      } else if (is_numeric($userReference) && $username != '') {
         $user = Gdn::UserModel()->GetID($userReference);
      } else {
         $user = Gdn::UserModel()->GetByUsername($userReference);
      }

      if ($user === FALSE) {
         throw NotFoundException();
      } else if ($user->Deleted == 1) {
         throw NotFoundException();
      } else if (GetValue('UserID', $user) == Gdn::Session()->UserID) {
         throw NotFoundException();
      } else {
         return $user;
      }
   }

   protected function AddIgnore($forUserID, $ignoreUserID) {
      $this->SetUserMeta($forUserID, "Blocked.User.{$ignoreUserID}", date('Y-m-d H:i:s'));

      // Since the Conversation application can be turned off, check first if the ConversationModel is present.
      if (class_exists('ConversationModel')) {
         // Remove from conversations
         $conversations = $this->IgnoreConversations($ignoreUserID, $forUserID);
         Gdn::SQL()->Delete('UserConversation', [
             'UserID' => $forUserID,
             'ConversationID' => $conversations
         ]);
         $conversationModel = new ConversationModel();
         $conversationModel->countUnread($forUserID, true);
      }
   }

   protected function RemoveIgnore($forUserID, $ignoreUserID) {
      $this->SetUserMeta($forUserID, "Blocked.User.{$ignoreUserID}", NULL);
   }

   public function Ignored($userID = NULL, $sessionUserID = NULL) {
      static $blockedUsers = NULL;

      if (is_null($sessionUserID))
         $sessionUserID = Gdn::Session()->UserID;

      if (is_null($blockedUsers))
         $blockedUsers = $this->GetUserMeta($sessionUserID, 'Blocked.User.%');

      if (is_null($userID)) return $blockedUsers;

      $blockKey = $this->MakeMetaKey("Blocked.User.{$userID}");
      if (array_key_exists($blockKey, $blockedUsers))
         return TRUE;

      return FALSE;
   }

   public function IgnoreRestricted($userID, $sessionUserID = NULL) {
      if (is_null($sessionUserID))
         $sessionUserID = Gdn::Session()->UserID;

      // Noone can ignore themselves
      if ($userID == $sessionUserID) return self::IGNORE_SELF;

      // Admins can't be ignored
      $ignoreUser = Gdn::UserModel()->GetID($userID);
      if ($ignoreUser->Admin) return self::IGNORE_GOD;

      // Forum admins can;t be ignored.
      if (Gdn::UserModel()->CheckPermission($ignoreUser, 'Garden.Settings.Manage')) {
         return self::IGNORE_FORUM_ADMIN;
      }

      if (!$this->allowModeratorIgnore && Gdn::UserModel()->CheckPermission($ignoreUser, 'Garden.Moderation.Manage')) {
         return self::IGNORE_FORUM_MOD;
      }

      // Admins can ignore anyone
      if (Gdn::Session()->CheckPermission('Garden.Settings.Manage')) return FALSE;

      // Ignore has been restricted for you
      $ignoreRestricted = $this->GetUserMeta($sessionUserID, 'Plugin.Ignore.Forbidden');
      $ignoreRestricted = GetValue('Plugin.Ignore.Forbidden', $ignoreRestricted, FALSE);
      if ($ignoreRestricted) return self::IGNORE_RESTRICTED;

      $ignoredUsers = $this->GetUserMeta($sessionUserID, 'Blocked.User.%');
      $numIgnoredUsers = sizeof($ignoredUsers);
      $maxIgnores = C('Plugins.Ignore.MaxIgnores', 5);
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

   public function IgnoreConversations($ignoreUserID, $sessionUserID = NULL) {
      // Guests cant ignore
      if (!Gdn::Session()->IsValid()) return FALSE;

      if (is_null($sessionUserID))
         $sessionUserID = Gdn::Session()->UserID;

      // Noone can ignore themselves
      if ($ignoreUserID == $sessionUserID) return self::IGNORE_SELF;

      // Avoid a call to the database if the Conversation application is turned off.
      if (!class_exists('ConversationModel')) {
         return [];
      }

      // Get ignore user's conversation IDs
      $ignoreConversations = Gdn::SQL()
         ->Select('ConversationID')
         ->From('UserConversation')
         ->Where('UserID', $ignoreUserID)
         ->Where('Deleted', 0)
         ->Get()->ResultArray();
      $ignoreConversationIDs = array_column($ignoreConversations, 'ConversationID', 'ConversationID');
      unset($ignoreConversations);

      // Get session user's conversation IDs
      $sessionConversations = Gdn::SQL()
         ->Select('ConversationID')
         ->From('UserConversation')
         ->Where('UserID', $sessionUserID)
         ->Where('Deleted', 0)
         ->Get()->ResultArray();
      $sessionConversationIDs = array_column($sessionConversations, 'ConversationID', 'ConversationID');
      unset($sessionConversations);

      $commonConversations = array_intersect($ignoreConversationIDs, $sessionConversationIDs);
      $commonConversationIDs = array_values($commonConversations);
      $commonConversationIDs = array_unique($commonConversationIDs);

      return $commonConversationIDs;
   }

}
