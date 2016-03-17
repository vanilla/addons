<?php
/**
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU GPL v2
 */
$PluginInfo['PrefixDiscussionFilter'] = array(
    'Name' => 'PrefixDiscussion Filter',
    'Description' => 'Allow filtering of disussions by prefixes.',
    'Version' => '1.0',
    'RequiredApplications' => array('Vanilla' => '2.2.102'),
    'RequiredPlugins' => array('PrefixDiscussion' => '1.1'),
    'HasLocale' => false,
    'License' => 'GNU GPL2',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'Author' => 'Alexandre (DaazKu) Chouinard',
    'AuthorEmail' => 'alexandre.c@vanillaforums.com'
);

/**
 * Class PrefixDiscussionFilterPlugin
 */
class PrefixDiscussionFilterPlugin extends Gdn_Plugin {
    /**
     * Setup is called when plugin is enabled and prepares config and db.
     */
    public function setup() {
        $this->structure();
    }

    /**
     * Update the DB structure. Called on /utility/update and when the plugin is enabled
     */
    public function structure() {
        // Add the new index (`Prefix`, `CategoryID`)
        Gdn::database()->structure()
            ->table('Discussion')
            ->column('Prefix', 'varchar(64)', true, ['index.PrefixCategory'])
            ->column('CategoryID', 'int', false, ['index.PrefixCategory'])
            ->set();
    }

    /**
     * Transform a string into a slug (URL compatible "token")
     *
     * @param string $prefix Prefix
     * @return string The slug
     */
    protected function stringToSlug($prefix) {
        static $usedSlug = [];

        $prefix = str_replace(' ', '-', strtolower($prefix));
        $prefix = preg_replace('#-+#', '-', $prefix);
        $slug = preg_replace('#[^a-z0-9-]#', null, $prefix);

        if (strlen($slug) === 0) {
            // Reliable way to get something 'unique' that is URL friendly
            $slug = hash('crc32b', $prefix);
        }

        // We cannot have 2 times the same slug :D
        while(in_array($slug, $usedSlug)) {
            $slug = hash('crc32b', $slug);
        }

        $usedSlug[] = $slug;

        return $slug;
    }

    /**
     * Add new filters to the discussion model
     *
     * @param DiscussionModel $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionModel_initStatic_handler($sender, $args) {
        DiscussionModel::addFilterSet('prefix', 'Prefixes');
        DiscussionModel::addFilter('has-prefix', 'Has prefix', ['d.Prefix IS NOT NULL' => null], 'base-filter', 'prefix');
        DiscussionModel::addFilter('no-prefix', 'No prefix', ['d.Prefix IS NULL' => null], 'base-filter', 'prefix');

        $currentPrefixes = PrefixDiscussionPlugin::getPrefixes();
        unset($currentPrefixes['-']);

        $usedPrefixesResult =
            Gdn::sql()
                ->select('Prefix')
                ->from('Discussion')
                ->where('Prefix IS NOT NULL')
                ->get()
                ->resultArray();

        foreach($usedPrefixesResult as $row) {
            $prefix = $row['Prefix'];
            if (!isset($currentPrefixes[$prefix])) {
                $currentPrefixes[$prefix] = $prefix;
            }
        }

        natsort($currentPrefixes);
        foreach($currentPrefixes as $prefix) {
            DiscussionModel::addFilter('prefix-'.$this->stringToSlug($prefix), $prefix, ['d.Prefix' => $prefix], 'prefix-filter', 'prefix');
        }
    }

    /**
     * Inject the DiscussionsSortFilterModule in in the page
     *
     * @param object $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function base_pageControls_handler($sender, $args) {
        echo new DiscussionsSortFilterModule();
    }
}
