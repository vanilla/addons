<?php use Vanilla\AddonManager;

if (!defined("APPLICATION")) {
    exit();
}
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

class StopAutoDraftPlugin extends Gdn_Plugin
{
    /** @var \Vanilla\Contracts\ConfigurationInterface */
    protected $config;

    /** @var \Vanilla\Models\AddonModel  */
    protected $addonModel;

    /** @var AddonManager  */
    protected $addonManager;

    public function __construct(
        \Vanilla\Contracts\ConfigurationInterface $config,
        \Vanilla\Models\AddonModel $addonModel,
        AddonManager $addonManager
    ) {
        $this->config = $config;
        $this->addonModel = $addonModel;
        $this->addonManager = $addonManager;
        parent::__construct();
    }

    public function structure()
    {
        $this->config->saveToConfig("Vanilla.Drafts.Autosave", false);
        $addon = $this->addonManager->lookupAddon("stopautodraft");
        $this->addonModel->disable($addon);
    }

    public function setup()
    {
        $this->structure();
    }

    public function onDisable()
    {
    }
}
