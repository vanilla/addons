<?php if (!defined('APPLICATION')) exit();

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
   public function SettingsController_TouchIcon_Create($sender) {
      $sender->Permission('Garden.Settings.Manage');
      $sender->AddSideMenu('settings/touchicon');
      $sender->Title(T('Touch Icon'));

      if ($sender->Form->AuthenticatedPostBack()) {
         $upload = new Gdn_UploadImage();
         try {
            // Validate the upload
            $tmpImage = $upload->ValidateUpload('TouchIcon', FALSE);
            if ($tmpImage) {
               // Save the uploaded image.
               $touchIconPath ='banner/touchicon_'.substr(md5(microtime()), 16).'.png';
               $imageInfo = $upload->SaveImageAs(
                  $tmpImage,
                  $touchIconPath,
                  114,
                  114,
                  ['OutputType' => 'png', 'ImageQuality' => '8']
               );

               SaveToConfig('Garden.TouchIcon', $imageInfo['SaveName']);
            }
         } catch (Exception $ex) {
            $sender->Form->AddError($ex->getMessage());
         }

         $sender->InformMessage(T("Your icon has been saved."));
      }

      $sender->SetData('Path', $this->getIconUrl());
      $sender->Render($this->GetView('touchicon.php'));
   }

   /**
    * Get path to icon.
    *
    * @return string Path to icon
    */
   public function getIconUrl() {
      $icon = C('Garden.TouchIcon') ? Gdn_Upload::Url(C('Garden.TouchIcon')) : Asset(self::DEFAULT_PATH);
      return $icon;
   }

   /**
    * Redirect to icon.
    *
    * @since 1.0
    * @access public
    */
   public function UtilityController_ShowTouchIcon_Create($sender) {
      $redirect = $this->getIconUrl();
      redirectTo($redirect, 302, false);
      exit();
   }
}
