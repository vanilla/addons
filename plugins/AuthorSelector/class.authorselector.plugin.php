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
    public function base_discussionOptions_handler($Sender, $Args) {
        $Discussion = $Args['Discussion'];
        if (Gdn::session()->checkPermission('Vanilla.Discussions.Edit', true, 'Category', $Discussion->PermissionCategoryID)) {
            $Label = t('Change Author');
            $Url = "/discussion/author?discussionid={$Discussion->DiscussionID}";

            // Deal with inconsistencies in how options are passed
            if (isset($Sender->Options)) {
                $Sender->Options .= wrap(anchor($Label, $Url, 'ChangeAuthor'), 'li');
            }
            else {
                $Args['DiscussionOptions']['ChangeAuthor'] = ['Label' => $Label, 'Url' => $Url, 'Class' => 'ChangeAuthor'];
            }
        }
    }

    /**
     * Handle discussion option menu Change Author action.
     */
    public function discussionController_author_create($Sender) {
        $DiscussionID = $Sender->Request->get('discussionid');
        $Discussion = $Sender->DiscussionModel->getID($DiscussionID);
        if (!$Discussion) {
            throw NotFoundException('Discussion');
        }

        // Check edit permission
        $Sender->permission('Vanilla.Discussions.Edit', true, 'Category', $Discussion->PermissionCategoryID);

        if ($Sender->Form->authenticatedPostBack()) {
            // Change the author
            $Name = $Sender->Form->getFormValue('Author', '');
            $UserModel = new UserModel();
            if (trim($Name) != '') {
                $User = $UserModel->getByUsername(trim($Name));
                if (is_object($User)) {
                    if ($Discussion->InsertUserID == $User->UserID)
                        $Sender->Form->addError('That user is already the discussion author.');
                    else {
                        // Change discussion InsertUserID
                        $Sender->DiscussionModel->setField($DiscussionID, 'InsertUserID', $User->UserID);

                        // Update users' discussion counts
                        $Sender->DiscussionModel->updateUserDiscussionCount($Discussion->InsertUserID);
                        $Sender->DiscussionModel->updateUserDiscussionCount($User->UserID, true); // Increment

                        // Go to the updated discussion
                        redirectTo(discussionUrl($Discussion));
                    }
                }
                else {
                    $Sender->Form->addError('No user with that name was found.');
                }
            }
        }
        else {
            // Form to change the author
            $Sender->setData('Title', $Discussion->Name);
        }

        $Sender->render('changeauthor', '', 'plugins/AuthorSelector');
    }

    /**
     * Add Javascript files required for autocomplete / username token.
     *
     * @param Gdn_Controller $Sender
     */
    protected function addJsFiles($Sender) {
        $Sender->addJsFile('jquery.tokeninput.js');
        $Sender->addJsFile('authorselector.js', 'plugins/AuthorSelector');
    }

    /**
     * @param DiscussionController $Sender
     */
    public function discussionsController_render_before($Sender) {
        $this->addJsFiles($Sender);
    }

    /**
     * @param DiscussionController $Sender
     */
    public function discussionController_render_before($Sender) {
        $this->addJsFiles($Sender);
    }

    /**
     * @param CategoriesController $Sender
     */
    public function categoriesController_render_before($Sender) {
        $this->addJsFiles($Sender);
    }
}
