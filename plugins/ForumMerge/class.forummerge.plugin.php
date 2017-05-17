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
   public function Base_GetAppSettingsMenuItems_Handler($Sender, $Args) {
      $Args['SideMenu']->AddLink('Import', T('Merge'), 'utility/merge', 'Garden.Settings.Manage');
   }

   /**
    * Admin screen for merging forums.
    */
   public function UtilityController_Merge_Create($Sender) {
      $Sender->Permission('Garden.Settings.Manage');
      $Sender->AddSideMenu('utility/merge');

      if ($Sender->Form->AuthenticatedPostBack()) {
         $Database = $Sender->Form->GetFormValue('Database');
         $Prefix = $Sender->Form->GetFormValue('Prefix');
         $LegacySlug = $Sender->Form->GetFormValue('LegacySlug');
         $this->MergeCategories = ($Sender->Form->GetFormValue('MergeCategories')) ? TRUE : FALSE;
         $this->MergeForums($Database, $Prefix, $LegacySlug);
      }

      $Sender->Render($Sender->FetchViewLocation('merge', '', 'plugins/ForumMerge'));
   }

   /**
    *  Match up columns existing in both target and source tables.
    *
    * @return string CSV list of columns in both copies of the table minus the primary key.
    */
   public function GetColumns($Table, $OldDatabase, $OldPrefix, $Options = array()) {
      Gdn::Structure()->Database->DatabasePrefix = '';
      $OldColumns = Gdn::Structure()->Get($OldDatabase.'.'.$OldPrefix.$Table)->Columns();

      Gdn::Structure()->Database->DatabasePrefix = C('Database.DatabasePrefix');
      $NewColumns = Gdn::Structure()->Get($Table)->Columns();

      $Columns = array_intersect_key($OldColumns, $NewColumns);
      unset($Columns[$Table.'ID']);

      if (!empty($Options['Legacy'])) {
         unset($Columns['ForeignID']);
      }

      return trim(implode(',',array_keys($Columns)),',');
   }

   /**
    * Do we have a corresponding table to merge?
    *
    * @param $TableName
    * @return bool
    */
   public function OldTableExists($TableName) {
      return (Gdn::SQL()->Query('SHOW TABLES IN `'.$this->OldDatabase.'` LIKE "'.$this->OldPrefix.$TableName.'"')->NumRows() == 1);
   }

   /**
    * Grab second forum's data and merge with current forum.
    *
    * Merge Users on email address. Keeps this forum's username/password.
    * Merge Roles, Tags, and Categories on precise name matches.
    *
    * @todo Compare column names between forums and use intersection
    */
   public function MergeForums($OldDatabase, $OldPrefix, $LegacySlug) {
      $NewPrefix = C('Database.DatabasePrefix');
      $this->OldDatabase = $OldDatabase;
      $this->OldPrefix = $OldPrefix;

      $DoLegacy = !empty($LegacySlug);

      // USERS //
      if ($this->OldTableExists('User')) {
         $UserColumns = $this->GetColumns('User', $OldDatabase, $OldPrefix);

         // Merge IDs of duplicate users
         Gdn::SQL()->Query('update '.$NewPrefix.'User u set u.OldID =
            (select u2.UserID from `'.$OldDatabase.'`.'.$OldPrefix.'User u2 where u2.Email = u.Email limit 1)');

         // Copy non-duplicate users
         Gdn::SQL()->Query('insert into '.$NewPrefix.'User ('.$UserColumns.', OldID)
            select '.$UserColumns.', UserID
            from `'.$OldDatabase.'`.'.$OldPrefix.'User
            where Email not in (select Email from '.$NewPrefix.'User)');

         // UserMeta
         if ($this->OldTableExists('UserMeta')) {
            Gdn::SQL()->Query('insert ignore into '.$NewPrefix.'UserMeta (UserID, Name, Value)
               select u.UserID, um.Name, um.Value
               from '.$NewPrefix.'User u, `'.$OldDatabase.'`.'.$OldPrefix.'UserMeta um
               where u.OldID = um.UserID');
         }
      }


      // ROLES //
      if ($this->OldTableExists('Role')) {
         $RoleColumns = $this->GetColumns('Role', $OldDatabase, $OldPrefix);

         // Merge IDs of duplicate roles
         Gdn::SQL()->Query('update '.$NewPrefix.'Role r set r.OldID =
            (select r2.RoleID from `'.$OldDatabase.'`.'.$OldPrefix.'Role r2 where r2.Name = r.Name)');

         // Copy non-duplicate roles
         Gdn::SQL()->Query('insert into '.$NewPrefix.'Role ('.$RoleColumns.', OldID)
            select '.$RoleColumns.', RoleID
            from `'.$OldDatabase.'`.'.$OldPrefix.'Role
            where Name not in (select Name from '.$NewPrefix.'Role)');

         // UserRole
         if ($this->OldTableExists('UserRole')) {
            Gdn::SQL()->Query('insert ignore into '.$NewPrefix.'UserRole (RoleID, UserID)
               select r.RoleID, u.UserID
               from '.$NewPrefix.'User u, '.$NewPrefix.'Role r, `'.$OldDatabase.'`.'.$OldPrefix.'UserRole ur
               where u.OldID = (ur.UserID) and r.OldID = (ur.RoleID)');
         }
      }


      // CATEGORIES //
      if ($this->OldTableExists('Category')) {
         $CategoryColumnOptions = array('Legacy' => $DoLegacy);
         $CategoryColumns = $this->GetColumns('Category', $OldDatabase, $OldPrefix, $CategoryColumnOptions);

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
         if ($DoLegacy) {
            Gdn::SQL()->Query('insert into ' . $NewPrefix . 'Category (' . $CategoryColumns . ', OldID, ForeignID)
               select ' . $CategoryColumns . ', CategoryID, concat(\'' . $LegacySlug . '-\', CategoryID)
               from `' . $OldDatabase . '`.' . $OldPrefix . 'Category
               where Name <> "Root"');
         } else {
            Gdn::SQL()->Query('insert into ' . $NewPrefix . 'Category (' . $CategoryColumns . ', OldID)
               select ' . $CategoryColumns . ', CategoryID
               from `' . $OldDatabase . '`.' . $OldPrefix . 'Category
               where Name <> "Root"');
         }

         // Remap hierarchy in the ugliest way possible
         $CategoryMap = array();
         $Categories = Gdn::SQL()->Select('CategoryID')
            ->Select('ParentCategoryID')
            ->Select('OldID')
            ->From('Category')
            ->Where(array('OldID >' => 0))
            ->Get()->Result(DATASET_TYPE_ARRAY);
         foreach ($Categories as $Category) {
            $CategoryMap[$Category['OldID']] = $Category['CategoryID'];
         }
         foreach ($Categories as $Category) {
            if ($Category['ParentCategoryID'] > 0 && !empty($CategoryMap[$Category['ParentCategoryID']])) {
               $ParentID = $CategoryMap[$Category['ParentCategoryID']];
               Gdn::SQL()->Update('Category')
                  ->Set(array('ParentCategoryID' => $ParentID))
                  ->Where(array('CategoryID' => $Category['CategoryID']))
                  ->Put();
            }
         }
         $CategoryModel = new CategoryModel();
         $CategoryModel->RebuildTree();

         //}

         // UserCategory

      }


      // DISCUSSIONS //
      if ($this->OldTableExists('Discussion')) {
         $DiscussionColumnOptions = array('Legacy' => $DoLegacy);
         $DiscussionColumns = $this->GetColumns('Discussion', $OldDatabase, $OldPrefix, $DiscussionColumnOptions);

         // Copy over all discussions
         if ($DoLegacy) {
            Gdn::SQL()->Query('insert into ' . $NewPrefix . 'Discussion (' . $DiscussionColumns . ', OldID, ForeignID)
               select ' . $DiscussionColumns . ', DiscussionID, concat(\'' . $LegacySlug . '-\', DiscussionID)
               from `' . $OldDatabase . '`.' . $OldPrefix . 'Discussion');
         } else {
            Gdn::SQL()->Query('insert into ' . $NewPrefix . 'Discussion (' . $DiscussionColumns . ', OldID)
               select ' . $DiscussionColumns . ', DiscussionID
               from `' . $OldDatabase . '`.' . $OldPrefix . 'Discussion');
         }

         // Convert imported discussions to use new UserIDs
         Gdn::SQL()->Query('update '.$NewPrefix.'Discussion d
           set d.InsertUserID = (SELECT u.UserID from '.$NewPrefix.'User u where u.OldID = d.InsertUserID)
           where d.OldID > 0');
         Gdn::SQL()->Query('update '.$NewPrefix.'Discussion d
           set d.UpdateUserID = (SELECT u.UserID from '.$NewPrefix.'User u where u.OldID = d.UpdateUserID)
           where d.OldID > 0
             and d.UpdateUserID is not null');
         Gdn::SQL()->Query('update '.$NewPrefix.'Discussion d
           set d.CategoryID = (SELECT c.CategoryID from '.$NewPrefix.'Category c where c.OldID = d.CategoryID)
           where d.OldID > 0');

         // UserDiscussion
         if ($this->OldTableExists('UserDiscussion')) {
            Gdn::SQL()->Query('insert ignore into '.$NewPrefix.'UserDiscussion
                  (DiscussionID, UserID, Score, CountComments, DateLastViewed, Dismissed, Bookmarked)
               select d.DiscussionID, u.UserID, ud.Score, ud.CountComments, ud.DateLastViewed, ud.Dismissed, ud.Bookmarked
               from '.$NewPrefix.'User u, '.$NewPrefix.'Discussion d, `'.$OldDatabase.'`.'.$OldPrefix.'UserDiscussion ud
               where u.OldID = (ud.UserID) and d.OldID = (ud.DiscussionID)');
         }
      }


      // COMMENTS //
      if ($this->OldTableExists('Comment')) {
         $CommentColumnOptions = array('Legacy' => $DoLegacy);
         $CommentColumns = $this->GetColumns('Comment', $OldDatabase, $OldPrefix, $CommentColumnOptions);

         // Copy over all comments
         if ($DoLegacy) {
            Gdn::SQL()->Query('insert into ' . $NewPrefix . 'Comment (' . $CommentColumns . ', OldID, ForeignID)
               select ' . $CommentColumns . ', CommentID, concat(\'' . $LegacySlug . '-\', CommentID)
               from `' . $OldDatabase . '`.' . $OldPrefix . 'Comment');
         } else {
            Gdn::SQL()->Query('insert into ' . $NewPrefix . 'Comment (' . $CommentColumns . ', OldID)
               select ' . $CommentColumns . ', CommentID
               from `' . $OldDatabase . '`.' . $OldPrefix . 'Comment');
         }

         // Convert imported comments to use new UserIDs
         Gdn::SQL()->Query('update '.$NewPrefix.'Comment c
           set c.InsertUserID = (SELECT u.UserID from '.$NewPrefix.'User u where u.OldID = c.InsertUserID)
           where c.OldID > 0');
         Gdn::SQL()->Query('update '.$NewPrefix.'Comment c
           set c.UpdateUserID = (SELECT u.UserID from '.$NewPrefix.'User u where u.OldID = c.UpdateUserID)
           where c.OldID > 0
             and c.UpdateUserID is not null');

         // Convert imported comments to use new DiscussionIDs
         Gdn::SQL()->Query('update '.$NewPrefix.'Comment c
           set c.DiscussionID = (SELECT d.DiscussionID from '.$NewPrefix.'Discussion d where d.OldID = c.DiscussionID)
           where c.OldID > 0');
      }


      // MEDIA //
      if ($this->OldTableExists('Media')) {
         $MediaColumns = $this->GetColumns('Media', $OldDatabase, $OldPrefix);

         // Copy over all media
         Gdn::SQL()->Query('insert into '.$NewPrefix.'Media ('.$MediaColumns.', OldID)
            select '.$MediaColumns.', MediaID
            from `'.$OldDatabase.'`.'.$OldPrefix.'Media');

         // InsertUserID
         Gdn::SQL()->Query('update '.$NewPrefix.'Media m
           set m.InsertUserID = (SELECT u.UserID from '.$NewPrefix.'User u where u.OldID = m.InsertUserID)
           where m.OldID > 0');

         // ForeignID / ForeignTable
         //Gdn::SQL()->Query('update '.$NewPrefix.'Media m
         //  set m.ForeignID = (SELECT c.CommentID from '.$NewPrefix.'Comment c where c.OldID = m.ForeignID)
         //  where m.OldID > 0 and m.ForeignTable = \'comment\'');
         Gdn::SQL()->Query('update '.$NewPrefix.'Media m
           set m.ForeignID = (SELECT d.DiscussionID from '.$NewPrefix.'Discussion d where d.OldID = m.ForeignID)
           where m.OldID > 0 and m.ForeignTable = \'discussion\'');
      }


      // CONVERSATION //
      if ($this->OldTableExists('Conversation')) {
         $ConversationColumns = $this->GetColumns('Conversation', $OldDatabase, $OldPrefix);

         // Copy over all Conversations
         Gdn::SQL()->Query('insert into '.$NewPrefix.'Conversation ('.$ConversationColumns.', OldID)
            select '.$ConversationColumns.', ConversationID
            from `'.$OldDatabase.'`.'.$OldPrefix.'Conversation');
         // InsertUserID
         Gdn::SQL()->Query('update '.$NewPrefix.'Conversation c
           set c.InsertUserID = (SELECT u.UserID from '.$NewPrefix.'User u where u.OldID = c.InsertUserID)
           where c.OldID > 0');
         // UpdateUserID
         Gdn::SQL()->Query('update '.$NewPrefix.'Conversation c
           set c.UpdateUserID = (SELECT u.UserID from '.$NewPrefix.'User u where u.OldID = c.UpdateUserID)
           where c.OldID > 0');
         // Contributors
         // a. Build userid lookup

         $Users = Gdn::SQL()->Query('select UserID, OldID from '.$NewPrefix.'User');
         $UserIDLookup = array();
         foreach($Users->Result() as $User) {
            $OldID = GetValue('OldID', $User);
            $UserIDLookup[$OldID] = GetValue('UserID', $User);
         }
         // b. Translate contributor userids
         $Conversations = Gdn::SQL()->Query('select ConversationID, Contributors
            from '.$NewPrefix.'Conversation
            where Contributors <> ""');
         foreach($Conversations->Result() as $Conversation) {
            $Contributors = dbdecode(GetValue('Contributors', $Conversation));
            if (!is_array($Contributors))
               continue;
            $UpdatedContributors = array();
            foreach($Contributors as $UserID) {
               if (isset($UserIDLookup[$UserID]))
                  $UpdatedContributors[] = $UserIDLookup[$UserID];
            }
            // c. Update each conversation
            $ConversationID = GetValue('ConversationID', $Conversation);
            Gdn::SQL()->Query('update '.$NewPrefix.'Conversation
               set Contributors = "'.mysql_real_escape_string(dbencode($UpdatedContributors)).'"
               where ConversationID = '.$ConversationID);
         }

         // ConversationMessage
         // Copy over all ConversationMessages
         Gdn::SQL()->Query('insert into '.$NewPrefix.'ConversationMessage (ConversationID,Body,Format,
               InsertUserID,DateInserted,InsertIPAddress,OldID)
            select ConversationID,Body,Format,InsertUserID,DateInserted,InsertIPAddress,MessageID
            from `'.$OldDatabase.'`.'.$OldPrefix.'ConversationMessage');
         // ConversationID
         Gdn::SQL()->Query('update '.$NewPrefix.'ConversationMessage cm
           set cm.ConversationID =
              (SELECT c.ConversationID from '.$NewPrefix.'Conversation c where c.OldID = cm.ConversationID)
           where cm.OldID > 0');
         // InsertUserID
         Gdn::SQL()->Query('update '.$NewPrefix.'ConversationMessage c
           set c.InsertUserID = (SELECT u.UserID from '.$NewPrefix.'User u where u.OldID = c.InsertUserID)
           where c.OldID > 0');

         // Conversation FirstMessageID
         Gdn::SQL()->Query('update '.$NewPrefix.'Conversation c
           set c.FirstMessageID =
              (SELECT cm.MessageID from '.$NewPrefix.'ConversationMessage cm where cm.OldID = c.FirstMessageID)
           where c.OldID > 0');
         // Conversation LastMessageID
         Gdn::SQL()->Query('update '.$NewPrefix.'Conversation c
           set c.LastMessageID =
              (SELECT cm.MessageID from '.$NewPrefix.'ConversationMessage cm where cm.OldID = c.LastMessageID)
           where c.OldID > 0');

         // UserConversation
         Gdn::SQL()->Query('insert ignore into '.$NewPrefix.'UserConversation
               (ConversationID, UserID, CountReadMessages, DateLastViewed, DateCleared,
               Bookmarked, Deleted, DateConversationUpdated)
            select c.ConversationID, u.UserID,  uc.CountReadMessages, uc.DateLastViewed, uc.DateCleared,
               uc.Bookmarked, uc.Deleted, uc.DateConversationUpdated
            from '.$NewPrefix.'User u, '.$NewPrefix.'Conversation c, `'.$OldDatabase.'`.'.$OldPrefix.'UserConversation uc
            where u.OldID = (uc.UserID) and c.OldID = (uc.ConversationID)');
      }


      // POLLS //
      if ($this->OldTableExists('Poll')) {
         $PollColumns = $this->GetColumns('Poll', $OldDatabase, $OldPrefix);
         $PollOptionColumns = $this->GetColumns('PollOption', $OldDatabase, $OldPrefix);

         // Copy over all polls & options
         Gdn::SQL()->Query('insert into '.$NewPrefix.'Poll ('.$PollColumns.', OldID)
            select '.$PollColumns.', PollID
            from `'.$OldDatabase.'`.'.$OldPrefix.'Poll');
         Gdn::SQL()->Query('insert into '.$NewPrefix.'PollOption ('.$PollOptionColumns.', OldID)
            select '.$PollOptionColumns.', PollOptionID
            from `'.$OldDatabase.'`.'.$OldPrefix.'PollOption');

         // Convert imported options to use new PollIDs
         Gdn::SQL()->Query('update '.$NewPrefix.'PollOption o
           set o.PollID = (SELECT p.DiscussionID from '.$NewPrefix.'Poll p where p.OldID = o.PollID)
           where o.OldID > 0');

         // Convert imported polls & options to use new UserIDs
         Gdn::SQL()->Query('update '.$NewPrefix.'Poll p
           set p.InsertUserID = (SELECT u.UserID from '.$NewPrefix.'User u where u.OldID = p.InsertUserID)
           where p.OldID > 0');
         Gdn::SQL()->Query('update '.$NewPrefix.'PollOption o
           set o.InsertUserID = (SELECT u.UserID from '.$NewPrefix.'User u where u.OldID = o.InsertUserID)
           where o.OldID > 0');
      }

      // TAGS //
      if ($this->OldTableExists('Tag')) {
         $TagColumns = $this->GetColumns('Tag', $OldDatabase, $OldPrefix);
         $TagDiscussionColumns = $this->GetColumns('TagDiscussion', $OldDatabase, $OldPrefix);

         // Record reference of source forum tag ID
         Gdn::SQL()->Query('update '.$NewPrefix.'Tag t set t.OldID =
            (select t2.TagID from `'.$OldDatabase.'`.'.$OldPrefix.'Tag t2 where t2.Name = t.Name limit 1)');

         // Import tags not present in destination forum
         Gdn::SQL()->Query('insert into '.$NewPrefix.'Tag ('.$TagColumns.', OldID)
            select '.$TagColumns.', TagID
            from `'.$OldDatabase.'`.'.$OldPrefix.'Tag
            where Name not in (select Name from '.$NewPrefix.'Tag)');

         // TagDiscussion
         if ($this->OldTableExists('TagDiscussion')) {
            // Insert source tag:discussion mapping
            Gdn::SQL()->Query('insert ignore into '.$NewPrefix.'TagDiscussion (TagID, DiscussionID, OldCategoryID)
               select t.TagID, d.DiscussionID, td.CategoryID
               from '.$NewPrefix.'Tag t, '.$NewPrefix.'Discussion d, `'.$OldDatabase.'`.'.$OldPrefix.'TagDiscussion td
               where t.OldID = (td.TagID) and d.OldID = (td.DiscussionID)');

            /**
             * Incoming tags may or may not have CategoryIDs associated with them, so we'll need to update them with a
             * current CategoryID, if applicable, based on the original category ID (OldCategoryID) from the source
             */
            Gdn::SQL()->Query('update '.$NewPrefix.'TagDiscussion td set CategoryID =
               (select c.CategoryID from '.$NewPrefix.'Category c where c.OldID = td.OldCategoryID limit 1)
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

      $Construct = Gdn::Database()->Structure();
      $Construct->Table('Poll');
      if ($Construct->TableExists()) {
         Gdn::SQL()->Update('Poll')->Set('OldID', NULL)->Put();
         Gdn::SQL()->Update('PollOption')->Set('OldID', NULL)->Put();
      }
   }

   public function Setup() {
      $this->Structure();
   }

   public function Structure() {
      $Px = Gdn::Database()->DatabasePrefix;

      // Preparing to capture SQL (not execute) for operations that may need to be performed manually by the user
      Gdn::Structure()->CaptureOnly = TRUE;
      Gdn::Structure()->Database->CapturedSql = array();

      // Comment table threshold check
      $CurrentComments =  GDN::SQL()->Query("show table status where Name = '{$Px}Comment'")->FirstRow()->Rows;
      if ($CurrentComments > $this->TableRowThreshold) { // Does the number of rows exceed the threshold?
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
      $CapturedSql = Gdn::Structure()->Database->CapturedSql;
      if (!empty($CapturedSql)) {
         throw new Exception(
            "Due to the size of some tables, the following MySQL commands will need to be manually executed:\n" .
            implode("\n", $CapturedSql)
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

      $Construct = Gdn::Database()->Structure();
      $Construct->Table('Poll');
      if ($Construct->TableExists()) {
         Gdn::Structure()->Table('Poll')->Column('OldID', 'int', TRUE, 'key')->Set();
         Gdn::Structure()->Table('PollOption')->Column('OldID', 'int', TRUE, 'key')->Set();
      }
   }
}
