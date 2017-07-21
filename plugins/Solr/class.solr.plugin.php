<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */

class SolrPlugin extends Gdn_Plugin {
   public function SettingsController_Solr_Create($sender, $args = []) {
      $sender->Permission('Garden.Settings.Manage');

      $conf = new ConfigurationModule($sender);
      $conf->Initialize([
          'Plugins.Solr.SearchUrl' => ['Default' => 'http://localhost:8983/solr/select/?']
      ]);

      $sender->AddSideMenu();
      $sender->SetData('Title', T('Solr Search Settings'));
      $sender->ConfigurationModule = $conf;
//      $Conf->RenderAll();
      $sender->Render('Settings', '', 'plugins/Solr');
   }
}
