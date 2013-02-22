<?php if (!defined('APPLICATION')) exit();

$PluginInfo['SupportTracker'] = array(
   'Name' => 'Support Tracker',
   'Description' => "Use Vanilla as a support ticket system. Adds: ability to make discussions private 
      between starter and moderators; ability for moderators to mark discussions as 'answered'; 
      ticket number after discussion titles.",
   'Version' => '1.0',
   'MobileFriendly' => TRUE,
   'Author' => "Lincoln Russell",
   'AuthorEmail' => 'lincoln@vanillaforums.com',
   'AuthorUrl' => 'http://lincolnwebs.com'
);

// @todo Claim tickets

class SupportTrackerPlugin extends Gdn_Plugin {
   /**
    * Add 'Unanswered' option to discussion filters.
    */
   public function Base_AfterDiscussionFilters_Handler($Sender, $Args) {
      if (Gdn::Session()->CheckPermission('Garden.Moderation.Manage')) {
         $DiscussionModel = new DiscussionModel();
         $Active = $Controller->RequestMethod == 'unanswered' ? ' Active' : '';
         $Unanswered = Sprite('SpUnansweredQuestionsSpUnansweredQuestions').' '.T('Unanswered').FilterCountString($DiscussionModel->UnansweredCount());
         echo '<li class="Unanswered '.$Active.'">'.Anchor($Unanswered, 'discussions/unanswered').'</li>';
      }
   }
   
   /**
    * Show discussion answered state.
    */
   public function Base_BeforeDiscussionMeta_Handler($Sender, $Args) {
      $Answered = GetValue('Answered', GetValue('Discussion', $Args));
      if (!$Answered)
         echo ' <span class="Tag TagUnanswered">'.T('Unanswered').'</span> ';
      
      $Private = GetValue('PrivateSupport', GetValue('Discussion', $Args));
      if ($Private)
         echo ' <span class="Tag TagPrivate">'.T('Private').'</span> ';
   }
   
   /**
    * Add 'Answered' checkbox to discussion form.
    */
   public function Base_DiscussionFormOptions_Handler($Sender, &$Args) {
      if (CheckPermission('Garden.Moderation.Manage'))
         $Args['Options'] .= '<li>'.$Sender->Form->CheckBox('Answered', T('Answered'), array('value' => '1')).'</li>';
      $Args['Options'] .= '<li>'.$Sender->Form->CheckBox('PrivateSupport', T('CheckboxPrivate', 'Private (select if sharing sensitive information)'), array('value' => '1')).'</li>';
   }   
   
   /**
    * CSS hackery.
    */
   public function Base_Render_Before($Sender) {
      $Sender->AddAsset('Head', '<style>.Tag.TagUnanswered { background: #D00; }</style>');
   }
   
   /**
    * Toggle discussion answered automatically.
    */
   public function CommentModel_BeforeSaveComment_Handler($Sender) {
      // Set based on permission of who answered last.
      $Answered = (CheckPermission('Garden.Moderation.Manage')) ? 1 : 0;
      $DiscussionID = GetValue('DiscussionID', GetValue('FormPostValues', $Sender->EventArguments));
      if (!GetValue('CommentID', $Sender->EventArguments)) { 
         // New comment was made
         Gdn::SQL()->Update('Discussion')
            ->Set('Answered', $Answered)
            ->Where('DiscussionID', $DiscussionID)
            ->Put();
      }
   }
   
   /**
    * Suffix discussion name with DiscussionID in single view.
    */
   public function DiscussionController_BeforeDiscussionOptions_Handler($Sender, $Args) {
      $Discussion = $Sender->Data('Discussion');
      $NewName = GetValue('Name', $Discussion).' ['.GetValue('DiscussionID', $Discussion).']';
      SetValue('Name', $Discussion, $NewName);
      $Sender->SetData('Discussion', $Discussion);
   }
   
