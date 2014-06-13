<?php

/**
 * @copyright 2010-2014 Vanilla Forums Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 */

$PluginInfo['mathjax'] = array(
    'Description' => 'This plugin enables MathJax syntax in discussions and comments.',
    'Version' => '1.0',
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'MobileFriendly' => TRUE,
    'Author' => "Tim Gunter",
    'AuthorEmail' => 'tim@vanillaforums.com',
    'AuthorUrl' => 'http://www.vanillaforums.com'
);

/**
 * MathJax Plugin
 *
 * This plugin allows the forum to parse MathJax syntax to support rendering of complex mathematical formulas
 * in discussions and comments.
 *
 * Currently, MathJax version 2.4 is supported.
 *
 * Changes:
 *  1.0     Initial release
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package addons
 */
class MathJaxPlugin extends Gdn_Plugin {

    /**
     * Insert MathJax javascript into discussion pages
     *
     * @param DiscussionController $sender
     */
    public function DiscussionController_Render_Before(&$sender) {
        $sender->addJsFile("http://cdn.mathjax.org/mathjax/2.4-latest/MathJax.js");
        $sender->addJsFile("live.js", "plugins/MathJax");
    }

}
