<?php

$PluginInfo['MeAction'] = array(
    'Description' => 'Allows IRC-style /me actions in the middle of comments as long as they appear at start of a new line.',
    'Version' => '1.1',
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'MobileFriendly' => true,
    'Author' => 'Lincoln Russell',
    'AuthorEmail' => 'lincoln@vanillaforums.com',
    'AuthorUrl' => 'http://lincolnwebs.com',
    'License' => 'GNU GPLv2'
);

class MeActionPlugin extends Gdn_Plugin {

    /**
     * Enable the formatter in Gdn_Format::Mentions.
     */
    public function setup() {
        saveToConfig('Garden.Format.MeActions', true);
    }

    /**
     * Disable the formatter in Gdn_Format::Mentions.
     */
    public function onDisable() {
        saveToConfig('Garden.Format.MeActions', false);
    }

}
