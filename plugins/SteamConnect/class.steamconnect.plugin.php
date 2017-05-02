<?php if (!defined('APPLICATION')) exit;

$PluginInfo['SteamConnect'] = array(
    'Name'        => "Steam Connect",
    'Description' => "Allow users to sign in with their Steam Account. Requires &lsquo;OpenID&rsquo; plugin to be enabled first.",
    'Version'     => '1.0.0',
    'RequiredPlugins' => array('OpenID' => '0.1a'),
    'MobileFriendly' => TRUE,
    'Author'      => "Becky Van Bussel",
    'AuthorEmail' => 'becky@vanillaforums.com',
    'AuthorUrl'   => 'http://vanillaforums.com',
    'SettingsUrl' => '/settings/steamconnect',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'License' => 'GNU GPLv2'
);

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

    protected function _AuthorizeHref($Popup = FALSE) {
        $Url = Url('/entry/openid', TRUE);
        $UrlParts = explode('?', $Url);
        parse_str(GetValue(1, $UrlParts, ''), $Query);
        $Query['url'] = 'http://steamcommunity.com/openid';
        $Path = '/'.Gdn::Request()->Path();
        $Query['Target'] = GetValue('Target', $_GET, $Path ? $Path : '/');
        if ($Popup)
            $Query['display'] = 'popup';

        $Result = $UrlParts[0].'?'.http_build_query($Query);
        return $Result;
    }


    /// Plugin Event Handlers ///

    public function EntryController_SignIn_Handler($Sender, $Args) {

        if (isset($Sender->Data['Methods']) && $this->isConfig()) {
            $Url = $this->_AuthorizeHref();

            // Add the steam method to the controller.
            $Method = array(
                'Name' => 'Steam',
                'SignInHtml' => SocialSigninButton('Steam', $Url, 'button', array('class' => 'js-extern'))
            );

            $Sender->Data['Methods'][] = $Method;
        }
    }

    public function Base_SignInIcons_Handler($Sender, $Args) {
        if ($this->isConfig()) {
            echo "\n".$this->_GetButton();
        }
    }

    public function Base_BeforeSignInButton_Handler($Sender, $Args) {
        if ($this->isConfig()) {
            echo "\n".$this->_GetButton();
        }
    }

    public function AssetModel_StyleCss_Handler($Sender) {
        $Sender->AddCssFile('steam-connect.css', 'plugins/SteamConnect');
    }

    private function _GetButton() {
        if ($this->isConfig()) {
            $Url = $this->_AuthorizeHref();
            return SocialSigninButton('Steam', $Url, 'icon', array('class' => 'js-extern'));
        }
    }

    public function Base_BeforeSignInLink_Handler($Sender) {
        if (!Gdn::Session()->IsValid() && $this->isConfig()) {
            echo "\n".Wrap($this->_GetButton(), 'li', array('class' => 'Connect SteamConnect'));
        }
    }

    public function OpenIDPlugin_AfterConnectData_Handler($Sender, $Args) {

        $Form = $Args['Form'];
        $OpenID = $Args['OpenID'];
        $SteamID = $this->getSteamID($OpenID);

        // Make a call to steam.
        $qs = array(
            'key' => C('Plugins.SteamConnect.APIKey'),
            'steamids' => $SteamID
        );

        $url = 'http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?'.http_build_query($qs);

        $json_object= file_get_contents($url);
        $json_decoded = json_decode($json_object);

        $player = $json_decoded->response->players[0];

        $Form->SetFormValue('Provider', 'Steam');
        $Form->SetFormValue('ProviderName', 'Steam');
        $Form->SetFormValue('UniqueID', $SteamID);
        $Form->SetFormValue('Photo', $player->avatarfull);

        /**
         * Check to see if we already have an authentication record for this user.  If we don't, setup their username.
         */
        if (!Gdn::UserModel()->GetAuthentication($SteamID, 'Steam')) {
            $Form->SetFormValue('Name', $player->personaname);
        }

        if (isset($player->realname)) {
            $Form->SetFormValue('FullName', $player->realname);
        }
    }

    public function getSteamID($OpenID) {
        $ptn = "/^http:\/\/steamcommunity\.com\/openid\/id\/(7[0-9]{15,25}+)$/";
        preg_match($ptn, $OpenID->identity, $matches);
        return $matches[1];
    }

    public function SettingsController_SteamConnect_Create($Sender) {
        $Sender->Permission('Garden.Settings.Manage');

        $APIKeyDescription =  '<div class="help">'.sprintf(T('A %s is necessary for this plugin to work.'), T('Steam Web API Key')).' '
            .sprintf(T('Don\'t have a %s?'), T('Steam Web API Key'))
            .' <a href="http://steamcommunity.com/dev/apikey">'.T('Get one here.').'</a>';

        $Conf = new ConfigurationModule($Sender);
        $Conf->Initialize(array(
            'Plugins.SteamConnect.APIKey' => array('Control' => 'TextBox', 'LabelCode' => 'Steam Web API Key', 'Options' => array('class' => 'InputBox BigInput'), 'Description' => $APIKeyDescription)
        ));

        $Sender->AddSideMenu();
        $Sender->SetData('Title', sprintf(T('%s Settings'), T('Steam Connect')));
        $Sender->ConfigurationModule = $Conf;
        $Conf->RenderAll();
    }
}
