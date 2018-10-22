<?php
/**
 * Homepage Canonical Plugin.
 *
 * @author Patrick Kelly <patrick.k@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license GNU GPLv2 http://www.opensource.org/licenses/gpl-2.0.php
 */

class HomepageCanonicalPlugin extends Gdn_Plugin {

    /**
     * If the Default Controller page is requested (Discussions or Categories), 301 redirect to "/" and insert the Canonical tag.
     *
     * @param VanillaController $sender
     * @param array $args
     */
    public function base_render_before($sender, $args) {
        // Use $SERVER['REQUEST_URI'], and not request()->path() because we alter path;
        $requestURI = Gdn::request()->getValueFrom('server', 'REQUEST_URI');
        $defaultController = Gdn::router()->getDestination('DefaultController');
        if ((strpos($requestURI,'?') === false) && preg_match('/\/'.$defaultController.'\/?$/i',$requestURI)) {
            safeHeader('Location: '.url('/', true), true, '301');
        }

        if ($requestURI === '/') {
            saveToConfig('Garden.Modules.NoCanonicalUrl', true, false);
            $sender->Head->addTag('link', ['rel' => 'canonical', 'href' => url('/', true)], null, false);
        }
    }

}
