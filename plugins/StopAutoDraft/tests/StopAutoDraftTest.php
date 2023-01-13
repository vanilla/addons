<?php

/**
 * @author Richard Flynn <rflynn@higherlogic.com>
 * @copyright 2009-2023 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */
class StopAutoDraftTest extends \VanillaTests\SiteTestCase
{
    public function testEnablingStopAutoDraft(): void
    {
        $this->assertConfigValue("Vanilla.Drafts.Autosave", null);
        $this->enableAddon("stopautodraft");
        $this->assertConfigValue("Vanilla.Drafts.Autosave", false);
    }
}
