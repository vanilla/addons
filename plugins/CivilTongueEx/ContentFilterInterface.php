<?php
/**
 * @author Isis Graziatto<isis.g@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace Vanilla\Plugins;

/**
 * Interface ContentFilterInterface
 * @package Vanilla\Plugins\CivilTonguePlugin
 */
interface ContentFilterInterface {

    /**
     * Replace black-listed words according to pattern
     *
     * @param string $text
     * @return mixed
     */
    public function replace($text);
}
