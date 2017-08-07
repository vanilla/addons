<?php if (!defined('APPLICATION')) exit();

class CategoryCollapserPlugin extends Gdn_Plugin {
   /**
	 * Just include our JS on all category pages.
	 */
   public function categoriesController_render_before($sender) {
      $sender->addJsFile("category_collapse.js", "plugins/CategoryCollapser");
      $style = wrap('
      .Expando {
         float: right;
         background: url(plugins/CategoryCollapser/design/tagsprites.png) no-repeat 0 -52px;
         height: 16px;
         width: 16px;
         color: transparent;
         text-shadow: none;
         cursor: pointer; }
      .Expando-Collapsed .Expando {
         background-position: 0 -69px; }', 'style');
      $sender->addAsset('Head', $style);
   }
}
