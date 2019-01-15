<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

class ShareThisPlugin extends Gdn_Plugin {
    /**
     * Discussion Body
     *
     * Show buttons after OP message body.
     * @param $sender
     */
    public function discussionController_afterDiscussionBody_handler($sender) {

        echo <<<SHARETHIS
    <div class="sharethis-inline-share-buttons"></div>
SHARETHIS;
    }


    public function setup() {
        // Nothing to do here!
    }

    /**
     * Settings page.
     *
     * @param $sender
     */
    public function pluginController_shareThis_create($sender) {
        $sender->permission('Garden.Settings.Manage');
        $sender->title('ShareThis');
        $sender->addSideMenu('plugin/sharethis');
        $sender->Form = new Gdn_Form();

        $publisherNumber = c('Plugin.ShareThis.PublisherNumber', 'Publisher Number');
        $viaHandle = c('Plugin.ShareThis.ViaHandle', '');
        $copyNShare = c('Plugin.ShareThis.CopyNShare', false);

        $validation = new Gdn_Validation();
        $configurationModel = new Gdn_ConfigurationModel($validation);
        $configArray = ['Plugin.ShareThis.PublisherNumber', 'Plugin.ShareThis.ViaHandle', 'Plugin.ShareThis.CopyNShare'];
        if ($sender->Form->authenticatedPostBack() === false) {
            $configArray['Plugin.ShareThis.PublisherNumber'] = $publisherNumber;
            $configArray['Plugin.ShareThis.ViaHandle'] = $viaHandle;
            $configArray['Plugin.ShareThis.CopyNShare'] = $copyNShare;
        }

        $configurationModel->setField($configArray);
        $sender->Form->setModel($configurationModel);
        // If seeing the form for the first time...
        if ($sender->Form->authenticatedPostBack() === false) {
            // Apply the config settings to the form.
            $sender->Form->setData($configurationModel->Data);
        } else {
            // Define some validation rules for the fields being saved
            $configurationModel->Validation->applyRule('Plugin.ShareThis.PublisherNumber', 'Required');
            if ($sender->Form->save() !== false) {
                $sender->informMessage(t("Your changes have been saved."));
            }
        }

        $sender->render('sharethis', '', 'plugins/ShareThis');
    }

    /**
     * Discussion Render Before
     *
     * @param $sender DiscussionController
     */
    public function discussionController_render_before($sender) {
        $sender->addCssFile('ShareThis.css', 'plugins/ShareThis');
        $sender->Head->addScript(
            '//platform-api.sharethis.com/js/sharethis.js#property=5c3bb6075533a5001139a061&product=inline-share-buttons',
            'text/javascript',
            false,
            ['async' => 'aysnc']
        );
    }
}

