<?php if (!defined('APPLICATION')) exit();

// Define the plugin:
$PluginInfo['TouchIcon'] = array(
   'Name' => 'Touch Icon',
   'Description' => 'Allow admin to upload touch icon for Apple devices.',
   'Version' => '1.0',
   'Author' => "Matt Lincoln Russell",
   'AuthorEmail' => 'lincoln@vanillaforums.com',
   'AuthorUrl' => 'http://lincolnwebs.com',
   'RequiredApplications' => array('Vanilla' => '2.0.17'),
   'MobileFriendly' => TRUE,
   'SettingsUrl' => '/settings/touchicon',
   'SettingsPermission' => 'Garden.Settings.Manage'
);

class TouchIconPlugin extends Gdn_Plugin {
   /**
	 * Create route.
	 */
   public function Setup() {
      Gdn::Router()->SetRoute(
         'apple-touch-icon.png',
         'utility/showtouchicon',
         'Internal'
      );
   }
   
   /**
    * Remove route.
    */
   public function OnDisable() {
      Gdn::Router()->DeleteRoute('apple-touch-icon.png');
   }
   
   /**
    * Touch icon management screen.
    *
    * @since 1.0
    * @access public
    */
   public function SettingsController_TouchIcon_Create($Sender) {
      $Sender->Permission('Garden.Settings.Manage');
      $Sender->AddSideMenu('dashboard/settings/touchicon');
      $Sender->Title(T('Touch Icon'));
      
      if ($Sender->Form->AuthenticatedPostBack()) {
         $Upload = new Gdn_UploadImage();
         try {
            // Validate the upload
            $TmpImage = $Upload->ValidateUpload('TouchIcon', FALSE);
            if ($TmpImage) {
               // Save the uploaded image
               $Upload->SaveImageAs(
                  $TmpImage,
                  PATH_ROOT . DS . 'uploads' . DS . 'TouchIcon' . DS . 'apple-touch-icon.png',
                  114,
                  114,
                  array('OutputType' => 'png', 'ImageQuality' => '100')
               );
            }
         } catch (Exception $ex) {
            $Sender->Form->AddError($ex->getMessage());
         }
         
         $Sender->InformMessage(T('TouchIconSaved', "Your icon has been saved."));
      }
      
      $Sender->Render($this->GetView('touchicon.php'));      
   }
   
   /**
    * Show icon.
    *
    * @since 1.0
    * @access public
    */
   public function UtilityController_ShowTouchIcon_Create($Sender) {
      $IconPath = PATH_ROOT.'/uploads/TouchIcon/apple-touch-icon.png';
      $Default = PATH_ROOT.'/plugins/TouchIcon/design/default.png';
      $File = new Gdn_FileSystem();
      
      if ($File->Exists($IconPath))
         $File->ServeFile($IconPath, 'apple-touch-icon.png', 'image/png', 'inline');
      else
         $File->ServeFile($Default, 'apple-touch-icon.png', 'image/png', 'inline');
   }
}