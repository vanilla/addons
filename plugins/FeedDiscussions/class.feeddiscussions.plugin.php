<?php
/**
 * Feed Discussions
 *
 * Automatically creates new discussions based on content imported from supplied RSS feeds.
 *
 * Changes:
 *  1.0     Initial release/rewrite
 *  1.0.1   Minor fixes for logic
 *  1.0.2   Fix repeat posting bug
 *  1.0.3   Change version requirement to 2.0.18.4
 *  1.1     Changed paths
 *  1.1.1   Fire 'Published' event after publication
 *  1.2     Cleanup docs & version
 *  1.2.1   Include link to source of feed
 *  1.2.2   Tigthen permissions
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2003 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Addons
 */

// Define the plugin:
$PluginInfo['FeedDiscussions'] = [
    'Name' => 'Feed Discussions',
    'Description' => "Automatically creates new discussions based on content imported from supplied RSS feeds.",
    'Version' => '1.2.2',
    'RequiredApplications' => ['Vanilla' => '2.0.18'],
    'HasLocale' => true,
    'RegisterPermissions' => false,
    'Icon' => 'feed_discussions.png',
    'Author' => "Tim Gunter",
    'AuthorEmail' => 'tim@vanillaforums.com',
    'AuthorUrl' => 'http://www.vanillaforums.com',
    'License' => 'GNU GPLv2'
];

class FeedDiscussionsPlugin extends Gdn_Plugin {

    protected $FeedList    = null;
    protected $RawFeedList = null;

    /**
     * Set up appmenu link
     */
    public function base_getAppSettingsMenuItems_handler($Sender) {
        $Menu = &$Sender->EventArguments['SideMenu'];
        $Menu->addItem('Forum', t('Forum'));
        $Menu->addLink('Forum', t('Feed Discussions'), 'plugin/feeddiscussions', 'Garden.Settings.Manage');
    }

    /**
     * Include Javascript in discussion pages.
     *
     * @param $Sender
     */
    public function discussionController_beforeDiscussionRender_handler($Sender) {
        if ($this->isEnabled()) {
            if ($this->checkFeeds(false)) {
                $Sender->addJsFile('feeddiscussions.js', 'plugins/FeedDiscussions');
            }

            $Sender->addCssFile('feeddiscussions.css', 'plugins/FeedDiscussions');
        }
    }

    /**
     * Act as a mini dispatcher for API requests to the plugin app
     */
    public function pluginController_feedDiscussions_create($Sender) {
        $this->dispatch($Sender, $Sender->RequestArgs);
    }

    /**
     * Handle toggling of the FeedDiscussions.Enabled setting
     *
     * This method handles the internally re-dispatched call generated when a user clicks
     * the 'Enable' or 'Disable' button within the dashboard settings page for Feed Discussions.
     */
    public function controller_Toggle($Sender) {
        $Sender->permission('Garden.Settings.Manage');

        // Handle Enabled/Disabled toggling
        $this->autoToggle($Sender);
    }

    /**
     * Endpoint to trigger feed check & update.
     *
     * @param $Sender
     */
    public function controller_CheckFeeds($Sender) {
        $Sender->deliveryMethod(DELIVERY_METHOD_JSON);
        $Sender->deliveryType(DELIVERY_TYPE_DATA);
        $this->checkFeeds();
        $Sender->render();
    }

