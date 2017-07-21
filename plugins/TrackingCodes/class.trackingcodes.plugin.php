<?php if (!defined('APPLICATION')) exit();

// 0.2 - 2011-09-07 - mosullivan - Added InjectCssClass, Optimized querying.

class TrackingCodesPlugin extends Gdn_Plugin {

   /**
    * Adds dashboard menu option.
    */
   public function Base_GetAppSettingsMenuItems_Handler($sender) {
      $menu = &$sender->EventArguments['SideMenu'];
      $menu->AddLink('Add-ons', T('Tracking Codes'), 'settings/trackingcodes', 'Garden.Settings.Manage');
	}

	/**
	 * Tracking codes management page.
	 */
	public function SettingsController_TrackingCodes_Create($sender) {
		$sender->Permission('Garden.Settings.Manage');
      $sender->AddSideMenu('settings/trackingcodes');

      $sender->Title('Tracking Codes');
		$action = strtolower(GetValue(0, $sender->RequestArgs, ''));
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
			$sender->Render('index', '', 'plugins/TrackingCodes');
	}

   /**
    * Delete a code.
    */
   private function _Delete($sender) {
      $sender->Permission('Garden.Settings.Manage');
		$key = GetValue(1, $sender->RequestArgs);
		$transientKey = GetValue(2, $sender->RequestArgs);
      $session = Gdn::Session();
      if ($transientKey !== FALSE && $session->ValidateTransientKey($transientKey)) {
			$trackingCodes = C('Plugins.TrackingCodes.All');
			if (!is_array($trackingCodes))
				$trackingCodes = [];

			if ($key !== FALSE)
				foreach ($trackingCodes as $index => $code) {
					if ($key == GetValue('Key', $code, FALSE)) {
						unset($trackingCodes[$index]);
						SaveToConfig('Plugins.TrackingCodes.All', $trackingCodes);
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
      $sender->Permission('Garden.Settings.Manage');
		$key = GetValue(1, $sender->RequestArgs);
		$transientKey = GetValue(2, $sender->RequestArgs);
      $session = Gdn::Session();
      if ($transientKey !== FALSE && $session->ValidateTransientKey($transientKey)) {
			$trackingCodes = C('Plugins.TrackingCodes.All');
			if (!is_array($trackingCodes))
				$trackingCodes = [];

			if ($key !== FALSE)
				foreach ($trackingCodes as $index => $code) {
					if ($key == GetValue('Key', $code, FALSE)) {
						$code['Enabled'] = GetValue('Enabled', $code) == '1' ? '0' : '1';
						$trackingCodes[$index] = $code;
						SaveToConfig('Plugins.TrackingCodes.All', $trackingCodes);
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
		$sender->Permission('Garden.Settings.Manage');
      $sender->AddSideMenu('settings/trackingcodes');
      $sender->AddJsFile('jquery.autogrow.js');
		$editIndex = FALSE;
		$editKey = GetValue(1, $sender->RequestArgs);
		$sender->Code = FALSE;
		$trackingCodes = C('Plugins.TrackingCodes.All');
		if (!is_array($trackingCodes))
			$trackingCodes = [];

		if ($editKey !== FALSE)
			foreach ($trackingCodes as $index => $code) {
				if ($editKey == GetValue('Key', $code, FALSE)) {
					$editIndex = $index;
					$sender->Code = $code;
					break;
				}
			}

      if (!$sender->Form->AuthenticatedPostBack()) {
			// Set defaults
			if ($sender->Code)
				$sender->Form->SetData($sender->Code);
      } else {
			// Let the form take care of itself, but save to the db.
			$formValues = $sender->Form->FormValues();
			$valuesToSave['Key'] = GetValue('Key', $formValues, '');
			if ($valuesToSave['Key'] == '')
				$valuesToSave['Key'] = time().Gdn::Session()->UserID; // create a new unique id for the item

			$valuesToSave['Name'] = GetValue('Name', $formValues, '');
			$valuesToSave['Code'] = GetValue('Code', $formValues, '');
			$valuesToSave['Enabled'] = GetValue('Enabled', $formValues, '');
			if ($editIndex !== FALSE) {
				$sender->Code = $valuesToSave; // Show the correct page title (add or edit).
				$trackingCodes[$editIndex] = $valuesToSave;
			} else {
				$trackingCodes[] = $valuesToSave;
			}

			SaveToConfig('Plugins.TrackingCodes.All', $trackingCodes);
         $sender->InformMessage(T('Your changes have been saved.'));
			$sender->setRedirectTo('settings/trackingcodes');
      }

      $sender->Render('edit', '', 'plugins/TrackingCodes');
   }


	/**
	 * Dump all of the tracking codes to the page if *not* in admin master view.
	 */
	public function Base_AfterBody_Handler($sender) {
		if ($sender->MasterView == 'admin')
			return;

		$trackingCodes = C('Plugins.TrackingCodes.All');
		if (!is_array($trackingCodes))
			$trackingCodes = [];
		foreach ($trackingCodes as $index => $code) {
			if (GetValue('Enabled', $code) == '1')
				echo GetValue('Code', $code);
		}
	}
}
