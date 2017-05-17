<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright 2009-2014 Vanilla Forums, Inc.
 * @license GNU GPLv2
 */

class LinkTypesPlugin extends Gdn_Plugin {
   /**
    * Add JS file.
    *
    * @param $Sender AssetModel
    */
   public function Base_Render_Before($Sender) {
      $Sender->AddJsFile('linktypes.js', 'plugins/LinkTypes');
   }
   
   /**
    * Plugin setup.
    */
   public function Setup() {

   }
}
