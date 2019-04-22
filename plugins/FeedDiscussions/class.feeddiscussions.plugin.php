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

class FeedDiscussionsPlugin extends Gdn_Plugin {

    protected $FeedList    = null;
    protected $RawFeedList = null;

    /**
     * Set up appmenu link
     */
    public function base_getAppSettingsMenuItems_handler($sender) {
        $menu = &$sender->EventArguments['SideMenu'];
        $menu->addItem('Forum', t('Forum'));
        $menu->addLink('Forum', t('Feed Discussions'), 'plugin/feeddiscussions', 'Garden.Settings.Manage');
    }

    /**
     * Include Javascript in discussion pages.
     *
     * @param $sender
     */
    public function discussionController_beforeDiscussionRender_handler($sender) {
        if ($this->isEnabled()) {
            if ($this->checkFeeds(false)) {
                $sender->addJsFile('feeddiscussions.js', 'plugins/FeedDiscussions');
            }

            $sender->addCssFile('feeddiscussions.css', 'plugins/FeedDiscussions');
        }
    }

    /**
     * Act as a mini dispatcher for API requests to the plugin app
     */
    public function pluginController_feedDiscussions_create($sender) {
        $this->dispatch($sender, $sender->RequestArgs);
    }

    /**
     * Handle toggling of the FeedDiscussions.Enabled setting
     *
     * This method handles the internally re-dispatched call generated when a user clicks
     * the 'Enable' or 'Disable' button within the dashboard settings page for Feed Discussions.
     */
    public function controller_Toggle($sender) {
        $sender->permission('Garden.Settings.Manage');

        // Handle Enabled/Disabled toggling
        $this->autoToggle($sender);
    }

    /**
     * Endpoint to trigger feed check & update.
     *
     * @param $sender
     */
    public function controller_CheckFeeds($sender) {
        $sender->deliveryMethod(DELIVERY_METHOD_JSON);
        $sender->deliveryType(DELIVERY_TYPE_DATA);
        $this->checkFeeds();
        $sender->render();
    }

    /**
     * Time to update from RSS?
     *
     * @param bool $autoImport
     * @return bool|int
     */
    public function checkFeeds($autoImport = true) {
        Gdn::controller()->setData("AutoImport", $autoImport);
        $needToPoll = 0;
        foreach ($this->getFeeds() as $feedURL => $feedData) {
            Gdn::controller()->setData("{$feedURL}", $feedData);
            // Check feed here
            $lastImport = val('LastImport', $feedData) == 'never' ? null : strtotime(val('LastImport', $feedData));
            if (is_null($lastImport)) {
                $lastImport = strtotime(val('Added', $feedData, 0));
            }

            $historical = (bool)val('Historical', $feedData, false);
            $delay = val('Refresh', $feedData);
            $delayStr = '+'.str_replace([
                    'm',
                    'h',
                    'd',
                    'w'
                ], [
                    'minutes',
                    'hours',
                    'days',
                    'weeks'
                ], $delay);
            $delayMinTime = strtotime($delayStr, $lastImport);
            if (
                ($lastImport && time() > $delayMinTime) ||                  // We've imported before, and this article was published since then

                (!$lastImport && (time() > $delayMinTime || $historical))   // We've not imported before, and this is either a new article,
                // or its old and we're allowed to import old articles
            ) {
                if ($autoImport) {
                    $needToPoll = $needToPoll | 1;
                    $this->pollFeed($feedURL, $lastImport);
                } else {
                    return true;
                }
            }
        }
        $needToPoll = (bool)$needToPoll;
        if ($needToPoll && $autoImport) {
            Gdn::controller()->statusCode(201);
        }

        return $needToPoll;
    }

    /**
     * Dashboard settings page.
     *
     * @param $sender
     */
    public function controller_Index($sender) {
        $sender->permission('Garden.Settings.Manage');
        $sender->title($this->getPluginKey('name'));
        $sender->addSideMenu('plugin/feeddiscussions');
        $sender->setData('Description', $this->getPluginKey('description'));
        $sender->addCssFile('feeddiscussions.css', 'plugins/FeedDiscussions');

        $categories = CategoryModel::categories();
        $sender->setData('Categories', $categories);
        $sender->setData('Feeds', $this->getFeeds());

        $sender->render('feeddiscussions', '', 'plugins/FeedDiscussions');
    }

