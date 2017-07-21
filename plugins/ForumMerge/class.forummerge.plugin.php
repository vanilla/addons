<?php if (!defined('APPLICATION')) exit();

/**
 * Forum Merge plugin.
 *
 * @todo Allow multiple merges by resetting OldID to NULL before run.
 * @todo Add additional datatypes (noted at end of script)
 */
class ForumMergePlugin implements Gdn_IPlugin {

   /**
    * @var int Limit of rows in a table before structural changes are aborted
    */
   protected $TableRowThreshold = 500000;

   /**
    * Add to the dashboard menu.
    */
   public function Base_GetAppSettingsMenuItems_Handler($sender, $args) {
      $args['SideMenu']->AddLink('Import', T('Merge'), 'utility/merge', 'Garden.Settings.Manage');
   }

   /**
    * Admin screen for merging forums.
    */
   public function UtilityController_Merge_Create($sender) {
      $sender->Permission('Garden.Settings.Manage');
      $sender->AddSideMenu('utility/merge');

      if ($sender->Form->AuthenticatedPostBack()) {
         $database = $sender->Form->GetFormValue('Database');
         $prefix = $sender->Form->GetFormValue('Prefix');
         $legacySlug = $sender->Form->GetFormValue('LegacySlug');
         $this->MergeCategories = ($sender->Form->GetFormValue('MergeCategories')) ? TRUE : FALSE;
         $this->MergeForums($database, $prefix, $legacySlug);
      }

      $sender->Render($sender->FetchViewLocation('merge', '', 'plugins/ForumMerge'));
   }

   /**
    *  Match up columns existing in both target and source tables.
    *
    * @return string CSV list of columns in both copies of the table minus the primary key.
    */
   public function GetColumns($table, $oldDatabase, $oldPrefix, $options = []) {
      Gdn::Structure()->Database->DatabasePrefix = '';
      $oldColumns = Gdn::Structure()->Get($oldDatabase.'.'.$oldPrefix.$table)->Columns();

      Gdn::Structure()->Database->DatabasePrefix = C('Database.DatabasePrefix');
      $newColumns = Gdn::Structure()->Get($table)->Columns();

      $columns = array_intersect_key($oldColumns, $newColumns);
      unset($columns[$table.'ID']);

      if (!empty($options['Legacy'])) {
         unset($columns['ForeignID']);
      }

      return trim(implode(',',array_keys($columns)),',');
   }

   /**
    * Do we have a corresponding table to merge?
    *
    * @param $tableName
    * @return bool
    */
   public function OldTableExists($tableName) {
      return (Gdn::SQL()->Query('SHOW TABLES IN `'.$this->OldDatabase.'` LIKE "'.$this->OldPrefix.$tableName.'"')->NumRows() == 1);
   }