   /**
    * Deny access to private support discussions.
    */
   public function DiscussionController_Render_Before($Sender, $Args) {
      // Get category & discussion data
      $Category = CategoryModel::Categories($Sender->Data('CategoryID'));
      $Discussion = CategoryModel::Categories($Sender->Data('CategoryID'));
      
      // Evaluate conditions
      $Private = (GetValue('PrivateSupport', $Category)) ? TRUE : FALSE;
      $Mine = (GetValue('InsertUserID', $Discussion) == Gdn::Session()->UserID) ? TRUE : FALSE;
      
      // Deny access to private support discussions
      if (!$Mine && $Private)
         $Sender->Permission('Garden.Moderation.Manage');
   }
      
   /**
    * Toggle discussion answered manually.
    */
   public function DiscussionController_Answered_Create($Sender, $Args) {
      $Sender->DeliveryType(DELIVERY_TYPE_BOOL);
      list($DiscussionID, $TransientKey) = $Args;
      $Session = Gdn::Session();
      $DiscussionModel = new DiscussionModel();
      
      if (
         is_numeric($DiscussionID)
         && $DiscussionID > 0
         && $Session->UserID > 0
         && $Session->ValidateTransientKey($TransientKey)
      )
         $DiscussionModel->AnswerDiscussion($DiscussionID);
      
      // Redirect back where the user came from if necessary
      if ($Sender->DeliveryType() === DELIVERY_TYPE_ALL)
         Redirect(GetIncomingValue('Target', 'discussions/unanswered'));
         
      $Sender->InformMessage(T('Your changes have been saved.'));
      $Sender->Render();
   }
   
   /**
    * Add 'Un/Answered' to discussion options menu.
    */
   public function DiscussionController_DiscussionOptions_Handler($Sender, $Args) {
      $Discussion = $Args['Discussion'];
      $Args['DiscussionOptions']['DeleteDiscussion'] = array(
         'Label' => T($Discussion->Answered ? 'Unanswered' : 'Answered'), 
         'Url' => 'vanilla/discussion/answered/'.$Discussion->DiscussionID.'/'.Gdn::Session()->TransientKey().'?Target='.urlencode($Sender->SelfUrl.'#Head'), 
         'Class' => 'Hijack'
      );
   }
   
   /**
    * Suffix discussion names with DiscussionID in list.
    */
   public function DiscussionsController_AfterDiscussionTitle_Handler($Sender, $Args) {
      echo ' ['.GetValue('DiscussionID', GetValue('Discussion', $Args)).']';
   }
   
   /**
    * Unanswered discussions list.
    */
   public function DiscussionsController_Unanswered_Create($Sender, $Args) {
      $Sender->Permission('Garden.Moderation.Manage');
      $Page = ArrayValue(0, $Args, 0);
      
      // Determine offset from $Page
      list($Page, $Limit) = OffsetLimit($Page, C('Vanilla.Discussions.PerPage', 30));
      
      // Validate $Page
      if (!is_numeric($Page) || $Page < 0)
         $Page = 0;
      
      $DiscussionModel = new DiscussionModel();
      $Wheres = array('d.Answered' => '0');
      
      $Sender->DiscussionData = $DiscussionModel->Get($Page, $Limit, $Wheres);
      $Sender->SetData('Discussions', $Sender->DiscussionData);
      $CountDiscussions = $DiscussionModel->GetCount($Wheres);
      $Sender->SetData('CountDiscussions', $CountDiscussions);
      $Sender->Category = FALSE;
      
      $Sender->SetJson('Loading', $Page . ' to ' . $Limit);
      
      // Build a pager
      $PagerFactory = new Gdn_PagerFactory();
		$Sender->EventArguments['PagerType'] = 'Pager';
		$Sender->FireEvent('BeforeBuildBookmarkedPager');
      $Sender->Pager = $PagerFactory->GetPager($Sender->EventArguments['PagerType'], $Sender);
      $Sender->Pager->ClientID = 'Pager';
      $Sender->Pager->Configure(
         $Page,
         $Limit,
         $CountDiscussions,
         'discussions/unanswered/%1$s'
      );
      
      if (!$Sender->Data('_PagerUrl'))
         $Sender->SetData('_PagerUrl', 'discussions/unanswered/{Page}');
      $Sender->SetData('_Page', $Page);
      $Sender->SetData('_Limit', $Limit);
		$Sender->FireEvent('AfterBuildBookmarkedPager');
      
      // Deliver JSON data if necessary
      if ($Sender->DeliveryType() != DELIVERY_TYPE_ALL) {
         $Sender->SetJson('LessRow', $Sender->Pager->ToString('less'));
         $Sender->SetJson('MoreRow', $Sender->Pager->ToString('more'));
         $Sender->View = 'discussions';
      }
      
      // Add modules
      $Sender->AddModule('DiscussionFilterModule');
      $Sender->AddModule('NewDiscussionModule');
      $Sender->AddModule('CategoriesModule');
      
      // Render default view (discussions/bookmarked.php)
      $Sender->SetData('Title', T('Unanswered'));
		$Sender->SetData('Breadcrumbs', array(array('Name' => T('Unanswered'), 'Url' => '/discussions/unanswered')));
      $Sender->Render('index');
   }
      
