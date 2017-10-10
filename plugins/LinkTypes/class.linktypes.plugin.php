<?php if (!defined('APPLICATION')) { exit(); }
/**
 * @copyright 2009-2017 Vanilla Forums, Inc.
 * @license GNU GPLv2
 */

/**
 * Class LinkTypesPlugin
 */
class LinkTypesPlugin extends Gdn_Plugin {
   /**
    * Add JS file.
    *
    * @param $sender AssetModel
    */
   public function base_render_before($sender) {
      $sender->addJsFile('linktypes.js', 'plugins/LinkTypes');
   }
}
