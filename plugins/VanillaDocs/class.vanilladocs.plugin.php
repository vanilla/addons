<?php if (!defined('APPLICATION')) exit();
/**
 * @license GNU GPL2
 * @copyright 2014 Vanilla Forums Inc.
*/

$PluginInfo['VanillaDocs'] = array(
   'Description' => 'Convert folders of Markdown text files into categories and discussions.',
   'Version' => '0.1',
   'RequiredApplications' => array('Vanilla' => '2.1b'),
   'Author' => "Lincoln Russell",
   'AuthorEmail' => 'lincoln@vanillaforums.com',
   'AuthorUrl' => 'http://lincolnwebs.com'
);

/**
 * Each folder must have a config file that defines its FolderCode on line 1.
 *    Subsequent lines can define a document sort order list. [todo]
 *    Folders will be sorted alphabetically. [todo]
 *    Config files are generated automatically if not present at build.
 * Each documentation file's first line must be its DocumentCode and nothing else.
 * Codes must be unique and constant.
 * Codes must be alphanumeric with dashes and underscores and are case-insensitive.
 * Codes are never displayed anywhere in the docs.
 * Go to /utility/docsbuild to (re)build the categories/discussions.
 *
 * @todo Counters not updated.
 */
class VanillaDocsPlugin extends Gdn_Plugin {
   /** @var string  /path/to/docs */
   public $DocsRoot;

   /**
    * Add a link to the dashboard menu.
    * 
    * By grabbing a reference to the current SideMenu object we gain access to its methods, allowing us
    * to add a menu link to the newly created /plugin/documentation method.
    *
    * @param $Sender Sending controller instance
    */
   public function Base_GetAppSettingsMenuItems_Handler($Sender) {
      $Menu = &$Sender->EventArguments['SideMenu'];
      $Menu->AddLink('Vanilla', 'Documentation', 'settings/documentation', 'Garden.Settings.Manage');
   }

   /**
    * Set location of Markdown source and fire a rebuild.
    *
    * @param $Sender
    */
//   public function SettingsController_Documentation_Create($Sender) {
//      // Config module
//   }

   /**
    * Handle rebuilding discussions & categories from Markdown files & folders.
    *
    * @param $Sender
    */
   public function UtilityController_DocsBuild_Create($Sender) {
      $Sender->Permission('Garden.Settings.Manage');

      // Get location
      $this->DocsRoot = C('Vanilla.Docs.RootPath');
      if (!$this->DocsRoot)
         die("No docs root set.");

      // Initiate the sync
      $this->ParseDocuments($this->DocsRoot);

      // get $CodesFound which should be built along the way
      //$CodesFound = array(); // @todo

      // Find existing docs
      //$CodesExisting = $this->GetCodes();

      // Remove discussions whose docs were removed
      //$CodesToDelete = array_diff($CodesExisting, $CodesFound);
      //decho('TO DELETE');
      //decho($CodesToDelete);
      // not yet @todo

      echo "Success!";
   }

   /**
    * Get all document codes that currently exist.
    *
    * @return array All the codes.
    */
   public function GetCodes() {
      $Result = Gdn::SQL()->Select('DocumentCode')
         ->From('Discussion')
         ->Where('DocumentCode is not null')
         ->Get()
         ->ResultArray();
      return ConsolidateArrayValuesByKey($Result, 'DocumentCode');
   }

   /**
    * Syncs the doc tree to the discussion list and categories (add-only).
    *
    * @param $Path string
    */
   public function ParseDocuments($Path) {
      // Get folder structure and sync with categories, using Documentation as parent
      $Files = SafeGlob(rtrim($Path,'/').'/*');

      // Recurse thru the file structure
      $CodesFound = array();
      foreach ($Files as $Path) {
         if (is_dir($Path)) {
            // Sync folders to categories
            $this->ParseFolder($Path);
            $CodesFound = array_merge($CodesFound, $this->ParseDocuments($Path));
         }
         else {
            // Sync files to discussions
            $Code = $this->ParseFile($Path);
            if ($Code)
               $CodesFound[] = $Code;
         }
      }

      return $CodesFound;
   }