    /**
     * Time to update from RSS?
     *
     * @param bool $AutoImport
     * @return bool|int
     */
    public function checkFeeds($AutoImport = true) {
        Gdn::controller()->setData("AutoImport", $AutoImport);
        $NeedToPoll = 0;
        foreach ($this->getFeeds() as $FeedURL => $FeedData) {
            Gdn::controller()->setData("{$FeedURL}", $FeedData);
            // Check feed here
            $LastImport = val('LastImport', $FeedData) == 'never' ? null : strtotime(val('LastImport', $FeedData));
            if (is_null($LastImport)) {
                $LastImport = strtotime(val('Added', $FeedData, 0));
            }

            $Historical = (bool)val('Historical', $FeedData, false);
            $Delay = val('Refresh', $FeedData);
            $DelayStr = '+'.str_replace([
                    'm',
                    'h',
                    'd',
                    'w'
                ], [
                    'minutes',
                    'hours',
                    'days',
                    'weeks'
                ], $Delay);
            $DelayMinTime = strtotime($DelayStr, $LastImport);
            if (
                ($LastImport && time() > $DelayMinTime) ||                  // We've imported before, and this article was published since then

                (!$LastImport && (time() > $DelayMinTime || $Historical))   // We've not imported before, and this is either a new article,
                // or its old and we're allowed to import old articles
            ) {
                if ($AutoImport) {
                    $NeedToPoll = $NeedToPoll | 1;
                    $this->pollFeed($FeedURL, $LastImport);
                } else {
                    return true;
                }
            }
        }
        $NeedToPoll = (bool)$NeedToPoll;
        if ($NeedToPoll && $AutoImport) {
            Gdn::controller()->statusCode(201);
        }

        return $NeedToPoll;
    }

    /**
     * Dashboard settings page.
     *
     * @param $Sender
     */
    public function controller_Index($Sender) {
        $Sender->permission('Garden.Settings.Manage');
        $Sender->title($this->getPluginKey('name'));
        $Sender->addSideMenu('plugin/feeddiscussions');
        $Sender->setData('Description', $this->getPluginKey('description'));
        $Sender->addCssFile('feeddiscussions.css', 'plugins/FeedDiscussions');

        $Categories = CategoryModel::categories();
        $Sender->setData('Categories', $Categories);
        $Sender->setData('Feeds', $this->getFeeds());

        $Sender->render('feeddiscussions', '', 'plugins/FeedDiscussions');
    }

    /**
     * Add a feed.
     *
     * @param $Sender
     */
    public function controller_AddFeed($Sender) {

        $Categories = CategoryModel::categories();
        $Sender->setData('Categories', $Categories);

        // Do addfeed stuff here;
        if ($Sender->Form->authenticatedPostback()) {

            // Grab posted values and merge with defaults
            $FormPostValues = $Sender->Form->formValues();
            $Defaults = [
                'Historical' => 1,
                'Refresh' => '1d',
                'Category' => -1
            ];
            $FormPostValues = array_merge($Defaults, $FormPostValues);

            try {
                $FeedURL = val('FeedURL', $FormPostValues, null);
                if (empty($FeedURL)) {
                    throw new Exception("You must supply a valid Feed URL");
                }

                if ($this->haveFeed($FeedURL, false)) {
                    throw new Exception("The Feed URL you supplied is already part of an Active Feed");
                }

                $FeedCategoryID = val('Category', $FormPostValues);
                if (!array_key_exists($FeedCategoryID, $Categories)) {
                    throw new Exception("You need to select a Category");
                }

                // Check feed is valid RSS:
                $Pr = new ProxyRequest();
                $FeedRSS = $Pr->request([
                    'URL' => $FeedURL
                ]);

                if (!$FeedRSS) {
                    throw new Exception("The Feed URL you supplied is not available");
                }

                $RSSData = simplexml_load_string($FeedRSS);
                if (!$RSSData) {
                    throw new Exception("The Feed URL you supplied is not valid XML");
                }

                $Channel = val('channel', $RSSData, false);
                if (!$Channel) {
                    throw new Exception("The Feed URL you supplied is not an RSS stream");
                }

                $this->addFeed($FeedURL, [
                    'Historical' => $FormPostValues['Historical'],
                    'Refresh' => $FormPostValues['Refresh'],
                    'Category' => $FeedCategoryID,
                    'Added' => date('Y-m-d H:i:s'),
                    'LastImport' => "never"
                ]);
                $Sender->informMessage(sprintf(t("Feed has been added"), $FeedURL));
                $Sender->Form->clearInputs();

            } catch (Exception $e) {
                $Sender->Form->addError(T($e->getMessage()));
            }
        }

        // redirect('/plugin/feeddiscussions/');
        $this->controller_Index($Sender);
    }

