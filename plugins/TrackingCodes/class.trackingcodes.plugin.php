<?php if (!defined('APPLICATION')) exit();

// 0.2 - 2011-09-07 - mosullivan - Added InjectCssClass, Optimized querying.

class TrackingCodesPlugin extends Gdn_Plugin {

   /**
    * Adds dashboard menu option.
    */
   public function Base_GetAppSettingsMenuItems_Handler($Sender) {
      $Menu = &$Sender->EventArguments['SideMenu'];
      $Menu->AddLink('Add-ons', T('Tracking Codes'), 'settings/trackingcodes', 'Garden.Settings.Manage');
	}

	/**
	 * Tracking codes management page.
	 */
	public function SettingsController_TrackingCodes_Create($Sender) {
		$Sender->Permission('Garden.Settings.Manage');
      $Sender->AddSideMenu('settings/trackingcodes');

      $Sender->Title('Tracking Codes');
		$Action = strtolower(GetValue(0, $Sender->RequestArgs, ''));
		if ($Action == 'add')
			$this->_Add($Sender);
		else if ($Action == 'edit')
			$this->_Edit($Sender);
		else if ($Action == 'delete')
			$this->_Delete($Sender);
		else if ($Action == 'toggle')
			$this->_Toggle($Sender);
		else if ($Action == 'sort')
			$this->_Sort($Sender);
		else
			$Sender->Render('index', '', 'plugins/TrackingCodes');
	}

   /**
    * Delete a code.
    */
   private function _Delete($Sender) {
      $Sender->Permission('Garden.Settings.Manage');
		$Key = GetValue(1, $Sender->RequestArgs);
		$TransientKey = GetValue(2, $Sender->RequestArgs);
      $Session = Gdn::Session();
      if ($TransientKey !== FALSE && $Session->ValidateTransientKey($TransientKey)) {
			$TrackingCodes = C('Plugins.TrackingCodes.All');
			if (!is_array($TrackingCodes))
				$TrackingCodes = array();

			if ($Key !== FALSE)
				foreach ($TrackingCodes as $Index => $Code) {
					if ($Key == GetValue('Key', $Code, FALSE)) {
						unset($TrackingCodes[$Index]);
						SaveToConfig('Plugins.TrackingCodes.All', $TrackingCodes);
						break;
					}
				}
      }

      redirectTo('settings/trackingcodes', 302, false);
   }

   /**
    * Toggle a tracking code's state.
    */
   private function _Toggle($Sender) {
      $Sender->Permission('Garden.Settings.Manage');
		$Key = GetValue(1, $Sender->RequestArgs);
		$TransientKey = GetValue(2, $Sender->RequestArgs);
      $Session = Gdn::Session();
      if ($TransientKey !== FALSE && $Session->ValidateTransientKey($TransientKey)) {
			$TrackingCodes = C('Plugins.TrackingCodes.All');
			if (!is_array($TrackingCodes))
				$TrackingCodes = array();

			if ($Key !== FALSE)
				foreach ($TrackingCodes as $Index => $Code) {
					if ($Key == GetValue('Key', $Code, FALSE)) {
						$Code['Enabled'] = GetValue('Enabled', $Code) == '1' ? '0' : '1';
						$TrackingCodes[$Index] = $Code;
						SaveToConfig('Plugins.TrackingCodes.All', $TrackingCodes);
						break;
					}
				}
      }

      redirectTo('settings/trackingcodes', 302, false);
   }

   /**
    * Form to edit an existing code.
    *
    * @param SettingsController $Sender
    */
   private function _Edit($Sender) {
		$Sender->Permission('Garden.Settings.Manage');
      $Sender->AddSideMenu('settings/trackingcodes');
      $Sender->AddJsFile('jquery.autogrow.js');
		$EditIndex = FALSE;
		$EditKey = GetValue(1, $Sender->RequestArgs);
		$Sender->Code = FALSE;
		$TrackingCodes = C('Plugins.TrackingCodes.All');
		if (!is_array($TrackingCodes))
			$TrackingCodes = array();

		if ($EditKey !== FALSE)
			foreach ($TrackingCodes as $Index => $Code) {
				if ($EditKey == GetValue('Key', $Code, FALSE)) {
					$EditIndex = $Index;
					$Sender->Code = $Code;
					break;
				}
			}

      if (!$Sender->Form->AuthenticatedPostBack()) {
			// Set defaults
			if ($Sender->Code)
				$Sender->Form->SetData($Sender->Code);
      } else {
			// Let the form take care of itself, but save to the db.
			$FormValues = $Sender->Form->FormValues();
			$ValuesToSave['Key'] = GetValue('Key', $FormValues, '');
			if ($ValuesToSave['Key'] == '')
				$ValuesToSave['Key'] = time().Gdn::Session()->UserID; // create a new unique id for the item

			$ValuesToSave['Name'] = GetValue('Name', $FormValues, '');
			$ValuesToSave['Code'] = GetValue('Code', $FormValues, '');
			$ValuesToSave['Enabled'] = GetValue('Enabled', $FormValues, '');
			if ($EditIndex !== FALSE) {
				$Sender->Code = $ValuesToSave; // Show the correct page title (add or edit).
				$TrackingCodes[$EditIndex] = $ValuesToSave;
			} else {
				$TrackingCodes[] = $ValuesToSave;
			}

			SaveToConfig('Plugins.TrackingCodes.All', $TrackingCodes);
         $Sender->InformMessage(T('Your changes have been saved.'));
			$Sender->setRedirectTo('settings/trackingcodes');
      }

      $Sender->Render('edit', '', 'plugins/TrackingCodes');
   }


	/**
	 * Dump all of the tracking codes to the page if *not* in admin master view.
	 */
	public function Base_AfterBody_Handler($Sender) {
		if ($Sender->MasterView == 'admin')
			return;

		$TrackingCodes = C('Plugins.TrackingCodes.All');
		if (!is_array($TrackingCodes))
			$TrackingCodes = array();
		foreach ($TrackingCodes as $Index => $Code) {
			if (GetValue('Enabled', $Code) == '1')
				echo GetValue('Code', $Code);
		}
	}
}