    /**
     * Add a feed.
     *
     * @param $sender
     */
    public function controller_AddFeed($sender) {

        $categories = CategoryModel::categories();
        $sender->setData('Categories', $categories);

        // Do addfeed stuff here;
        if ($sender->Form->authenticatedPostback()) {

            // Grab posted values and merge with defaults
            $formPostValues = $sender->Form->formValues();
            $defaults = [
                'Historical' => 1,
                'Refresh' => '1d',
                'Category' => -1
            ];
            $formPostValues = array_merge($defaults, $formPostValues);

            try {
                $feedURL = val('FeedURL', $formPostValues, null);
                if (empty($feedURL)) {
                    throw new Exception("You must supply a valid Feed URL");
                }

                if ($this->haveFeed($feedURL, false)) {
                    throw new Exception("The Feed URL you supplied is already part of an Active Feed");
                }

                $feedCategoryID = val('Category', $formPostValues);
                if (!array_key_exists($feedCategoryID, $categories)) {
                    throw new Exception("You need to select a Category");
                }

                // Check feed is valid RSS:
                $pr = new ProxyRequest();
                $feedRSS = $pr->request([
                    'URL' => $feedURL
                ]);

                if (!$feedRSS) {
                    throw new Exception("The Feed URL you supplied is not available");
                }

                $rSSData = simplexml_load_string($feedRSS);
                if (!$rSSData) {
                    throw new Exception("The Feed URL you supplied is not valid XML");
                }

                $channel = val('channel', $rSSData, false);
                if (!$channel) {
                    throw new Exception("The Feed URL you supplied is not an RSS stream");
                }

                $this->addFeed($feedURL, [
                    'Historical' => $formPostValues['Historical'],
                    'Refresh' => $formPostValues['Refresh'],
                    'Category' => $feedCategoryID,
                    'Added' => date('Y-m-d H:i:s'),
                    'LastImport' => "never"
                ]);
                $sender->informMessage(sprintf(t("Feed has been added"), $feedURL));
                $sender->Form->clearInputs();

            } catch (Exception $e) {
                $sender->Form->addError(t($e->getMessage()));
            }
        }

        // redirectTo('/plugin/feeddiscussions/');
        $this->controller_Index($sender);
    }

    /**
     * Delete a feed.
     *
     * @param $sender
     */
    public function controller_DeleteFeed($sender) {
        $sender->permission('Garden.Settings.Manage');
        if (!$sender->Form->authenticatedPostBack()) {
            throw new Exception('Requires POST', 405);
        }
        $feedKey = val(1, $sender->RequestArgs, null);
        if (!is_null($feedKey) && $this->haveFeed($feedKey)) {
            $feed = $this->getFeed($feedKey, true);
            $feedURL = $feed['URL'];

            $this->removeFeed($feedKey);
            $sender->informMessage(sprintf(t("Feed has been removed"), $feedURL));
        }

        // redirectTo('/plugin/feeddiscussions/');
        $this->controller_Index($sender);
    }

    protected function getFeeds($raw = false, $regen = false) {
        if (is_null($this->FeedList) || $regen) {
            $feedArray = $this->getUserMeta(0, "Feed.%");

            //die('feeds');
            $this->FeedList = [];
            $this->RawFeedList = [];

            foreach ($feedArray as $feedMetaKey => $feedItem) {
                $decodedFeedItem = json_decode($feedItem, true);
                $feedURL = val('URL', $decodedFeedItem, null);
                $feedKey = self::encodeFeedKey($feedURL);

                if (is_null($feedURL)) {
                    //$this->removeFeed($FeedKey);
                    continue;
                }

                $this->RawFeedList[$feedKey] = $this->FeedList[$feedURL] = $decodedFeedItem;
            }
        }

        return ($raw) ? $this->RawFeedList : $this->FeedList;
    }