    /**
     * Delete a feed.
     *
     * @param $Sender
     */
    public function controller_DeleteFeed($Sender) {
        $FeedKey = val(1, $Sender->RequestArgs, null);
        if (!is_null($FeedKey) && $this->haveFeed($FeedKey)) {
            $Feed = $this->getFeed($FeedKey, true);
            $FeedURL = $Feed['URL'];

            $this->removeFeed($FeedKey);
            $Sender->informMessage(sprintf(t("Feed has been removed"), $FeedURL));
        }

        // redirect('/plugin/feeddiscussions/');
        $this->controller_Index($Sender);
    }

    protected function getFeeds($Raw = false, $Regen = false) {
        if (is_null($this->FeedList) || $Regen) {
            $FeedArray = $this->getUserMeta(0, "Feed.%");

            //die('feeds');
            $this->FeedList = [];
            $this->RawFeedList = [];

            foreach ($FeedArray as $FeedMetaKey => $FeedItem) {
                $DecodedFeedItem = json_decode($FeedItem, true);
                $FeedURL = val('URL', $DecodedFeedItem, null);
                $FeedKey = self::encodeFeedKey($FeedURL);

                if (is_null($FeedURL)) {
                    //$this->removeFeed($FeedKey);
                    continue;
                }

                $this->RawFeedList[$FeedKey] = $this->FeedList[$FeedURL] = $DecodedFeedItem;
            }
        }

        return ($Raw) ? $this->RawFeedList : $this->FeedList;
    }

    protected function pollFeed($FeedURL, $LastImportDate) {
        $Pr = new ProxyRequest();
        $FeedRSS = $Pr->request([
            'URL' => $FeedURL
        ]);

        if (!$FeedRSS) {
            return false;
        }

        $RSSData = simplexml_load_string($FeedRSS);
        if (!$RSSData) {
            return false;
        }

        $Channel = val('channel', $RSSData, false);
        if (!$Channel) {
            return false;
        }

        if (!array_key_exists('item', $Channel)) {
            return false;
        }

        $Feed = $this->getFeed($FeedURL, false);

        $DiscussionModel = new DiscussionModel();
        $DiscussionModel->SpamCheck = false;

        $LastPublishDate = val('LastPublishDate', $Feed, date('c'));
        $LastPublishTime = strtotime($LastPublishDate);

        $FeedLastPublishTime = 0;
        foreach (val('item', $Channel) as $Item) {
            $FeedItemGUID = trim((string)val('guid', $Item));
            if (empty($FeedItemGUID)) {
                trace('guid is not set in each item of the RSS.  Will attempt to use link as unique identifier.');
                $FeedItemGUID = val('link', $Item);
            }
            $FeedItemID = substr(md5($FeedItemGUID), 0, 30);

            $ItemPubDate = (string)val('pubDate', $Item, null);
            if (is_null($ItemPubDate)) {
                $ItemPubTime = time();
            } else {
                $ItemPubTime = strtotime($ItemPubDate);
            }

            if ($ItemPubTime > $FeedLastPublishTime) {
                $FeedLastPublishTime = $ItemPubTime;
            }

            if ($ItemPubTime < $LastPublishTime && !$Feed['Historical']) {
                continue;
            }

            $ExistingDiscussion = $DiscussionModel->getWhere([
                'ForeignID' => $FeedItemID
            ]);

            if ($ExistingDiscussion && $ExistingDiscussion->numRows()) {
                continue;
            }

            $this->EventArguments['Publish'] = true;

            $this->EventArguments['FeedURL'] = $FeedURL;
            $this->EventArguments['Feed'] = &$Feed;
            $this->EventArguments['Item'] = &$Item;
            $this->fireEvent('FeedItem');

            if (!$this->EventArguments['Publish']) {
                continue;
            }

            $StoryTitle = array_shift($Trash = explode("\n", (string)val('title', $Item)));
            $StoryBody = (string)val('description', $Item);
            $StoryPublished = date("Y-m-d H:i:s", $ItemPubTime);

            $ParsedStoryBody = $StoryBody;
            $ParsedStoryBody = '<div class="AutoFeedDiscussion">'.$ParsedStoryBody.'</div> <br /><div class="AutoFeedSource">Source: '.$FeedItemGUID.'</div>';

            $DiscussionData = [
                'Name' => $StoryTitle,
                'Format' => 'Html',
                'CategoryID' => $Feed['Category'],
                'ForeignID' => substr($FeedItemID, 0, 30),
                'Body' => $ParsedStoryBody
            ];

            // Post as Minion (if one exists) or the system user
            if (Gdn::addonManager()->isEnabled('Minion', \Vanilla\Addon::TYPE_ADDON)) {
                $Minion = Gdn::pluginManager()->getPluginInstance('MinionPlugin');
                $InsertUserID = $Minion->getMinionUserID();
            } else {
                $InsertUserID = Gdn::userModel()->getSystemUserID();
            }

            $DiscussionData[$DiscussionModel->DateInserted] = $StoryPublished;
            $DiscussionData[$DiscussionModel->InsertUserID] = $InsertUserID;

            $DiscussionData[$DiscussionModel->DateUpdated] = $StoryPublished;
            $DiscussionData[$DiscussionModel->UpdateUserID] = $InsertUserID;

            $this->EventArguments['FeedDiscussion'] = &$DiscussionData;
            $this->fireEvent('Publish');

            if (!$this->EventArguments['Publish']) {
                continue;
            }

            $InsertID = $DiscussionModel->save($DiscussionData);

            $this->EventArguments['DiscussionID'] = $InsertID;
            $this->EventArguments['Validation'] = $DiscussionModel->Validation;
            $this->fireEvent('Published');

            // Reset discussion validation
            $DiscussionModel->Validation->results(true);
        }

        $FeedKey = self::encodeFeedKey($FeedURL);
        $this->updateFeed($FeedKey, [
            'LastImport' => date('Y-m-d H:i:s'),
            'LastPublishDate' => date('c', $FeedLastPublishTime)
        ]);
    }

