<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license Proprietary
 */

class AllowRawFormatPlugin extends Gdn_Plugin {
   public function base_beforeDispatch_handler($sender, $args) {
      if (Gdn::session()->checkPermission('Plugins.AllowRawFormat.Allow')) {
         saveToConfig('Garden.InputFormatter', 'Raw', ['Save' => FALSE]);
      }
   }
}
