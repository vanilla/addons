<?php
/**
 * @author Isis Graziatto<isis.g@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

use Vanilla\Plugins\ContentFilterInterface;
use Vanilla\Utility\ContainerUtils;

$container = \Gdn::getContainer();

$container
    ->rule(ContentFilterInterface::class)
    ->setClass(\CivilTongueEx\Library\ContentFilter::class)
    ->addCall('setReplacement', [
        ContainerUtils::config('Plugins.CivilTongue.Replacement')
    ])
    ->addCall('setWords', [
        ContainerUtils::config('Plugins.CivilTongue.Words')
    ]);
