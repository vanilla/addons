<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

// Define the plugin:
$PluginInfo['Sitemaps'] = [
   'Name' => 'Sitemaps',
   'Description' => "Creates an XML sitemap based on http://www.sitemaps.org.",
   'Version' => '2.0.1',
   'MobileFriendly' => true,
   'RequiredApplications' => ['Vanilla' => '2.0.18'],
   'RequiredTheme' => false,
   'RequiredPlugins' => false,
   'HasLocale' => true,
   'RegisterPermissions' => false,
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com',
   'SettingsUrl' => '/settings/sitemaps',
   'SettingsPermission' => 'Garden.Settings.Manage',
   'Icon' => 'site-maps.png'
];

class SitemapsPlugin extends Gdn_Plugin {

   /// Methods ///

   public function buildCategorySiteMap($urlCode, &$urls) {
      $category = CategoryModel::categories($urlCode);
      if (!$category)
         throw notFoundException();

      // Get the min/max dates for the sitemap.
      $row = Gdn::sql()
         ->select('DateInserted', 'min', 'MinDate')
         ->select('DateInserted', 'max', 'MaxDate')
         ->from('Discussion')
         ->where('CategoryID', $category['CategoryID'])
         ->get()->firstRow(DATASET_TYPE_ARRAY);

      if ($row) {
         $from = strtotime('first day of this month 00:00:00', strtotime($row['MaxDate']));
         $to = strtotime('first day of this month 00:00:00', strtotime($row['MinDate']));

         if (!$from || !$to) {
            $from = -1;
            $to = 0;
         }
      } else {
         $from = -1;
         $to = 0;
      }

      $now = time();

      for( $i = $from; $i >= $to; $i = strtotime('-1 month', $i) ){
         $url = [
            'Loc' => url('/categories/archives/'.rawurlencode($category['UrlCode'] ? $category['UrlCode'] : $category['CategoryID']).'/'.gmdate('Y-m', $i), true),
            'LastMod' => '',
            'ChangeFreq' => ''
         ];

         $lastMod = strtotime('last day of this month', $i);
         if( $lastMod > $now ){
            $lastMod = $now;
         }
         $url['LastMod'] = gmdate('c', $lastMod);

         $urls[] = $url;
      }

      // If there are no links then just link to the category.
      if( count($urls) === 0 ){
         $url = [
            'Loc' => CategoryUrl($category),
            'LastMode' => '',
            'ChangeFreq' => ''
         ];
         $urls[] = $url;
      }
   }

   public function setup() {
      $this->structure();
   }

   public function structure() {
      Gdn::router()->setRoute('sitemapindex.xml', '/utility/sitemapindex.xml', 'Internal');
      Gdn::router()->setRoute('sitemap-(.+)', '/utility/sitemap/$1', 'Internal');
      Gdn::router()->setRoute('robots.txt', '/utility/robots', 'Internal');
   }


   /// Event Handlers ///

   public function settingsController_Sitemaps_Create($sender) {
      $sender->permission('Garden.Settings.Manage');
      $sender->setData('Title', t('Sitemap Settings'));
      $sender->addSideMenu();
      $sender->render('Settings', '', 'plugins/Sitemaps');
   }

   /**
    * @param Gdn_Controller $sender
    */
   public function utilityController_Robots_Create($sender) {
      // Clear the session to mimic a crawler.
      Gdn::session()->UserID = 0;
      Gdn::session()->User = false;
      $sender->deliveryMethod(DELIVERY_METHOD_XHTML);
      $sender->deliveryType(DELIVERY_TYPE_VIEW);
      $sender->setHeader('Content-Type', 'text/plain');

      $sender->render('Robots', '', 'plugins/Sitemaps');
   }



   /**
    * @param Gdn_Controller $sender
    * @param type $Args
    */
   public function utilityController_SiteMapIndex_Create($sender) {
      // Clear the session to mimic a crawler.
      Gdn::session()->start(0, false, false);
      $sender->deliveryMethod(DELIVERY_METHOD_XHTML);
      $sender->deliveryType(DELIVERY_TYPE_VIEW);
      $sender->setHeader('Content-Type', 'text/xml');

      $siteMaps = [];

      if (class_exists('CategoryModel')) {
        $Categories = CategoryModel::categories();
        $sender->EventArguments['Categories'] = &$categories;
        $this->fireEvent('siteMapCategories');

         foreach ($Categories as $category) {
            if (!$category['PermsDiscussionsView'] || $category['CategoryID'] < 0 || $category['CountDiscussions'] == 0)
               continue;

            $SiteMap = [
                'Loc' => Url('/sitemap-category-'.rawurlencode($category['UrlCode'] ? $category['UrlCode'] : $category['CategoryID']).'.xml', true),
                'LastMod' => $category['DateLastComment'],
                'ChangeFreq' => '',
                'Priority' => ''
            ];
            $siteMaps[] = $SiteMap;
         }
      }
      $sender->setData('SiteMaps', $siteMaps);
      $sender->render('SiteMapIndex', '', 'plugins/Sitemaps');
   }

   public function utilityController_SiteMap_Create($sender, $Args = []) {
      Gdn::session()->start(0, false, false);
      $sender->deliveryMethod(DELIVERY_METHOD_XHTML);
      $sender->deliveryType(DELIVERY_TYPE_VIEW);
      $sender->setHeader('Content-Type', 'text/xml');

      $arg = stringEndsWith(val(0, $Args), '.xml', true, true);
      $parts = explode('-', $arg, 2);
      $type = strtolower($parts[0]);
      $arg = val(1, $parts, '');

      $urls = [];
      switch ($type) {
         case 'category':
            // Build the category site map.
            $this->buildCategorySiteMap($arg, $urls);
            break;
         default:
            // See if a plugin can build the sitemap.
            $this->EventArguments['Type'] = $type;
            $this->EventArguments['Arg'] = $arg;
            $this->EventArguments['Urls'] =& $urls;
            $this->fireEvent('SiteMap'.ucfirst($type));
            break;
      }

      $sender->setData('Urls', $urls);
      $sender->render('SiteMap', '', 'plugins/Sitemaps');
   }
}
