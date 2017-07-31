<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

class VotingPlugin extends Gdn_Plugin {
	/**
	 * Admin Toggle to turn Voting on/off
	 */
   public function Base_GetAppSettingsMenuItems_Handler($sender) {
      $menu = &$sender->EventArguments['SideMenu'];
      $menu->AddItem('Forum', T('Forum'));
      $menu->AddLink('Forum', T('Voting'), 'settings/voting', 'Garden.Settings.Manage');
   }
   public function SettingsController_Voting_Create($sender) {
      $sender->Permission('Garden.Settings.Manage');
      $conf = new ConfigurationModule($sender);
		$conf->Initialize([
			'Plugins.Voting.ModThreshold1' => ['Type' => 'int', 'Control' => 'TextBox', 'Default' => -10, 'Description' => 'The vote that will flag a post for moderation.'],
			'Plugins.Voting.ModThreshold2' => ['Type' => 'int', 'Control' => 'TextBox', 'Default' => -20, 'Description' => 'The vote that will remove a post to the moderation queue.']
		]);

     $sender->AddSideMenu('settings/voting');
     $sender->SetData('Title', T('Vote Settings'));
     $sender->ConfigurationModule = $conf;
     $conf->RenderAll();
   }
   public function SettingsController_ToggleVoting_Create($sender) {
      $sender->Permission('Garden.Settings.Manage');
      if (Gdn::Session()->ValidateTransientKey(GetValue(0, $sender->RequestArgs)))
         SaveToConfig('Plugins.Voting.Enabled', C('Plugins.Voting.Enabled') ? FALSE : TRUE);

      redirectTo('settings/voting');
   }

	/**
	 * Add JS & CSS to the page.
	 */
   public function AddJsCss($sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      $sender->AddCSSFile('voting.css', 'plugins/Voting');
		$sender->AddJSFile('voting.js', 'plugins/Voting');
   }
	public function DiscussionsController_Render_Before($sender) {
		$this->AddJsCss($sender);
	}
   public function CategoriesController_Render_Before($sender) {
      $this->AddJsCss($sender);
   }

	/**
	 * Add the "Stats" buttons to the discussion list.
	 */
	public function Base_BeforeDiscussionContent_Handler($sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

		$session = Gdn::Session();
		$discussion = GetValue('Discussion', $sender->EventArguments);
		// Answers
		$css = 'StatBox AnswersBox';
		if ($discussion->CountComments > 1)
			$css .= ' HasAnswersBox';

		$countVotes = 0;
		if (is_numeric($discussion->Score)) // && $Discussion->Score > 0)
			$countVotes = $discussion->Score;

		if (!is_numeric($discussion->CountBookmarks))
			$discussion->CountBookmarks = 0;

		echo Wrap(
			// Anchor(
			Wrap(T('Comments')) . Gdn_Format::BigNumber($discussion->CountComments)
			// ,'/discussion/'.$Discussion->DiscussionID.'/'.Gdn_Format::Url($Discussion->Name).($Discussion->CountCommentWatch > 0 ? '/#Item_'.$Discussion->CountCommentWatch : '')
			// )
			, 'div', ['class' => $css]);

		// Views
		echo Wrap(
			// Anchor(
			Wrap(T('Views')) . Gdn_Format::BigNumber($discussion->CountViews)
			// , '/discussion/'.$Discussion->DiscussionID.'/'.Gdn_Format::Url($Discussion->Name).($Discussion->CountCommentWatch > 0 ? '/#Item_'.$Discussion->CountCommentWatch : '')
			// )
			, 'div', ['class' => 'StatBox ViewsBox']);

		// Follows
		$title = T($discussion->Bookmarked == '1' ? 'Unbookmark' : 'Bookmark');
		if ($session->IsValid()) {
			echo Wrap(Anchor(
				Wrap(T('Follows')) . Gdn_Format::BigNumber($discussion->CountBookmarks),
				'/discussion/bookmark/'.$discussion->DiscussionID.'/'.$session->TransientKey().'?Target='.urlencode($sender->SelfUrl),
				'',
				['title' => $title]
			), 'div', ['class' => 'StatBox FollowsBox']);
		} else {
			echo Wrap(Wrap(T('Follows')) . $discussion->CountBookmarks, 'div', ['class' => 'StatBox FollowsBox']);
		}

		// Votes
		if ($session->IsValid()) {
			echo Wrap(Anchor(
				Wrap(T('Votes')) . Gdn_Format::BigNumber($countVotes),
				'/discussion/votediscussion/'.$discussion->DiscussionID.'/'.$session->TransientKey().'?Target='.urlencode($sender->SelfUrl),
				'',
				['title' => T('Vote')]
			), 'div', ['class' => 'StatBox VotesBox']);
		} else {
			echo Wrap(Wrap(T('Votes')) . $countVotes, 'div', ['class' => 'StatBox VotesBox']);
		}
	}

