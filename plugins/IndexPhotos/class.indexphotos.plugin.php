<?php if (!defined('APPLICATION')) exit();

$PluginInfo['IndexPhotos'] = array(
   'Name' => 'IndexPhotos',
   'Description' => "Adds user photos to discussions list.",
   'Version' => '1.0',
   'RequiredApplications' => array('Vanilla' => '2.0.18b4'),
   'RegisterPermissions' => FALSE,
   'Author' => "Matt Lincoln Russell",
   'AuthorEmail' => 'lincolnwebs@gmail.com',
   'AuthorUrl' => 'http://lincolnwebs.com'
);

class IndexPhotosPlugin extends Gdn_Plugin {
   /**
    * Extra style sheet.
    */
   public function DiscussionsController_Render_Before($Sender) {
      $Sender->AddCssFile($this->GetResource('design/indexphotos.css', FALSE, FALSE));
   }

   /**
    * Display user photo before each discussion row.
    */
   public function DiscussionsController_BeforeDiscussionContent_Handler($Sender) {
      // Build user object & output photo
      $FirstUser = UserBuilder($Sender->EventArguments['Discussion'], 'First');
      echo UserPhoto($FirstUser);
   }
}