<?php if (!defined('APPLICATION')) exit();

class CategoryCollapserPlugin extends Gdn_Plugin {
   /**
	 * Just include our JS on all category pages.
	 */
   public function CategoriesController_Render_Before($Sender) {
      $Sender->AddJsFile("category_collapse.js", "plugins/CategoryCollapser");
      $Style = Wrap('
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
      $Sender->AddAsset('Head', $Style);
   }
}