   /**
	 * Sort the comments by popularity if necessary
    * @param CommentModel $commentModel
	 */
   public function CommentModel_AfterConstruct_Handler($commentModel) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      $sort = self::CommentSort();

      switch (strtolower($sort)) {
         case 'date':
            $commentModel->OrderBy('c.DateInserted');
            break;
         case 'popular':
         default:
            $commentModel->OrderBy(['coalesce(c.Score, 0) desc', 'c.CommentID']);
            break;
      }
   }

   protected static $_CommentSort;
   public static function CommentSort() {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      if (self::$_CommentSort)
         return self::$_CommentSort;

      $sort = GetIncomingValue('Sort', '');
      if (Gdn::Session()->IsValid()) {
         if ($sort == '') {
            // No sort was specified so grab it from the user's preferences.
            $sort = Gdn::Session()->GetPreference('Plugins.Voting.CommentSort', 'popular');
         } else {
            // Save the sort to the user's preferences.
            Gdn::Session()->SetPreference('Plugins.Voting.CommentSort', $sort == 'popular' ? '' : $sort);
         }
      }

      if (!in_array($sort, ['popular', 'date']))
         $sort = 'popular';
      self::$_CommentSort = $sort;
      return $sort;
   }

	/**
	 * Insert sorting tabs after first comment.
	 */
	public function DiscussionController_BeforeCommentDisplay_Handler($sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

		$answerCount = $sender->Discussion->CountComments - 1;
		$type = GetValue('Type', $sender->EventArguments, 'Comment');
		if ($type == 'Comment' && !GetValue('VoteHeaderWritten', $sender)) { //$Type != 'Comment' && $AnswerCount > 0) {
		?>
		<li class="Item">
			<div class="VotingSort">
			<?php
			echo
				Wrap($answerCount.' '.Plural($answerCount, 'Comment', 'Comments'), 'strong');
				echo ' sorted by '
               .Anchor('Votes', Url(DiscussionUrl($sender->Discussion).'?Sort=popular', TRUE), '', ['rel' => 'nofollow', 'class' => self::CommentSort() == 'popular' ? 'Active' : ''])
               .' '
               .Anchor('Date Added', Url(DiscussionUrl($sender->Discussion).'?Sort=date', TRUE), '', ['rel' => 'nofollow', 'class' => self::CommentSort() == 'date' ? 'Active' : '']);
			?>
			</div>
		</li>
		<?php
      $sender->VoteHeaderWritten = TRUE;
		}
	}

	public function DiscussionController_AfterCommentMeta_Handler($sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;
/*
		echo '<span class="Votes">';
			$Session = Gdn::Session();
			$Object = GetValue('Object', $Sender->EventArguments);
			$VoteType = $Sender->EventArguments['Type'] == 'Discussion' ? 'votediscussion' : 'votecomment';
			$ID = $Sender->EventArguments['Type'] == 'Discussion' ? $Object->DiscussionID : $Object->CommentID;
			$CssClass = '';
			$VoteUpUrl = '/discussion/'.$VoteType.'/'.$ID.'/voteup/'.$Session->TransientKey().'/';
			$VoteDownUrl = '/discussion/'.$VoteType.'/'.$ID.'/votedown/'.$Session->TransientKey().'/';
			if (!$Session->IsValid()) {
				$VoteUpUrl = Gdn::Authenticator()->SignInUrl($Sender->SelfUrl);
				$VoteDownUrl = $VoteUpUrl;
				$CssClass = ' SignInPopup';
			}
			echo Anchor(Wrap(Wrap('Vote Up', 'i'), 'i', array('class' => 'ArrowSprite SpriteUp', 'rel' => 'nofollow')), $VoteUpUrl, 'VoteUp'.$CssClass);
			echo Wrap(StringIsNullOrEmpty($Object->Score) ? '0' : Gdn_Format::BigNumber($Object->Score));
			echo Anchor(Wrap(Wrap('Vote Down', 'i'), 'i', array('class' => 'ArrowSprite SpriteDown', 'rel' => 'nofollow')), $VoteDownUrl, 'VoteDown'.$CssClass);
		echo '</span>';

 */

      $session = Gdn::Session();
      $object = GetValue('Object', $sender->EventArguments);
      $voteType = $sender->EventArguments['Type'] == 'Discussion' ? 'votediscussion' : 'votecomment';
      $iD = $sender->EventArguments['Type'] == 'Discussion' ? $object->DiscussionID : $object->CommentID;
      $cssClass = '';
      $voteUpUrl = '/discussion/'.$voteType.'/'.$iD.'/voteup/'.$session->TransientKey().'/';
      $voteDownUrl = '/discussion/'.$voteType.'/'.$iD.'/votedown/'.$session->TransientKey().'/';
      if (!$session->IsValid()) {
         $voteUpUrl = Gdn::Authenticator()->SignInUrl($sender->SelfUrl);
         $voteDownUrl = $voteUpUrl;
         $cssClass = ' SignInPopup';
      }

      echo '<span class="Voter">';
			echo Anchor(Wrap(Wrap('Vote Up', 'i'), 'i', ['class' => 'ArrowSprite SpriteUp', 'rel' => 'nofollow']), $voteUpUrl, 'VoteUp'.$cssClass);
			echo Wrap(StringIsNullOrEmpty($object->Score) ? '0' : Gdn_Format::BigNumber($object->Score));
			echo Anchor(Wrap(Wrap('Vote Down', 'i'), 'i', ['class' => 'ArrowSprite SpriteDown', 'rel' => 'nofollow']), $voteDownUrl, 'VoteDown'.$cssClass);
		echo '</span>';
 }


   /**
	 * Add the vote.js file to discussions page, and handle sorting of answers.
	 */
   public function DiscussionController_Render_Before($sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      $this->AddJsCss($sender);
   }


   /**
    * Increment/decrement comment scores
    */
   public function DiscussionController_VoteComment_Create($sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      $commentID = GetValue(0, $sender->RequestArgs, 0);
      $voteType = GetValue(1, $sender->RequestArgs);
      $transientKey = GetValue(2, $sender->RequestArgs);
      $session = Gdn::Session();
      $finalVote = 0;
      $total = 0;
      if ($session->IsValid() && $session->ValidateTransientKey($transientKey) && $commentID > 0) {
         $commentModel = new CommentModel();
         $oldUserVote = $commentModel->GetUserScore($commentID, $session->UserID);
         $newUserVote = $voteType == 'voteup' ? 1 : -1;
         $finalVote = intval($oldUserVote) + intval($newUserVote);
         // Allow admins to vote unlimited.
         $allowVote = $session->CheckPermission('Garden.Moderation.Manage');
         // Only allow users to vote up or down by 1.
         if (!$allowVote)
            $allowVote = $finalVote > -2 && $finalVote < 2;

         if ($allowVote)
            $total = $commentModel->SetUserScore($commentID, $session->UserID, $finalVote);

         // Move the comment into or out of moderation.
         if (class_exists('LogModel')) {
            $moderate = FALSE;

            if ($total <= C('Plugins.Voting.ModThreshold1', -10)) {
               $logOptions = ['GroupBy' => ['RecordID']];
               // Get the comment row.
               $data = $commentModel->GetID($commentID, DATASET_TYPE_ARRAY);
               if ($data) {
                  // Get the users that voted the comment down.
                  $otherUserIDs = $commentModel->SQL
                     ->Select('UserID')
                     ->From('UserComment')
                     ->Where('CommentID', $commentID)
                     ->Where('Score <', 0)
                     ->Get()->ResultArray();
                  $otherUserIDs = array_column($otherUserIDs, 'UserID');
                  $logOptions['OtherUserIDs'] = $otherUserIDs;

                  // Add the comment to moderation.
                  if ($total > C('Plugins.Voting.ModThreshold2', -20))
                     LogModel::Insert('Moderate', 'Comment', $data, $logOptions);
               }
               $moderate = TRUE;
            }
            if ($total <= C('Plugins.Voting.ModThreshold2', -20)) {
               // Remove the comment.
               $commentModel->Delete($commentID, ['Log' => 'Moderate']);

               $sender->InformMessage(sprintf(T('The %s has been removed for moderation.'), T('comment')));
            } elseif ($moderate) {
               $sender->InformMessage(sprintf(T('The %s has been flagged for moderation.'), T('comment')));
            }
         }
      }
      $sender->DeliveryType(DELIVERY_TYPE_BOOL);
      $sender->SetJson('TotalScore', $total);
      $sender->SetJson('FinalVote', $finalVote);
      $sender->Render();
   }

   /**
    * Increment/decrement discussion scores
    */
   public function DiscussionController_VoteDiscussion_Create($sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      $discussionID = GetValue(0, $sender->RequestArgs, 0);
      $transientKey = GetValue(1, $sender->RequestArgs);
      $voteType = FALSE;
      if ($transientKey == 'voteup' || $transientKey == 'votedown') {
         $voteType = $transientKey;
         $transientKey = GetValue(2, $sender->RequestArgs);
      }
      $session = Gdn::Session();
      $newUserVote = 0;
      $total = 0;
      if ($session->IsValid() && $session->ValidateTransientKey($transientKey) && $discussionID > 0) {
         $discussionModel = new DiscussionModel();
         $oldUserVote = $discussionModel->GetUserScore($discussionID, $session->UserID);

         if ($voteType == 'voteup')
            $newUserVote = 1;
         else if ($voteType == 'votedown')
            $newUserVote = -1;
         else
            $newUserVote = $oldUserVote == 1 ? -1 : 1;

         $finalVote = intval($oldUserVote) + intval($newUserVote);
         // Allow admins to vote unlimited.
         $allowVote = $session->CheckPermission('Garden.Moderation.Manage');
         // Only allow users to vote up or down by 1.
         if (!$allowVote)
            $allowVote = $finalVote > -2 && $finalVote < 2;

         if ($allowVote) {
            $total = $discussionModel->SetUserScore($discussionID, $session->UserID, $finalVote);
         } else {
				$discussion = $discussionModel->GetID($discussionID);
				$total = GetValue('Score', $discussion, 0);
				$finalVote = $oldUserVote;
			}

         // Move the comment into or out of moderation.
         if (class_exists('LogModel')) {
            $moderate = FALSE;

            if ($total <= C('Plugins.Voting.ModThreshold1', -10)) {
               $logOptions = ['GroupBy' => ['RecordID']];
               // Get the comment row.
               if (isset($discussion))
                  $data = (array)$discussion;
               else
                  $data = (array)$discussionModel->GetID($discussionID);
               if ($data) {
                  // Get the users that voted the comment down.
                  $otherUserIDs = $discussionModel->SQL
                     ->Select('UserID')
                     ->From('UserComment')
                     ->Where('CommentID', $discussionID)
                     ->Where('Score <', 0)
                     ->Get()->ResultArray();
                  $otherUserIDs = array_column($otherUserIDs, 'UserID');
                  $logOptions['OtherUserIDs'] = $otherUserIDs;

                  // Add the comment to moderation.
                  if ($total > C('Plugins.Voting.ModThreshold2', -20))
                     LogModel::Insert('Moderate', 'Discussion', $data, $logOptions);
               }
               $moderate = TRUE;
            }
            if ($total <= C('Plugins.Voting.ModThreshold2', -20)) {
               // Remove the comment.
               $discussionModel->Delete($discussionID, ['Log' => 'Moderate']);

               $sender->InformMessage(sprintf(T('The %s has been removed for moderation.'), T('discussion')));
            } elseif ($moderate) {
               $sender->InformMessage(sprintf(T('The %s has been flagged for moderation.'), T('discussion')));
            }
         }
      }
      $sender->DeliveryType(DELIVERY_TYPE_BOOL);
      $sender->SetJson('TotalScore', $total);
      $sender->SetJson('FinalVote', $finalVote);
      $sender->Render();
   }

   /**
    * Grab the score field whenever the discussions are queried.
    */
   public function DiscussionModel_AfterDiscussionSummaryQuery_Handler($sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      $sender->SQL->Select('d.Score');
   }

   public function DiscussionsController_AfterDiscussionFilters_Handler($sender) {
		echo '<li class="PopularDiscussions '.($sender->RequestMethod == 'popular' ? ' Active' : '').'">'
			.Anchor(Sprite('SpPopularDiscussions').' '.T('Popular'), '/discussions/popular', 'PopularDiscussions')
		.'</li>';
   }

	/**
	 * Add the "Popular Questions" tab.
    */
	public function Base_BeforeDiscussionTabs_Handler($sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

		echo '<li'.($sender->RequestMethod == 'popular' ? ' class="Active"' : '').'>'
			.Anchor(T('Popular'), '/discussions/popular', 'PopularDiscussions TabLink')
		.'</li>';
	}

