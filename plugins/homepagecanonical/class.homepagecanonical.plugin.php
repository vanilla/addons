<?php
/**
 * Homepage Canonical Plugin.
 *
 * @author Patrick Kelly <patrick.k@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

class HomepageCanonicalPlugin extends Gdn_Plugin {

    /**
     * If the Default Controller page is requested (Discussions or Categories), 301 redirect to "/".
     *
     * @param Gdn_Dispatcher $sender
     * @param array $args
     */
    public function gdn_dispatcher_beforeDispatch_handler($sender, $args) {
        //Use $SERVER['REQUEST_URI'], and not request()->path() because we alter path;
        $requestURI = Gdn::request()->getRequestArguments('server')['REQUEST_URI'];
        $defaultController = Gdn::router()->getDestination('DefaultController');
        if ((strpos($requestURI,'?') === false) && preg_match('/\/'.$defaultController.'\/?$/i',$requestURI)) {
            safeHeader('Location: '.url('/', true), true, '301');
        }
    }


    /**
     *  Insert the correct Canonical tag on the homepage, make sure the Canonical page is not also generated natively.
     *
     * @param Gdn_Controller $sender
     * @param array $args
     */
    public function base_render_before($sender, $args) {
        // Use $SERVER['REQUEST_URI'], and not request()->path() because we alter path;
        $requestURI = Gdn::request()->getRequestArguments('server')['REQUEST_URI'];
        if ($requestURI === '/') {
            // We are setting a new canonical tag so we have to, on this page load, stop the system from setting a conflicting canonical tag.
            saveToConfig('Garden.Modules.NoCanonicalUrl', true, false);
            $sender->Head->addTag('link', ['rel' => 'canonical', 'href' => url('/', true)], null, false);
        }
    }

}
