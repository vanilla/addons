<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */

class SolrPlugin extends Gdn_Plugin {
   public function settingsController_solr_create($sender, $args = []) {
      $sender->permission('Garden.Settings.Manage');

      $conf = new ConfigurationModule($sender);
      $conf->initialize([
          'Plugins.Solr.SearchUrl' => ['Default' => 'http://localhost:8983/solr/select/?']
      ]);

      $sender->addSideMenu();
      $sender->setData('Title', t('Solr Search Settings'));
      $sender->ConfigurationModule = $conf;
//      $Conf->renderAll();
      $sender->render('Settings', '', 'plugins/Solr');
   }
}