   /**
    * Toggle discussion's Answered value.
    */
   public function DiscussionModel_AnswerDiscussion_Create($Sender, $Args) {
      $DiscussionID = ArrayValue(0, $Sender->EventArguments, '');
		if (CheckPermission('Garden.Moderation.Manage')) {
         $Discussion = $Sender->GetID($DiscussionID);
         $State = (GetValue('Answered', $Discussion) == '1' ? '0' : '1');
         Gdn::SQL()->Update('Discussion')
            ->Set('Answered', $State)
            ->Where('DiscussionID', $DiscussionID)
            ->Put();
      }
      
      return $State == '1' ? TRUE : FALSE;
   }
   
   /**
    * Do not show private support discussions.
    */
   public function DiscussionModel_BeforeGet_Handler($Sender) {
      if (!CheckPermission('Garden.Moderation.Manage')) { 
         $Sender->SQL
            ->BeginWhereGroup()
            ->Where('d.PrivateSupport', 0) 
            ->OrWhere('d.InsertUserID', Gdn::Session()->UserID)
            ->EndWhereGroup();
      }
   }
   
   /**
    * Set user email preference & 'PrivateSupport' when discussion started via VanillaPop.
    */
   public function DiscussionModel_BeforeSaveDiscussion_Handler($Sender, &$Args) {
      $Data = GetValue('FormPostValues', $Args);
      if (GetValue('Insert', $Args) && GetValue('Subject', $Data) && GetValue('From', $Data)) {
         $Args['FormPostValues']['PrivateSupport'] = 1;
         if ($UserID = GetValue('InsertUserID', $Data))
            Gdn::UserModel()->SavePreference($UserID, 'Notifications.Email.DiscussionComment', '1');
      }
   }
   
   /**
    * Count unanswered discussions.
    */
   public function DiscussionModel_UnansweredCount_Create($Sender) {
      $Data = $Sender->SQL
         ->Select('d.DiscussionID', 'count', 'Count')
         ->From('Discussion d')
         ->Where('d.Answered', '0')
         ->Get()
         ->FirstRow();
      
      return $Data !== FALSE ? $Data->Count : 0;
   }
   
   /**
    * Executes once when enabled.
    */
   public function Setup() {
      // Add Discussion.Answered toggle
      Gdn::Database()->Structure()->Table('Discussion')
         ->Column('Answered', 'tinyint(1)', 0)
         ->Column('PrivateSupport', 'tinyint(1)', 0)
         ->Set();
         
      // Set existing discussions to Answered
      Gdn::SQL()->Update('Discussion')->Set('Answered', 1)->Put();
      
      // Set default pref
      SaveToConfig('Preferences.Email.DiscussionComment', '1');
   }
}