<?php if (!defined('APPLICATION')) exit();

/**
 * 1.2 - mosullivan - 2011-08-30 - Added "Spoof" button to various screens for admins.
 */
class SpoofPlugin implements Gdn_IPlugin {

	/**
	 * Add the spoof admin screen to the dashboard menu.
	 */
   public function Base_GetAppSettingsMenuItems_Handler($sender) {
      // Clean out entire menu & re-add everything
      $menu = &$sender->EventArguments['SideMenu'];
      $menu->AddLink('Users', T('Spoof'), 'user/spoof', 'Garden.Settings.Manage');
	}

	/**
	 * Admin screen for spoofing a user.
	 */
   public function UserController_Spoof_Create($sender) {
		$sender->Permission('Garden.Settings.Manage');
      $sender->AddSideMenu('user/spoof');
		$this->_SpoofMethod($sender);
	}

	/**
	 * Validates the current user's permissions & transientkey and then spoofs
	 * the userid passed as the first arg and redirects to profile.
     *
     * @param UserController $sender
	 */
	public function UserController_AutoSpoof_Create($sender) {
		$spoofUserID = GetValue('0', $sender->RequestArgs);
		$transientKey = GetValue('1', $sender->RequestArgs);
		// Validate the transient key && permissions
		if (Gdn::Session()->ValidateTransientKey($transientKey) && Gdn::Session()->CheckPermission('Garden.Settings.Manage')) {
			$identity = new Gdn_CookieIdentity();
			$identity->Init([
				'Salt' => Gdn::Config('Garden.Cookie.Salt'),
				'Name' => Gdn::Config('Garden.Cookie.Name'),
				'Domain' => Gdn::Config('Garden.Cookie.Domain')
			]);
			$identity->SetIdentity($spoofUserID, TRUE);
		}
		if ($this->_DeliveryType !== DELIVERY_TYPE_ALL) {
			$sender->setRedirectTo('profile');
			$sender->render('blank', 'utility', 'dashboard');
		} else {
			redirectTo('profile');
		}
	}

	/**
	 * Adds a "Spoof" link to the user management list.
	 */
	public function UserController_UserListOptions_Handler($sender) {
		if (!Gdn::Session()->CheckPermission('Garden.Settings.Manage'))
			return;

		$user = GetValue('User', $sender->EventArguments);
		if ($user) {
			$attr = [
				'aria-label' => t('Spoof'),
				'title' => t('Spoof'),
				'data-follow-link' => 'true'
			];
			$class = 'js-modal-confirm btn btn-icon';
			echo anchor(dashboardSymbol('spoof'), '/user/autospoof/'.$user->UserID.'/'.Gdn::Session()->TransientKey(), $class, $attr);
		}
	}

	/**
	 * Adds a "Spoof" link to the site management list.
	 */
	public function ManageController_SiteListOptions_Handler($sender) {
		if (!Gdn::Session()->CheckPermission('Garden.Settings.Manage'))
			return;

		$site = GetValue('Site', $sender->EventArguments);
		if ($site)
			echo Anchor(T('Spoof'), '/user/autospoof/'.$site->InsertUserID.'/'.Gdn::Session()->TransientKey(), 'PopConfirm SmallButton');
	}

   public function ProfileController_AfterAddSideMenu_Handler($sender) {
		if (!Gdn::Session()->CheckPermission('Garden.Settings.Manage'))
			return;

      $sideMenu = $sender->EventArguments['SideMenu'];
      $viewingUserID = Gdn::Session()->UserID;

      if ($sender->User->UserID != $viewingUserID)
         $sideMenu->AddLink('Options', T('Spoof User'), '/user/autospoof/'.$sender->User->UserID.'/'.Gdn::Session()->TransientKey(), '', ['class' => 'PopConfirm']);
   }


	/**
	 * Creates a spoof login page.
	 */
	public function EntryController_Spoof_Create($sender) {
		$this->_SpoofMethod($sender);
	}

	/**
	 * Standard method for authenticating an admin and allowing them to spoof a user.
	 */
	private function _SpoofMethod($sender) {
      $sender->Title('Spoof');
      $sender->Form = new Gdn_Form();
      $userReference = $sender->Form->GetValue('UserReference', '');
      $email = $sender->Form->GetValue('Email', '');
      $password = $sender->Form->GetValue('Password', '');

      if ($userReference != '' && $email != '' && $password != '') {
         $userModel = Gdn::UserModel();
         $userData = $userModel->ValidateCredentials($email, 0, $password);

         if (is_object($userData) && $userModel->checkPermission($userData->UserID, 'Garden.Settings.Manage')) {
				if (is_numeric($userReference)) {
					$spoofUser = $userModel->GetID($userReference);
				} else {
				   $spoofUser = $userModel->GetByUsername($userReference);
				}

				if ($spoofUser) {
					$identity = new Gdn_CookieIdentity();
					$identity->Init([
						'Salt' => Gdn::Config('Garden.Cookie.Salt'),
						'Name' => Gdn::Config('Garden.Cookie.Name'),
						'Domain' => Gdn::Config('Garden.Cookie.Domain')
					]);
					$identity->SetIdentity($spoofUser->UserID, TRUE);
	                redirectTo('profile');
				} else {
					$sender->Form->AddError('Failed to find requested user.');
				}
         } else {
            $sender->Form->AddError('Bad Credentials');
         }
      }

      $sender->Render(PATH_PLUGINS . DS . 'Spoof' . DS . 'views' . DS . 'spoof.php');
   }

   public function Setup() {}

}
