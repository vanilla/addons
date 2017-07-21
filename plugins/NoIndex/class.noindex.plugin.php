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
   public function Base_DiscussionOptions_Handler($sender, $args) {
      if (CheckPermission(['Garden.Moderation.Manage', 'Garden.Curation.Manage'], FALSE)) {
         $discussion = $args['Discussion'];
         $label = (GetValue('NoIndex', $discussion)) ? T('Remove NoIndex') : T('Add NoIndex');
         $url = "/discussion/noindex?discussionid={$discussion->DiscussionID}";
         // Deal with inconsistencies in how options are passed
         if (isset($sender->Options)) {
            $sender->Options .= Wrap(Anchor($label, $url, 'NoIndex'), 'li');
         }
         else {
            $args['DiscussionOptions']['Bump'] = [
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
   public function DiscussionController_NoIndex_Create($sender, $args) {
      $sender->Permission(['Garden.Moderation.Manage', 'Garden.Curation.Manage'], FALSE);

      // Get discussion
      $discussionID = $sender->Request->Get('discussionid');
      $discussion = $sender->DiscussionModel->GetID($discussionID);
      if (!$discussion) {
         throw NotFoundException('Discussion');
      }

      // Toggle NoIndex
      $noIndex = GetValue('NoIndex', $discussion) ? 0 : 1;

      // Update DateLastComment & redirect
      $sender->DiscussionModel->SetProperty($discussionID, 'NoIndex', $noIndex);
      redirectTo(DiscussionUrl($discussion));
   }

    /**
     * Add a mod message to NoIndex discussions.
     */
    public function DiscussionController_BeforeDiscussionDisplay_Handler($sender, $args) {
        if (!CheckPermission(['Garden.Moderation.Manage', 'Garden.Curation.Manage'], FALSE))
            return;

        if (GetValue('NoIndex', $sender->Data('Discussion'))) {
            echo Wrap(T('Discussion marked as noindex'), 'div', ['class' => 'Warning']);
        }
    }

    /**
     * Add the noindex/noarchive meta tag.
     */
    public function DiscussionController_Render_Before($sender, $args) {
        if ($sender->Head && GetValue('NoIndex', $sender->Data('Discussion'))) {
            $sender->Head->AddTag('meta', ['name' => 'robots', 'content' => 'noindex,noarchive']);
        }
    }

    /**
     * Show NoIndex meta tag on discussions list.
     */
    public function Base_BeforeDiscussionMeta_Handler($sender, $args) {
        $noIndex = GetValue('NoIndex', GetValue('Discussion', $args));
        if (CheckPermission(['Garden.Moderation.Manage', 'Garden.Curation.Manage'], FALSE) && $noIndex) {
            echo ' <span class="Tag Tag-NoIndex">'.T('NoIndex').'</span> ';
        }
    }

   /**
    * Invoke structure changes.
    */
   public function Setup() {
        $this->Structure();
   }

    /**
     * Add NoIndex property to discussions.
     */
    public function Structure() {
       Gdn::Structure()
          ->Table('Discussion')
          ->Column('NoIndex', 'int', '0')
          ->Set();
   }
}
