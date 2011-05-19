<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license Proprietary
 */

// Define the plugin:
$PluginInfo['Akismet'] = array(
   'Name' => 'Akismet',
   'Description' => 'Akismet spam protection integration for Vanilla.',
   'Version' => '1.0b',
   'RequiredApplications' => array('Vanilla' => '2.0.18a1'),
   'SettingsUrl' => '/settings/akismet',
   'SettingsPermission' => 'Garden.Settings.Manage',
   'Author' => 'Todd Burry',
   'AuthorEmail' => 'todd@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.org/profile/todd'
);

class AkismetPlugin extends Gdn_Plugin {
   /// PROPERTIES ///

   /// METHODS ///

   public function CheckAkismet($RecordType, $Data) {
      $Key = C('Plugins.Akismet.Key');
      if (!$Key)
         return FALSE;

      static $Akismet;
      if (!$Akismet) $Akismet = new Akismet(Gdn::Request()->Url('/', TRUE), $Key);

      $Akismet->setCommentAuthor($Data['Username']);
      $Akismet->setCommentAuthorEmail($Data['Email']);

      $Body = ConcatSep("\n\n", GetValue('Name', $Data), GetValue('Body', $Data), GetValue('Story', $Data));
      $Akismet->setCommentContent($Body);
      $Akismet->setUserIP($Data['IPAddress']);

      $Result = $Akismet->isCommentSpam();
      return $Result;
   }

   /// EVENT HANDLERS ///

   public function Base_CheckSpam_Handler($Sender, $Args) {
      if ($Args['IsSpam'])
         return; // don't double check

      $RecordType = $Args['RecordType'];
      $Data = $Args['Data'];


      switch ($RecordType) {
         case 'User':
            $Data['Name'] = '';
            $Data['Body'] = GetValue('DiscoveryText', $Data);
            $Result = $this->CheckAkismet($RecordType, $Data);
            break;
         case 'Comment':
         case 'Discussion':
         case 'Activity':
            $Result = $this->CheckAkismet($RecordType, $Data);
            break;
      }
      $Sender->EventArguments['IsSpam'] = $Result;
   }

   public function SettingsController_Akismet_Create($Sender, $Args) {
      $Sender->SetData('Title', T('Akismet Settings'));

      $Cf = new ConfigurationModule($Sender);
      $Cf->Initialize(array('Plugins.Akismet.Key' => array('Description' => 'Enter the key you obtained from <a href="http://akismet.com">akismet.com</a>')));

      $Sender->AddSideMenu('dashboard/settings/plugins');
      $Cf->RenderAll();
   }
}