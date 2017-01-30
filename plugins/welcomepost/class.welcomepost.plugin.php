<?php
/**
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU GPL v2
 */

$PluginInfo['welcomepost'] = [
    'Name' => 'Welcome Post',
    'Description' => 'Redirect users, after registration, to an "introduce yourself" page that posts in a "welcome" category.'
            .' This plugin does not work with the "approval" registration mode.',
    'Version' => '1.1',
    'RequiredApplications' => ['Vanilla' => '2.2'],
    'License' => 'GNU GPL2',
    'Author' => 'Alexandre (DaazKu) Chouinard',
    'AuthorEmail' => 'alexandre.c@vanillaforums.com',
    'AuthorUrl' => 'https://github.com/DaazKu',
];

/**
 * Class WelcomePostPlugin
 */
class WelcomePostPlugin extends Gdn_Plugin {

    /**
     * Executed when the plugin is enabled.
     */
    public function setup() {
        $this->structure();
    }

    /**
     * Create the target category that is needed for this plugin to work.
     */
    public function structure() {
        $category = (array)CategoryModel::instance()->getByCode('welcome');
        $cachedCategoryID = val('CategoryID', $category, false);

        if (!$cachedCategoryID) {
            $categoryModel = CategoryModel::instance();
            $categoryModel->save([
                'ParentCategoryID' => -1,
                'Depth' => 1,
                'InsertUserID' => 1,
                'UpdateUserID' => 1,
                'DateInserted' => Gdn_Format::toDateTime(),
                'DateUpdated' => Gdn_Format::toDateTime(),
                'Name' => 'Welcome',
                'UrlCode' => 'welcome',
                'Description' => 'Introduce yourself to the community!',
                'PermissionCategoryID' => -1
            ]);
        }
    }

    /**
     * Check whether all condition are met for this plugin to do its magic.
     *
     * @param Gdn_Request $request
     * @param int|bool $categoryID Category ID of the welcome category if it exists and this function return true
     * @return bool
     */
    protected function isWelcomePostActive($request, &$categoryID) {
        static $cachedCategoryID = null;

        if ($cachedCategoryID === null) {
            $isWelcomePost = true;

            if ($request->get('welcomepost', false) !== "true") {
                $cachedCategoryID = false;
                return false;
            }

            if (!Gdn::session()->isValid()) {
                $cachedCategoryID = false;
                return false;
            }

            $category = (array)CategoryModel::instance()->getByCode('welcome');
            $cachedCategoryID = val('CategoryID', $category, false);
            if (!$cachedCategoryID) {
                return false;
            }

            $categoryID = $cachedCategoryID;
        } else {
            $isWelcomePost = (bool)$cachedCategoryID;
            $categoryID = $cachedCategoryID;
        }

        return $isWelcomePost;
    }

    /**
     * Add needed resources to the page.
     *
     * @param PostController $sender Sending controller instance.
     * @param array $args Event's arguments.
     */
    public function postController_render_before($sender, $args) {
        if (!$this->isWelcomePostActive($sender->Request, $categoryID)) {
            return;
        }
        $sender->addCssFile('welcomepost.css', 'plugins/welcomepost');
    }

    /**
     * Redirect users to the /post/discussion end point after registration.
     */
    public function entryController_registrationSuccessful_handler($sender, $args) {
        if (!c('Garden.Registration.ConfirmEmail')) {
            $target = (Gdn::request()->post('Target')) ? '&Target='.Gdn::request()->post('Target') : null;
            redirect('/post/discussion?welcomepost=true'.$target);
        }
    }

    /**
     * When connecting through SSO, redirect user to the Welcome Post page.
     *
     * @param UserModel $sender
     * @param UserModel $args
     */
    public function userModel_afterSignIn_handler($sender, $args) {
        $target = (Gdn::request()->post('Target')) ? '&Target='.Gdn::request()->post('Target') : null;
        // AfterSignIn event is fired in several places, the InsertUserID
        // argument is only passed in the connect script.
        if (val('InsertUserID', $args)) {
            redirect('/post/discussion?welcomepost=true'.$target);
        }
    }

    /**
     * Redirect users to the /post/discussion end point after email confirmation.
     *
     * @param EntryController $sender Sending controller instance.
     */
    public function entryController_render_after($sender) {
        if ($sender->data('EmailConfirmed')) {
            echo '<meta http-equiv="Refresh" content="1; url='.url('/post/discussion?welcomepost=true').'">';
        }
    }

    /**
     * Inject the appropriate Welcome Post instructions if this is a Welcome Post form.
     *
     * @param PostController $sender
     * @param PostController $args
     */
    public function postController_beforeFormInputs_handler($sender, $args) {
        if ($this->isWelcomePostActive($sender->Request, $categoryID)) {
            echo t('WelcomePostInstruction', 'Say \'Hello\' to the rest of the community. If you\'re feeling too shy, press \'Skip\'');
        }
    }

    /**
     * Tweak the discussion form.
     *
     * Post in the "welcome" category, prefill title...
     *
     * @param PostController $sender Sending controller instance.
     * @param array $args Event's arguments.
     */
    public function postController_beforeDiscussionRender_handler($sender, $args) {
        if (!$this->isWelcomePostActive($sender->Request, $categoryID)) {
            return;
        }

        // Change the title of the Welcome Post
        $sender->title(t('Welcome to the Community'));

        // Change the text on the Cancel button
        Gdn::locale()->setTranslation('Cancel', t('Skip'));

        //Skipping will bring you to where you were going
        $cancelURL = (Gdn::request()->get('Target')) ? Gdn::request()->get('Target') : '/';
        $sender->setData('_CancelUrl', $cancelURL);

        $username = val('Name', Gdn::session()->User, 'Unknown');

        // Force post to be in the "Welcome" category
        $sender->ShowCategorySelector = false;
        $sender->Form->addHidden('CategoryID', $categoryID);

        $sender->Form->setValue('Name', sprintf(t('Welcome post discussion name', 'Hi, my name is %s!'), $username));
    }
}
