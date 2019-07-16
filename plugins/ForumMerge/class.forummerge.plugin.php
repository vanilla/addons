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
   public function base_getAppSettingsMenuItems_handler($sender, $args) {
      $args['SideMenu']->addLink('Import', t('Merge'), 'utility/merge', 'Garden.Settings.Manage');
   }

   /**
    * Admin screen for merging forums.
    */
   public function utilityController_merge_create($sender) {
      $sender->permission('Garden.Settings.Manage');
      $sender->addSideMenu('utility/merge');

      if ($sender->Form->authenticatedPostBack()) {
          if ($this->validateForm($sender->Form)) {
              $database = $sender->Form->getFormValue('Database');
              $prefix = $sender->Form->getFormValue('Prefix');
              $legacySlug = $sender->Form->getFormValue('LegacySlug');
              $this->MergeCategories = ($sender->Form->getFormValue('MergeCategories')) ? TRUE : FALSE;
              $this->mergeForums($database, $prefix, $legacySlug);
          }
      }

      $sender->render($sender->fetchViewLocation('merge', '', 'plugins/ForumMerge'));
   }

    /**
     * Validate form values.
     *
     * @param Gdn_Form $form
     * @return bool
     */
   private function validateForm(Gdn_Form &$form): bool {
       //Validate form value 'Database', other form values sanitized through PDO->prepare when query
       $oldDatabase = trim(str_replace('`', '', $form->getFormValue('Database')));
       $res = array_column(Gdn::sql()->query('SHOW DATABASES;')->resultArray(), 'Database');
       if (!in_array($oldDatabase, $res)) {
           $form->addError(t('Database not found.'), 'Database');
       } else {
           $form->setFormValue('Database', $oldDatabase);
       }
       return empty($form->errors());
   }


   /**
    *  Match up columns existing in both target and source tables.
    *
    * @return string CSV list of columns in both copies of the table minus the primary key.
    */
   public function getColumns($table, $oldDatabase, $oldPrefix, $options = []) {
      Gdn::structure()->Database->DatabasePrefix = '';
      $oldColumns = Gdn::structure()->get($oldDatabase.'.'.$oldPrefix.$table)->columns();

      Gdn::structure()->Database->DatabasePrefix = c('Database.DatabasePrefix');
      $newColumns = Gdn::structure()->get($table)->columns();

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
   public function oldTableExists($tableName) {
      return (Gdn::sql()->query('SHOW TABLES IN `'.$this->OldDatabase.'` LIKE "'.$this->OldPrefix.$tableName.'"')->numRows() == 1);
   }

   /**
    * Grab second forum's data and merge with current forum.
    *
    * Merge Users on email address. Keeps this forum's username/password.
    * Merge Roles, Tags, and Categories on precise name matches.
    *
    * @todo Compare column names between forums and use intersection
    */
   public function mergeForums($oldDatabase, $oldPrefix, $legacySlug) {
      $newPrefix = c('Database.DatabasePrefix');
      $this->OldDatabase = $oldDatabase;
      $this->OldPrefix = $oldPrefix;

      $doLegacy = !empty($legacySlug);

      // USERS //
      if ($this->oldTableExists('User')) {
         $userColumns = $this->getColumns('User', $oldDatabase, $oldPrefix);

         // Merge IDs of duplicate users
         Gdn::sql()->query('update '.$newPrefix.'User u set u.OldID =
            (select u2.UserID from `'.$oldDatabase.'`.'.$oldPrefix.'User u2 where u2.Email = u.Email limit 1)');

         // Copy non-duplicate users
         Gdn::sql()->query('insert into '.$newPrefix.'User ('.$userColumns.', OldID)
            select '.$userColumns.', UserID
            from `'.$oldDatabase.'`.'.$oldPrefix.'User
            where Email not in (select Email from '.$newPrefix.'User)');

         // UserMeta
         if ($this->oldTableExists('UserMeta')) {
            Gdn::sql()->query('insert ignore into '.$newPrefix.'UserMeta (UserID, Name, Value)
               select u.UserID, um.Name, um.Value
               from '.$newPrefix.'User u, `'.$oldDatabase.'`.'.$oldPrefix.'UserMeta um
               where u.OldID = um.UserID');
         }
      }


      // ROLES //
      if ($this->oldTableExists('Role')) {
         $roleColumns = $this->getColumns('Role', $oldDatabase, $oldPrefix);

         // Merge IDs of duplicate roles
         Gdn::sql()->query('update '.$newPrefix.'Role r set r.OldID =
            (select r2.RoleID from `'.$oldDatabase.'`.'.$oldPrefix.'Role r2 where r2.Name = r.Name)');

         // Copy non-duplicate roles
         Gdn::sql()->query('insert into '.$newPrefix.'Role ('.$roleColumns.', OldID)
            select '.$roleColumns.', RoleID
            from `'.$oldDatabase.'`.'.$oldPrefix.'Role
            where Name not in (select Name from '.$newPrefix.'Role)');

         // UserRole
         if ($this->oldTableExists('UserRole')) {
            Gdn::sql()->query('insert ignore into '.$newPrefix.'UserRole (RoleID, UserID)
               select r.RoleID, u.UserID
               from '.$newPrefix.'User u, '.$newPrefix.'Role r, `'.$oldDatabase.'`.'.$oldPrefix.'UserRole ur
               where u.OldID = (ur.UserID) and r.OldID = (ur.RoleID)');
         }
      }


      // CATEGORIES //
      if ($this->oldTableExists('Category')) {
         $categoryColumnOptions = ['Legacy' => $doLegacy];
         $categoryColumns = $this->getColumns('Category', $oldDatabase, $oldPrefix, $categoryColumnOptions);

         /*if ($this->MergeCategories) {
            // Merge IDs of duplicate category names
            Gdn::sql()->query('update '.$NewPrefix.'Category c set c.OldID =
               (select c2.CategoryID from `'.$OldDatabase.'`.'.$OldPrefix.'Category c2 where c2.Name = c.Name)');

            // Copy non-duplicate categories
            Gdn::sql()->query('insert into '.$NewPrefix.'Category ('.$CategoryColumns.', OldID)
               select '.$CategoryColumns.', CategoryID
               from `'.$OldDatabase.'`.'.$OldPrefix.'Category
               where Name not in (select Name from '.$NewPrefix.'Category)');
         }
         else {*/
         // Import categories
         if ($doLegacy) {
            Gdn::sql()->query('insert into ' . $newPrefix . 'Category (' . $categoryColumns . ', OldID, ForeignID)
               select ' . $categoryColumns . ', CategoryID, concat(\'' . $legacySlug . '-\', CategoryID)
               from `' . $oldDatabase . '`.' . $oldPrefix . 'Category
               where Name <> "Root"');
         } else {
            Gdn::sql()->query('insert into ' . $newPrefix . 'Category (' . $categoryColumns . ', OldID)
               select ' . $categoryColumns . ', CategoryID
               from `' . $oldDatabase . '`.' . $oldPrefix . 'Category
               where Name <> "Root"');
         }

         // Remap hierarchy in the ugliest way possible
         $categoryMap = [];
         $categories = Gdn::sql()->select('CategoryID')
            ->select('ParentCategoryID')
            ->select('OldID')
            ->from('Category')
            ->where(['OldID >' => 0])
            ->get()->result(DATASET_TYPE_ARRAY);
         foreach ($categories as $category) {
            $categoryMap[$category['OldID']] = $category['CategoryID'];
         }
         foreach ($categories as $category) {
            if ($category['ParentCategoryID'] > 0 && !empty($categoryMap[$category['ParentCategoryID']])) {
               $parentID = $categoryMap[$category['ParentCategoryID']];
               Gdn::sql()->update('Category')
                  ->set(['ParentCategoryID' => $parentID])
                  ->where(['CategoryID' => $category['CategoryID']])
                  ->put();
            }
         }
         $categoryModel = new CategoryModel();
         $categoryModel->rebuildTree();

         //}

         // UserCategory

      }


      // DISCUSSIONS //
      if ($this->oldTableExists('Discussion')) {
         $discussionColumnOptions = ['Legacy' => $doLegacy];
         $discussionColumns = $this->getColumns('Discussion', $oldDatabase, $oldPrefix, $discussionColumnOptions);

         // Copy over all discussions
         if ($doLegacy) {
            Gdn::sql()->query('insert into ' . $newPrefix . 'Discussion (' . $discussionColumns . ', OldID, ForeignID)
               select ' . $discussionColumns . ', DiscussionID, concat(\'' . $legacySlug . '-\', DiscussionID)
               from `' . $oldDatabase . '`.' . $oldPrefix . 'Discussion');
         } else {
            Gdn::sql()->query('insert into ' . $newPrefix . 'Discussion (' . $discussionColumns . ', OldID)
               select ' . $discussionColumns . ', DiscussionID
               from `' . $oldDatabase . '`.' . $oldPrefix . 'Discussion');
         }

         // Convert imported discussions to use new UserIDs
         Gdn::sql()->query('update '.$newPrefix.'Discussion d
           set d.InsertUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = d.InsertUserID)
           where d.OldID > 0');
         Gdn::sql()->query('update '.$newPrefix.'Discussion d
           set d.UpdateUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = d.UpdateUserID)
           where d.OldID > 0
             and d.UpdateUserID is not null');
         Gdn::sql()->query('update '.$newPrefix.'Discussion d
           set d.CategoryID = (SELECT c.CategoryID from '.$newPrefix.'Category c where c.OldID = d.CategoryID)
           where d.OldID > 0');

         // UserDiscussion
         if ($this->oldTableExists('UserDiscussion')) {
            Gdn::sql()->query('insert ignore into '.$newPrefix.'UserDiscussion
                  (DiscussionID, UserID, Score, CountComments, DateLastViewed, Dismissed, Bookmarked)
               select d.DiscussionID, u.UserID, ud.Score, ud.CountComments, ud.DateLastViewed, ud.Dismissed, ud.Bookmarked
               from '.$newPrefix.'User u, '.$newPrefix.'Discussion d, `'.$oldDatabase.'`.'.$oldPrefix.'UserDiscussion ud
               where u.OldID = (ud.UserID) and d.OldID = (ud.DiscussionID)');
         }
      }


      // COMMENTS //
      if ($this->oldTableExists('Comment')) {
         $commentColumnOptions = ['Legacy' => $doLegacy];
         $commentColumns = $this->getColumns('Comment', $oldDatabase, $oldPrefix, $commentColumnOptions);

         // Copy over all comments
         if ($doLegacy) {
            Gdn::sql()->query('insert into ' . $newPrefix . 'Comment (' . $commentColumns . ', OldID, ForeignID)
               select ' . $commentColumns . ', CommentID, concat(\'' . $legacySlug . '-\', CommentID)
               from `' . $oldDatabase . '`.' . $oldPrefix . 'Comment');
         } else {
            Gdn::sql()->query('insert into ' . $newPrefix . 'Comment (' . $commentColumns . ', OldID)
               select ' . $commentColumns . ', CommentID
               from `' . $oldDatabase . '`.' . $oldPrefix . 'Comment');
         }

         // Convert imported comments to use new UserIDs
         Gdn::sql()->query('update '.$newPrefix.'Comment c
           set c.InsertUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = c.InsertUserID)
           where c.OldID > 0');
         Gdn::sql()->query('update '.$newPrefix.'Comment c
           set c.UpdateUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = c.UpdateUserID)
           where c.OldID > 0
             and c.UpdateUserID is not null');

         // Convert imported comments to use new DiscussionIDs
         Gdn::sql()->query('update '.$newPrefix.'Comment c
           set c.DiscussionID = (SELECT d.DiscussionID from '.$newPrefix.'Discussion d where d.OldID = c.DiscussionID)
           where c.OldID > 0');
      }


      // MEDIA //
      if ($this->oldTableExists('Media')) {
         $mediaColumns = $this->getColumns('Media', $oldDatabase, $oldPrefix);

         // Copy over all media
         Gdn::sql()->query('insert into '.$newPrefix.'Media ('.$mediaColumns.', OldID)
            select '.$mediaColumns.', MediaID
            from `'.$oldDatabase.'`.'.$oldPrefix.'Media');

         // InsertUserID
         Gdn::sql()->query('update '.$newPrefix.'Media m
           set m.InsertUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = m.InsertUserID)
           where m.OldID > 0');

         // ForeignID / ForeignTable
         //Gdn::sql()->query('update '.$NewPrefix.'Media m
         //  set m.ForeignID = (SELECT c.CommentID from '.$NewPrefix.'Comment c where c.OldID = m.ForeignID)
         //  where m.OldID > 0 and m.ForeignTable = \'comment\'');
         Gdn::sql()->query('update '.$newPrefix.'Media m
           set m.ForeignID = (SELECT d.DiscussionID from '.$newPrefix.'Discussion d where d.OldID = m.ForeignID)
           where m.OldID > 0 and m.ForeignTable = \'discussion\'');
      }


      // CONVERSATION //
      if ($this->oldTableExists('Conversation')) {
         $conversationColumns = $this->getColumns('Conversation', $oldDatabase, $oldPrefix);

         // Copy over all Conversations
         Gdn::sql()->query('insert into '.$newPrefix.'Conversation ('.$conversationColumns.', OldID)
            select '.$conversationColumns.', ConversationID
            from `'.$oldDatabase.'`.'.$oldPrefix.'Conversation');
         // InsertUserID
         Gdn::sql()->query('update '.$newPrefix.'Conversation c
           set c.InsertUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = c.InsertUserID)
           where c.OldID > 0');
         // UpdateUserID
         Gdn::sql()->query('update '.$newPrefix.'Conversation c
           set c.UpdateUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = c.UpdateUserID)
           where c.OldID > 0');
         // Contributors
         // a. Build userid lookup

         $users = Gdn::sql()->query('select UserID, OldID from '.$newPrefix.'User');
         $userIDLookup = [];
         foreach($users->result() as $user) {
            $oldID = getValue('OldID', $user);
            $userIDLookup[$oldID] = getValue('UserID', $user);
         }
         // b. Translate contributor userids
         $conversations = Gdn::sql()->query('select ConversationID, Contributors
            from '.$newPrefix.'Conversation
            where Contributors <> ""');
         foreach($conversations->result() as $conversation) {
            $contributors = dbdecode(getValue('Contributors', $conversation));
            if (!is_array($contributors))
               continue;
            $updatedContributors = [];
            foreach($contributors as $userID) {
               if (isset($userIDLookup[$userID]))
                  $updatedContributors[] = $userIDLookup[$userID];
            }
            // c. Update each conversation
            $conversationID = getValue('ConversationID', $conversation);
            Gdn::sql()->query('update '.$newPrefix.'Conversation
               set Contributors = "'.mysql_real_escape_string(dbencode($updatedContributors)).'"
               where ConversationID = '.$conversationID);
         }

         // ConversationMessage
         // Copy over all ConversationMessages
         Gdn::sql()->query('insert into '.$newPrefix.'ConversationMessage (ConversationID,Body,Format,
               InsertUserID,DateInserted,InsertIPAddress,OldID)
            select ConversationID,Body,Format,InsertUserID,DateInserted,InsertIPAddress,MessageID
            from `'.$oldDatabase.'`.'.$oldPrefix.'ConversationMessage');
         // ConversationID
         Gdn::sql()->query('update '.$newPrefix.'ConversationMessage cm
           set cm.ConversationID =
              (SELECT c.ConversationID from '.$newPrefix.'Conversation c where c.OldID = cm.ConversationID)
           where cm.OldID > 0');
         // InsertUserID
         Gdn::sql()->query('update '.$newPrefix.'ConversationMessage c
           set c.InsertUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = c.InsertUserID)
           where c.OldID > 0');

         // Conversation FirstMessageID
         Gdn::sql()->query('update '.$newPrefix.'Conversation c
           set c.FirstMessageID =
              (SELECT cm.MessageID from '.$newPrefix.'ConversationMessage cm where cm.OldID = c.FirstMessageID)
           where c.OldID > 0');
         // Conversation LastMessageID
         Gdn::sql()->query('update '.$newPrefix.'Conversation c
           set c.LastMessageID =
              (SELECT cm.MessageID from '.$newPrefix.'ConversationMessage cm where cm.OldID = c.LastMessageID)
           where c.OldID > 0');

         // UserConversation
         Gdn::sql()->query('insert ignore into '.$newPrefix.'UserConversation
               (ConversationID, UserID, CountReadMessages, DateLastViewed, DateCleared,
               Bookmarked, Deleted, DateConversationUpdated)
            select c.ConversationID, u.UserID,  uc.CountReadMessages, uc.DateLastViewed, uc.DateCleared,
               uc.Bookmarked, uc.Deleted, uc.DateConversationUpdated
            from '.$newPrefix.'User u, '.$newPrefix.'Conversation c, `'.$oldDatabase.'`.'.$oldPrefix.'UserConversation uc
            where u.OldID = (uc.UserID) and c.OldID = (uc.ConversationID)');
      }


      // POLLS //
      if ($this->oldTableExists('Poll')) {
         $pollColumns = $this->getColumns('Poll', $oldDatabase, $oldPrefix);
         $pollOptionColumns = $this->getColumns('PollOption', $oldDatabase, $oldPrefix);

         // Copy over all polls & options
         Gdn::sql()->query('insert into '.$newPrefix.'Poll ('.$pollColumns.', OldID)
            select '.$pollColumns.', PollID
            from `'.$oldDatabase.'`.'.$oldPrefix.'Poll');
         Gdn::sql()->query('insert into '.$newPrefix.'PollOption ('.$pollOptionColumns.', OldID)
            select '.$pollOptionColumns.', PollOptionID
            from `'.$oldDatabase.'`.'.$oldPrefix.'PollOption');

         // Convert imported options to use new PollIDs
         Gdn::sql()->query('update '.$newPrefix.'PollOption o
           set o.PollID = (SELECT p.DiscussionID from '.$newPrefix.'Poll p where p.OldID = o.PollID)
           where o.OldID > 0');

         // Convert imported polls & options to use new UserIDs
         Gdn::sql()->query('update '.$newPrefix.'Poll p
           set p.InsertUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = p.InsertUserID)
           where p.OldID > 0');
         Gdn::sql()->query('update '.$newPrefix.'PollOption o
           set o.InsertUserID = (SELECT u.UserID from '.$newPrefix.'User u where u.OldID = o.InsertUserID)
           where o.OldID > 0');
      }

      // TAGS //
      if ($this->oldTableExists('Tag')) {
         $tagColumns = $this->getColumns('Tag', $oldDatabase, $oldPrefix);
         $tagDiscussionColumns = $this->getColumns('TagDiscussion', $oldDatabase, $oldPrefix);

         // Record reference of source forum tag ID
         Gdn::sql()->query('update '.$newPrefix.'Tag t set t.OldID =
            (select t2.TagID from `'.$oldDatabase.'`.'.$oldPrefix.'Tag t2 where t2.Name = t.Name limit 1)');

         // Import tags not present in destination forum
         Gdn::sql()->query('insert into '.$newPrefix.'Tag ('.$tagColumns.', OldID)
            select '.$tagColumns.', TagID
            from `'.$oldDatabase.'`.'.$oldPrefix.'Tag
            where Name not in (select Name from '.$newPrefix.'Tag)');

         // TagDiscussion
         if ($this->oldTableExists('TagDiscussion')) {
            // Insert source tag:discussion mapping
            Gdn::sql()->query('insert ignore into '.$newPrefix.'TagDiscussion (TagID, DiscussionID, OldCategoryID)
               select t.TagID, d.DiscussionID, td.CategoryID
               from '.$newPrefix.'Tag t, '.$newPrefix.'Discussion d, `'.$oldDatabase.'`.'.$oldPrefix.'TagDiscussion td
               where t.OldID = (td.TagID) and d.OldID = (td.DiscussionID)');

            /**
             * Incoming tags may or may not have CategoryIDs associated with them, so we'll need to update them with a
             * current CategoryID, if applicable, based on the original category ID (OldCategoryID) from the source
             */
            Gdn::sql()->query('update '.$newPrefix.'TagDiscussion td set CategoryID =
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
   public function utilityController_mergeReset_create() {
      Gdn::sql()->update('Activity')->set('OldID', NULL)->put();
      Gdn::sql()->update('Category')->set('OldID', NULL)->put();
      Gdn::sql()->update('Comment')->set('OldID', NULL)->put();
      Gdn::sql()->update('Conversation')->set('OldID', NULL)->put();
      Gdn::sql()->update('ConversationMessage')->set('OldID', NULL)->put();
      Gdn::sql()->update('Discussion')->set('OldID', NULL)->put();
      Gdn::sql()->update('Media')->set('OldID', NULL)->put();
      Gdn::sql()->update('Role')->set('OldID', NULL)->put();
      Gdn::sql()->update('Tag')->set('OldID', NULL)->put();
      Gdn::sql()->update('TagDiscussion')->set('OldCategoryID', NULL)->put();
      Gdn::sql()->update('User')->set('OldID', NULL)->put();

      $construct = Gdn::database()->structure();
      $construct->table('Poll');
      if ($construct->tableExists()) {
         Gdn::sql()->update('Poll')->set('OldID', NULL)->put();
         Gdn::sql()->update('PollOption')->set('OldID', NULL)->put();
      }
   }

   public function setup() {
      $this->structure();
   }

   public function structure() {
      $px = Gdn::database()->DatabasePrefix;

      // Preparing to capture SQL (not execute) for operations that may need to be performed manually by the user
      Gdn::structure()->CaptureOnly = TRUE;
      Gdn::structure()->Database->CapturedSql = [];

      // Comment table threshold check
      $currentComments =  GDN::sql()->query("show table status where Name = '{$px}Comment'")->firstRow()->Rows;
      if ($currentComments > $this->TableRowThreshold) { // Does the number of rows exceed the threshold?
         // Execute functions for generating the SQL related to structural updates.  SQL is saved in CapturedSql
         Gdn::structure()->table('Comment')->column('OldID', 'int', true, 'key')
            ->column('ForeignID', 'varchar(32)', true, 'key')->set();
      }

      // Allow execution of structural operations
      Gdn::structure()->CaptureOnly = FALSE;

      /**
       * If any SQL commands were captured, it means we have a problem.  Throw an exception and report the necessary
       * SQL commands back to the user
       */
      $capturedSql = Gdn::structure()->Database->CapturedSql;
      if (!empty($capturedSql)) {
         throw new Exception(
            "Due to the size of some tables, the following MySQL commands will need to be manually executed:\n" .
            implode("\n", $capturedSql)
         );
      }

      Gdn::structure()->table('Activity')->column('OldID', 'int', TRUE, 'key')->set();
      Gdn::structure()->table('Category')->column('OldID', 'int', TRUE, 'key')
         ->column('ForeignID', 'varchar(32)', TRUE, 'key')->set();
      Gdn::structure()->table('Comment')->column('OldID', 'int', true, 'key')
         ->column('ForeignID', 'varchar(32)', true, 'key')->set();
      Gdn::structure()->table('Conversation')->column('OldID', 'int', TRUE, 'key')->set();
      Gdn::structure()->table('ConversationMessage')->column('OldID', 'int', TRUE, 'key')->set();
      Gdn::structure()->table('Discussion')->column('OldID', 'int', TRUE, 'key')->set();
      Gdn::structure()->table('Media')->column('OldID', 'int', TRUE, 'key')->set();
      Gdn::structure()->table('Role')->column('OldID', 'int', TRUE, 'key')->set();
      Gdn::structure()->table('Tag')->column('OldID', 'int', TRUE, 'key')->set();
      Gdn::structure()->table('TagDiscussion')->column('OldCategoryID', 'int', TRUE, 'key')->set();
      Gdn::structure()->table('User')->column('OldID', 'int', TRUE, 'key')->set();

      $construct = Gdn::database()->structure();
      $construct->table('Poll');
      if ($construct->tableExists()) {
         Gdn::structure()->table('Poll')->column('OldID', 'int', TRUE, 'key')->set();
         Gdn::structure()->table('PollOption')->column('OldID', 'int', TRUE, 'key')->set();
      }
   }
}
