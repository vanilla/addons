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
   public function base_getAppSettingsMenuItems_handler($sender) {
      $menu = &$sender->EventArguments['SideMenu'];
      $menu->addItem('Forum', t('Forum'));
      $menu->addLink('Forum', t('Voting'), 'settings/voting', 'Garden.Settings.Manage');
   }
   public function settingsController_voting_create($sender) {
      $sender->permission('Garden.Settings.Manage');
      $conf = new ConfigurationModule($sender);
		$conf->initialize([
			'Plugins.Voting.ModThreshold1' => ['Type' => 'int', 'Control' => 'TextBox', 'Default' => -10, 'Description' => 'The vote that will flag a post for moderation.'],
			'Plugins.Voting.ModThreshold2' => ['Type' => 'int', 'Control' => 'TextBox', 'Default' => -20, 'Description' => 'The vote that will remove a post to the moderation queue.']
		]);

     $sender->addSideMenu('settings/voting');
     $sender->setData('Title', t('Vote Settings'));
     $sender->ConfigurationModule = $conf;
     $conf->renderAll();
   }
   public function settingsController_toggleVoting_create($sender) {
      $sender->permission('Garden.Settings.Manage');
      if (Gdn::session()->validateTransientKey(getValue(0, $sender->RequestArgs)))
         saveToConfig('Plugins.Voting.Enabled', c('Plugins.Voting.Enabled') ? FALSE : TRUE);

      redirectTo('settings/voting');
   }

	/**
	 * Add JS & CSS to the page.
	 */
   public function addJsCss($sender) {
//		if (!c('Plugins.Voting.Enabled'))
//			return;

      $sender->addCSSFile('voting.css', 'plugins/Voting');
		$sender->addJSFile('voting.js', 'plugins/Voting');
   }
	public function discussionsController_render_before($sender) {
		$this->addJsCss($sender);
	}
   public function categoriesController_render_before($sender) {
      $this->addJsCss($sender);
   }

	/**
	 * Add the "Stats" buttons to the discussion list.
	 */
	public function base_beforeDiscussionContent_handler($sender) {
//		if (!c('Plugins.Voting.Enabled'))
//			return;

		$session = Gdn::session();
		$discussion = getValue('Discussion', $sender->EventArguments);
		// Answers
		$css = 'StatBox AnswersBox';
		if ($discussion->CountComments > 1)
			$css .= ' HasAnswersBox';

		$countVotes = 0;
		if (is_numeric($discussion->Score)) // && $Discussion->Score > 0)
			$countVotes = $discussion->Score;

		if (!is_numeric($discussion->CountBookmarks))
			$discussion->CountBookmarks = 0;

		echo wrap(
			// anchor(
			wrap(t('Comments')) . Gdn_Format::bigNumber($discussion->CountComments)
			// ,'/discussion/'.$Discussion->DiscussionID.'/'.Gdn_Format::url($Discussion->Name).($Discussion->CountCommentWatch > 0 ? '/#Item_'.$Discussion->CountCommentWatch : '')
			// )
			, 'div', ['class' => $css]);

		// Views
		echo wrap(
			// anchor(
			wrap(t('Views')) . Gdn_Format::bigNumber($discussion->CountViews)
			// , '/discussion/'.$Discussion->DiscussionID.'/'.Gdn_Format::url($Discussion->Name).($Discussion->CountCommentWatch > 0 ? '/#Item_'.$Discussion->CountCommentWatch : '')
			// )
			, 'div', ['class' => 'StatBox ViewsBox']);

		// Follows
		$title = t($discussion->Bookmarked == '1' ? 'Unbookmark' : 'Bookmark');
		if ($session->isValid()) {
			echo wrap(anchor(
				wrap(t('Follows')) . Gdn_Format::bigNumber($discussion->CountBookmarks),
				'/discussion/bookmark/'.$discussion->DiscussionID.'/'.$session->transientKey().'?Target='.urlencode($sender->SelfUrl),
				'',
				['title' => $title]
			), 'div', ['class' => 'StatBox FollowsBox']);
		} else {
			echo wrap(wrap(t('Follows')) . $discussion->CountBookmarks, 'div', ['class' => 'StatBox FollowsBox']);
		}

		// Votes
		if ($session->isValid()) {
			echo wrap(anchor(
				wrap(t('Votes')) . Gdn_Format::bigNumber($countVotes),
				'/discussion/votediscussion/'.$discussion->DiscussionID.'/'.$session->transientKey().'?Target='.urlencode($sender->SelfUrl),
				'',
				['title' => t('Vote')]
			), 'div', ['class' => 'StatBox VotesBox']);
		} else {
			echo wrap(wrap(t('Votes')) . $countVotes, 'div', ['class' => 'StatBox VotesBox']);
		}
	}

   /**
	 * Sort the comments by popularity if necessary
    * @param CommentModel $commentModel
	 */
   public function commentModel_afterConstruct_handler($commentModel) {
//		if (!c('Plugins.Voting.Enabled'))
//			return;

      $sort = self::commentSort();

      switch (strtolower($sort)) {
         case 'date':
            $commentModel->orderBy('c.DateInserted');
            break;
         case 'popular':
         default:
            $commentModel->orderBy(['coalesce(c.Score, 0) desc', 'c.CommentID']);
            break;
      }
   }

   protected static $_CommentSort;
   public static function commentSort() {
//		if (!c('Plugins.Voting.Enabled'))
//			return;

      if (self::$_CommentSort)
         return self::$_CommentSort;

      $sort = getIncomingValue('Sort', '');
      if (Gdn::session()->isValid()) {
         if ($sort == '') {
            // No sort was specified so grab it from the user's preferences.
            $sort = Gdn::session()->getPreference('Plugins.Voting.CommentSort', 'popular');
         } else {
            // Save the sort to the user's preferences.
            Gdn::session()->setPreference('Plugins.Voting.CommentSort', $sort == 'popular' ? '' : $sort);
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
	public function discussionController_beforeCommentDisplay_handler($sender) {
//		if (!c('Plugins.Voting.Enabled'))
//			return;

		$answerCount = $sender->Discussion->CountComments - 1;
		$type = getValue('Type', $sender->EventArguments, 'Comment');
		if ($type == 'Comment' && !getValue('VoteHeaderWritten', $sender)) { //$Type != 'Comment' && $AnswerCount > 0) {
		?>
		<li class="Item">
			<div class="VotingSort">
			<?php
			echo
				wrap($answerCount.' '.plural($answerCount, 'Comment', 'Comments'), 'strong');
				echo ' sorted by '
               .anchor('Votes', url(discussionUrl($sender->Discussion).'?Sort=popular', TRUE), '', ['rel' => 'nofollow', 'class' => self::commentSort() == 'popular' ? 'Active' : ''])
               .' '
               .anchor('Date Added', url(discussionUrl($sender->Discussion).'?Sort=date', TRUE), '', ['rel' => 'nofollow', 'class' => self::commentSort() == 'date' ? 'Active' : '']);
			?>
			</div>
		</li>
		<?php
      $sender->VoteHeaderWritten = TRUE;
		}
	}

	public function discussionController_afterCommentMeta_handler($sender) {
//		if (!c('Plugins.Voting.Enabled'))
//			return;
/*
		echo '<span class="Votes">';
			$Session = Gdn::session();
			$Object = getValue('Object', $Sender->EventArguments);
			$VoteType = $Sender->EventArguments['Type'] == 'Discussion' ? 'votediscussion' : 'votecomment';
			$ID = $Sender->EventArguments['Type'] == 'Discussion' ? $Object->DiscussionID : $Object->CommentID;
			$CssClass = '';
			$VoteUpUrl = '/discussion/'.$VoteType.'/'.$ID.'/voteup/'.$Session->transientKey().'/';
			$VoteDownUrl = '/discussion/'.$VoteType.'/'.$ID.'/votedown/'.$Session->transientKey().'/';
			if (!$Session->isValid()) {
				$VoteUpUrl = Gdn::authenticator()->signInUrl($Sender->SelfUrl);
				$VoteDownUrl = $VoteUpUrl;
				$CssClass = ' SignInPopup';
			}
			echo anchor(Wrap(wrap('Vote Up', 'i'), 'i', array('class' => 'ArrowSprite SpriteUp', 'rel' => 'nofollow')), $VoteUpUrl, 'VoteUp'.$CssClass);
			echo wrap(StringIsNullOrEmpty($Object->Score) ? '0' : Gdn_Format::bigNumber($Object->Score));
			echo anchor(Wrap(wrap('Vote Down', 'i'), 'i', array('class' => 'ArrowSprite SpriteDown', 'rel' => 'nofollow')), $VoteDownUrl, 'VoteDown'.$CssClass);
		echo '</span>';

 */

      $session = Gdn::session();
      $object = getValue('Object', $sender->EventArguments);
      $voteType = $sender->EventArguments['Type'] == 'Discussion' ? 'votediscussion' : 'votecomment';
      $iD = $sender->EventArguments['Type'] == 'Discussion' ? $object->DiscussionID : $object->CommentID;
      $cssClass = '';
      $voteUpUrl = '/discussion/'.$voteType.'/'.$iD.'/voteup/'.$session->transientKey().'/';
      $voteDownUrl = '/discussion/'.$voteType.'/'.$iD.'/votedown/'.$session->transientKey().'/';
      if (!$session->isValid()) {
         $voteUpUrl = Gdn::authenticator()->signInUrl($sender->SelfUrl);
         $voteDownUrl = $voteUpUrl;
         $cssClass = ' SignInPopup';
      }

      echo '<span class="Voter">';
			echo anchor(wrap(wrap('Vote Up', 'i'), 'i', ['class' => 'ArrowSprite SpriteUp', 'rel' => 'nofollow']), $voteUpUrl, 'VoteUp'.$cssClass);
			echo wrap(stringIsNullOrEmpty($object->Score) ? '0' : Gdn_Format::bigNumber($object->Score));
			echo anchor(wrap(wrap('Vote Down', 'i'), 'i', ['class' => 'ArrowSprite SpriteDown', 'rel' => 'nofollow']), $voteDownUrl, 'VoteDown'.$cssClass);
		echo '</span>';
 }


   /**
	 * Add the vote.js file to discussions page, and handle sorting of answers.
	 */
   public function discussionController_render_before($sender) {
//		if (!c('Plugins.Voting.Enabled'))
//			return;

      $this->addJsCss($sender);
   }


   /**
    * Increment/decrement comment scores
    */
   public function discussionController_voteComment_create($sender) {
//		if (!c('Plugins.Voting.Enabled'))
//			return;

      $commentID = getValue(0, $sender->RequestArgs, 0);
      $voteType = getValue(1, $sender->RequestArgs);
      $transientKey = getValue(2, $sender->RequestArgs);
      $session = Gdn::session();
      $finalVote = 0;
      $total = 0;
      if ($session->isValid() && $session->validateTransientKey($transientKey) && $commentID > 0) {
         $commentModel = new CommentModel();
         $oldUserVote = $commentModel->getUserScore($commentID, $session->UserID);
         $newUserVote = $voteType == 'voteup' ? 1 : -1;
         $finalVote = intval($oldUserVote) + intval($newUserVote);
         // Allow admins to vote unlimited.
         $allowVote = $session->checkPermission('Garden.Moderation.Manage');
         // Only allow users to vote up or down by 1.
         if (!$allowVote)
            $allowVote = $finalVote > -2 && $finalVote < 2;

         if ($allowVote)
            $total = $commentModel->setUserScore($commentID, $session->UserID, $finalVote);

         // Move the comment into or out of moderation.
         if (class_exists('LogModel')) {
            $moderate = FALSE;

            if ($total <= c('Plugins.Voting.ModThreshold1', -10)) {
               $logOptions = ['GroupBy' => ['RecordID']];
               // Get the comment row.
               $data = $commentModel->getID($commentID, DATASET_TYPE_ARRAY);
               if ($data) {
                  // Get the users that voted the comment down.
                  $otherUserIDs = $commentModel->SQL
                     ->select('UserID')
                     ->from('UserComment')
                     ->where('CommentID', $commentID)
                     ->where('Score <', 0)
                     ->get()->resultArray();
                  $otherUserIDs = array_column($otherUserIDs, 'UserID');
                  $logOptions['OtherUserIDs'] = $otherUserIDs;

                  // Add the comment to moderation.
                  if ($total > c('Plugins.Voting.ModThreshold2', -20))
                     LogModel::insert('Moderate', 'Comment', $data, $logOptions);
               }
               $moderate = TRUE;
            }
            if ($total <= c('Plugins.Voting.ModThreshold2', -20)) {
               // Remove the comment.
               $commentModel->delete($commentID, ['Log' => 'Moderate']);

               $sender->informMessage(sprintf(t('The %s has been removed for moderation.'), t('comment')));
            } elseif ($moderate) {
               $sender->informMessage(sprintf(t('The %s has been flagged for moderation.'), t('comment')));
            }
         }
      }
      $sender->deliveryType(DELIVERY_TYPE_BOOL);
      $sender->setJson('TotalScore', $total);
      $sender->setJson('FinalVote', $finalVote);
      $sender->render();
   }

   /**
    * Increment/decrement discussion scores
    */
   public function discussionController_voteDiscussion_create($sender) {
//		if (!c('Plugins.Voting.Enabled'))
//			return;

      $discussionID = getValue(0, $sender->RequestArgs, 0);
      $transientKey = getValue(1, $sender->RequestArgs);
      $voteType = FALSE;
      if ($transientKey == 'voteup' || $transientKey == 'votedown') {
         $voteType = $transientKey;
         $transientKey = getValue(2, $sender->RequestArgs);
      }
      $session = Gdn::session();
      $newUserVote = 0;
      $total = 0;
      if ($session->isValid() && $session->validateTransientKey($transientKey) && $discussionID > 0) {
         $discussionModel = new DiscussionModel();
         $oldUserVote = $discussionModel->getUserScore($discussionID, $session->UserID);

         if ($voteType == 'voteup')
            $newUserVote = 1;
         else if ($voteType == 'votedown')
            $newUserVote = -1;
         else
            $newUserVote = $oldUserVote == 1 ? -1 : 1;

         $finalVote = intval($oldUserVote) + intval($newUserVote);
         // Allow admins to vote unlimited.
         $allowVote = $session->checkPermission('Garden.Moderation.Manage');
         // Only allow users to vote up or down by 1.
         if (!$allowVote)
            $allowVote = $finalVote > -2 && $finalVote < 2;

         if ($allowVote) {
            $total = $discussionModel->setUserScore($discussionID, $session->UserID, $finalVote);
         } else {
				$discussion = $discussionModel->getID($discussionID);
				$total = getValue('Score', $discussion, 0);
				$finalVote = $oldUserVote;
			}

         // Move the comment into or out of moderation.
         if (class_exists('LogModel')) {
            $moderate = FALSE;

            if ($total <= c('Plugins.Voting.ModThreshold1', -10)) {
               $logOptions = ['GroupBy' => ['RecordID']];
               // Get the comment row.
               if (isset($discussion))
                  $data = (array)$discussion;
               else
                  $data = (array)$discussionModel->getID($discussionID);
               if ($data) {
                  // Get the users that voted the comment down.
                  $otherUserIDs = $discussionModel->SQL
                     ->select('UserID')
                     ->from('UserComment')
                     ->where('CommentID', $discussionID)
                     ->where('Score <', 0)
                     ->get()->resultArray();
                  $otherUserIDs = array_column($otherUserIDs, 'UserID');
                  $logOptions['OtherUserIDs'] = $otherUserIDs;

                  // Add the comment to moderation.
                  if ($total > c('Plugins.Voting.ModThreshold2', -20))
                     LogModel::insert('Moderate', 'Discussion', $data, $logOptions);
               }
               $moderate = TRUE;
            }
            if ($total <= c('Plugins.Voting.ModThreshold2', -20)) {
               // Remove the comment.
               $discussionModel->delete($discussionID, ['Log' => 'Moderate']);

               $sender->informMessage(sprintf(t('The %s has been removed for moderation.'), t('discussion')));
            } elseif ($moderate) {
               $sender->informMessage(sprintf(t('The %s has been flagged for moderation.'), t('discussion')));
            }
         }
      }
      $sender->deliveryType(DELIVERY_TYPE_BOOL);
      $sender->setJson('TotalScore', $total);
      $sender->setJson('FinalVote', $finalVote);
      $sender->render();
   }

   /**
    * Grab the score field whenever the discussions are queried.
    */
   public function discussionModel_afterDiscussionSummaryQuery_handler($sender) {
//		if (!c('Plugins.Voting.Enabled'))
//			return;

      $sender->SQL->select('d.Score');
   }

   public function discussionsController_afterDiscussionFilters_handler($sender) {
		echo '<li class="PopularDiscussions '.($sender->RequestMethod == 'popular' ? ' Active' : '').'">'
			.anchor(sprite('SpPopularDiscussions').' '.t('Popular'), '/discussions/popular', 'PopularDiscussions')
		.'</li>';
   }

	/**
	 * Add the "Popular Questions" tab.
    */
	public function base_beforeDiscussionTabs_handler($sender) {
//		if (!c('Plugins.Voting.Enabled'))
//			return;

		echo '<li'.($sender->RequestMethod == 'popular' ? ' class="Active"' : '').'>'
			.anchor(t('Popular'), '/discussions/popular', 'PopularDiscussions TabLink')
		.'</li>';
	}

//   public function categoriesController_BeforeDiscussionContent_Handler($Sender) {
//      $this->discussionsController_BeforeDiscussionContent_Handler($Sender);
//   }

   /**
    * Load popular discussions.
    */
   public function discussionsController_popular_create($sender) {
//		if (!c('Plugins.Voting.Enabled'))
//			return;

      $sender->addModule('DiscussionFilterModule');
      $sender->title(t('Popular'));
      $sender->Head->title($sender->Head->title());

      $offset = getValue('0', $sender->RequestArgs, '0');

      // Get rid of announcements from this view
      if ($sender->Head) {
         $sender->addJsFile('discussions.js');
         $sender->Head->addRss($sender->SelfUrl.'/feed.rss', $sender->Head->title());
      }
      if (!is_numeric($offset) || $offset < 0)
         $offset = 0;

      // Add Modules
      $sender->addModule('NewDiscussionModule');
      $bookmarkedModule = new BookmarkedModule($sender);
      $bookmarkedModule->getData();
      $sender->addModule($bookmarkedModule);

      $sender->setData('Category', FALSE, TRUE);
      $limit = c('Vanilla.Discussions.PerPage', 30);
      $discussionModel = new DiscussionModel();
      $countDiscussions = $discussionModel->getCount();
      $sender->setData('CountDiscussions', $countDiscussions);
      $sender->AnnounceData = FALSE;
		$sender->setData('Announcements', [], TRUE);
      $discussionModel->SQL->orderBy('d.CountViews', 'desc');
      $sender->DiscussionData = $discussionModel->get($offset, $limit);
      $sender->setData('Discussions', $sender->DiscussionData, TRUE);
      $sender->setJson('Loading', $offset . ' to ' . $limit);

      // Build a pager.
      $pagerFactory = new Gdn_PagerFactory();
      $sender->Pager = $pagerFactory->getPager('Pager', $sender);
      $sender->Pager->ClientID = 'Pager';
      $sender->Pager->configure(
         $offset,
         $limit,
         $countDiscussions,
         'discussions/popular/%1$s'
      );

      // Deliver json data if necessary
      if ($sender->deliveryType() != DELIVERY_TYPE_ALL) {
         $sender->setJson('LessRow', $sender->Pager->toString('less'));
         $sender->setJson('MoreRow', $sender->Pager->toString('more'));
         $sender->View = 'discussions';
      }

      // Render the controller
      $sender->View = 'index';
      $sender->render();
   }

	/**
	 * If turning off scoring, make the forum go back to the traditional "jump
	 * to what I last read" functionality.
	 */
   public function onDisable() {
		saveToConfig('Vanilla.Comments.AutoOffset', TRUE);
   }

   /**
   * Don't let the users access the category management screens.
   public function settingsController_Render_Before($Sender) {
      if (strpos(strtolower($Sender->RequestMethod), 'categor') > 0)
         redirectTo($Sender->Routes['DefaultPermission']);
   }
   */


	/**
	 * Insert the voting html on comments in a discussion.
	 */
	public function postController_afterCommentMeta_handler($sender) {
//		if (!c('Plugins.Voting.Enabled'))
//			return;

		$this->discussionController_afterCommentMeta_handler($sender);
	}

	/**
	 * Add voting css to post controller.
	 */
	public function postController_render_before($sender) {
//		if (!c('Plugins.Voting.Enabled'))
//			return;

      $this->addJsCss($sender);
	}

   public function profileController_render_before($sender) {
//		if (!c('Plugins.Voting.Enabled'))
//			return;

      $this->addJsCss($sender);
   }

	/**
	 * Add a field to the db for storing the "State" of a question.
	 */
   public function setup() {
      // Add some fields to the database
      $structure = Gdn::structure();

      // "Unanswered" or "Answered"
      $structure->table('Discussion')
         ->column('State', 'varchar(30)', TRUE)
         ->set(FALSE, FALSE);

//    saveToConfig('Vanilla.Categories.Use', FALSE);
//      saveToConfig('Vanilla.Comments.AutoOffset', FALSE);
   }
}
