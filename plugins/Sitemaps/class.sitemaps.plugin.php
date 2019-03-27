<?php if (!defined('APPLICATION')) { exit; }

/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

class SitemapsPlugin extends Gdn_Plugin {
    /**
     * @var \Garden\EventManager
     */
    private $eventManager;

    public function __construct(\Garden\EventManager $eventManager) {
        parent::__construct();
        $this->eventManager = $eventManager;
    }

    /// Methods ///

    public function buildCategorySiteMap($urlCode, &$urls) {
        $category = CategoryModel::categories($urlCode);
        if (!$category) {
            throw notFoundException();
        }

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

        for ($i = $from; $i >= $to; $i = strtotime('-1 month', $i)) {
            $url = [
                'Loc' => url('/categories/archives/'.rawurlencode($category['UrlCode'] ? $category['UrlCode'] : $category['CategoryID']).'/'.gmdate('Y-m', $i), true),
                'LastMod' => '',
                'ChangeFreq' => ''
            ];

            $lastMod = strtotime('last day of this month', $i);
            if ($lastMod > $now) {
                $lastMod = $now;
            }
            $url['LastMod'] = gmdate('c', $lastMod);

            $urls[] = $url;
        }

        // If there are no links then just link to the category.
        if (count($urls) === 0) {
            $url = [
                'Loc' => categoryUrl($category),
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

    public function settingsController_sitemaps_create($sender) {
        $sender->permission('Garden.Settings.Manage');
        $sender->setData('Title', t('Sitemap Settings'));
        $sender->addSideMenu();
        $sender->render('Settings', '', 'plugins/Sitemaps');
    }

    /**
     * @param Gdn_Controller $sender
     */
    public function utilityController_robots_create($sender) {
        // Clear the session to mimic a crawler.
        Gdn::session()->UserID = 0;
        Gdn::session()->User = false;
        $sender->deliveryMethod(DELIVERY_METHOD_XHTML);
        $sender->deliveryType(DELIVERY_TYPE_VIEW);
        $sender->setHeader('Content-Type', 'text/plain');

        $robots = new \Vanilla\Web\Robots();
        $robots->addSitemap('/sitemapindex.xml');
        $robots->addRule(c('Sitemap.Robots.Rules', 'User-agent: *
Disallow: /entry/
Disallow: /messages/
Disallow: /profile/comments/
Disallow: /profile/discussions/
Disallow: /search/'));
        $sender->setData('robots', $robots);
        $this->eventManager->fire('robots_init', $robots);

        $sender->render('Robots', '', 'plugins/Sitemaps');
    }

    /**
     * @param UtilityController $Sender Sending controller instance
     * @param array $args Event's arguments
     */
    public function utilityController_siteMapIndex_create($Sender, $args) {
        // Clear the session to mimic a crawler.
        Gdn::session()->start(0, false, false);
        $Sender->deliveryMethod(DELIVERY_METHOD_XHTML);
        $Sender->deliveryType(DELIVERY_TYPE_VIEW);
        $Sender->setHeader('Content-Type', 'text/xml');

        $SiteMaps = [];

        if (class_exists('CategoryModel')) {
            $Categories = CategoryModel::categories();

            $this->EventArguments['Categories'] = &$categories;
            $this->fireEvent('siteMapCategories');

            foreach ($Categories as $Category) {
                if (!$Category['PermsDiscussionsView'] || $Category['CategoryID'] < 0 || $Category['CountDiscussions'] == 0) {
                    continue;
                }

                $SiteMap = [
                    'Loc' => url('/sitemap-category-'.rawurlencode($Category['UrlCode'] ? $Category['UrlCode'] : $Category['CategoryID']).'.xml', true),
                    'LastMod' => $Category['DateLastComment'],
                    'ChangeFreq' => '',
                    'Priority' => ''
                ];
                $SiteMaps[] = $SiteMap;
            }
        }
        $Sender->setData('SiteMaps', $SiteMaps);
        $Sender->render('SiteMapIndex', '', 'plugins/Sitemaps');
    }

    /**
     * @param UtilityController $sender Sending controller instance
     * @param array $args Event's arguments
     */
    public function utilityController_siteMap_create($sender, $args) {
        Gdn::session()->start(0, false, false);
        $sender->deliveryMethod(DELIVERY_METHOD_XHTML);
        $sender->deliveryType(DELIVERY_TYPE_VIEW);
        $sender->setHeader('Content-Type', 'text/xml');

        $arg = stringEndsWith(val(0, $args), '.xml', true, true);
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
