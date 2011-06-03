<?php

if (!defined('APPLICATION'))
   exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */
// Define the plugin:
$PluginInfo['StopForumSpam'] = array(
    'Name' => 'Stop Forum Spam',
    'Description' => "Integrates the spammer blacklist from stopforumspam.com",
    'Version' => '1.0a',
    'RequiredApplications' => array('Vanilla' => '2.0.18b1'),
    'Author' => 'Todd Burry',
    'AuthorEmail' => 'todd@vanillaforums.com',
    'AuthorUrl' => 'http://www.vanillaforums.org/profile/todd'
);

class StopForumSpamPlugin extends Gdn_Plugin {

   /// Properties ///
   /// Methods ///

   public static function Check($Data) {
      // Make the request.
      $Get = array();


     
      if (isset($Data['IPAddress'])) {
         $AddIP = TRUE;
         // Don't check against the localhost.
         foreach (array(
            '127.0.0.1/0',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16') as $LocalCIDR) {

            if (Gdn_Statistics::CIDRCheck($Data['IPAddress'], $LocalCIDR)) {
               $AddIP = FALSE;
               break;
            }
         }
         if ($AddIP)
            $Get['ip'] = $Data['IPAddress'];
      }
      if (isset($Data['Username'])) {
         $Get['username'] = $Data['Username'];
      }
      if (isset($Data['Email'])) {
         $Get['email'] = $Data['Email'];
      }

      if (empty($Get))
         return FALSE;

      $Get['f'] = 'json';

      $Url = "http://www.stopforumspam.com/api?" . http_build_query($Get);

      $Curl = curl_init();
      curl_setopt($Curl, CURLOPT_URL, $Url);
      curl_setopt($Curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($Curl, CURLOPT_TIMEOUT, 4);
      curl_setopt($Curl, CURLOPT_FAILONERROR, 1);
      $ResultString = curl_exec($Curl);
      curl_close($Curl);

      if ($ResultString) {
         $Result = json_decode($ResultString, TRUE);

         // Ban ip addresses appearing more than o
         if (GetValueR('ip.frequency', $Result) > 5)
            return TRUE;
         elseif (GetValueR('email.frequency', $Result) > 20)
            return TRUE;
      }

      return FALSE;
   }

   /// Event Handlers ///

   public function Base_CheckSpam_Handler($Sender, $Args) {
      // Don't check for spam if another plugin has already determined it is.
      if ($Sender->EventArguments['IsSpam'])
         return;

      $RecordType = $Args['RecordType'];
      $Data = $Args['Data'];

      $Result = FALSE;
      switch ($RecordType) {
         case 'User':
            $Result = self::Check($Data);
            break;
         case 'Comment':
         case 'Discussion':
         case 'Activity':
//            $Result = $this->CheckTest($RecordType, $Data) || $this->CheckStopForumSpam($RecordType, $Data) || $this->CheckAkismet($RecordType, $Data);
            break;
      }
      $Sender->EventArguments['IsSpam'] = $Result;
   }
}