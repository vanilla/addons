<?php if (!defined('APPLICATION')) exit();

/**
 * Class NoIndexPlugin.
 *
 * Adds an optional 'NoIndex' property to discussions which removes the discussion from search indexes.
 * We do this as a DB column so we can easily add search & count functionality later if we want.
 * Removing these discussions from internal search would also be an interesting extension.
 */
class NoIndexPlugin extends Gdn_Plugin {
   /**
    * Allow mods to add/remove NoIndex via discussion options.
    */
   public function base_discussionOptions_handler($sender, $args) {
      if (checkPermission(['Garden.Moderation.Manage', 'Garden.Curation.Manage'], FALSE)) {
         $discussion = $args['Discussion'];
         $label = (getValue('NoIndex', $discussion)) ? t('Remove NoIndex') : t('Add NoIndex');
         $url = "/discussion/noindex?discussionid={$discussion->DiscussionID}";
         // Deal with inconsistencies in how options are passed
         if (isset($sender->Options)) {
            $sender->Options .= wrap(anchor($label, $url, 'NoIndex'), 'li');
         }
         else {
            $args['DiscussionOptions']['NoIndex'] = [
               'Label' => $label,
               'Url' => $url,
               'Class' => 'NoIndex'
            ];
         }
      }
   }

   /**
    * Handle discussion option menu NoIndex action (simple toggle).
    */
   public function discussionController_noIndex_create($sender, $args) {
      $sender->permission(['Garden.Moderation.Manage', 'Garden.Curation.Manage'], FALSE);

      // Get discussion
      $discussionID = $sender->Request->get('discussionid');
      $discussion = $sender->DiscussionModel->getID($discussionID);
      if (!$discussion) {
         throw notFoundException('Discussion');
      }

      // Toggle NoIndex
      $noIndex = getValue('NoIndex', $discussion) ? 0 : 1;

      // Update DateLastComment & redirect
      $sender->DiscussionModel->setProperty($discussionID, 'NoIndex', $noIndex);
      redirectTo(discussionUrl($discussion));
   }

    /**
     * Add a mod message to NoIndex discussions.
     */
    public function discussionController_beforeDiscussionDisplay_handler($sender, $args) {
        if (!checkPermission(['Garden.Moderation.Manage', 'Garden.Curation.Manage'], FALSE))
            return;

        if ($sender->data('Discussion.NoIndex')) {
            echo wrap(t('Discussion marked as noindex'), 'div', ['class' => 'Warning']);
        }
    }

    /**
     * Add the noindex/noarchive meta tag.
     *
     * @param \DiscussionController
     */
    public function discussionController_render_before($sender, $args) {
        if ($sender->Head && $sender->data('Discussion.NoIndex')) {
            $sender->Head->addTag('meta', ['name' => 'robots', 'content' => 'noindex,noarchive']);
        }
    }

    /**
     * Show NoIndex meta tag on discussions list.
     */
    public function base_beforeDiscussionMeta_handler($sender, $args) {
        $noIndex = valr('Discussion.NoIndex', $args);
        if (checkPermission(['Garden.Moderation.Manage', 'Garden.Curation.Manage'], FALSE) && $noIndex) {
            echo ' <span class="Tag Tag-NoIndex">'.t('NoIndex').'</span> ';
        }
    }

   /**
    * Invoke structure changes.
    */
   public function setup() {
        $this->structure();
   }

    /**
     * Add NoIndex property to discussions.
     */
    public function structure() {
       Gdn::structure()
          ->table('Discussion')
          ->column('NoIndex', 'int', '0')
          ->set();
   }
}
