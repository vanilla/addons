<?php

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