    protected function pollFeed($feedURL, $lastImportDate) {
        $pr = new ProxyRequest();
        $feedRSS = $pr->request([
            'URL' => $feedURL
        ]);

        if (!$feedRSS) {
            return false;
        }

        $rSSData = simplexml_load_string($feedRSS);
        if (!$rSSData) {
            return false;
        }

        $channel = val('channel', $rSSData, false);
        if (!$channel) {
            return false;
        }

        if (!array_key_exists('item', $channel)) {
            return false;
        }

        $feed = $this->getFeed($feedURL, false);

        $discussionModel = new DiscussionModel();
        $discussionModel->SpamCheck = false;

        $lastPublishDate = val('LastPublishDate', $feed, date('c'));
        $lastPublishTime = strtotime($lastPublishDate);

        $feedLastPublishTime = 0;
        foreach (val('item', $channel) as $item) {
            $feedItemGUID = trim((string)val('guid', $item));
            if (empty($feedItemGUID)) {
                trace('guid is not set in each item of the RSS.  Will attempt to use link as unique identifier.');
                $feedItemGUID = val('link', $item);
            }
            $feedItemID = substr(md5($feedItemGUID), 0, 30);

            $itemPubDate = (string)val('pubDate', $item, null);
            if (is_null($itemPubDate)) {
                $itemPubTime = time();
            } else {
                $itemPubTime = strtotime($itemPubDate);
            }

            if ($itemPubTime > $feedLastPublishTime) {
                $feedLastPublishTime = $itemPubTime;
            }

            if ($itemPubTime < $lastPublishTime && !$feed['Historical']) {
                continue;
            }

            $existingDiscussion = $discussionModel->getWhere([
                'ForeignID' => $feedItemID
            ]);

            if ($existingDiscussion && $existingDiscussion->numRows()) {
                continue;
            }

            $this->EventArguments['Publish'] = true;

            $this->EventArguments['FeedURL'] = $feedURL;
            $this->EventArguments['Feed'] = &$feed;
            $this->EventArguments['Item'] = &$item;
            $this->fireEvent('FeedItem');

            if (!$this->EventArguments['Publish']) {
                continue;
            }

            $storyTitle = array_shift($trash = explode("\n", (string)val('title', $item)));
            $storyBody = (string)val('description', $item);
            $storyPublished = date("Y-m-d H:i:s", $itemPubTime);

            $parsedStoryBody = $storyBody;
            $parsedStoryBody = '<div class="AutoFeedDiscussion">'.$parsedStoryBody.'</div> <br /><div class="AutoFeedSource">Source: '.$feedItemGUID.'</div>';

            $discussionData = [
                'Name' => $storyTitle,
                'Format' => 'Html',
                'CategoryID' => $feed['Category'],
                'ForeignID' => substr($feedItemID, 0, 30),
                'Body' => $parsedStoryBody
            ];

            // Post as Minion (if one exists) or the system user
            if (Gdn::addonManager()->isEnabled('Minion', \Vanilla\Addon::TYPE_ADDON)) {
                $minion = Gdn::pluginManager()->getPluginInstance('MinionPlugin');
                $insertUserID = $minion->getMinionUserID();
            } else {
                $insertUserID = Gdn::userModel()->getSystemUserID();
            }

            $discussionData[$discussionModel->DateInserted] = $storyPublished;
            $discussionData[$discussionModel->InsertUserID] = $insertUserID;

            $discussionData[$discussionModel->DateUpdated] = $storyPublished;
            $discussionData[$discussionModel->UpdateUserID] = $insertUserID;

            $this->EventArguments['FeedDiscussion'] = &$discussionData;
            $this->fireEvent('Publish');

            if (!$this->EventArguments['Publish']) {
                continue;
            }

            $insertID = $discussionModel->save($discussionData);

            $this->EventArguments['DiscussionID'] = $insertID;
            $this->EventArguments['Validation'] = $discussionModel->Validation;
            $this->fireEvent('Published');

            // Reset discussion validation
            $discussionModel->Validation->results(true);
        }

        $feedKey = self::encodeFeedKey($feedURL);
        $this->updateFeed($feedKey, [
            'LastImport' => date('Y-m-d H:i:s'),
            'LastPublishDate' => date('c', $feedLastPublishTime)
        ]);
    }

    public function replaceBadURLs($matches) {
        $matchedURL = $matches[0];
        $fixedURL = array_pop($trash = explode("/*", $matchedURL));
        return 'href="'.$fixedURL.'"';
    }

    protected function addFeed($feedURL, $feed) {
        $feedKey = self::encodeFeedKey($feedURL);

        $feed['URL'] = $feedURL;
        $encodedFeed = json_encode($feed);
        $this->setUserMeta(0, "Feed.{$feedKey}", $encodedFeed);

        // regenerate the internal feed list
        $this->getFeeds(true, true);
    }

    protected function updateFeed($feedKey, $feedOptionKey, $feedOptionValue = null) {
        $feed = $this->getFeed($feedKey);

        if (!is_array($feedOptionKey)) {
            $feedOptionKey = [$feedOptionKey => $feedOptionValue];
        }

        $feed = array_merge($feed, $feedOptionKey);

        $encodedFeed = json_encode($feed);
        $this->setUserMeta(0, "Feed.{$feedKey}", $encodedFeed);

        // regenerate the internal feed list
        $this->getFeeds(true, true);
    }

    protected function removeFeed($feedKey, $preEncoded = true) {
        $feedKey = (!$preEncoded) ? self::encodeFeedKey($feedKey) : $feedKey;
        $this->setUserMeta(0, "Feed.{$feedKey}", null);

        // regenerate the internal feed list
        $this->getFeeds(true, true);
    }

    protected function getFeed($feedKey, $preEncoded = true) {
        $feedKey = (!$preEncoded) ? self::encodeFeedKey($feedKey) : $feedKey;
        $feeds = $this->getFeeds(true);

        if (array_key_exists($feedKey, $feeds)) {
            return $feeds[$feedKey];
        }

        return null;
    }

    protected function haveFeed($feedKey, $preEncoded = true) {
        $feedKey = (!$preEncoded) ? self::encodeFeedKey($feedKey) : $feedKey;
        $feed = $this->getFeed($feedKey);
        if (!empty($feed)) {
            return true;
        }
        return false;
    }

    public static function encodeFeedKey($key) {
        return md5($key);
    }

    public function setup() {
        // Nothing to do here!
    }
}
