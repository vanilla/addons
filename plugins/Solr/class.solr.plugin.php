<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */

class SolrPlugin extends Gdn_Plugin {
   public function SettingsController_Solr_Create($Sender, $Args = []) {
      $Sender->Permission('Garden.Settings.Manage');

      $Conf = new ConfigurationModule($Sender);
      $Conf->Initialize([
          'Plugins.Solr.SearchUrl' => ['Default' => 'http://localhost:8983/solr/select/?']
      ]);

      $Sender->AddSideMenu();
      $Sender->SetData('Title', T('Solr Search Settings'));
      $Sender->ConfigurationModule = $Conf;
//      $Conf->RenderAll();
      $Sender->Render('Settings', '', 'plugins/Solr');
   }
}
