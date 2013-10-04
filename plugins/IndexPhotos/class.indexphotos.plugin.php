<?php if (!defined('APPLICATION')) exit();

$PluginInfo['IndexPhotos'] = array(
   'Name' => 'Discussion Photos',
   'Description' => "Displays photo and name of the user who started each discussion anywhere discussions are listed.",
   'Version' => '1.2.1',
   'RequiredApplications' => array('Vanilla' => '2.0.18'),
   'RegisterPermissions' => FALSE,
   'MobileFriendly' => TRUE,
   'Author' => "Matt Lincoln Russell",
   'AuthorEmail' => 'lincolnwebs@gmail.com',
   'AuthorUrl' => 'http://lincolnwebs.com'
);

class IndexPhotosPlugin extends Gdn_Plugin {
   
   public function AssetModel_StyleCss_Handler($Sender) {
      if (!$this->hasLayoutTables()) {
         $Sender->AddCssFile('indexphotos.css', 'plugins/IndexPhotos');
      }
   }
   
   /**
    * Add OP name to start of discussion meta.
    */
   public function DiscussionsController_AfterDiscussionLabels_Handler($Sender, $Args) {
      if (!$this->hasLayoutTables()) {
         if (GetValue('FirstUser', $Args))
            echo '<span class="MItem DiscussionAuthor">'.UserAnchor(GetValue('FirstUser', $Args)).'</span>';
      }
   }
   public function CategoriesController_AfterDiscussionLabels_Handler($Sender, $Args) {
      if (!$this->hasLayoutTables()) {
         if (GetValue('FirstUser', $Args))
            echo '<span class="MItem DiscussionAuthor">'.UserAnchor(GetValue('FirstUser', $Args)).'</span>';
      }
   }

   /**
    * Trigger on All Discussions.
    */
   public function DiscussionsController_BeforeDiscussionContent_Handler($Sender) {
      if (!$this->hasLayoutTables()) {
         $this->DisplayPhoto($Sender);
      }
   }
   
   /**
    * Trigger on Categories.
    */
   public function CategoriesController_BeforeDiscussionContent_Handler($Sender) {
      if (!$this->hasLayoutTables()) {
         $this->DisplayPhoto($Sender);
      }
   }
   
   /**
    * Display user photo for first user in each discussion.
    */
   protected function DisplayPhoto($Sender) {
      // Build user object & output photo
      $FirstUser = UserBuilder($Sender->EventArguments['Discussion'], 'First');
      echo UserPhoto($FirstUser);
   }
   
   /**
    * Determine whether layout is "table" (vs. "modern")
    * 
    * @return bool If forum is using table layout, returns true
    */
   public function hasLayoutTables() {
      $layoutTables = false;
      $layoutFormat = 'table';
      
      // These are the two areas where tables could be used.
      $tablePossibilities = array(
         C('Vanilla.Discussions.Layout'), 
         C('Vanilla.Categories.Layout')
      );
      
      if (in_array($layoutFormat, $tablePossibilities)) {
         $layoutTables = true;
      }

      return $layoutTables;
   }
}