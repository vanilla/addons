<?php
/**
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

$container = Gdn::getContainer();

$container->rule(IPBFormatter\Formatter::class)
    ->addAlias('IPBFormatter')
    ->addAlias('ipbFormatter')
    ->setShared(true);
