<?php
/**
 * @copyright 2014-2016 Vanilla Forums, Inc.
 */

$PluginInfo['InvisibilityCloak'] = array(
    'Name' => 'Invisibility Cloak',
    'Description' => 'Hide your forum from the prying eyes of search engines and bots while you set it up.',
    'Version' => '1.0',
    'RequiredApplications' => ['Vanilla' => '2.1'],
    'Author' => "Lincoln Russell",
    'AuthorEmail' => 'lincoln@vanillaforums.com',
    'License' => 'GNU GPLv2'
);

/**
 * Class InvisibilityCloakPlugin
 */
class InvisibilityCloakPlugin extends Gdn_Plugin {

    /**
     * robots.txt.
     */
    public function rootController_robots_create($sender) {
        header("Content-Type: text/plain");
        echo "User-agent: *\nDisallow: /";
    }

    /**
     * No bots meta tag.
     */
    public function base_render_before($sender) {
        if ($sender->Head) {
            $sender->Head->addTag('meta', array('name' => 'robots', 'content' => 'noindex,noarchive'));
        }
    }
}
