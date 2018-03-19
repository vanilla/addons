<?php
/**
 * @copyright 2008-2016 Vanilla Forums, Inc.
 * @license GNU GPLv2 http://www.opensource.org/licenses/gpl-2.0.php
 */

/**
 * Class AuthorSelectorPlugin
 */
class AuthorSelectorPlugin extends Gdn_Plugin {

    /**
     * Allow admin to Change Author via discussion options.
     */
    public function base_discussionOptions_handler($sender, $args) {
        $discussion = $args['Discussion'];
        if (Gdn::session()->checkPermission('Vanilla.Discussions.Edit', true, 'Category', $discussion->PermissionCategoryID)) {
            $label = t('Change Author');
            $url = url("/discussion/author?discussionid={$discussion->DiscussionID}");

            // Deal with inconsistencies in how options are passed
            if (isset($sender->Options)) {
                $sender->Options .= wrap(anchor($label, $url, 'ChangeAuthor'), 'li');
            }
            else {
                $args['DiscussionOptions']['ChangeAuthor'] = ['Label' => $label, 'Url' => $url, 'Class' => 'ChangeAuthor'];
            }
        }
    }

    /**
     * Handle discussion option menu Change Author action.
     */
    public function discussionController_author_create($sender) {
        $discussionID = $sender->Request->get('discussionid');
        $discussion = $sender->DiscussionModel->getID($discussionID);
        if (!$discussion) {
            throw notFoundException('Discussion');
        }

        // Check edit permission
        $sender->permission('Vanilla.Discussions.Edit', true, 'Category', $discussion->PermissionCategoryID);

        if ($sender->Form->authenticatedPostBack()) {
            // Change the author
            $name = $sender->Form->getFormValue('Author', '');
            $userModel = new UserModel();
            if (trim($name) != '') {
                $user = $userModel->getByUsername(trim($name));
                if (is_object($user)) {
                    if ($discussion->InsertUserID == $user->UserID)
                        $sender->Form->addError('That user is already the discussion author.');
                    else {
                        // Change discussion InsertUserID
                        $sender->DiscussionModel->setField($discussionID, 'InsertUserID', $user->UserID);

                        // Update users' discussion counts
                        $sender->DiscussionModel->updateUserDiscussionCount($discussion->InsertUserID);
                        $sender->DiscussionModel->updateUserDiscussionCount($user->UserID, true); // Increment

                        // Go to the updated discussion
                        redirectTo(discussionUrl($discussion));
                    }
                }
                else {
                    $sender->Form->addError('No user with that name was found.');
                }
            }
        }
        else {
            // Form to change the author
            $sender->setData('Title', $discussion->Name);
        }

        $sender->render('changeauthor', '', 'plugins/AuthorSelector');
    }

    /**
     * Add Javascript files required for autocomplete / username token.
     *
     * @param Gdn_Controller $sender
     */
    protected function addJsFiles($sender) {
        $sender->addJsFile('jquery.tokeninput.js');
        $sender->addJsFile('authorselector.js', 'plugins/AuthorSelector');
    }

    /**
     * @param DiscussionController $sender
     */
    public function discussionsController_render_before($sender) {
        $this->addJsFiles($sender);
    }

    /**
     * @param DiscussionController $sender
     */
    public function discussionController_render_before($sender) {
        $this->addJsFiles($sender);
    }

    /**
     * @param CategoriesController $sender
     */
    public function categoriesController_render_before($sender) {
        $this->addJsFiles($sender);
    }
}
