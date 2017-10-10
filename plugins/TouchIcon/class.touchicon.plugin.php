<?php if (!defined('APPLICATION')) exit();

class TouchIconPlugin extends Gdn_Plugin {

   /**
    * @var string Default icon path
    */
   const DEFAULT_PATH = 'plugins/TouchIcon/design/default.png';

   /**
	 * Create route.
	 */
   public function setup() {
      Gdn::router()->setRoute(
         'apple-touch-icon.png',
         'utility/showtouchicon',
         'Internal'
      );
   }

   /**
    * Remove route.
    */
   public function onDisable() {
      Gdn::router()->deleteRoute('apple-touch-icon.png');
   }

   /**
    * Updates.
    */
   public function structure() {
      // Backwards compatibility with v1.
      if (c('Plugins.TouchIcon.Uploaded')) {
         saveToConfig('Garden.TouchIcon', 'TouchIcon/apple-touch-icon.png');
         removeFromConfig('Plugins.TouchIcon.Uploaded');
      }
   }

   /**
    * Touch icon management screen.
    *
    * @since 1.0
    * @access public
    */
   public function settingsController_touchIcon_create($sender) {
      $sender->permission('Garden.Settings.Manage');
      $sender->addSideMenu('settings/touchicon');
      $sender->title(t('Touch Icon'));

      if ($sender->Form->authenticatedPostBack()) {
         $upload = new Gdn_UploadImage();
         try {
            // Validate the upload
            $tmpImage = $upload->validateUpload('TouchIcon', FALSE);
            if ($tmpImage) {
               // Save the uploaded image.
               $touchIconPath ='banner/touchicon_'.substr(md5(microtime()), 16).'.png';
               $imageInfo = $upload->saveImageAs(
                  $tmpImage,
                  $touchIconPath,
                  114,
                  114,
                  ['OutputType' => 'png', 'ImageQuality' => '8']
               );

               saveToConfig('Garden.TouchIcon', $imageInfo['SaveName']);
            }
         } catch (Exception $ex) {
            $sender->Form->addError($ex->getMessage());
         }

         $sender->informMessage(t("Your icon has been saved."));
      }

      $sender->setData('Path', $this->getIconUrl());
      $sender->render($this->getView('touchicon.php'));
   }

   /**
    * Get path to icon.
    *
    * @return string Path to icon
    */
   public function getIconUrl() {
      $icon = c('Garden.TouchIcon') ? Gdn_Upload::url(c('Garden.TouchIcon')) : asset(self::DEFAULT_PATH);
      return $icon;
   }

   /**
    * Redirect to icon.
    *
    * @since 1.0
    * @access public
    */
   public function utilityController_showTouchIcon_create($sender) {
      $redirect = $this->getIconUrl();
      redirectTo($redirect, 302, false);
      exit();
   }
}
