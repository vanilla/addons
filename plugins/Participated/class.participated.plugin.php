<?php if (!defined('APPLICATION')) exit();
/**
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license GPLv2
 */

// Define the plugin:
$PluginInfo['Participated'] = [
    'Name' => 'Participated Discussions',
    'Description' => 'Users may view a list of all discussions they have commented on. This is a more user-friendly version of an auto-subscribe option.',
    'Version' => '1.1.1',
    'MobileFriendly' => true,
    'RequiredApplications' => false,
    'RequiredTheme' => false,
    'RequiredPlugins' => false,
    'HasLocale' => true,
    'RegisterPermissions' => false,
    'Author' => 'Tim Gunter',
    'AuthorEmail' => 'tim@vanillaforums.com',
    'AuthorUrl' => 'http://www.vanillaforums.com',
    'Icon' => 'participated-discussions.png'
];

class ParticipatedPlugin extends Gdn_Plugin {

    /** @var int|null  */
    protected $countParticipated = null;

    /**
     * Get the current user's total participated discussions.
     *
     * @return int|bool|null
     */
    protected function getCountParticipated() {
        if ($this->countParticipated === null) {
            $discussionModel = new DiscussionModel();
            try {
                $this->countParticipated = $discussionModel->getCountParticipated(null);
            } catch (Exception $e) {
                $this->countParticipated = false;
            }
        }
        return $this->countParticipated;
    }

    /**
     * Gets list of discussions user has commented on.
     *
     * @param DiscussionModel $sender
     * @throws Exception if user is not logged in.
     * @return Gdn_DataSet
     */
    public function discussionModel_getParticipated_create($sender) {
        $userID = val(0, $sender->EventArguments);
        $offset = val(1, $sender->EventArguments);
        $limit = val(2, $sender->EventArguments);

        if ($userID === null) {
            if (!Gdn::session()->isValid()) {
                throw new Exception(t('Could not get participated discussions for non logged-in user.'));
            }
            $userID = Gdn::session()->UserID;
        }

        $sender->SQL->reset();
        $sender->SQL->select('d.*')
            ->from('UserDiscussion ud')
            ->join('Discussion d', 'ud.DiscussionID = d.DiscussionID')
            ->join('Comment c', 'ud.DiscussionID = c.DiscussionID and c.InsertUserID = ud.UserID')
            ->where('ud.UserID', $userID)
            ->where('ud.Participated', 1)
            ->groupBy('d.DiscussionID')
            ->orderBy('d.DateLastComment', 'desc')
            ->limit($limit, $offset);

        $permissions = DiscussionModel::categoryPermissions();
        if ($permissions !== true) {
            $sender->SQL->where('d.CategoryID', $permissions);
        }

        $data = $sender->SQL->get();
        $sender->addDiscussionColumns($data);
        Gdn::userModel()->joinUsers($data, ['FirstUserID', 'LastUserID']);
        CategoryModel::joinCategories($data);

        return $data;
    }

    /**
     * Gets number of discussions user has commented on.
     *
     * @param DiscussionModel $sender
     * @throws Exception if user is not logged in.
     * @return int|false
     */
    public function discussionModel_getCountParticipated_create($sender) {
        $userID = val(0, $sender->EventArguments);

        if ($userID === null) {
            if (!Gdn::session()->isValid()) {
                throw new Exception(t('Could not get participated discussions for non logged-in user.'));
            }
            $userID = Gdn::session()->UserID;
        }

        $count = Gdn::sql()->select('c.DiscussionID','distinct','NumDiscussions')
            ->from('Comment c')
            ->where('c.InsertUserID', $userID)
            ->groupBy('c.DiscussionID')
            ->get();

        $result = ($count instanceof Gdn_Dataset) ? $count->numRows() : false;
        return $result;
    }

    /**
     * Add navigation tab.
     *
     * @deprecated
     * @param mixed $sender
     */
    public function addParticipatedTab($sender) {
        $myParticipated = t('Participated Discussions');
        $attributes = [];

        if ($sender->RequestMethod == 'participated') {
            $attributes['class'] = 'Active';
        }

        $result = wrap(
            anchor($myParticipated, '/discussions/participated', 'MyParticipated TabLink'),
            'li',
            $attributes
        );
        echo $result;
    }

