<?php if (!defined('APPLICATION')) exit();

$PluginInfo['FacebookID'] = array(
   'Description' => 'Displays users facebook IDs in verious locations in the site.',
   'Version' => '1.0b',
   'RequiredApplications' => array('Vanilla' => '2.0.16'),
   'Author' => "Todd Burry",
   'AuthorEmail' => 'todd@vanillaforums.com',
   'AuthorUrl' => 'http://vanillaforums.com/profile/todd',
   'RegisterPermissions' => array('Plugins.FacebookID.View'),
);

class FacebookIDPlugin extends Gdn_Plugin {
}