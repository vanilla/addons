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
    * Show buttons after OP message body.
    */
	public function discussionController_afterDiscussionBody_handler($sender) {
      $publisherNumber = c('Plugin.ShareThis.PublisherNumber', 'Publisher Number');
      $viaHandle = c('Plugin.ShareThis.ViaHandle', '');
      $copyNShare = c('Plugin.ShareThis.CopyNShare', false);

      $doNotHash = $copyNShare ? 'false' : 'true';
      $doNotCopy = $copyNShare ? 'false' : 'true';
      $domain = Gdn::request()->scheme() == 'https' ? 'https://ws.sharethis.com' : 'http://w.sharethis.com';

      echo <<<SHARETHIS
      <script type="text/javascript">var switchTo5x=true;</script>
      <script type="text/javascript" src="{$domain}/button/buttons.js"></script>
      <script type="text/javascript">stLight.options({
         publisher: "{$publisherNumber}",
         doNotHash: {$doNotHash},
         doNotCopy: {$doNotCopy},
         hashAddressBar: false
      });</script>
      <div class="ShareThisButtonWrapper Right">
         <span class="st_twitter_hcount ShareThisButton" st_via="{$viaHandle}" displayText="Tweet"></span>
         <span class="st_facebook_hcount ShareThisButton" displayText="Facebook"></span>
         <span class="st_linkedin_hcount ShareThisButton " displayText="LinkedIn"></span>
         <span class="st_googleplus_hcount ShareThisButton Hidden" displayText="Google +"></span>
         <span class="st_reddit_hcount ShareThisButton Hidden" displayText="Reddit"></span>
         <span class="st_pinterest_hcount ShareThisButton Hidden" displayText="Pinterest"></span>
         <span class="st_email_hcount ShareThisButton" displayText="Email"></span>
         <span class="st_sharethis_hcount ShareThisButton Hidden" displayText="ShareThis"></span>
      </div>
SHARETHIS;

   }

   public function setup() {
      // Nothing to do here!
   }
   
   /**
    * Settings page.
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
      $configArray = ['Plugin.ShareThis.PublisherNumber','Plugin.ShareThis.ViaHandle', 'Plugin.ShareThis.CopyNShare'];
      if ($sender->Form->authenticatedPostBack() === FALSE) {
         $configArray['Plugin.ShareThis.PublisherNumber'] = $publisherNumber;
         $configArray['Plugin.ShareThis.ViaHandle'] = $viaHandle;
         $configArray['Plugin.ShareThis.CopyNShare'] = $copyNShare;
      }

      $configurationModel->setField($configArray);
      $sender->Form->setModel($configurationModel);
      // If seeing the form for the first time...
      if ($sender->Form->authenticatedPostBack() === FALSE) {
         // Apply the config settings to the form.
         $sender->Form->setData($configurationModel->Data);
      } else {
         // Define some validation rules for the fields being saved
         $configurationModel->Validation->applyRule('Plugin.ShareThis.PublisherNumber', 'Required');
         if ($sender->Form->save() !== FALSE)
            $sender->informMessage(t("Your changes have been saved."));
      }

      $sender->render('sharethis', '', 'plugins/ShareThis');
   }

    public function discussionController_render_before($sender) {
        $sender->addCssFile('ShareThis.css', 'plugins/ShareThis');
    }
}

