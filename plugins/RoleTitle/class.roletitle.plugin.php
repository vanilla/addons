<?php

class RoleTitlePlugin extends Gdn_Plugin {

    /**
     * Add role(s) CSS classes to the target.
     *
     * @param stdclass $target Discussion or Comment
     * @param string $cssClass CSS class(es) of the target
     */
    private function injectCssClass($target, &$cssClass) {
        $cssRoles = val('Roles', $target);
        if (empty($cssRoles) || !is_array($cssRoles)) {
            return;
        }

        foreach ($cssRoles as &$rawRole) {
            $rawRole = $this->formatRoleCss($rawRole);
        }

        $cssClass .= ' '.implode(' ', $cssRoles);
    }

    /**
     * Generate a valid css class from a role name.
     *
     * @param string $rawRole role name
     * @return string CSS class
     */
    private function formatRoleCss($rawRole) {
        return 'Role_'.str_replace(' ', '_', Gdn_Format::alphaNumeric($rawRole));
    }

    /**
     * Inject roles into authorInfo
     *
     * @param DiscussionController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionController_authorInfo_handler($sender, $args) {
        $target = $args['Type'];

        $roles = val('Roles', $args[$target]);
        if (!$roles) {
            return;
        }

        echo '<span class="MItem RoleTitle">'.implode(', ', $roles).'</span> ';
    }

    /**
     * Inject css classes into the comment containers.
     *
     * @param DiscussionController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionController_beforeCommentDisplay_handler($sender, $args) {
        $this->injectCssClass($args[$args['Type']], $args['CssClass']);
    }

    /**
     * Inject css classes into the comment containers.
     *
     * @param PostController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function postController_beforeCommentDisplay_handler($sender, $args) {
        $this->injectCssClass($args[$args['Type']], $args['CssClass']);
    }

    /**
     * Add the user's roles to the comment data so we can visually
     * identify different roles in the view.
     *
     * @param DiscussionController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
	public function discussionController_render_before($sender, $args) {
	    $session = Gdn::session();

	    if ($session->isValid()) {
	        $joinUser = [$session->User];
	        RoleModel::setUserRoles($joinUser, 'UserID');
	    }

	    if (property_exists($sender, 'Discussion')) {
	        $joinDiscussion = [$sender->Discussion];
	        RoleModel::setUserRoles($joinDiscussion, 'InsertUserID');
	        $comments = $sender->data('Comments');
	        RoleModel::setUserRoles($comments->result(), 'InsertUserID');

	        $answers = $sender->data('Answers');
	        if (is_array($answers)) {
	            RoleModel::setUserRoles($answers, 'InsertUserID');
	        }

            // And add the css class to the discussion
            if (is_array($sender->Discussion->Roles)) {
                if (count($sender->Discussion->Roles)) {
                    $cssRoles = val('Roles', $sender->Discussion);
                    foreach ($cssRoles as &$rawRole) {
                        $rawRole = $this->formatRoleCss($rawRole);
                    }

                    $sender->Discussion->_CssClass = val('_CssClass', $sender->Discussion, '').' '.implode(' ',$cssRoles);
                }
            }
	    }
	}

    /**
     *
     * @param PostController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function postController_render_before($sender, $args) {
        $data = $sender->data('Comments');
        if (is_object($data)) {
            RoleModel::setUserRoles($data->result(), 'InsertUserID');
        }
    }

    /**
     * Add the roles to the comment form
     *
     * @param object $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function base_beforeCommentForm_handler($sender, $args) {
        $cssRoles = val('Roles', Gdn::session()->User);
        if (!is_array($cssRoles)) {
            return;
        }

        $cssClass = val('FormCssClass', $args, null);

        foreach ($cssRoles as &$rawRole) {
            $rawRole = $this->formatRoleCss($rawRole);
        }

        $args['FormCssClass'] = trim($cssClass.' '.implode(' ',$cssRoles));
    }

    /**
     * Add the roles to the profile body tag
     *
     * @param ProfileController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function profileController_render_before($sender, $args) {
        $cssRoles = $sender->data('UserRoles');
        if (!is_array($cssRoles)) {
            return;
        }

        foreach ($cssRoles as &$rawRole) {
            $rawRole = $this->formatRoleCss($rawRole);
        }

        $sender->CssClass = trim($sender->CssClass.' '.implode(' ', $cssRoles));
    }
}