    public function replaceBadURLs($Matches) {
        $MatchedURL = $Matches[0];
        $FixedURL = array_pop($Trash = explode("/*", $MatchedURL));
        return 'href="'.$FixedURL.'"';
    }

    protected function addFeed($FeedURL, $Feed) {
        $FeedKey = self::encodeFeedKey($FeedURL);

        $Feed['URL'] = $FeedURL;
        $EncodedFeed = json_encode($Feed);
        $this->setUserMeta(0, "Feed.{$FeedKey}", $EncodedFeed);

        // regenerate the internal feed list
        $this->getFeeds(true, true);
    }

    protected function updateFeed($FeedKey, $FeedOptionKey, $FeedOptionValue = null) {
        $Feed = $this->getFeed($FeedKey);

        if (!is_array($FeedOptionKey)) {
            $FeedOptionKey = [$FeedOptionKey => $FeedOptionValue];
        }

        $Feed = array_merge($Feed, $FeedOptionKey);

        $EncodedFeed = json_encode($Feed);
        $this->setUserMeta(0, "Feed.{$FeedKey}", $EncodedFeed);

        // regenerate the internal feed list
        $this->getFeeds(true, true);
    }

    protected function removeFeed($FeedKey, $PreEncoded = true) {
        $FeedKey = (!$PreEncoded) ? self::encodeFeedKey($FeedKey) : $FeedKey;
        $this->setUserMeta(0, "Feed.{$FeedKey}", null);

        // regenerate the internal feed list
        $this->getFeeds(true, true);
    }

    protected function getFeed($FeedKey, $PreEncoded = true) {
        $FeedKey = (!$PreEncoded) ? self::encodeFeedKey($FeedKey) : $FeedKey;
        $Feeds = $this->getFeeds(true);

        if (array_key_exists($FeedKey, $Feeds)) {
            return $Feeds[$FeedKey];
        }

        return null;
    }

    protected function haveFeed($FeedKey, $PreEncoded = true) {
        $FeedKey = (!$PreEncoded) ? self::encodeFeedKey($FeedKey) : $FeedKey;
        $Feed = $this->getFeed($FeedKey);
        if (!empty($Feed)) {
            return true;
        }
        return false;
    }

    public static function encodeFeedKey($Key) {
        return md5($Key);
    }

    public function setup() {
        // Nothing to do here!
    }
}
