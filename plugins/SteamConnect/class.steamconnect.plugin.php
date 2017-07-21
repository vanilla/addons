<?php if (!defined('APPLICATION')) exit;

/**
 * Steam Connect Plugin
 *
 * @author    Becky Van Bussel <becky@vanillaforums.com>
 * @copyright 2014 (c) Vanilla Forums Inc
 * @package   Steam Connect
 * @since     1.0.0
 */
class SteamConnectPlugin extends Gdn_Plugin {
    /**
     * This will run when you "Enable" the plugin
     *
     * @since  1.0.0
     * @access public
     * @return bool
     */
    public function setup() {

    }

    public function isConfig() {
        return C('Plugins.SteamConnect.APIKey', FALSE);
    }

    protected function _AuthorizeHref($popup = FALSE) {
        $url = Url('/entry/openid', TRUE);
        $urlParts = explode('?', $url);
        parse_str(GetValue(1, $urlParts, ''), $query);
        $query['url'] = 'http://steamcommunity.com/openid';
        $path = '/'.Gdn::Request()->Path();
        $query['Target'] = GetValue('Target', $_GET, $path ? $path : '/');
        if ($popup)
            $query['display'] = 'popup';

        $result = $urlParts[0].'?'.http_build_query($query);
        return $result;
    }


    /// Plugin Event Handlers ///

    public function EntryController_SignIn_Handler($sender, $args) {

        if (isset($sender->Data['Methods']) && $this->isConfig()) {
            $url = $this->_AuthorizeHref();

            // Add the steam method to the controller.
            $method = [
                'Name' => 'Steam',
                'SignInHtml' => SocialSigninButton('Steam', $url, 'button', ['class' => 'js-extern'])
            ];

            $sender->Data['Methods'][] = $method;
        }
    }

    public function Base_SignInIcons_Handler($sender, $args) {
        if ($this->isConfig()) {
            echo "\n".$this->_GetButton();
        }
    }

    public function Base_BeforeSignInButton_Handler($sender, $args) {
        if ($this->isConfig()) {
            echo "\n".$this->_GetButton();
        }
    }

    public function AssetModel_StyleCss_Handler($sender) {
        $sender->AddCssFile('steam-connect.css', 'plugins/SteamConnect');
    }

    private function _GetButton() {
        if ($this->isConfig()) {
            $url = $this->_AuthorizeHref();
            return SocialSigninButton('Steam', $url, 'icon', ['class' => 'js-extern']);
        }
    }

    public function Base_BeforeSignInLink_Handler($sender) {
        if (!Gdn::Session()->IsValid() && $this->isConfig()) {
            echo "\n".Wrap($this->_GetButton(), 'li', ['class' => 'Connect SteamConnect']);
        }
    }

    public function OpenIDPlugin_AfterConnectData_Handler($sender, $args) {

        $form = $args['Form'];
        $openID = $args['OpenID'];
        $steamID = $this->getSteamID($openID);

        // Make a call to steam.
        $qs = [
            'key' => C('Plugins.SteamConnect.APIKey'),
            'steamids' => $steamID
        ];

        $url = 'http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?'.http_build_query($qs);

        $json_object= file_get_contents($url);
        $json_decoded = json_decode($json_object);

        $player = $json_decoded->response->players[0];

        $form->SetFormValue('Provider', 'Steam');
        $form->SetFormValue('ProviderName', 'Steam');
        $form->SetFormValue('UniqueID', $steamID);
        $form->SetFormValue('Photo', $player->avatarfull);

        /**
         * Check to see if we already have an authentication record for this user.  If we don't, setup their username.
         */
        if (!Gdn::UserModel()->GetAuthentication($steamID, 'Steam')) {
            $form->SetFormValue('Name', $player->personaname);
        }

        if (isset($player->realname)) {
            $form->SetFormValue('FullName', $player->realname);
        }
    }

    public function getSteamID($openID) {
        $ptn = "/^http:\/\/steamcommunity\.com\/openid\/id\/(7[0-9]{15,25}+)$/";
        preg_match($ptn, $openID->identity, $matches);
        return $matches[1];
    }

    public function SettingsController_SteamConnect_Create($sender) {
        $sender->Permission('Garden.Settings.Manage');

        $aPIKeyDescription =  '<div class="info">'.sprintf(T('A %s is necessary for this plugin to work.'), T('Steam Web API Key')).' '
            .sprintf(T('Don\'t have a %s?'), T('Steam Web API Key'))
            .' <a href="http://steamcommunity.com/dev/apikey">'.T('Get one here.').'</a></div>';

        $conf = new ConfigurationModule($sender);
        $conf->Initialize([
            'Plugins.SteamConnect.APIKey' => ['Control' => 'TextBox', 'LabelCode' => 'Steam Web API Key', 'Description' => $aPIKeyDescription]
        ]);

        $sender->AddSideMenu();
        $sender->SetData('Title', sprintf(T('%s Settings'), T('Steam Connect')));
        $sender->ConfigurationModule = $conf;
        $conf->RenderAll();
    }
}
