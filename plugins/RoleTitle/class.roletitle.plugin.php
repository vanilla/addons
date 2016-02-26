<?php if (!defined('APPLICATION')) { exit(); }

// 0.2 - 2011-09-07 - mosullivan - Added InjectCssClass, Optimized querying.
// 0.3 - 2011-12-13 - linc - Add class to title span, make injected CSS class Vanilla-like (capitalized, no dashes).
// 0.2 - 2012-05-21 - mosullivan - Add _CssClass to Discussion object so first comment in list gets the role css.

$PluginInfo['RoleTitle'] = array(
   'Name' => 'Role Titles',
   'Description' => "Lists users' roles under their name and adds role-specific CSS classes to their comments for theming.",
   'Version' => '1.0',
   'RequiredApplications' => array('Vanilla' => '2.0.18'),
   'MobileFriendly' => true,
   'RegisterPermissions' => false,
   'Author' => "Matt Lincoln Russell",
   'AuthorEmail' => 'lincolnwebs@gmail.com',
   'AuthorUrl' => 'http://lincolnwebs.com'
);

class RoleTitlePlugin extends Gdn_Plugin {

   public function discussionController_authorInfo_handler($sender) {
       $this->_attachTitle($sender);
   }

    private function _attachTitle($sender) {
        $object = getValue('Object', $sender->EventArguments);
        $roles = $object ? getValue('Roles', $object, array()) : false;
        if (!$roles) {
            return;
        }

        echo '<span class="MItem RoleTitle">'.implode(', ', $roles).'</span> ';
    }

   /**
    * Inject css classes into the comment containers.
    */
    public function discussionController_beforeCommentDisplay_handler($sender) {
        $this->_injectCssClass($sender);
    }

    public function postController_beforeCommentDisplay_handler($sender) {
        $this->_injectCssClass($sender);
    }

    private function _injectCssClass($sender) {
        $object = getValue('Object', $sender->EventArguments);
        $cssRoles = $object ? getValue('Roles', $object, array()) : false;
        if (!$cssRoles) {
            return;
        }

        foreach ($cssRoles as &$rawRole) {
            $rawRole = $this->_formatRoleCss($rawRole);
        }

        if (count($cssRoles)) {
            $sender->EventArguments['CssClass'] .= ' '.implode(' ',$cssRoles);
        }
    }

   /**
    * Add the insert user's roles to the comment data so we can visually
    * identify different roles in the view.
    */
	public function discussionController_render_before($sender) {
	    $session = Gdn::session();
	    if ($session->isValid()) {
	        $joinUser = array($session->User);
	        RoleModel::setUserRoles($joinUser, 'UserID');
	    }
	    if (property_exists($sender, 'Discussion')) {
	        $joinDiscussion = array($sender->Discussion);
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
                    $cssRoles = getValue('Roles', $sender->Discussion);
                    foreach ($cssRoles as &$rawRole) {
                        $rawRole = $this->_formatRoleCss($rawRole);
                    }

                    $sender->Discussion->_CssClass = getValue('_CssClass', $sender->Discussion, '').' '.implode(' ',$cssRoles);
                }
            }
	    }
	}

    public function postController_render_before($sender) {
        $data = $sender->data('Comments');
        if (is_object($data)) {
            RoleModel::setUserRoles($data->result(), 'InsertUserID');
        }
    }

    // Add it to the comment form
    public function base_beforeCommentForm_handler($sender) {
        $cssClass = getValue('FormCssClass', $sender->EventArguments, '');
        $cssRoles = getValue('Roles', Gdn::session()->User);
        if (!is_array($cssRoles)) {
            return;
        }

        foreach ($cssRoles as &$rawRole) {
            $rawRole = $this->_formatRoleCss($rawRole);
        }

        $sender->EventArguments['FormCssClass'] = $cssClass.' '.implode(' ',$cssRoles);
    }


    private function _formatRoleCss($rawRole) {
        return 'Role_'.str_replace(' ','_', Gdn_Format::alphaNumeric($rawRole));
    }

    // Add the roles to the profile body tag
    public function profileController_render_before($sender) {
        $cssRoles = $sender->data('UserRoles');
        if (!is_array($cssRoles)) {
            return;
        }

        foreach ($cssRoles as &$rawRole) {
            $rawRole = $this->_formatRoleCss($rawRole);
        }

        $sender->CssClass = trim($sender->CssClass.' '.implode(' ',$cssRoles));
    }
}
