<?php if (!defined('APPLICATION')) exit();

/**
 * SockPuppet Plugin
 * 
 * This plugin detects duplicate user accounts using cookies.
 * 
 * Changes: 
 *  1.0     Release
 * 
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2003 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Addons
 */

$PluginInfo['SockPuppet'] = array(
   'Name' => 'Sock Puppet Detector',
   'Description' => "Allows the forum to detect and report on duplicate user accounts.",
   'Version' => '1.0b',
   'RequiredApplications' => array('Vanilla' => '2.1'),
   'MobileFriendly' => TRUE,
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://vanillaforums.com'
);

class SockPuppetPlugin extends Gdn_Plugin {
   
   const COOKIENAME = '__vnf';
   
   /**
    * Hook into application startup and start fingerprinting
    * 
    * @param Gdn_Dispatcher $Sender
    */
   public function Gdn_Dispatcher_AppStartup_Handler($Sender) {
      $this->Fingerprint();
   }
   
   /**
    * Logic for fingerprinting the active user
    * 
    * @return type 
    */
   public function Fingerprint() {
      // Don't do anything if the user isn't signed in.
		if (!Gdn::Session()->IsValid())
			return;
		
		$CookieValue = GetValue(self::COOKIENAME, $_COOKIE, '');
		$FingerprintValue = GetValue('Fingerprint', Gdn::Session()->User, '');
		
      
      // We're already tracking this computer/browser
		if (!empty($CookieValue)) {
         
			// If the logged-in user has a different server-side fingerprint than the browser - update the user to the browser fingerprint
			if ($FingerprintValue != $CookieValue)
				$this->SetUserFingerprint(Gdn::Session()->UserID, $CookieValue);
         
         return;
      }
      
      // We're already tracking this user
		if (!empty($FingerprintValue)) {
         
         // If the logged-in user has a fingerprint, but the browser doesn't - track the browser with this user's fingerprint
			if ($FingerprintValue != $CookieValue)
				$this->SetBrowserFingerprint($FingerprintValue);
         
         return;
		}
      
      // We're not tracking either
      if (empty($CookieValue) && empty($FingerprintValue)) {
			
         // Set the user and browser to the same fingerprint
			$FingerprintValue = uniqid();
			$this->SetUserFingerprint(Gdn::Session()->UserID, $FingerprintValue);
			$this->SetBrowserFingerprint($FingerprintValue);
         
         return;
		}
   }
   
   public function FingerprintBanned($Fingerprint, &$SharedUsers) {
      // Get banned users with the same fingerprint.
      $Users = Gdn::SQL()->Select('UserID, Name')->GetWhere('User', array('Fingerprint' => $Fingerprint, 'Banned' => TRUE))->ResultArray();
      
      if (empty($Users)) {
         return FALSE;
      } else {
         $SharedUsers = $Users;
         return TRUE;
      }
   }
   
   /**
    * Set the browser tracking cookie
    * 
    * @param string $Fingerprint 
    */
   protected function SetBrowserFingerprint($Fingerprint) {
      $Expires = time()+60*60*24*256; // Expire one year from now
      setcookie(self::COOKIENAME, $Fingerprint, $Expires, C('Garden.Cookie.Path', '/'), C('Garden.Cookie.Domain', ''));
      $_COOKIE[self::COOKIENAME] = $Fingerprint;
   }
   
   /**
    * Set the user tracking column
    * 
    * @param int $UserID
    * @param string $Fingerprint 
    */
   protected function SetUserFingerprint($UserID, $Fingerprint) {
      Gdn::UserModel()->SetField($UserID, 'Fingerprint', $Fingerprint);
      Gdn::Session()->User->Fingerprint = $Fingerprint;
   }
   
   public function UserID() {
      return C('Plugins.SockPuppet.UserID', NULL);
   }
   
   public function Base_CheckSpam_Handler($Sender, $Args) {
      // Don't check for spam if another plugin has already determined it is.
      if ($Sender->EventArguments['IsSpam'])
         return;

      $RecordType = $Args['RecordType'];
      $Data =& $Args['Data'];
      $Options =& $Args['Options'];

      $Result = FALSE;
      switch ($RecordType) {
         case 'Registration':
            $Fingerprint = GetValue(self::COOKIENAME, $_COOKIE, '');
//            decho($Fingerprint, 'fingerprint');
            
            if ($Fingerprint) {
               $Result = $this->FingerprintBanned($Fingerprint, $Users);
            }
            
//            decho($Result, 'IsSpam');
//            decho($Users, 'Shared Accounts');
            
            if ($Result) {
               $Users = ConsolidateArrayValuesByKey($Users, 'UserID');
               $Data['_Meta']['Shared Accounts'] = FormatString('{UserID,User}', array('UserID' => $Users));
//               decho($Data);
               
               $Data['Log_InsertUserID'] = $this->UserID();
               $Data['RecordIPAddress'] = Gdn::Request()->IpAddress();
            }
            
//            die();
            break;
         case 'Comment':
         case 'Discussion':
         case 'Activity':
//            $Result = $this->CheckTest($RecordType, $Data) || $this->CheckStopForumSpam($RecordType, $Data) || $this->CheckAkismet($RecordType, $Data);
            break;
      }
      $Sender->EventArguments['IsSpam'] = $Result;
   }
   
   /**
	 * Display shared accounts on the user profiles for admins.
    * 
    * @param Gdn_Controller $Sender
	 */
	public function ProfileController_Render_Before($Sender) {
      
      // Don't show this unless we're an admin user
		if (!Gdn::Session()->CheckPermission('Garden.Users.Edit'))
			return;
		
		if (!property_exists($Sender, 'User'))
			return;
		
		// Get the current user's fingerprint value
		$FingerprintValue = GetValue('Fingerprint', $Sender->User);
		if (empty($FingerprintValue))
			return;
		
		// Display all accounts that share that fingerprint value
      $FingerprintModule = new FingerprintModule($Sender);
      $FingerprintModule->GetData($Sender->User->UserID, $FingerprintValue);
      $Sender->AddModule($FingerprintModule);
	}
   
   public function Setup() {
      $this->Structure();
   }

   public function Structure() {
      Gdn::Structure()
         ->Table('User')
			->Column('Fingerprint', 'varchar(50)', NULL, 'index')
         ->Set();
      
      // Get a user for operations.
      $UserID = Gdn::SQL()->GetWhere('User', array('Name' => 'Fingerprint', 'Admin' => 2))->Value('UserID');

      if (!$UserID) {
         $UserID = Gdn::SQL()->Insert('User', array(
            'Name' => 'Fingerprint',
            'Password' => RandomString('20'),
            'HashMethod' => 'Random',
            'Email' => 'fingerprint@domain.com',
            'DateInserted' => Gdn_Format::ToDateTime(),
            'Admin' => '2'
         ));
      }
      SaveToConfig('Plugins.SockPuppet.UserID', $UserID, array('CheckExisting' => TRUE));
	}	
   
}