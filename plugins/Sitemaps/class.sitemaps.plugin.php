<?php if (!defined('APPLICATION')) {
    exit();
}
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

// Define the plugin:
$PluginInfo['Sitemaps'] = array(
    'Name' => 'Sitemaps',
    'Description' => "Creates an XML sitemap based on http://www.sitemaps.org.",
    'Version' => '2.0.1',
    'MobileFriendly' => true,
    'RequiredApplications' => array('Vanilla' => '2.0.18'),
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
);

class SitemapsPlugin extends Gdn_Plugin {

    /// Methods ///

    public function buildCategorySiteMap($UrlCode, &$Urls) {
        $Category = CategoryModel::categories($UrlCode);
        if (!$Category) {
            throw notFoundException();
        }

        // Get the min/max dates for the sitemap.
        $Row = Gdn::sql()
            ->select('DateInserted', 'min', 'MinDate')
            ->select('DateInserted', 'max', 'MaxDate')
            ->from('Discussion')
            ->where('CategoryID', $Category['CategoryID'])
            ->get()->firstRow(DATASET_TYPE_ARRAY);

        if ($Row) {
            $From = strtotime('first day of this month 00:00:00', strtotime($Row['MaxDate']));
            $To = strtotime('first day of this month 00:00:00', strtotime($Row['MinDate']));

            if (!$From || !$To) {
                $From = -1;
                $To = 0;
            }
        } else {
            $From = -1;
            $To = 0;
        }

        $Now = time();

        for ($i = $From; $i >= $To; $i = strtotime('-1 month', $i)) {
            $Url = array(
                'Loc' => url('/categories/archives/'.rawurlencode($Category['UrlCode'] ? $Category['UrlCode'] : $Category['CategoryID']).'/'.gmdate('Y-m', $i), true),
                'LastMod' => '',
                'ChangeFreq' => ''
            );

            $LastMod = strtotime('last day of this month', $i);
            if ($LastMod > $Now) {
                $LastMod = $Now;
            }
            $Url['LastMod'] = gmdate('c', $LastMod);

            $Urls[] = $Url;
        }

        // If there are no links then just link to the category.
        if (count($Urls) === 0) {
            $Url = array(
                'Loc' => categoryUrl($Category),
                'LastMode' => '',
                'ChangeFreq' => ''
            );
            $Urls[] = $Url;

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

    public function settingsController_sitemaps_create($Sender) {
        $Sender->permission('Garden.Settings.Manage');
        $Sender->setData('Title', t('Sitemap Settings'));
        $Sender->addSideMenu();
        $Sender->render('Settings', '', 'plugins/Sitemaps');
    }

    /**
     * @param Gdn_Controller $Sender
     */
    public function utilityController_robots_create($Sender) {
        // Clear the session to mimic a crawler.
        Gdn::session()->UserID = 0;
        Gdn::session()->User = false;
        $Sender->deliveryMethod(DELIVERY_METHOD_XHTML);
        $Sender->deliveryType(DELIVERY_TYPE_VIEW);
        $Sender->setHeader('Content-Type', 'text/plain');

        $Sender->render('Robots', '', 'plugins/Sitemaps');
    }

    /**
     * @param Gdn_Controller $Sender
     * @param type $Args
     */
    public function utilityController_siteMapIndex_create($Sender) {
        // Clear the session to mimic a crawler.
        Gdn::session()->start(0, false, false);
        $Sender->deliveryMethod(DELIVERY_METHOD_XHTML);
        $Sender->deliveryType(DELIVERY_TYPE_VIEW);
        $Sender->setHeader('Content-Type', 'text/xml');

        $SiteMaps = array();

        if (class_exists('CategoryModel')) {
            $Categories = CategoryModel::categories();
            foreach ($Categories as $Category) {
                if (!$Category['PermsDiscussionsView'] || $Category['CategoryID'] < 0 || $Category['CountDiscussions'] == 0) {
                    continue;
                }

                $SiteMap = array(
                    'Loc' => url('/sitemap-category-'.rawurlencode($Category['UrlCode'] ? $Category['UrlCode'] : $Category['CategoryID']).'.xml', true),
                    'LastMod' => $Category['DateLastComment'],
                    'ChangeFreq' => '',
                    'Priority' => ''
                );
                $SiteMaps[] = $SiteMap;
            }
        }
        $Sender->setData('SiteMaps', $SiteMaps);
        $Sender->render('SiteMapIndex', '', 'plugins/Sitemaps');
    }

    public function utilityController_siteMap_create($Sender, $Args = array()) {
        Gdn::session()->start(0, false, false);
        $Sender->deliveryMethod(DELIVERY_METHOD_XHTML);
        $Sender->deliveryType(DELIVERY_TYPE_VIEW);
        $Sender->setHeader('Content-Type', 'text/xml');

        $Arg = stringEndsWith(GetValue(0, $Args), '.xml', true, true);
        $Parts = explode('-', $Arg, 2);
        $Type = strtolower($Parts[0]);
        $Arg = getValue(1, $Parts, '');

        $Urls = array();
        switch ($Type) {
            case 'category':
                // Build the category site map.
                $this->buildCategorySiteMap($Arg, $Urls);
                break;
            default:
                // See if a plugin can build the sitemap.
                $this->EventArguments['Type'] = $Type;
                $this->EventArguments['Arg'] = $Arg;
                $this->EventArguments['Urls'] =& $Urls;
                $this->fireEvent('SiteMap'.ucfirst($Type));
                break;
        }

        $Sender->setData('Urls', $Urls);
        $Sender->render('SiteMap', '', 'plugins/Sitemaps');
    }
}
