<?php if (!defined('APPLICATION')) exit();

// 0.2 - 2011-09-07 - mosullivan - Added InjectCssClass, Optimized querying.

class TrackingCodesPlugin extends Gdn_Plugin {

   /**
    * Adds dashboard menu option.
    */
   public function base_getAppSettingsMenuItems_handler($sender) {
      $menu = &$sender->EventArguments['SideMenu'];
      $menu->addLink('Add-ons', t('Tracking Codes'), 'settings/trackingcodes', 'Garden.Settings.Manage');
	}

	/**
	 * Tracking codes management page.
	 */
	public function settingsController_trackingCodes_create($sender) {
		$sender->permission('Garden.Settings.Manage');
      $sender->addSideMenu('settings/trackingcodes');

      $sender->title('Tracking Codes');
		$action = strtolower(getValue(0, $sender->RequestArgs, ''));
		if ($action == 'add')
			$this->_Add($sender);
		else if ($action == 'edit')
			$this->_Edit($sender);
		else if ($action == 'delete')
			$this->_Delete($sender);
		else if ($action == 'toggle')
			$this->_Toggle($sender);
		else if ($action == 'sort')
			$this->_Sort($sender);
		else
			$sender->render('index', '', 'plugins/TrackingCodes');
	}

   /**
    * Delete a code.
    */
   private function _Delete($sender) {
      $sender->permission('Garden.Settings.Manage');
		$key = getValue(1, $sender->RequestArgs);
		$transientKey = getValue(2, $sender->RequestArgs);
      $session = Gdn::session();
      if ($transientKey !== FALSE && $session->validateTransientKey($transientKey)) {
			$trackingCodes = c('Plugins.TrackingCodes.All');
			if (!is_array($trackingCodes))
				$trackingCodes = [];

			if ($key !== FALSE)
				foreach ($trackingCodes as $index => $code) {
					if ($key == getValue('Key', $code, FALSE)) {
						unset($trackingCodes[$index]);
						saveToConfig('Plugins.TrackingCodes.All', $trackingCodes);
						break;
					}
				}
      }

      redirectTo('settings/trackingcodes');
   }

   /**
    * Toggle a tracking code's state.
    */
   private function _Toggle($sender) {
      $sender->permission('Garden.Settings.Manage');
		$key = getValue(1, $sender->RequestArgs);
		$transientKey = getValue(2, $sender->RequestArgs);
      $session = Gdn::session();
      if ($transientKey !== FALSE && $session->validateTransientKey($transientKey)) {
			$trackingCodes = c('Plugins.TrackingCodes.All');
			if (!is_array($trackingCodes))
				$trackingCodes = [];

			if ($key !== FALSE)
				foreach ($trackingCodes as $index => $code) {
					if ($key == getValue('Key', $code, FALSE)) {
						$code['Enabled'] = getValue('Enabled', $code) == '1' ? '0' : '1';
						$trackingCodes[$index] = $code;
						saveToConfig('Plugins.TrackingCodes.All', $trackingCodes);
						break;
					}
				}
      }

      redirectTo('settings/trackingcodes');
   }

   /**
    * Form to edit an existing code.
    *
    * @param SettingsController $sender
    */
   private function _Edit($sender) {
		$sender->permission('Garden.Settings.Manage');
      $sender->addSideMenu('settings/trackingcodes');
      $sender->addJsFile('jquery.autogrow.js');
		$editIndex = FALSE;
		$editKey = getValue(1, $sender->RequestArgs);
		$sender->Code = FALSE;
		$trackingCodes = c('Plugins.TrackingCodes.All');
		if (!is_array($trackingCodes))
			$trackingCodes = [];

		if ($editKey !== FALSE)
			foreach ($trackingCodes as $index => $code) {
				if ($editKey == getValue('Key', $code, FALSE)) {
					$editIndex = $index;
					$sender->Code = $code;
					break;
				}
			}

      if (!$sender->Form->authenticatedPostBack()) {
			// Set defaults
			if ($sender->Code)
				$sender->Form->setData($sender->Code);
      } else {
			// Let the form take care of itself, but save to the db.
			$formValues = $sender->Form->formValues();
			$valuesToSave['Key'] = getValue('Key', $formValues, '');
			if ($valuesToSave['Key'] == '')
				$valuesToSave['Key'] = time().Gdn::session()->UserID; // create a new unique id for the item

			$valuesToSave['Name'] = getValue('Name', $formValues, '');
			$valuesToSave['Code'] = getValue('Code', $formValues, '');
			$valuesToSave['Enabled'] = getValue('Enabled', $formValues, '');
			if ($editIndex !== FALSE) {
				$sender->Code = $valuesToSave; // Show the correct page title (add or edit).
				$trackingCodes[$editIndex] = $valuesToSave;
			} else {
				$trackingCodes[] = $valuesToSave;
			}

			saveToConfig('Plugins.TrackingCodes.All', $trackingCodes);
         $sender->informMessage(t('Your changes have been saved.'));
			$sender->setRedirectTo('settings/trackingcodes');
      }

      $sender->render('edit', '', 'plugins/TrackingCodes');
   }


	/**
	 * Dump all of the tracking codes to the page if *not* in admin master view.
	 */
	public function base_afterBody_handler($sender) {
		if ($sender->MasterView == 'admin')
			return;

		$trackingCodes = c('Plugins.TrackingCodes.All');
		if (!is_array($trackingCodes))
			$trackingCodes = [];
		foreach ($trackingCodes as $index => $code) {
			if (getValue('Enabled', $code) == '1')
				echo getValue('Code', $code);
		}
	}
}
