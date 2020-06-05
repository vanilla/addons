<?php

/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 */

use Vanilla\Web\TwigRenderTrait;

/**
 * MathJax Plugin
 *
 * This plugin allows the forum to parse MathJax syntax to support rendering of complex mathematical formulas
 * in discussions and comments.
 *
 * Currently, MathJax version 2.7.6 is supported.
 *
 * Changes:
 *  1.0     Initial release
 *  1.1     Support previews
 *  1.3     Refactoring - Security patch
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package addons
 */
class MathJaxPlugin extends Gdn_Plugin {

    use TwigRenderTrait;

    /**
     * Insert MathJax javascript into discussion pages
     *
     * @param DiscussionController $sender
     */
    public function discussionController_render_before($sender) {

        // Add basic MathJax configuration
        $mathJaxConfig = $this->renderTwig("plugins/MathJax/views/inlineMathJax.twig", []);
        $sender->Head->addString($mathJaxConfig);
        $sender->addJsFile("https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.6/MathJax.js?delayStartupUntil=onload");
        $sender->addJsFile("live.js", "plugins/MathJax");
    }
}
