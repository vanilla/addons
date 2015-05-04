<?php if (!defined('APPLICATION')) exit();

// Define the plugin:
$PluginInfo['TouchIcon'] = array(
   'Name' => 'Touch Icon',
   'Description' => 'Adds option to upload a touch icon for Apple iDevices.',
   'Version' => '1.1',
   'Author' => "Matt Lincoln Russell",
   'AuthorEmail' => 'lincoln@vanillaforums.com',
   'AuthorUrl' => 'http://lincolnwebs.com',
   'RequiredApplications' => array('Vanilla' => '2.0.18'),
   'MobileFriendly' => TRUE,
   'SettingsUrl' => '/settings/touchicon',
   'SettingsPermission' => 'Garden.Settings.Manage'
);

class TouchIconPlugin extends Gdn_Plugin {

   /**
    * @var string Default icon path
    */
   const DEFAULT_PATH = 'plugins/TouchIcon/design/default.png';

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
    * Updates.
    */
   public function Structure() {
      // Backwards compatibility with v1.
      if (C('Plugins.TouchIcon.Uploaded')) {
         SaveToConfig('Garden.TouchIcon', 'TouchIcon/apple-touch-icon.png');
         RemoveFromConfig('Plugins.TouchIcon.Uploaded');
      }
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
               // Save the uploaded image.
               $TouchIconPath ='banner/touchicon_'.substr(md5(microtime()), 16).'.png';
               $ImageInfo = $Upload->SaveImageAs(
                  $TmpImage,
                  $TouchIconPath,
                  114,
                  114,
                  array('OutputType' => 'png', 'ImageQuality' => '8')
               );

               SaveToConfig('Garden.TouchIcon', $ImageInfo['SaveName']);
            }
         } catch (Exception $ex) {
            $Sender->Form->AddError($ex->getMessage());
         }

         $Sender->InformMessage(T("Your icon has been saved."));
      }

      $Sender->SetData('Path', $this->getIconUrl());
      $Sender->Render($this->GetView('touchicon.php'));
   }

   /**
    * Get path to icon.
    *
    * @return string Path to icon
    */
   public function getIconUrl() {
      $Icon = C('Garden.TouchIcon') ? Gdn_Upload::Url(C('Garden.TouchIcon')) : Asset(self::DEFAULT_PATH);
      return $Icon;
   }

   /**
    * Redirect to icon.
    *
    * @since 1.0
    * @access public
    */
   public function UtilityController_ShowTouchIcon_Create($Sender) {
      $Redirect = $this->getIconUrl();
      Redirect($Redirect, 302);
      exit();
   }
}
