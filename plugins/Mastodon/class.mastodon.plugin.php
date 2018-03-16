<?php
/**
 * Mastodon Plugin.
 * @copyright wakin
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU GPL v2
 */

/**
 * Class MastodonPlugin
 */
class MastodonPlugin extends Gdn_Plugin {

    /** Authentication Provider key. */
    const PROVIDER_KEY = 'Mastodon';

    /** @var string */
    protected $_Domain = null;

    /** @var string */
    protected $_AccessToken = null;

    /** @var string */
    protected $_RedirectUri = null;

    /**
     * Get appInfo
     */
    public function appInfo() {
        $str_domain = $this->str_domain();
        if (!c('Plugins.Mastodon.AppInfo.'.$str_domain)) {
            $appInfo = $this->registerApplication();
            if ($appInfo) {
                $settings = [
                    'Plugins.Mastodon.AppInfo.'.$str_domain => $appInfo
                ];
                saveToConfig($settings);
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Register Application to Mastodon Instance.
     */
    public function registerApplication() {
        $redirect_uris = explode('?', url('', true));
        return $this->api('/apps', [
            'client_name' => c('Plugins.Mastodon.AppName'),
            'redirect_uris' => $redirect_uris[0],
            'scopes' => 'read write',
        ]);
    }

    /**
     *
     */
    public function authorize() {
        $_domain = $this->str_domain();
        if (!c('Plugins.Mastodon.AppInfo.'.$_domain.'.client_id') || !c('Plugins.Mastodon.AppInfo.'.$_domain.'.client_secret')) {
            return;
        }

        $url = $this->authorizeUri();

        redirectTo($url, 302, false);
    }

    /**
     * Get Mastodon profile.
     */
    public function getProfile() {
        $profile = $this->api('/accounts/verify_credentials');

        $profile['domain'] = $this->domain();
        $profile['full_acct'] = val('username', $profile).'@'.$this->domain();

        return $profile;
    }

    /**
     * Get current redirect_uri.
     */
    public function redirect_uri($path=null) {
        if ($path != null) {
            $this->_RedirectUri = url($path, true);
        } else if ($this->_RedirectUri == null) {
            $this->_RedirectUri = url('/entry/mastodon', true);
        }

        return $this->_RedirectUri;
    }

    /**
     * Get current domain.
     */
    public function domain($domain=null) {
        if ($domain != null) {
            Gdn::session()->setCookie('Domain', $domain, 60*5);
            $this->_Domain = $domain;
        } else if ($this->_Domain == null) {
            if (Gdn::session()->User) {
                $this->_Domain = valr(self::PROVIDER_KEY.'.Profile.domain', Gdn::session()->User->Attributes);
            }

            if ($this->_Domain == null) {
                $this->_Domain = Gdn::session()->getCookie('Domain', null);
            }
        }

        if ($this->_Domain == null) {
            throw new Gdn_UserException('session timeout(no mastodon domain).');
        }

        return $this->_Domain;
    }
    public function str_domain() {
        return str_replace('.', '_', $this->domain());
    }

    /**
     * Get current access token.
     *
     * @param bool $newValue
     * @return bool|mixed|null
     */
    public function accessToken($newValue = false) {
        if (!$this->isConfigured()) {
            return false;
        }

        if ($newValue !== false) {
            $this->_AccessToken = $newValue;
        }

        if ($this->_AccessToken === null && Gdn::session()->User) {
            $this->_AccessToken = valr(self::PROVIDER_KEY.'.AccessToken', Gdn::session()->User->Attributes);
        }

        return $this->_AccessToken;
    }

    /**
     * Send request to the Mastodon API.
     *
     * @param string $path
     * @param array $post
     *
     * @return mixed
     * @throws Gdn_UserException
     */
    public function api($path, $post = []) {
        $url = 'https://'.$this->domain().'/api/v1/'.ltrim($path, '/');
        $result = self::curl($url, empty($post) ? 'GET' : 'POST', $post, $this->accessToken());
        return $result;
    }

    /**
     * Retrieve where to send the user for authorization.
     *
     * @param array $state
     *
     * @return string
     */
    public function authorizeUri($state = []) {
        $url = 'https://'.$this->domain().'/oauth/authorize';
        $get = [
            'response_type' => 'code',
            'redirect_uri' => $this->redirect_uri(),
            'client_id' => c('Plugins.Mastodon.AppInfo.'.$this->str_domain().'.client_id'),
            'scope' => 'read write'
        ];

        if (is_array($state)) {
            $get['state'] = http_build_query($state);
        }

        return $url.'?'.http_build_query($get);
    }

    /**
     * Get an access token from Mastodon.
     *
     * @param $code
     *
     * @return mixed
     * @throws Gdn_UserException
     */
    public function getAccessToken($code) {
        $url = 'https://'.$this->domain().'/oauth/token';
        $str_domain = $this->str_domain();
        $post = [
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirect_uri(),
            'client_id' => c('Plugins.Mastodon.AppInfo.'.$str_domain.'.client_id'),
            'client_secret' => c('Plugins.Mastodon.AppInfo.'.$str_domain.'.client_secret'),
            'code' => $code
        ];

        $data = self::curl($url, 'POST', $post);

        $accessToken = $data['access_token'];
        return $accessToken;
    }

    /**
     * Whether this addon has enough configuration to work.
     *
     * @return bool
     */
    public function isConfigured() {
        $result = c('Plugins.Mastodon.AppName');
        return $result;
    }

    /**
     * Whether social sharing is enabled.
     *
     * @return bool
     */
    public function socialSharing() {
        return c('Plugins.Mastodon.SocialSharing', true);
    }

    /**
     * Whether social reactions are enabled.
     *
     * @return bool
     */
    public function socialReactions() {
        return c('Plugins.Mastodon.SocialReactions', true);
    }

    /**
     * Send a cURL request.
     *
     * @param $url
     * @param string $method
     * @param array $data
     * @return mixed
     * @throws Gdn_UserException
     */
    public static function curl($url, $method = 'GET', $data = [], $accessToken=false) {
        $ch = curl_init();
        if ($accessToken) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer '.$accessToken
            ));
        }
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, $url);

        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            trace("  POST $url");
        } else {
            trace("  GET  $url");
        }

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $result = @json_decode($response, true);
        if (!$result) {
            $result = $response;
        }

        if ($httpCode != 200) {
            $error = val('error', $result, $response);

            throw new Gdn_UserException("invalid domain.");
            #throw new Gdn_UserException($error, $httpCode);
        }

        return $result;
    }

    /**
     * mastodon.css
     */
    public function assetModel_styleCss_handler($sender) {
        $sender->addCssFile('mastodon.css', 'plugins/Mastodon');
    }

    /**
     * Run once on enable.
     */
    public function setup() {
        $this->structure();
    }

    /**
     * Gimme button!
     *
     * @param string $type
     * @return string
     */
    public function signInButton($type = 'button') {
        $target = Gdn::request()->post('Target', Gdn::request()->get('Target', url('', '/')));
        $url = '/entry/mastodon';

        if ($target) {
            $url .= '&'.http_build_query(['target' => $target, 'domain-entry' => '1']);
        }

        $result = socialSignInButton('Mastodon', $url, $type, ['rel' => 'nofollow']);
        return $result;
    }

    /**
     * Run on utility/update.
     */
    public function structure() {
        if (Gdn::sql()->getWhere('UserAuthenticationProvider', ['AuthenticationSchemeAlias' => 'Mastodon'])->firstRow()) {
            Gdn::sql()->put('UserAuthenticationProvider', ['AuthenticationSchemeAlias' => self::PROVIDER_KEY], ['AuthenticationSchemeAlias' => 'Mastodon']);
        }

        // Save the mastodon provider type.
        Gdn::sql()->replace(
            'UserAuthenticationProvider',
            ['AuthenticationSchemeAlias' => self::PROVIDER_KEY, 'URL' => '', 'AssociationSecret' => '', 'AssociationHashMethod' => '...'],
            ['AuthenticationKey' => self::PROVIDER_KEY],
            true
        );
    }

    /**
     * Calculate the final sign in and register urls for Mastodon.
     *
     * @param authenticationProviderModel $sender Not used.
     * @param array $args Contains the provider and data.
     */
    public function authenticationProviderModel_calculateMastodon_handler($sender, $args) {
        $provider =& $args['Provider'];
        $target = val('Target', null);

        if (!$target) {
            $target = Gdn::request()->post('Target', Gdn::request()->get('Target', url('', '/')));
        }

        $provider['SignInUrlFinal'] = $this->authorizeUri(['target' => $target]);
    }

    /**
     * Generic SSO hook into Vanilla for authorizing via Mastodon and pass user info.
     *
     * @param EntryController $sender
     * @param array $args
     */
    public function base_connectData_handler($sender, $args) {
        if (val(0, $args) != 'mastodon') {
            return;
        }

        // Grab the mastodon profile from the session staff.
        $mastodon = Gdn::session()->stash(self::PROVIDER_KEY, '', false);
        $accessToken = val('AccessToken', $mastodon);
        $profile = val('Profile', $mastodon);

        // This isn't a trusted connection. Don't allow it to automatically connect a user account.
        saveToConfig('Garden.Registration.AutoConnect', true, false);

        $form = $sender->Form;
        $form->setFormValue('UniqueID', val('full_acct', $profile));
        $form->setFormValue('Provider', self::PROVIDER_KEY);
        $form->setFormValue('ProviderName', 'Mastodon');
        $form->setFormValue('FullName', val('full_acct', $profile));
        if (c('Plugins.Mastodon.UseAvatars', true)) {
            $form->setFormValue('Photo', val('avatar', $profile));
        }

        #$form->setFormValue('Name', val('username', $profile));

        // Save some original data in the attributes of the connection for later API calls.
        $attributes = [];
        $attributes[self::PROVIDER_KEY] = [
            'AccessToken' => $accessToken,
            'Profile' => $profile
        ];
        $form->setFormValue('Attributes', $attributes);
        $sender->setData('Verified', true);

        $this->EventArguments['Form'] = $form;
        $this->fireEvent('AfterConnectData');
    }

    /**
     * Add Mastodon option to MeModule.
     *
     * @param Gdn_Controller $sender
     * @param array $args
     */
    public function base_signInIcons_handler($sender, $args) {
        if (!$this->isConfigured()) {
            return;
        }
        echo 'test '.$this->signInButton('icon').' ';
    }

    /**
     * Add Mastodon option to GuestModule.
     *
     * @param Gdn_Controller $sender
     * @param array $args
     */
    public function base_beforeSignInButton_handler($sender, $args) {
        if (!$this->isConfigured()) {
            return;
        }
        echo ' '.$this->signInButton('icon').' ';
    }

    /**
     * Add Mastodon to the list of available providers.
     *
     * @param Gdn_Controller $sender
     * @param array $args
     */
    public function base_getConnections_handler($sender, $args) {
        $gPlus = valr('User.Attributes.'.self::PROVIDER_KEY, $args);
        $profile = valr('User.Attributes.'.self::PROVIDER_KEY.'.Profile', $args);

        $sender->Data['Connections'][self::PROVIDER_KEY] = [
            'Icon' => $this->getWebResource('icon.png'),
            'Name' => 'Mastodon',
            'ProviderKey' => self::PROVIDER_KEY,
            'ConnectUrl' => '/entry/mastodon?target=profile&domain-entry=1',
            'Profile' => [
                'Name' => val('full_acct', $profile),
                'Photo' => val('avatar', $profile)
            ]
        ];

        trace(val('AccessToken', $gPlus), 'mastodon access token');
    }

    /**
     * Endpoint for authenticating with Mastodon.
     *
     * @param EntryController $sender
     * @param string|bool $code
     * @param string|bool $state
     *
     * @throws Gdn_UserException
     */
    public function entryController_mastodon_create($sender, $code = false, $state = false) {
        if ($error = $sender->Request->get('error')) {
            throw new Gdn_UserException($error);
        }

        if (val('target', $_GET) == 'discussions' || val('target', $_GET) == '/discussions') {
            $this->redirect_uri('/entry/mastodon');
        } else if (val('target', $_GET) == 'profile' || val('target', $_GET) == '/profile') {
            $this->redirect_uri(userUrl(Gdn::session()->User, false, 'mastodonconnect'));
        }

        if (val('domain-entry', $_GET) == 1) {
            $sender->setData('Title', t('Sign In with Mastodon'));
            $sender->render('Domain', '', 'plugins/Mastodon');

            exit();
        }

        if (val('domain', $_GET)) {
            $this->domain( urlencode(val('domain', $_GET)) );
            if($this->appInfo()) {
                $this->authorize();
            } else {
                $sender->setData('Error', t('Invalid Domain'));
            }
            $sender->setData('Title', t('Sign In with Mastodon'));
            $sender->render('Domain', '', 'plugins/Mastodon');

            exit();
        }

        // Get an access token.
        Gdn::session()->stash(self::PROVIDER_KEY); // remove any old mastodon.
        $accessToken = $this->getAccessToken($code);
        $this->accessToken($accessToken);

        // Get the user's information.
        $profile = $this->getProfile();

        // This is an sso request, we need to redispatch to /entry/connect/mastodon
        Gdn::session()->stash(self::PROVIDER_KEY, ['AccessToken' => $accessToken, 'Profile' => $profile]);
        $url = '/entry/connect/mastodon';

        if ($target = val('target', $state)) {
            $url .= '?Target='.urlencode($target);
        }
        redirectTo($url);
    }

    /**
     * Add Mastodon as option to the normal signin page.
     *
     * @param Gdn_Controller $sender
     * @param array $args
     */
    public function entryController_signIn_handler($sender, $args) {
        if (!$this->isConfigured()) {
            return;
        }

        if (isset($sender->Data['Methods'])) {
            $url = '/entry/mastodon';

            // Add the Mastodon method to the controller.
            $method = [
                'Name' => 'Mastodon',
                'SignInHtml' => $this->signInButton()
            ];

            $sender->Data['Methods'][] = $method;
        }
    }

    /**
     * Endpoint to connect to Mastodon via user profile.
     *
     * @param ProfileController $sender
     * @param mixed $userReference
     * @param string $username
     * @param string $oauth_token
     * @param string $oauth_verifier
     */
    public function profileController_mastodonConnect_create($sender, $userReference = '', $username = '', $oauth_token = '', $oauth_verifier = '') {

        $sender->permission('Garden.SignIn.Allow');

        $sender->getUserInfo($userReference, $username, '', true);

        $sender->_setBreadcrumbs(t('Connections'), '/profile/connections');

        $code = val('code', $_GET);

        $accessToken = $sender->Form->getFormValue('AccessToken');

        if ($code && !$accessToken) {
            $this->redirect_uri(userUrl(Gdn::session()->User, false, 'mastodonconnect'));
            $accessToken = $this->getAccessToken($code);
            $this->accessToken($accessToken);
        }

        if ($accessToken) {
            $profile = $this->getProfile();
        } else {
            redirectTo($this->authorizeUri(), 302, false);
        }

        // Save the authentication.
        Gdn::userModel()->saveAuthentication([
            'UserID' => $sender->User->UserID,
            'Provider' => self::PROVIDER_KEY,
            'UniqueID' => val('username', $profile).'@'.val('domain', $profile)]);

        // Save the information as attributes.
        $attributes = [
            'AccessToken' => $accessToken,
            'Profile' => $profile
        ];
        Gdn::userModel()->saveAttribute($sender->User->UserID, self::PROVIDER_KEY, $attributes);

        $this->EventArguments['Provider'] = self::PROVIDER_KEY;
        $this->EventArguments['User'] = $sender->User;
        $this->fireEvent('AfterConnection');

        redirectTo(userUrl($sender->User, '', 'connections'));
    }

    /**
     * Endpoint to share to Mastodon.
     *
     * I'm sure someone out there does this. Somewhere. Probably alone.
     *
     * @param PostController $sender
     * @param type $recordType
     * @param type $iD
     * @throws type
     */
    public function postController_mastodon_create($sender, $recordType, $iD) {
        $row = getRecord($recordType, $iD);
        if ($row) {
            $url = 'https://'.$this->domain().'/share?'.http_build_query(['text' => $row['Name'].' '.$row['ShareUrl']]);

            redirectTo($url, 302, false);
        }

        $sender->render('Blank', 'Utility', 'Dashboard');
    }

    /**
     * Endpoint to comnfigure this addon.
     *
     * @param $sender
     * @param $args
     */
    public function socialController_mastodon_create($sender, $args) {
        $sender->permission('Garden.Settings.Manage');

        $conf = new ConfigurationModule($sender);
        $conf->initialize([
            'Plugins.Mastodon.AppName' => ['LabelCode' => 'App Name', 'Default' => 'vanilla_forums_sso'],
            'Plugins.Mastodon.UseAvatars' => ['Control' => 'checkbox', 'Default' => true],
        ]);

        if (Gdn::request()->isAuthenticatedPostBack()) {
            $model = new Gdn_AuthenticationProviderModel();
            $model->save(['AuthenticationKey' => self::PROVIDER_KEY, 'IsDefault' => c('Plugins.Mastodon.Default')]);
        }

        $sender->setHighlightRoute('dashboard/social');
        $sender->setData('Title', sprintf(t('%s Settings'), 'Mastodon'));
        $sender->ConfigurationModule = $conf;
        $sender->render('Settings', '', 'plugins/Mastodon');
    }
}