    /**
     * Handle the AfterDiscussionTabs event on /discussions pages.
     *
     * @param DiscussionsController $sender
     */
    public function discussionsController_afterDiscussionTabs_handler($sender) {
        $this->addParticipatedTab($sender);
    }

    /**
     * Handle the AfterDiscussionTabs event on /categories pages.
     *
     * @param CategoriesController $sender
     */
    public function categoriesController_afterDiscussionTabs_handler($sender) {
        $this->addParticipatedTab($sender);
    }

    /**
     * Handle the AfterDiscussionTabs event on /drafts pages.
     *
     * @param DraftsController $sender
     */
    public function draftsController_afterDiscussionTabs_handler($sender) {
        $this->addParticipatedTab($sender);
    }

    /**
     * New navigation menu item.
     *
     * @since 2.1
     * @param mixed $sender
     */
    public function base_afterDiscussionFilters_handler($sender) {
        if (!Gdn::session()->checkPermission('Garden.SignIn.Allow')) {
            return;
        }

        // Participated
        $cssClass = 'Participated';
        $controller = strtolower(Gdn::controller()->ControllerName);
        $method = strtolower(Gdn::controller()->RequestMethod);
        if ($controller == 'discussionscontroller' && $method == 'participated') {
            $cssClass .= ' Active';
        }

        $result = wrap(
        anchor(sprite('SpParticipated').t('Participated'), '/discussions/participated'),
            'li', ['class' => $cssClass]
        );
        echo $result;
    }

    /**
     * Create paginated list of discussions user has participated in.
     *
     * @param DiscussionsController $sender
     * @param array $args
     */
    public function discussionsController_participated_create($sender, $args) {
        $sender->permission('Garden.SignIn.Allow');
        Gdn_Theme::section('DiscussionList');

        $page = val(0, $args);

        // Set criteria & get discussions data
        list($offset, $limit) = offsetLimit($page, c('Vanilla.Discussions.PerPage', 30));
        $discussionModel = new DiscussionModel();

        $sender->DiscussionData = $discussionModel->getParticipated(Gdn::session()->UserID, $offset, $limit);
        $sender->setData('Discussions', $sender->DiscussionData);

        //Set view
        $sender->View = 'index';
        if (c('Vanilla.Discussions.Layout') === 'table') {
            $sender->View = 'table';
        }

        // Build a pager
        $pagerFactory = new Gdn_PagerFactory();
        $sender->EventArguments['PagerType'] = 'Pager';
        $sender->fireEvent('BeforeBuildParticipatedPager');
        $sender->Pager = $pagerFactory->getPager($sender->EventArguments['PagerType'], $sender);
        $sender->Pager->ClientID = 'Pager';
        $sender->Pager->configure(
            $offset,
            $limit,
            false,
            'discussions/participated/{Page}'
        );
        $sender->setData('CountDiscussions', false); // force prev/next pager
        $sender->fireEvent('AfterBuildParticipatedPager');

        // Deliver JSON data if necessary
        if ($sender->deliveryType() != DELIVERY_TYPE_ALL) {
            $sender->setJson('LessRow', $sender->Pager->toString('less'));
            $sender->setJson('MoreRow', $sender->Pager->toString('more'));
            $sender->View = 'discussions';
        }

        $sender->setData('_PagerUrl', 'discussions/participated/{Page}');
        $sender->setData('_Page', $page);
        $sender->setData('_Limit', $limit);

        // Add modules
        $sender->addModule('NewDiscussionModule');
        $sender->addModule('DiscussionFilterModule');
        $sender->addModule('CategoriesModule');
        $sender->addModule('BookmarkedModule');

        $sender->title(t('Participated Discussions'));
        $sender->setData(
            'Breadcrumbs',
            [['Name' => t('Participated Discussions'), 'Url' => '/discussions/participated']]
        );
        $sender->render();
    }
}

/**
 * This function returns the link to the "participated" page
 *
 * @param array $params The parameters passed into the function.
 * @param Smarty $smarty The smarty object rendering the template.
 * @return string
 */
function smarty_function_participated_link($params, &$smarty) {
    $wrap = val('wrap', $params, 'li');
    $result = Gdn_Theme::link(
        '/discussions/participated',
        val('text', $params, t('Participated')),
        val('format', $params, wrap('<a href="%url" class="%class">%text</a>', $wrap))
    );
    return $result;
}
