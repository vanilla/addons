<?php
/**
 * @copyright 2014-2016 Vanilla Forums, Inc.
 */

/**
 * Class InvisibilityCloakPlugin
 */
class InvisibilityCloakPlugin extends Gdn_Plugin {

    /** @var Gdn_Configuration */
    private $configuration;

    /**
     * Addon constructor.
     *
     * @param Gdn_Configuration $configuration
     */
    public function __construct(Gdn_Configuration $configuration) {
        $this->configuration = $configuration;
    }

    /**
     * Hook into the startup event.
     */
    public function gdn_dispatcher_appStartup_handler() {
        $this->configuration->set("Robots.Invisible", true, true, false);

        if (!Gdn::session()->checkPermission('Garden.Settings.Manage')) {
            $this->checkAuthentication();
        }
    }

    /**
     * No bots meta tag.
     *
     * @param object $sender
     */
    public function base_render_before($sender) {
        if ($sender->Head) {
            $sender->Head->addTag('meta', ['name' => 'robots', 'content' => 'noindex,noarchive']);
        }
    }

    /**
     * Settings for the addon.
     *
     * @param SettingsController $sender
     */
    public function settingsController_invisibilityCloak_create(SettingsController $sender) {
        $sender->permission('Garden.Settings.Manage');

        $cf = new ConfigurationModule($sender);
        $cf->initialize([
            'Username' => [
                'Config' => 'Plugins.InvisibilityCloak.Username',
            ],
            'Password' => [
                'Config' => 'Plugins.InvisibilityCloak.Password',
                'Options' => ['type' => 'password'],
            ],
        ]);

        $sender->title('Title', t('Invisibility Cloak Settings'));
        $sender->setData('Description', t('You can protect your site with an HTTP username/password during development.'));
        $cf->renderAll();
    }

    /**
     * Check basic HTTP authentication against configured values.
     */
    private function checkAuthentication(): void {
        $configUsername = Gdn::config('Plugins.InvisibilityCloak.Username', '');
        $configPassword = Gdn::config('Plugins.InvisibilityCloak.Password', '');
        if (!empty($configUsername) || !empty($configPassword)) {
            $username = Gdn::request()->getValueFrom(Gdn_Request::INPUT_SERVER, 'PHP_AUTH_USER');
            $password = Gdn::request()->getValueFrom(Gdn_Request::INPUT_SERVER, 'PHP_AUTH_PW');

            if (!hash_equals($configUsername, $username) ||
                !hash_equals($configPassword, $password)
            ) {
                header('WWW-Authenticate: Basic realm="Community"');
                header('Unauthorized', true, 401);
                echo 'The site is currently username/password protected for development.';
                exit;
            }
        }
    }
}
