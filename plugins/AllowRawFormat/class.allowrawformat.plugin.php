<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license Proprietary
 */

class AllowRawFormatPlugin extends Gdn_Plugin {
   public function Base_BeforeDispatch_Handler($sender, $args) {
      if (Gdn::Session()->CheckPermission('Plugins.AllowRawFormat.Allow')) {
         SaveToConfig('Garden.InputFormatter', 'Raw', ['Save' => FALSE]);
      }
   }
}
