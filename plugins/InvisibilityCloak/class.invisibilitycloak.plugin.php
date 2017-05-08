<?php
/**
 * @copyright 2014-2016 Vanilla Forums, Inc.
 */

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
