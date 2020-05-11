<?php if (!defined('APPLICATION')) exit();

/**
 * 1.2 - mosullivan - 2011-08-30 - Added "Spoof" button to various screens for admins.
 */
class SpoofPlugin implements Gdn_IPlugin {

	/**
	 * Add the spoof admin screen to the dashboard menu.
	 */
   public function base_getAppSettingsMenuItems_handler($sender) {
      // Clean out entire menu & re-add everything
      $menu = &$sender->EventArguments['SideMenu'];
      $menu->addLink('Users', t('Spoof'), 'user/spoof', 'Garden.Settings.Manage');
	}

	/**
	 * Admin screen for spoofing a user.
	 */
   public function userController_spoof_create($sender) {
		$sender->permission('Garden.Settings.Manage');
      $sender->addSideMenu('user/spoof');
		$this->_SpoofMethod($sender);
	}

	/**
	 * Validates the current user's permissions & transientkey and then spoofs
	 * the userid passed as the first arg and redirects to profile.
     *
     * @param UserController $sender
	 */
	public function userController_autoSpoof_create($sender) {
		$spoofUserID = getValue('0', $sender->RequestArgs);
		$transientKey = getValue('1', $sender->RequestArgs);
		// Validate the transient key && permissions
		if (Gdn::session()->validateTransientKey($transientKey) && Gdn::session()->checkPermission('Garden.Settings.Manage')) {
			$identity = new Gdn_CookieIdentity();
			$identity->init([
				'Salt' => Gdn::config('Garden.Cookie.Salt'),
				'Name' => Gdn::config('Garden.Cookie.Name'),
				'Domain' => Gdn::config('Garden.Cookie.Domain')
			]);
			$identity->setIdentity($spoofUserID, TRUE);
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
	public function userController_userListOptions_handler($sender) {
		if (!Gdn::session()->checkPermission('Garden.Settings.Manage'))
			return;

		$user = getValue('User', $sender->EventArguments);
		if ($user && $user->Admin !== 2) {
			$attr = [
				'aria-label' => t('Spoof'),
				'title' => t('Spoof'),
				'data-follow-link' => 'true'
			];
			$class = 'js-modal-confirm btn btn-icon';
			echo anchor(dashboardSymbol('spoof'), '/user/autospoof/'.$user->UserID.'/'.Gdn::session()->transientKey(), $class, $attr);
		}
	}

	/**
	 * Adds a "Spoof" link to the site management list.
	 */
	public function manageController_siteListOptions_handler($sender) {
		if (!Gdn::session()->checkPermission('Garden.Settings.Manage'))
			return;

		$site = getValue('Site', $sender->EventArguments);
		if ($site)
			echo anchor(t('Spoof'), '/user/autospoof/'.$site->InsertUserID.'/'.Gdn::session()->transientKey(), 'PopConfirm SmallButton');
	}

   public function profileController_afterAddSideMenu_handler($sender) {
		if (!Gdn::session()->checkPermission('Garden.Settings.Manage'))
			return;

      $sideMenu = $sender->EventArguments['SideMenu'];
      $viewingUserID = Gdn::session()->UserID;

      if ($sender->User->UserID != $viewingUserID)
         $sideMenu->addLink('Options', t('Spoof User'), '/user/autospoof/'.$sender->User->UserID.'/'.Gdn::session()->transientKey(), '', ['class' => 'PopConfirm']);
   }


	/**
	 * Creates a spoof login page.
	 */
	public function entryController_spoof_create($sender) {
		$this->_SpoofMethod($sender);
	}

	/**
	 * Standard method for authenticating an admin and allowing them to spoof a user.
	 */
	private function _SpoofMethod($sender) {
      $sender->title('Spoof');
      $sender->Form = new Gdn_Form();
      $userReference = $sender->Form->getValue('UserReference', '');
      $email = $sender->Form->getValue('Email', '');
      $password = $sender->Form->getValue('Password', '');

      if ($userReference != '' && $email != '' && $password != '') {
         $userModel = Gdn::userModel();
         $userData = $userModel->validateCredentials($email, 0, $password);

         if (is_object($userData) && $userModel->checkPermission($userData->UserID, 'Garden.Settings.Manage')) {
				if (is_numeric($userReference)) {
					$spoofUser = $userModel->getID($userReference);
				} else {
				   $spoofUser = $userModel->getByUsername($userReference);
				}

				if ($spoofUser) {
					$identity = new Gdn_CookieIdentity();
					$identity->init([
						'Salt' => Gdn::config('Garden.Cookie.Salt'),
						'Name' => Gdn::config('Garden.Cookie.Name'),
						'Domain' => Gdn::config('Garden.Cookie.Domain')
					]);
					$identity->setIdentity($spoofUser->UserID, TRUE);
	                redirectTo('profile');
				} else {
					$sender->Form->addError('Failed to find requested user.');
				}
         } else {
            $sender->Form->addError('Bad Credentials');
         }
      }

      $sender->render(PATH_PLUGINS . DS . 'Spoof' . DS . 'views' . DS . 'spoof.php');
   }

   public function setup() {}

}
