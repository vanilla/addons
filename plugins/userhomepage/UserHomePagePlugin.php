<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace Vanilla\Addons\UserHomePage;

/**
 * Plugin to allow users to select their own homepage preferences.
 */
class UserHomePagePlugin extends \Gdn_Plugin {

    const PREF_KEY = "DefaultController";

    /** @var \Gdn_Router */
    private $router;

    /** @var \UserMetaModel */
    private $userMetaModel;

    /** @var \Gdn_Session */
    private $session;

    /**
     * Dependency Injection.
     *
     * @param \Gdn_Router $router
     * @param \UserMetaModel $userMetaModel
     * @param \Gdn_Session $session
     */
    public function __construct(\Gdn_Router $router, \UserMetaModel $userMetaModel, \Gdn_Session $session) {
        parent::__construct();
        $this->router = $router;
        $this->userMetaModel = $userMetaModel;
        $this->session = $session;
    }

    /**
     * Dynamically set the homepage route if we have a user preference saved.
     */
    public function gdn_dispatcher_appStartup_handler() {
        $preference = $this->getUserHomepagePreference();
        if ($preference !== null) {
            $this->router->setRoute('DefaultController', $preference, 'Internal', false);
        }
    }

    /**
     * Get the user's homepage preference from the user meta table if possible.
     *
     * @return string|null
     */
    private function getUserHomepagePreference(): ?string {
        if (!$this->session->isValid()) {
            return null;
        }

        $currentUserID = $this->session->UserID;
        $result = $this->userMetaModel->getUserMeta($currentUserID, self::PREF_KEY);
        return $result['DefaultController'];
    }

    /**
     * Get the default configuration value for the homepage.
     *
     * @return string
     */
    private function getDefaultHomepage(): string {
        return '/' . $this->router->getRoute(self::PREF_KEY)['FinalDestination'];
    }

    /**
     * Create the /profile/homepage settings page.
     *
     * @param \ProfileController $sender
     * @throws \Gdn_UserException If permissions don't match.
     */
    public function profileController_homepage_create(\ProfileController $sender) {
        if (!$this->canEditUsersPreference($sender)) {
            permissionException('Garden.Users.Edit');
        }

        // Yuck we need to fudge the arguments when creating a handler here.
        $args = $sender->RequestArgs;
        if (sizeof($args) < 2) {
            $args = array_merge($args, [0, 0]);
        } elseif (sizeof($args) > 2) {
            $args = array_slice($args, 0, 2);
        }

        list($userReference, $username) = $args;

        $sender->getUserInfo($userReference, $username);

        $sender->editMode(true);
        $sender->title(t('Home Page Settings'));
        // Form initialization
        $homeUrl =  url('/', true);
        $formTemplate = [
            'homepage' => [
                'LabelCode' => 'Home Page',
                "Description" => sprintf(
                    t("Choose the page you would like to see when you visit visit: %s."),
                    "<strong><a href='$homeUrl'>$homeUrl</a></strong>"
                ),
                'Control' => 'RadioList',
                'Items' => [
                    '/categories' => '/categories',
                    '/discussions' => '/discussions',
                ],
                'Options' => ['Default' => $this->getDefaultHomepage()],

            ],
        ];

        $userPref = $this->getUserHomepagePreference() ?: $this->getDefaultHomepage();
        if ($userPref !== null) {
            $sender->Form->setData(['homepage' => $userPref]);
        }


        // Handle the form post back.
        if ($sender->Form->authenticatedPostBack()) {
            $values = $sender->Form->formValues();
            $newHomepagePref = $values['homepage'] ?? null;
            if ($newHomepagePref !== null) {
                $this->userMetaModel->setUserMeta($this->session->UserID, self::PREF_KEY, $newHomepagePref);
            }
        }
        $sender->setData('formTemplate', $formTemplate);
        $sender->setData('form', $sender->Form);
        $sender->render('homepage', '', 'plugins/userhomepage');
    }

    /**
     * Add "Signature Settings" to profile edit mode side menu.
     *
     * @param \ProfileController $sender
     */
    public function profileController_afterAddSideMenu_handler(\ProfileController $sender) {
        if (!$this->canEditUsersPreference($sender)) {
            return;
        }

        /** @var \MenuModule $sideMenu */
        $sideMenu = $sender->EventArguments['SideMenu'];
        $sideMenu->addLink(
            'Options',
            t('Home Page Settings'),
            userUrl($sender->User, '', 'homepage')
        );
    }

    /**
     * Add "Home Page Settings" to Profile Edit button group for user's that can't get navigate to edit profile.
     *
     * @param \ProfileController $sender
     * @param array $args
     */
    public function profileController_beforeProfileOptions_handler(\ProfileController $sender, array $args) {
        $canEditProfiles = checkPermission('Garden.Users.Edit') || checkPermission('Moderation.Profiles.Edit');
        if ($this->canEditUsersPreference($sender) && !$canEditProfiles) {
            $args['ProfileOptions'][] = [
                'Text' => t('Home Page Settings'),
                'Url' => userUrl($sender->User, '', 'homepage')
            ];
        }
    }

    /**
     * Determine if the sessioned user can edit the signature of a particular user.
     *
     * @param \ProfileController $profileController
     * @return bool
     */
    private function canEditUsersPreference(\ProfileController $profileController): bool {
        $userID = $profileController->User->UserID;
        if (!isset($userID)) {
            // We are viewing our own page.
            return true;
        }

        return
            $userID === $this->session->UserID
            || $this->session->checkPermission('Garden.Users.Edit');
    }
}