//   public function CategoriesController_BeforeDiscussionContent_Handler($Sender) {
//      $this->DiscussionsController_BeforeDiscussionContent_Handler($Sender);
//   }

   /**
    * Load popular discussions.
    */
   public function DiscussionsController_Popular_Create($sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      $sender->AddModule('DiscussionFilterModule');
      $sender->Title(T('Popular'));
      $sender->Head->Title($sender->Head->Title());

      $offset = GetValue('0', $sender->RequestArgs, '0');

      // Get rid of announcements from this view
      if ($sender->Head) {
         $sender->AddJsFile('discussions.js');
         $sender->Head->AddRss($sender->SelfUrl.'/feed.rss', $sender->Head->Title());
      }
      if (!is_numeric($offset) || $offset < 0)
         $offset = 0;

      // Add Modules
      $sender->AddModule('NewDiscussionModule');
      $bookmarkedModule = new BookmarkedModule($sender);
      $bookmarkedModule->GetData();
      $sender->AddModule($bookmarkedModule);

      $sender->SetData('Category', FALSE, TRUE);
      $limit = C('Vanilla.Discussions.PerPage', 30);
      $discussionModel = new DiscussionModel();
      $countDiscussions = $discussionModel->GetCount();
      $sender->SetData('CountDiscussions', $countDiscussions);
      $sender->AnnounceData = FALSE;
		$sender->SetData('Announcements', [], TRUE);
      $discussionModel->SQL->OrderBy('d.CountViews', 'desc');
      $sender->DiscussionData = $discussionModel->Get($offset, $limit);
      $sender->SetData('Discussions', $sender->DiscussionData, TRUE);
      $sender->SetJson('Loading', $offset . ' to ' . $limit);

      // Build a pager.
      $pagerFactory = new Gdn_PagerFactory();
      $sender->Pager = $pagerFactory->GetPager('Pager', $sender);
      $sender->Pager->ClientID = 'Pager';
      $sender->Pager->Configure(
         $offset,
         $limit,
         $countDiscussions,
         'discussions/popular/%1$s'
      );

      // Deliver json data if necessary
      if ($sender->DeliveryType() != DELIVERY_TYPE_ALL) {
         $sender->SetJson('LessRow', $sender->Pager->ToString('less'));
         $sender->SetJson('MoreRow', $sender->Pager->ToString('more'));
         $sender->View = 'discussions';
      }

      // Render the controller
      $sender->View = 'index';
      $sender->Render();
   }

	/**
	 * If turning off scoring, make the forum go back to the traditional "jump
	 * to what I last read" functionality.
	 */
   public function OnDisable() {
		SaveToConfig('Vanilla.Comments.AutoOffset', TRUE);
   }

   /**
   * Don't let the users access the category management screens.
   public function SettingsController_Render_Before($Sender) {
      if (strpos(strtolower($Sender->RequestMethod), 'categor') > 0)
         redirectTo($Sender->Routes['DefaultPermission']);
   }
   */


	/**
	 * Insert the voting html on comments in a discussion.
	 */
	public function PostController_AfterCommentMeta_Handler($sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

		$this->DiscussionController_AfterCommentMeta_Handler($sender);
	}

	/**
	 * Add voting css to post controller.
	 */
	public function PostController_Render_Before($sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      $this->AddJsCss($sender);
	}

   public function ProfileController_Render_Before($sender) {
//		if (!C('Plugins.Voting.Enabled'))
//			return;

      $this->AddJsCss($sender);
   }

	/**
	 * Add a field to the db for storing the "State" of a question.
	 */
   public function Setup() {
      // Add some fields to the database
      $structure = Gdn::Structure();

      // "Unanswered" or "Answered"
      $structure->Table('Discussion')
         ->Column('State', 'varchar(30)', TRUE)
         ->Set(FALSE, FALSE);

//    SaveToConfig('Vanilla.Categories.Use', FALSE);
//      SaveToConfig('Vanilla.Comments.AutoOffset', FALSE);
   }
}
