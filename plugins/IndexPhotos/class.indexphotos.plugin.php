<?php if (!defined('APPLICATION')) exit();

$PluginInfo['IndexPhotos'] = array(
   'Name' => 'Discussion Photos',
   'Description' => "Displays photo and name of the user who started each discussion anywhere discussions are listed.",
   'Version' => '1.2',
   'RequiredApplications' => array('Vanilla' => '2.0.18'),
   'RegisterPermissions' => FALSE,
   'MobileFriendly' => TRUE,
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
    * Add OP name to start of discussion meta.
    */
   public function DiscussionsController_AfterDiscussionLabels_Handler($Sender, $Args) {
      if (GetValue('FirstUser', $Args))
         echo '<span class="MItem DiscussionAuthor">'.UserAnchor(GetValue('FirstUser', $Args)).'</span>';
   }
   public function CategoriesController_AfterDiscussionLabels_Handler($Sender, $Args) {
      if (GetValue('FirstUser', $Args))
         echo '<span class="MItem DiscussionAuthor">'.UserAnchor(GetValue('FirstUser', $Args)).'</span>';
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