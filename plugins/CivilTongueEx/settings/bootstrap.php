<?php
/**
 * @author Isis Graziatto<isis.g@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

use Vanilla\Plugins\ContentFilterInterface;

$container = \Gdn::getContainer();

$container
    ->setClass(\CivilTonguePlugin::class)
    ->rule(ContentFilterInterface::class)
    ->addCall('replace');
