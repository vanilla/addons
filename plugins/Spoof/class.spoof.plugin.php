<?php if (!defined('APPLICATION')) exit();

/**
 * 1.2 - mosullivan - 2011-08-30 - Added "Spoof" button to various screens for admins.
 */
// Define the plugin:
$PluginInfo['Spoof'] = array(
   'Name' => 'Spoof',
   'Description' => 'Administrators may "spoof" other users, meaning they temporarily sign in as that user. Helpful for debugging permission problems.',
   'Version' => '1.2',
   'Author' => "Mark O'Sullivan",
   'AuthorEmail' => 'mark@vanillaforums.com',
   'AuthorUrl' => 'http://vanillaforums.com'
);

class SpoofPlugin implements Gdn_IPlugin {

	/**
	 * Add the spoof admin screen to the dashboard menu.
	 */
   public function Base_GetAppSettingsMenuItems_Handler($Sender) {
      // Clean out entire menu & re-add everything
      $Menu = &$Sender->EventArguments['SideMenu'];
      $Menu->AddLink('Users', T('Spoof'), 'dashboard/user/spoof', 'Garden.Settings.Manage');
	}
   
	/**
	 * Admin screen for spoofing a user.
	 */
   public function UserController_Spoof_Create($Sender) {
		$Sender->Permission('Garden.Settings.Manage');
      $Sender->AddSideMenu('dashboard/user/spoof');
		$this->_SpoofMethod($Sender);
	}
	
	/**
	 * Validates the current user's permissions & transientkey and then spoofs
	 * the userid passed as the first arg and redirects to profile.
	 */
	public function UserController_AutoSpoof_Create($Sender) {
		$SpoofUserID = GetValue('0', $Sender->RequestArgs);
		$TransientKey = GetValue('1', $Sender->RequestArgs);
		// Validate the transient key && permissions
		if (Gdn::Session()->ValidateTransientKey($TransientKey) && Gdn::Session()->CheckPermission('Garden.Settings.Manage')) {
			$Identity = new Gdn_CookieIdentity();
			$Identity->Init(array(
				'Salt' => Gdn::Config('Garden.Cookie.Salt'),
				'Name' => Gdn::Config('Garden.Cookie.Name'),
				'Domain' => Gdn::Config('Garden.Cookie.Domain')
			));
			$Identity->SetIdentity($SpoofUserID, TRUE);
		}
		Redirect('profile');
	}
	
	/**
	 * Adds a "Spoof" link to the user management list.
	 */
	public function UserController_UserListOptions_Handler($Sender) {
		if (!Gdn::Session()->CheckPermission('Garden.Settings.Manage'))
			return;
		
      $User = GetValue('User', $Sender->EventArguments);
		if ($User)
			echo Anchor(T('Spoof'), '/user/autospoof/'.$User->UserID.'/'.Gdn::Session()->TransientKey(), 'PopConfirm SmallButton');
	}
	
	/**
	 * Adds a "Spoof" link to the site management list.
	 */
	public function ManageController_SiteListOptions_Handler($Sender) {
		if (!Gdn::Session()->CheckPermission('Garden.Settings.Manage'))
			return;
		
		$Site = GetValue('Site', $Sender->EventArguments);
		if ($Site)
			echo Anchor(T('Spoof'), '/user/autospoof/'.$Site->InsertUserID.'/'.Gdn::Session()->TransientKey(), 'PopConfirm SmallButton');
	}
	
   public function ProfileController_AfterAddSideMenu_Handler($Sender) {
		if (!Gdn::Session()->CheckPermission('Garden.Settings.Manage'))
			return;
		
      $SideMenu = $Sender->EventArguments['SideMenu'];
      $ViewingUserID = Gdn::Session()->UserID;
      
      if ($Sender->User->UserID != $ViewingUserID)
         $SideMenu->AddLink('Options', T('Spoof User'), '/user/autospoof/'.$Sender->User->UserID.'/'.Gdn::Session()->TransientKey(), '', array('class' => 'PopConfirm'));
   }
	
	
	/**
	 * Creates a spoof login page.
	 */
	public function EntryController_Spoof_Create($Sender) {
		$this->_SpoofMethod($Sender);
	}
	
	/**
	 * Standard method for authenticating an admin and allowing them to spoof a user.
	 */
	private function _SpoofMethod($Sender) {
      $Sender->Title('Spoof');
      $Sender->Form = new Gdn_Form();
      $UserReference = $Sender->Form->GetValue('UserReference', '');
      $Email = $Sender->Form->GetValue('Email', '');
      $Password = $Sender->Form->GetValue('Password', '');
      if ($UserReference != '' && $Email != '' && $Password != '') {
         $UserModel = Gdn::UserModel();
         $UserData = $UserModel->ValidateCredentials($Email, 0, $Password);
			// if (1 == 1) {
         if (is_object($UserData) && $UserData->Admin) {
				if (is_numeric($UserReference)) {
					$SpoofUser = $UserModel->GetID($UserReference);
				} else {
				   $SpoofUser = $UserModel->GetByUsername($UserReference);
				}
				if ($SpoofUser) {
					$Identity = new Gdn_CookieIdentity();
					$Identity->Init(array(
						'Salt' => Gdn::Config('Garden.Cookie.Salt'),
						'Name' => Gdn::Config('Garden.Cookie.Name'),
						'Domain' => Gdn::Config('Garden.Cookie.Domain')
					));
					$Identity->SetIdentity($SpoofUser->UserID, TRUE);
	            Redirect('profile');
				} else {
					$Sender->Form->AddError('Failed to find requested user.');
				}
         } else {
            $Sender->Form->AddError('Bad Credentials');
         }
      }
      $Sender->Render(PATH_PLUGINS . DS . 'Spoof' . DS . 'views' . DS . 'spoof.php');
   }

   public function Setup() {}
	
}