   /**
    * Grab second forum's data and merge with current forum.
    *
    * Merge Users on email address. Keeps this forum's username/password.
    * Merge Roles, Tags, and Categories on precise name matches.
    *
    * @todo Compare column names between forums and use intersection
    */
   public function MergeForums($oldDatabase, $oldPrefix, $legacySlug) {
      $newPrefix = C('Database.DatabasePrefix');
      $this->OldDatabase = $oldDatabase;
      $this->OldPrefix = $oldPrefix;

      $doLegacy = !empty($legacySlug);

      // USERS //
      if ($this->OldTableExists('User')) {
         $userColumns = $this->GetColumns('User', $oldDatabase, $oldPrefix);

         // Merge IDs of duplicate users
         Gdn::SQL()->Query('update '.$newPrefix.'User u set u.OldID =
            (select u2.UserID from `'.$oldDatabase.'`.'.$oldPrefix.'User u2 where u2.Email = u.Email limit 1)');

         // Copy non-duplicate users
         Gdn::SQL()->Query('insert into '.$newPrefix.'User ('.$userColumns.', OldID)
            select '.$userColumns.', UserID
            from `'.$oldDatabase.'`.'.$oldPrefix.'User
            where Email not in (select Email from '.$newPrefix.'User)');

         // UserMeta
         if ($this->OldTableExists('UserMeta')) {
            Gdn::SQL()->Query('insert ignore into '.$newPrefix.'UserMeta (UserID, Name, Value)
               select u.UserID, um.Name, um.Value
               from '.$newPrefix.'User u, `'.$oldDatabase.'`.'.$oldPrefix.'UserMeta um
               where u.OldID = um.UserID');
         }
      }


      // ROLES //
      if ($this->OldTableExists('Role')) {
         $roleColumns = $this->GetColumns('Role', $oldDatabase, $oldPrefix);

         // Merge IDs of duplicate roles
         Gdn::SQL()->Query('update '.$newPrefix.'Role r set r.OldID =
            (select r2.RoleID from `'.$oldDatabase.'`.'.$oldPrefix.'Role r2 where r2.Name = r.Name)');

         // Copy non-duplicate roles
         Gdn::SQL()->Query('insert into '.$newPrefix.'Role ('.$roleColumns.', OldID)
            select '.$roleColumns.', RoleID
            from `'.$oldDatabase.'`.'.$oldPrefix.'Role
            where Name not in (select Name from '.$newPrefix.'Role)');

         // UserRole
         if ($this->OldTableExists('UserRole')) {
            Gdn::SQL()->Query('insert ignore into '.$newPrefix.'UserRole (RoleID, UserID)
               select r.RoleID, u.UserID
               from '.$newPrefix.'User u, '.$newPrefix.'Role r, `'.$oldDatabase.'`.'.$oldPrefix.'UserRole ur
               where u.OldID = (ur.UserID) and r.OldID = (ur.RoleID)');
         }
      }


      // CATEGORIES //
      if ($this->OldTableExists('Category')) {
         $categoryColumnOptions = ['Legacy' => $doLegacy];
         $categoryColumns = $this->GetColumns('Category', $oldDatabase, $oldPrefix, $categoryColumnOptions);

         /*if ($this->MergeCategories) {
            // Merge IDs of duplicate category names
            Gdn::SQL()->Query('update '.$NewPrefix.'Category c set c.OldID =
               (select c2.CategoryID from `'.$OldDatabase.'`.'.$OldPrefix.'Category c2 where c2.Name = c.Name)');

            // Copy non-duplicate categories
            Gdn::SQL()->Query('insert into '.$NewPrefix.'Category ('.$CategoryColumns.', OldID)
               select '.$CategoryColumns.', CategoryID
               from `'.$OldDatabase.'`.'.$OldPrefix.'Category
               where Name not in (select Name from '.$NewPrefix.'Category)');
         }
         else {*/
         // Import categories
         if ($doLegacy) {
            Gdn::SQL()->Query('insert into ' . $newPrefix . 'Category (' . $categoryColumns . ', OldID, ForeignID)
               select ' . $categoryColumns . ', CategoryID, concat(\'' . $legacySlug . '-\', CategoryID)
               from `' . $oldDatabase . '`.' . $oldPrefix . 'Category
               where Name <> "Root"');
         } else {
            Gdn::SQL()->Query('insert into ' . $newPrefix . 'Category (' . $categoryColumns . ', OldID)
               select ' . $categoryColumns . ', CategoryID
               from `' . $oldDatabase . '`.' . $oldPrefix . 'Category
               where Name <> "Root"');
         }

         // Remap hierarchy in the ugliest way possible
         $categoryMap = [];
         $categories = Gdn::SQL()->Select('CategoryID')
            ->Select('ParentCategoryID')
            ->Select('OldID')
            ->From('Category')
            ->Where(['OldID >' => 0])
            ->Get()->Result(DATASET_TYPE_ARRAY);
         foreach ($categories as $category) {
            $categoryMap[$category['OldID']] = $category['CategoryID'];
         }
         foreach ($categories as $category) {
            if ($category['ParentCategoryID'] > 0 && !empty($categoryMap[$category['ParentCategoryID']])) {
               $parentID = $categoryMap[$category['ParentCategoryID']];
               Gdn::SQL()->Update('Category')
                  ->Set(['ParentCategoryID' => $parentID])
                  ->Where(['CategoryID' => $category['CategoryID']])
                  ->Put();
            }
         }
         $categoryModel = new CategoryModel();
         $categoryModel->RebuildTree();

         //}

         // UserCategory

      }


      // DISCUSSIONS //
      if ($this->OldTableExists('Discussion')) {
         $discussionColumnOptions = ['Legacy' => $doLegacy];
         $discussionColumns = $this->GetColumns('Discussion', $oldDatabase, $oldPrefix, $discussionColumnOptions);

         // Copy over all discussions
         if ($doLegacy) {
            Gdn::SQL()->Query('insert into ' . $newPrefix . 'Discussion (' . $discussionColumns . ', OldID, ForeignID)
               select ' . $discussionColumns . ', DiscussionID, concat(\'' . $legacySlug . '-\', DiscussionID)
               from `' . $oldDatabase . '`.' . $oldPrefix . 'Discussion');
         } else {
            Gdn::SQL()->Query('insert into ' . $newPrefix . 'Discussion (' . $discussionColumns . ', OldID)
               select ' . $discussionColumns . ', DiscussionID
               from `' . $oldDatabase . '`.' . $oldPrefix . 'Discussion');
         }

         // Convert imported discussions to use new UserIDs
         Gdn::SQL()->Query('update '.$newPrefix.'Discussion d
           set d.InsertUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = d.InsertUserID)
           where d.OldID > 0');
         Gdn::SQL()->Query('update '.$newPrefix.'Discussion d
           set d.UpdateUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = d.UpdateUserID)
           where d.OldID > 0
             and d.UpdateUserID is not null');
         Gdn::SQL()->Query('update '.$newPrefix.'Discussion d
           set d.CategoryID = (SELECT c.CategoryID from '.$newPrefix.'Category c where c.OldID = d.CategoryID)
           where d.OldID > 0');

         // UserDiscussion
         if ($this->OldTableExists('UserDiscussion')) {
            Gdn::SQL()->Query('insert ignore into '.$newPrefix.'UserDiscussion
                  (DiscussionID, UserID, Score, CountComments, DateLastViewed, Dismissed, Bookmarked)
               select d.DiscussionID, u.UserID, ud.Score, ud.CountComments, ud.DateLastViewed, ud.Dismissed, ud.Bookmarked
               from '.$newPrefix.'User u, '.$newPrefix.'Discussion d, `'.$oldDatabase.'`.'.$oldPrefix.'UserDiscussion ud
               where u.OldID = (ud.UserID) and d.OldID = (ud.DiscussionID)');
         }
      }


      // COMMENTS //
      if ($this->OldTableExists('Comment')) {
         $commentColumnOptions = ['Legacy' => $doLegacy];
         $commentColumns = $this->GetColumns('Comment', $oldDatabase, $oldPrefix, $commentColumnOptions);

         // Copy over all comments
         if ($doLegacy) {
            Gdn::SQL()->Query('insert into ' . $newPrefix . 'Comment (' . $commentColumns . ', OldID, ForeignID)
               select ' . $commentColumns . ', CommentID, concat(\'' . $legacySlug . '-\', CommentID)
               from `' . $oldDatabase . '`.' . $oldPrefix . 'Comment');
         } else {
            Gdn::SQL()->Query('insert into ' . $newPrefix . 'Comment (' . $commentColumns . ', OldID)
               select ' . $commentColumns . ', CommentID
               from `' . $oldDatabase . '`.' . $oldPrefix . 'Comment');
         }

         // Convert imported comments to use new UserIDs
         Gdn::SQL()->Query('update '.$newPrefix.'Comment c
           set c.InsertUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = c.InsertUserID)
           where c.OldID > 0');
         Gdn::SQL()->Query('update '.$newPrefix.'Comment c
           set c.UpdateUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = c.UpdateUserID)
           where c.OldID > 0
             and c.UpdateUserID is not null');

         // Convert imported comments to use new DiscussionIDs
         Gdn::SQL()->Query('update '.$newPrefix.'Comment c
           set c.DiscussionID = (SELECT d.DiscussionID from '.$newPrefix.'Discussion d where d.OldID = c.DiscussionID)
           where c.OldID > 0');
      }


      // MEDIA //
      if ($this->OldTableExists('Media')) {
         $mediaColumns = $this->GetColumns('Media', $oldDatabase, $oldPrefix);

         // Copy over all media
         Gdn::SQL()->Query('insert into '.$newPrefix.'Media ('.$mediaColumns.', OldID)
            select '.$mediaColumns.', MediaID
            from `'.$oldDatabase.'`.'.$oldPrefix.'Media');

         // InsertUserID
         Gdn::SQL()->Query('update '.$newPrefix.'Media m
           set m.InsertUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = m.InsertUserID)
           where m.OldID > 0');

         // ForeignID / ForeignTable
         //Gdn::SQL()->Query('update '.$NewPrefix.'Media m
         //  set m.ForeignID = (SELECT c.CommentID from '.$NewPrefix.'Comment c where c.OldID = m.ForeignID)
         //  where m.OldID > 0 and m.ForeignTable = \'comment\'');
         Gdn::SQL()->Query('update '.$newPrefix.'Media m
           set m.ForeignID = (SELECT d.DiscussionID from '.$newPrefix.'Discussion d where d.OldID = m.ForeignID)
           where m.OldID > 0 and m.ForeignTable = \'discussion\'');
      }


      // CONVERSATION //
      if ($this->OldTableExists('Conversation')) {
         $conversationColumns = $this->GetColumns('Conversation', $oldDatabase, $oldPrefix);

         // Copy over all Conversations
         Gdn::SQL()->Query('insert into '.$newPrefix.'Conversation ('.$conversationColumns.', OldID)
            select '.$conversationColumns.', ConversationID
            from `'.$oldDatabase.'`.'.$oldPrefix.'Conversation');
         // InsertUserID
         Gdn::SQL()->Query('update '.$newPrefix.'Conversation c
           set c.InsertUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = c.InsertUserID)
           where c.OldID > 0');
         // UpdateUserID
         Gdn::SQL()->Query('update '.$newPrefix.'Conversation c
           set c.UpdateUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = c.UpdateUserID)
           where c.OldID > 0');
         // Contributors
         // a. Build userid lookup

         $users = Gdn::SQL()->Query('select UserID, OldID from '.$newPrefix.'User');
         $userIDLookup = [];
         foreach($users->Result() as $user) {
            $oldID = GetValue('OldID', $user);
            $userIDLookup[$oldID] = GetValue('UserID', $user);
         }
         // b. Translate contributor userids
         $conversations = Gdn::SQL()->Query('select ConversationID, Contributors
            from '.$newPrefix.'Conversation
            where Contributors <> ""');
         foreach($conversations->Result() as $conversation) {
            $contributors = dbdecode(GetValue('Contributors', $conversation));
            if (!is_array($contributors))
               continue;
            $updatedContributors = [];
            foreach($contributors as $userID) {
               if (isset($userIDLookup[$userID]))
                  $updatedContributors[] = $userIDLookup[$userID];
            }
            // c. Update each conversation
            $conversationID = GetValue('ConversationID', $conversation);
            Gdn::SQL()->Query('update '.$newPrefix.'Conversation
               set Contributors = "'.mysql_real_escape_string(dbencode($updatedContributors)).'"
               where ConversationID = '.$conversationID);
         }

         // ConversationMessage
         // Copy over all ConversationMessages
         Gdn::SQL()->Query('insert into '.$newPrefix.'ConversationMessage (ConversationID,Body,Format,
               InsertUserID,DateInserted,InsertIPAddress,OldID)
            select ConversationID,Body,Format,InsertUserID,DateInserted,InsertIPAddress,MessageID
            from `'.$oldDatabase.'`.'.$oldPrefix.'ConversationMessage');
         // ConversationID
         Gdn::SQL()->Query('update '.$newPrefix.'ConversationMessage cm
           set cm.ConversationID =
              (SELECT c.ConversationID from '.$newPrefix.'Conversation c where c.OldID = cm.ConversationID)
           where cm.OldID > 0');
         // InsertUserID
         Gdn::SQL()->Query('update '.$newPrefix.'ConversationMessage c
           set c.InsertUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = c.InsertUserID)
           where c.OldID > 0');

         // Conversation FirstMessageID
         Gdn::SQL()->Query('update '.$newPrefix.'Conversation c
           set c.FirstMessageID =
              (SELECT cm.MessageID from '.$newPrefix.'ConversationMessage cm where cm.OldID = c.FirstMessageID)
           where c.OldID > 0');
         // Conversation LastMessageID
         Gdn::SQL()->Query('update '.$newPrefix.'Conversation c
           set c.LastMessageID =
              (SELECT cm.MessageID from '.$newPrefix.'ConversationMessage cm where cm.OldID = c.LastMessageID)
           where c.OldID > 0');

         // UserConversation
         Gdn::SQL()->Query('insert ignore into '.$newPrefix.'UserConversation
               (ConversationID, UserID, CountReadMessages, DateLastViewed, DateCleared,
               Bookmarked, Deleted, DateConversationUpdated)
            select c.ConversationID, u.UserID,  uc.CountReadMessages, uc.DateLastViewed, uc.DateCleared,
               uc.Bookmarked, uc.Deleted, uc.DateConversationUpdated
            from '.$newPrefix.'User u, '.$newPrefix.'Conversation c, `'.$oldDatabase.'`.'.$oldPrefix.'UserConversation uc
            where u.OldID = (uc.UserID) and c.OldID = (uc.ConversationID)');
      }


      // POLLS //
      if ($this->OldTableExists('Poll')) {
         $pollColumns = $this->GetColumns('Poll', $oldDatabase, $oldPrefix);
         $pollOptionColumns = $this->GetColumns('PollOption', $oldDatabase, $oldPrefix);

         // Copy over all polls & options
         Gdn::SQL()->Query('insert into '.$newPrefix.'Poll ('.$pollColumns.', OldID)
            select '.$pollColumns.', PollID
            from `'.$oldDatabase.'`.'.$oldPrefix.'Poll');
         Gdn::SQL()->Query('insert into '.$newPrefix.'PollOption ('.$pollOptionColumns.', OldID)
            select '.$pollOptionColumns.', PollOptionID
            from `'.$oldDatabase.'`.'.$oldPrefix.'PollOption');

         // Convert imported options to use new PollIDs
         Gdn::SQL()->Query('update '.$newPrefix.'PollOption o
           set o.PollID = (SELECT p.DiscussionID from '.$newPrefix.'Poll p where p.OldID = o.PollID)
           where o.OldID > 0');

         // Convert imported polls & options to use new UserIDs
         Gdn::SQL()->Query('update '.$newPrefix.'Poll p
           set p.InsertUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = p.InsertUserID)
           where p.OldID > 0');
         Gdn::SQL()->Query('update '.$newPrefix.'PollOption o
           set o.InsertUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = o.InsertUserID)
           where o.OldID > 0');
      }

      // TAGS //
      if ($this->OldTableExists('Tag')) {
         $tagColumns = $this->GetColumns('Tag', $oldDatabase, $oldPrefix);
         $tagDiscussionColumns = $this->GetColumns('TagDiscussion', $oldDatabase, $oldPrefix);

         // Record reference of source forum tag ID
         Gdn::SQL()->Query('update '.$newPrefix.'Tag t set t.OldID =
            (select t2.TagID from `'.$oldDatabase.'`.'.$oldPrefix.'Tag t2 where t2.Name = t.Name limit 1)');

         // Import tags not present in destination forum
         Gdn::SQL()->Query('insert into '.$newPrefix.'Tag ('.$tagColumns.', OldID)
            select '.$tagColumns.', TagID
            from `'.$oldDatabase.'`.'.$oldPrefix.'Tag
            where Name not in (select Name from '.$newPrefix.'Tag)');

         // TagDiscussion
         if ($this->OldTableExists('TagDiscussion')) {
            // Insert source tag:discussion mapping
            Gdn::SQL()->Query('insert ignore into '.$newPrefix.'TagDiscussion (TagID, DiscussionID, OldCategoryID)
               select t.TagID, d.DiscussionID, td.CategoryID
               from '.$newPrefix.'Tag t, '.$newPrefix.'Discussion d, `'.$oldDatabase.'`.'.$oldPrefix.'TagDiscussion td
               where t.OldID = (td.TagID) and d.OldID = (td.DiscussionID)');

            /**
             * Incoming tags may or may not have CategoryIDs associated with them, so we'll need to update them with a
             * current CategoryID, if applicable, based on the original category ID (OldCategoryID) from the source
             */
            Gdn::SQL()->Query('update '.$newPrefix.'TagDiscussion td set CategoryID =
               (select c.CategoryID from '.$newPrefix.'Category c where c.OldID = td.OldCategoryID limit 1)
               where OldCategoryID > 0');
         }
      }

      ////

      // Draft - new UserIDs
      // Activity - wallpost, activitycomment
      // Tag - new UserID, merge on name
      // TagDiscussion - new DiscussionID, TagID
      // Update counters
      // LastCommentID
   }

   /**
    * Nuke every OldID column before a second merge.
    */
   public function UtilityController_MergeReset_Create() {
      Gdn::SQL()->Update('Activity')->Set('OldID', NULL)->Put();
      Gdn::SQL()->Update('Category')->Set('OldID', NULL)->Put();
      Gdn::SQL()->Update('Comment')->Set('OldID', NULL)->Put();
      Gdn::SQL()->Update('Conversation')->Set('OldID', NULL)->Put();
      Gdn::SQL()->Update('ConversationMessage')->Set('OldID', NULL)->Put();
      Gdn::SQL()->Update('Discussion')->Set('OldID', NULL)->Put();
      Gdn::SQL()->Update('Media')->Set('OldID', NULL)->Put();
      Gdn::SQL()->Update('Role')->Set('OldID', NULL)->Put();
      Gdn::SQL()->Update('Tag')->Set('OldID', NULL)->Put();
      Gdn::SQL()->Update('TagDiscussion')->Set('OldCategoryID', NULL)->Put();
      Gdn::SQL()->Update('User')->Set('OldID', NULL)->Put();

      $construct = Gdn::Database()->Structure();
      $construct->Table('Poll');
      if ($construct->TableExists()) {
         Gdn::SQL()->Update('Poll')->Set('OldID', NULL)->Put();
         Gdn::SQL()->Update('PollOption')->Set('OldID', NULL)->Put();
      }
   }

   public function Setup() {
      $this->Structure();
   }

   public function Structure() {
      $px = Gdn::Database()->DatabasePrefix;

      // Preparing to capture SQL (not execute) for operations that may need to be performed manually by the user
      Gdn::Structure()->CaptureOnly = TRUE;
      Gdn::Structure()->Database->CapturedSql = [];

      // Comment table threshold check
      $currentComments =  GDN::SQL()->Query("show table status where Name = '{$px}Comment'")->FirstRow()->Rows;
      if ($currentComments > $this->TableRowThreshold) { // Does the number of rows exceed the threshold?
         // Execute functions for generating the SQL related to structural updates.  SQL is saved in CapturedSql
         Gdn::Structure()->Table('Comment')->Column('OldID', 'int', true, 'key')
            ->Column('ForeignID', 'varchar(32)', true, 'key')->Set();
      }

      // Allow execution of structural operations
      Gdn::Structure()->CaptureOnly = FALSE;

      /**
       * If any SQL commands were captured, it means we have a problem.  Throw an exception and report the necessary
       * SQL commands back to the user
       */
      $capturedSql = Gdn::Structure()->Database->CapturedSql;
      if (!empty($capturedSql)) {
         throw new Exception(
            "Due to the size of some tables, the following MySQL commands will need to be manually executed:\n" .
            implode("\n", $capturedSql)
         );
      }

      Gdn::Structure()->Table('Activity')->Column('OldID', 'int', TRUE, 'key')->Set();
      Gdn::Structure()->Table('Category')->Column('OldID', 'int', TRUE, 'key')
         ->Column('ForeignID', 'varchar(32)', TRUE, 'key')->Set();
      Gdn::Structure()->Table('Comment')->Column('OldID', 'int', true, 'key')
         ->Column('ForeignID', 'varchar(32)', true, 'key')->Set();
      Gdn::Structure()->Table('Conversation')->Column('OldID', 'int', TRUE, 'key')->Set();
      Gdn::Structure()->Table('ConversationMessage')->Column('OldID', 'int', TRUE, 'key')->Set();
      Gdn::Structure()->Table('Discussion')->Column('OldID', 'int', TRUE, 'key')->Set();
      Gdn::Structure()->Table('Media')->Column('OldID', 'int', TRUE, 'key')->Set();
      Gdn::Structure()->Table('Role')->Column('OldID', 'int', TRUE, 'key')->Set();
      Gdn::Structure()->Table('Tag')->Column('OldID', 'int', TRUE, 'key')->Set();
      Gdn::Structure()->Table('TagDiscussion')->Column('OldCategoryID', 'int', TRUE, 'key')->Set();
      Gdn::Structure()->Table('User')->Column('OldID', 'int', TRUE, 'key')->Set();

      $construct = Gdn::Database()->Structure();
      $construct->Table('Poll');
      if ($construct->TableExists()) {
         Gdn::Structure()->Table('Poll')->Column('OldID', 'int', TRUE, 'key')->Set();
         Gdn::Structure()->Table('PollOption')->Column('OldID', 'int', TRUE, 'key')->Set();
      }
   }
}
