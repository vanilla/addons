<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license Proprietary
 */

// Define the plugin:
$PluginInfo['Akismet'] = array(
   'Name' => 'Akismet',
   'Description' => 'Akismet spam protection integration for Vanilla.',
   'Version' => '1.0.3',
   'RequiredApplications' => array('Vanilla' => '2.0.18'),
   'SettingsUrl' => '/settings/akismet',
   'SettingsPermission' => 'Garden.Settings.Manage',
   'Author' => 'Todd Burry',
   'AuthorEmail' => 'todd@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.org/profile/todd'
);

class AkismetPlugin extends Gdn_Plugin {
   /// PROPERTIES ///

   /// METHODS ///

   /**
    * @return Akismet
    */
   public static function Akismet() {
      static $Akismet;
      if (!$Akismet) {
         $Key = C('Plugins.Akismet.Key', C('Plugins.Akismet.MasterKey'));

         if (!$Key)
            return NULL;

         $Akismet = new Akismet(Gdn::Request()->Url('/', TRUE), $Key);

         $Server = C('Plugins.Akismet.Server');
         if ($Server) {
            $Akismet->setAkismetServer($Server);
         }
      }

      return $Akismet;
   }

   public function CheckAkismet($RecordType, $Data) {
      $UserID = $this->UserID();

      if (!$UserID)
         return FALSE;

      $Akismet = self::Akismet();

      if (!$Akismet)
         return FALSE;

      $Akismet->setCommentAuthor($Data['Username']);
      $Akismet->setCommentAuthorEmail($Data['Email']);

      if (!empty($Data['CommentType'])) {
         $Akismet->setCommentType($Data['CommentType']);
      }

      $Locale = Gdn::Locale()->Current();
      $LocaleParts = preg_split('`(_|-)`', $Locale, 2);
      if (count($LocaleParts) == 2) {
         $Akismet->setBlogLang($LocaleParts[0]);
      } else {
         $Akismet->setBlogLang($Locale);
      }

      $Akismet->setBlogCharset(C('Garden.Charset', 'utf-8'));

      $Body = ConcatSep("\n\n", GetValue('Name', $Data), GetValue('Body', $Data), GetValue('Story', $Data));
      $Akismet->setCommentContent($Body);
      $Akismet->setUserIP($Data['IPAddress']);

      $Result = $Akismet->isCommentSpam();

      return $Result;
   }

   public function Setup() {
      $this->Structure();
   }

   public function Structure() {
      // Get a user for operations.
      $UserID = Gdn::SQL()->GetWhere('User', array('Name' => 'Akismet', 'Admin' => 2))->Value('UserID');

      if (!$UserID) {
         $UserID = Gdn::SQL()->Insert('User', array(
            'Name' => 'Akismet',
            'Password' => RandomString('20'),
            'HashMethod' => 'Random',
            'Email' => 'akismet@domain.com',
            'DateInserted' => Gdn_Format::ToDateTime(),
            'Admin' => '2'
         ));
      }
      SaveToConfig('Plugins.Akismet.UserID', $UserID);
   }

   public function UserID() {
      return C('Plugins.Akismet.UserID', NULL);
   }

   /// EVENT HANDLERS ///

   public function Base_CheckSpam_Handler($Sender, $Args) {
      if ($Args['IsSpam'])
         return; // don't double check

      $RecordType = $Args['RecordType'];
      $Data =& $Args['Data'];

      $Result = FALSE;
      switch ($RecordType) {
         case 'Registration':
            $Data['Name'] = '';
            $Data['Body'] = GetValue('DiscoveryText', $Data);
            if ($Data['Body']) {
               // Only check for spam if there is discovery text.
               $Result = $this->CheckAkismet($RecordType, $Data);
               if ($Result)
                  $Data['Log_InsertUserID'] = $this->UserID();
            }
            break;
         case 'Comment':
         case 'Discussion':
            $Data['CommentType'] = 'forum-post';
         case 'Activity':
         case 'ActivityComment':
            $Result = $this->CheckAkismet($RecordType, $Data);
            if ($Result)
               $Data['Log_InsertUserID'] = $this->UserID();
            break;
         default:
            $Result = FALSE;
      }
      $Sender->EventArguments['IsSpam'] = $Result;
   }

   public function SettingsController_Akismet_Create($Sender, $Args = array()) {
      // Allow for master hosted key
      $KeyDesc = 'Enter the key you obtained from <a href="http://akismet.com">akismet.com</a>';
      if (C('Plugins.Akismet.MasterKey'))
         $KeyDesc = 'No key is required! You may optionally use your own.';

      $Sender->Permission('Garden.Settings.Manage');
      $Sender->SetData('Title', T('Akismet Settings'));

      $Cf = new ConfigurationModule($Sender);
      $Cf->Initialize(array(
          'Plugins.Akismet.Key' => array('Description' => $KeyDesc),
          'Plugins.Akismet.Server' => array('Description' => 'You can use either Akismet or TypePad antispam.', 'Control' => 'DropDown',
              'Items' => array('' => 'Aksimet', 'api.antispam.typepad.com' => 'TypePad', 'DefaultValue' => ''))
          ));

      $Sender->AddSideMenu('dashboard/settings/plugins');
      $Cf->RenderAll();
   }
}