   /**
    * Parse a folder into a category that contains docs.
    *
    * @param $Path string
    */
   public function ParseFolder($Path) {
      // Make remaining pieces into categories
      $CategoryModel = new CategoryModel();
      $Folders = explode('/', rtrim($Path,'/'));
      $Name = array_pop($Folders);

      // Get parent CategoryID
      // Massively easier than trying to track it thru the traversing
      $ParentID = C('Vanilla.Docs.ParentCategoryID', -1); // Default to Root
      if ($this->DocsRoot != $Path) {
         $ParentFolder = implode('/',$Folders);
         if ($this->DocsRoot != $ParentFolder) {
            $ParentCode = file_get_contents($ParentFolder.'/config');
            $ParentCategory = $CategoryModel->GetWhere(array('FolderCode' => $ParentCode))->FirstRow();
            $ParentID = GetValue('CategoryID', $ParentCategory);
         }
      }

      // Get category code via config
      $Code = FALSE;
      if (file_exists($Path.'/config')) {
         // Get existing config
         $Code = file_get_contents($Path.'/config');
      }
      if (!$Code) {
         // Drop a config file
         $Code = Gdn_Format::Url($Name).'-'.mt_rand(100,999);
         file_put_contents($Path.'/config', $Code);
      }

      // Get matching category data
      $Category = $CategoryModel->GetWhere(array('FolderCode' => $Code))->FirstRow();
      $CategoryID = GetValue('CategoryID', $Category);
      if (!$CategoryID) {
         // Create new category
         $CategoryID = $CategoryModel->Save(array(
            'Name' => $Name,
            'UrlCode' => Gdn_Format::Url($Name),
            'ParentCategoryID' => $ParentID,
            'FolderCode' => $Code
         ));
      }
      else {
         // Update our category's name & parent
         $Category = (array) $Category;
         $Category['Name'] = $Name;
         $Category['ParentCategoryID'] = $ParentID;
         $CategoryModel->Save($Category);
      }

      // Descend!
      $this->ParseDocuments($Path);
   }

   /**
    * Parse apart a doc file into its code, name, and body.
    *
    * @param $Path string System file path.
    */
   public function ParseFile($Path) {
      // Only parse Markdown files
      if (pathinfo($Path, PATHINFO_EXTENSION) != 'md')
         return;

      // Name
      $Name = pathinfo($Path, PATHINFO_FILENAME);

      // Code is the first line & it does not go in doc
      $Text = file_get_contents($Path);
      $Parts = explode("\n", $Text);
      $Code = $Parts[0];
      unset($Parts);
      $Text = str_replace($Code."\n",'',$Text);

      // Category is determined by the folder it's in
      $Directory = pathinfo($Path, PATHINFO_DIRNAME);
      $FolderCode = file_get_contents($Directory.'/config');
      $CategoryModel = new CategoryModel();
      $Category = $CategoryModel->GetWhere(array('FolderCode' => $FolderCode))->FirstRow();
      $CategoryID = GetValue('CategoryID', $Category, 1);

      $this->SyncDocDiscussion($Code, $Name, $Text, $CategoryID);

      return $Code;
   }

   /**
    * Sync a file to its discussion.
    *
    * @param $Code string
    * @parm $Name string
    * @param $Body string
    */
   public function SyncDocDiscussion($Code, $Name, $Body, $CategoryID) {
      $DiscussionModel = new DiscussionModel();
      $Document = $DiscussionModel->GetWhere(array('DocumentCode' => $Code))->FirstRow();
      if (!$Document) {
         // Create discussion
         $DiscussionModel->Save(array(
            'Name' => $Name,
            'Body' => $Body,
            'CategoryID' => $CategoryID,
            'Format' => 'Markdown',
            'Type' => 'Doc',
            'InsertUserID' => Gdn::UserModel()->GetSystemUserID(),
            'DocumentCode' => $Code
         ));
      }
      else {
         // Blindly update the discussion
         $DiscussionModel->Update(
            array( // Set
               'Name' => $Name,
               'Body' => $Body,
               'CategoryID' => $CategoryID,
            ),
            array( // Where
               'DiscussionID' => GetValue('DiscussionID', $Document)
            )
         );
      }
   }

   /**
    * Create a simple controller method for displaying docs tree.
    *
    * @param $Sender
    * @todo
    */
//   public function RootController_Docs_Create($Sender) {
//      echo 'Docs go here so they can have a special template';
//   }
   
   /**
    * Plugin setup.
    */
   public function Setup() {
      $this->Structure();
   }

   /**
    * Plugin structure.
    */
   public function Structure() {
      Gdn::Structure()
         ->Table('Discussion')
         ->Column('DocumentCode', 'varchar(255)', TRUE, 'index')
         ->Set();
      Gdn::Structure()
         ->Table('Category')
         ->Column('FolderCode', 'varchar(255)', TRUE, 'index')
         ->Set();
   }
}
