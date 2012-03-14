<?php if (!defined('APPLICATION')) exit();

$PluginInfo['IndexPhotos'] = array(
   'Name' => 'Discussion Photos',
   'Description' => "Displays photo of the user who started each discussion anywhere discussions are listed.",
   'Version' => '1.1',
   'RequiredApplications' => array('Vanilla' => '2.0.18'),
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
    * Extra style sheet.
    */
   public function CategoriesController_Render_Before($Sender) {
      $Sender->AddCssFile($this->GetResource('design/indexphotos.css', FALSE, FALSE));
   }

   /**
    * Trigger on All Discussions.
    */
   public function DiscussionsController_BeforeDiscussionContent_Handler($Sender) {
      $this->DisplayPhoto($Sender);
   }
   
   /**
    * Trigger on Categories.
    */
   public function CategoriesController_BeforeDiscussionContent_Handler($Sender) {
      $this->DisplayPhoto($Sender);
   }
   
   /**
    * Display user photo for first user in each discussion.
    */
   protected function DisplayPhoto($Sender) {
      // Build user object & output photo
      $FirstUser = UserBuilder($Sender->EventArguments['Discussion'], 'First');
      echo UserPhoto($FirstUser);
   }
}