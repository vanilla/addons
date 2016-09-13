<?php
/**
 * @copyright 2008-2016 Vanilla Forums, Inc.
 * @license GNU GPLv2 http://www.opensource.org/licenses/gpl-2.0.php
 * @since 1.1.2b Fixed ConnectUrl to examine given url for existing querystring params and concatenate query params appropriately.
 */

$PluginInfo['jsconnect'] = [
    'Name' => 'Vanilla jsConnect',
    'Description' => 'Enables custom single sign-on solutions. They can be same-domain or cross-domain. See the <a href="http://vanillaforums.org/docs/jsconnect">documentation</a> for details.',
    'Version' => '1.5.4',
    'RequiredApplications' => ['Vanilla' => '2.1'],
    'MobileFriendly' => true,
    'Author' => 'Todd Burry',
    'AuthorEmail' => 'todd@vanillaforums.com',
    'AuthorUrl' => 'http://www.vanillaforums.org/profile/todd',
    'SettingsUrl' => '/settings/jsconnect',
    'UsePopupSettings' => false,
    'SettingsPermission' => 'Garden.Settings.Manage',
    'Icon' => 'jsconnect.png'
];

/**
 * Class JsConnectPlugin
 */
class JsConnectPlugin extends Gdn_Plugin {

    /**
     * Add an element to the controls collection. Used to render settings forms.
     *
     * @param string $key
     * @param array $item
     * @throws Exception
     */
    public function addControl($key, $item) {
        // Make sure this isn't called before it's ready.
        if (!isset(Gdn::controller()->Data['_Controls'])) {
            throw new Exception("You can't add a control before the controls collection has been initialized.", 500);
        }

        Gdn::controller()->Data['_Controls'][$key] = $item;
    }

    /**
     *
     *
     * @param array $Options
     * @return string
     */
    public static function allConnectButtons($Options = []) {
        $Result = '';

        $Providers = self::getAllProviders();
        foreach ($Providers as $Provider) {
            $Result .= self::connectButton($Provider, $Options);
        }
        return $Result;
    }

    /**
     *
     *
     * @param $Provider
     * @param array $Options
     * @return string
     */
    public static function connectButton($Provider, $Options = []) {
        if (!is_array($Provider)) {
            $Provider = self::getProvider($Provider);
        }

        $Url = htmlspecialchars(self::connectUrl($Provider));
        $Data = $Provider;

        $Target = Gdn::request()->get('Target');
        if (!$Target) {
            $Target = '/'.ltrim(Gdn::request()->path());
        }

        if (stringBeginsWith($Target, '/entry/signin')) {
            $Target = '/';
        }

        $ConnectQuery = ['client_id' => $Provider['AuthenticationKey'], 'Target' => $Target];
        $Data['Target'] = urlencode(url('entry/jsconnect', true).'?'.http_build_query($ConnectQuery));
        $Data['Redirect'] = $Data['target'] = $Data['redirect'] = $Data['Target'];

        $SignInUrl = formatString(val('SignInUrl', $Provider, ''), $Data);
        $RegisterUrl = formatString(val('RegisterUrl', $Provider, ''), $Data);

        if ($RegisterUrl && !val('NoRegister', $Options)) {
            $RegisterLink = ' '.anchor(sprintf(t('Register with %s', 'Register'), $Provider['Name']), $RegisterUrl, 'Button RegisterLink');
        } else {
            $RegisterLink = '';
        }

        if (val('NoConnectLabel', $Options)) {
            $ConnectLabel = '';
        } else {
            $ConnectLabel = '<span class="Username"></span><div class="ConnectLabel TextColor">'.sprintf(t('Sign In with %s'), $Provider['Name']).'</div>';
        }

        if (!C('Plugins.JsConnect.NoGuestCheck')) {
            $Result = '<div style="display: none" class="JsConnect-Container ConnectButton Small UserInfo" rel="'.$Url.'">';

            if (!val('IsDefault', $Provider)) {
                $Result .= '<div class="JsConnect-Guest">'.anchor(sprintf(t('Sign In with %s'), $Provider['Name']), $SignInUrl, 'Button Primary SignInLink').$RegisterLink.'</div>';
            }
            $Result .=
                '<div class="JsConnect-Connect"><a class="ConnectLink">'
                .img('https://cd8ba0b44a15c10065fd-24461f391e20b7336331d5789078af53.ssl.cf1.rackcdn.com/images/usericon_50.png', ['class' => 'ProfilePhotoSmall UserPhoto'])
                .$ConnectLabel
                .'</a></div>';

            $Result .= '</div>';
        } else {
            if (!val('IsDefault', $Provider)) {
                $Result = '<div class="JsConnect-Guest">'.anchor(sprintf(t('Sign In with %s'), $Provider['Name']), $SignInUrl, 'Button Primary SignInLink').$RegisterLink.'</div>';
            }
        }

        return $Result;
    }

    /**
     *
     *
     * @param $provider
     * @param null $target
     * @return array
     */
    protected static function connectQueryString($provider, $target = null) {
        if ($target === null) {
            $target = Gdn::request()->get('Target');
            if (!$target) {
                $target = '/'.ltrim(Gdn::request()->path(), '/');
            }
        }

        if (StringBeginsWith($target, '/entry/signin')) {
            $target = '/';
        }

        $qs = array('client_id' => $provider['AuthenticationKey'], 'Target' => $target);
        return $qs;
    }

    /**
     *
     *
     * @param $Provider
     * @param bool $Secure
     * @param bool $Callback
     * @return bool|string
     */
    public static function connectUrl($Provider, $Secure = false, $Callback = true) {
        if (!is_array($Provider))
            $Provider = self::getProvider($Provider);

        if (!is_array($Provider)) {
            return false;
        }

        $Url = $Provider['AuthenticateUrl'];
        $Query = ['client_id' => $Provider['AuthenticationKey']];

        if ($Secure) {
            include_once dirname(__FILE__).'/functions.jsconnect.php';
            $Query['timestamp'] = jsTimestamp();
            $Query['signature'] = jsHash(($Query['timestamp']).$Provider['AssociationSecret'], val('HashType', $Provider));
        }

        if (($Target = Gdn::request()->get('Target'))) {
            $Query['Target'] = $Target;
        } else {
            $Query['Target'] = '/'.ltrim(Gdn::request()->path(), '/');
        }

        if (StringBeginsWith($Query['Target'], '/entry/signin')) {
            $Query['Target'] = '/';
        }

        $Result = $Url.(strpos($Url, '?') === false ? '?' : '&').http_build_query($Query);
        if ($Callback) {
            $Result .= '&callback=?';
        }

        return $Result;
    }

    /**
     * Convenience method for functional clarity.
     *
     * @return array|mixed
     */
    public static function getAllProviders() {
        return self::getProvider();
    }

    /**
     *
     *
     * @param null $client_id
     * @return array|mixed
     */
    public static function getProvider($client_id = null) {
        if ($client_id !== null) {
            $Where = ['AuthenticationKey' => $client_id];
        } else {
            $Where = ['AuthenticationSchemeAlias' => 'jsconnect'];
        }

        $Result = Gdn::sql()->getWhere('UserAuthenticationProvider', $Where)->resultArray();
        foreach ($Result as &$Row) {
            $Attributes = dbdecode($Row['Attributes']);
            if (is_array($Attributes)) {
                $Row = array_merge($Attributes, $Row);
            }
        }

        if ($client_id) {
            return val(0, $Result, false);
        } else {
            return $Result;
        }

        return $Result;
    }

    /**
     * Gets the full sign in url with the jsConnect redirect added.
     *
     * @param arrat|int $provider The authentication provider or its ID.
     * @param string|null $target The url to redirect to after signing in or null to guess the target.
     * @return string Returns the sign in url.
     */
    public static function getSignInUrl($provider, $target = null) {
        if (!is_array($provider)) {
            $provider = static::getProvider($provider);
        }

        $signInUrl = val('SignInUrl', $provider);
        if (!$signInUrl) {
            return '';
        }

        $qs = static::connectQueryString($provider, $target);
        $finalTarget = urlencode(Url('/entry/jsconnect', true).'?'.http_build_query($qs));

        $signInUrl = str_ireplace(
            ['{target}', '{redirect}'],
            $finalTarget,
            $signInUrl);

        return $signInUrl;
    }

    /**
     * Gets the full sign in url with the jsConnect redirect added.
     *
     * @param array|int $provider The authentication provider or its ID.
     * @param string|null $target The url to redirect to after signing in or null to guess the target.
     * @return string Returns the sign in url.
     */
    public static function getRegisterUrl($provider, $target = null) {
        if (!is_array($provider)) {
            $provider = static::getProvider($provider);
        }

        $registerUrl = val('RegisterUrl', $provider);
        if (!$registerUrl) {
            return '';
        }

        $qs = static::connectQueryString($provider, $target);
        $finalTarget = urlencode(url('/entry/jsconnect', true).'?'.http_build_query($qs));

        $registerUrl = str_ireplace(
            array('{target}', '{redirect}'),
            $finalTarget,
            $registerUrl);

        return $registerUrl;
    }


    /// EVENT HANDLERS ///


    /**
     * Calculate the final sign in and register urls for jsConnect.
     *
     * @param AuthenticationProviderModel $sender Not used.
     * @param array $args Contains the provider and
     */
    public function authenticationProviderModel_calculateJsConnect_handler($sender, $args) {
        $provider =& $args['Provider'];
        $target = val('Target', $args, null);

        $provider['SignInUrlFinal'] = static::getSignInUrl($provider, $target);
        $provider['RegisterUrlFinal'] = static::getRegisterUrl($provider, $target);
    }

    /**
     *
     *
     * @param Gdn_Controller $Sender
     * @param $Args
     */
    public function base_beforeSignInButton_handler($Sender, $Args) {
        $Providers = self::getAllProviders();
        foreach ($Providers as $Provider) {
            echo "\n".self::connectButton($Provider);
        }
    }

    /**
     *
     *
     * @param Gdn_Controller $Sender
     */
    public function base_beforeSignInLink_handler($Sender) {
        if (Gdn::session()->isValid()) {
            return;
        }

        $Providers = self::getAllProviders();
        foreach ($Providers as $Provider) {
            echo "\n".wrap(self::connectButton($Provider, ['NoRegister' => true, 'NoConnectLabel' => true]), 'li', ['class' => 'Connect jsConnect']);
        }
    }

    /**
     *
     *
     * @param EntryController $Sender
     * @param array $Args
     */
    public function base_connectData_handler($Sender, $Args) {
        if (val(0, $Args) != 'jsconnect') {
            return;
        }

        include_once dirname(__FILE__).'/functions.jsconnect.php';

        $Form = $Sender->Form;

        $JsConnect = $Form->getFormValue('JsConnect', $Form->getFormValue('Form/JsConnect'));
        parse_str($JsConnect, $JsData);

        // Make sure the data is valid.
        $client_id = val('client_id', $JsData, val('clientid', $JsData, $Sender->Request->get('client_id'), true), true);
        $Signature = val('signature', $JsData, false, true);
        $String = val('sigStr', $JsData, false, true); // debugging
        unset($JsData['string']);

        if (!$client_id) {
            throw new Gdn_UserException(sprintf(t('ValidateRequired'), 'client_id'), 400);
        }
        $Provider = self::getProvider($client_id);
        if (!$Provider) {
            throw new Gdn_UserException(sprintf(t('Unknown client: %s.'), htmlspecialchars($client_id)), 400);
        }

        if (!val('TestMode', $Provider)) {
            if (!$Signature) {
                throw new Gdn_UserException(sprintf(T('ValidateRequired'), 'signature'), 400);
            }

            // Validate the signature.
            $CalculatedSignature = signJsConnect($JsData, $client_id, val('AssociationSecret', $Provider), val('HashType', $Provider, 'md5'));
            if ($CalculatedSignature != $Signature) {
                throw new Gdn_UserException(t("Signature invalid."), 400);
            }
        }


        // Map all of the standard jsConnect data.
        $Map = ['uniqueid' => 'UniqueID', 'name' => 'Name', 'email' => 'Email', 'photourl' => 'Photo', 'fullname' => 'FullName', 'roles' => 'Roles'];
        foreach ($Map as $Key => $Value) {
            if (array_key_exists($Key, $JsData)) {
                $Form->SetFormValue($Value, $JsData[$Key]);
            }
        }

        // Now add any extended information that jsConnect might have sent.
        $ExtData = array_diff_key($JsData, $Map);

        if (class_exists('SimpleAPIPlugin')) {
            SimpleAPIPlugin::translatePost($ExtData, false);
        }

        Gdn::userModel()->defineSchema();
        $Keys = array_keys(Gdn::userModel()->Schema->fields());
        $UserFields = array_change_key_case(array_combine($Keys, $Keys));

        foreach ($ExtData as $Key => $Value) {
            $lkey = strtolower($Key);
            if (array_key_exists($lkey, $UserFields)) {
                $Form->setFormValue($UserFields[$lkey], $Value);
            } else {
                $Form->setFormValue($Key, $Value);
            }
        }

        $Form->setFormValue('Provider', $client_id);
        $Form->setFormValue('ProviderName', val('Name', $Provider, ''));
        $Form->addHidden('JsConnect', $JsData);

        $Sender->setData('ClientID', $client_id);
        $Sender->setData('Verified', true);
        $Sender->setData('Trusted', val('Trusted', $Provider, true)); // this is a trusted connection.
        $Sender->setData('SSOUser', $JsData);
    }

    /**
     *
     *
     * @param Gdn_Controller $Sender
     */
    public function base_getAppSettingsMenuItems_handler($Sender) {
        $Menu = $Sender->EventArguments['SideMenu'];
        $Menu->addItem('Users', t('Users'));
        $Menu->addLink('Users', 'jsConnect', 'settings/jsconnect', 'Garden.Settings.Manage', array('class' => 'nav-jsconnect'));
    }

    /**
     *
     *
     * @param Gdn_Controller $Sender
     * @param $Args
     */
    public function base_render_before($Sender, $Args) {
        if (!Gdn::Session()->UserID) {
            $Sender->AddJSFile('jsconnect.js', 'plugins/jsconnect');
            $Sender->AddCssFile('jsconnect.css', 'plugins/jsconnect');
        }
    }

    /**
     * An intermediate page for jsConnect that checks SSO against and then posts the information to /entry/connect.
     *
     * @param EntryController $Sender
     * @param string $Action A specific action. It can be one of the following:
     *
     * - blank: The default action.
     * - guest: There is no user signed in.
     * -
     * @param string $Target The url to redirect to after a successful connect.
     * @throws /Exception Throws an exception when the jsConnect provider is not found.
     */
    public function entryController_jsConnect_create($Sender, $Action = '', $Target = '') {
        $Sender->setData('_NoMessages', true);

        if ($Action) {
            if ($Action == 'guest') {
                $Sender->addDefinition('CheckPopup', true);

                $Target = $Sender->Form->getFormValue('Target', '/');
                $Sender->RedirectUrl = $Target;

                $Sender->render('JsConnect', '', 'plugins/jsconnect');
            } else {
                parse_str($Sender->Form->getFormValue('JsConnect'), $JsData);

                $Error = val('error', $JsData);
                $Message = val('message', $JsData);

                if ($Error === 'timeout' && !$Message) {
                    $Message = t('Your sso timed out.', 'Your sso timed out during the request. Please try again.');
                }

                Gdn::dispatcher()
                    ->passData('Exception', $Message ? htmlspecialchars($Message) : htmlspecialchars($Error))
                    ->dispatch('home/error');
            }
        } else {
            $client_id = $Sender->setData('client_id', $Sender->Request->get('client_id', 0));
            $Provider = self::getProvider($client_id);

            if (empty($Provider)) {
                throw NotFoundException('Provider');
            }

            $Get = arrayTranslate($Sender->Request->get(), ['client_id', 'display']);

            $Sender->addDefinition('JsAuthenticateUrl', self::connectUrl($Provider, true));
            $Sender->addJsFile('jsconnect.js', 'plugins/jsconnect');
            $Sender->setData('Title', t('Connecting...'));
            $Sender->Form->Action = url('/entry/connect/jsconnect?'.http_build_query($Get));
            $Sender->Form->addHidden('JsConnect', '');
            $Sender->Form->addHidden('Target', $Target);

            $Sender->MasterView = 'empty';
            $Sender->Render('JsConnect', '', 'plugins/jsconnect');
        }
    }

    /**
     *
     *
     * @param Gdn_Controller $Sender
     */
    public function entryController_signIn_handler($Sender, $Args) {
        $Providers = self::getAllProviders();

        foreach ($Providers as $Provider) {
            $Method = [
                'Name' => $Provider['Name'],
                'SignInHtml' => self::connectButton($Provider)
            ];

            $Sender->Data['Methods'][] = $Method;
        }
    }

    /**
     *
     *
     * @param Gdn_Controller $Sender
     * @param array $Args
     */
    public function profileController_jsConnect_create($Sender, $Args = array()) {
        include_once dirname(__FILE__).'/functions.jsconnect.php';

        $client_id = $Sender->Request->get('client_id', 0);

        $Provider = self::getProvider($client_id);

        $client_id = val('AuthenticationKey', $Provider);
        $Secret = val('AssociationSecret', $Provider);

        if (Gdn::session()->isValid()) {
            $User = ArrayTranslate((array)Gdn::session()->User, array('UserID' => 'UniqueID', 'Name', 'Email', 'PhotoUrl', 'DateOfBirth', 'Gender'));

            // Grab the user's roles.
            $Roles = Gdn::userModel()->getRoles(Gdn::session()->UserID);
            $Roles = array_column($Roles, 'Name');
            $User['Roles'] = '';
            if (is_array($Roles) && sizeof($Roles)) {
                $User['Roles'] = implode(',', $Roles);
            }

            if (!$User['PhotoUrl'] && function_exists('UserPhotoDefaultUrl')) {
                $User['PhotoUrl'] = Url(UserPhotoDefaultUrl(Gdn::session()->User), true);
            }
        } else {
            $User = [];
        }

        ob_clean();
        writeJsConnect($User, $Sender->Request->get(), $client_id, $Secret, val('HashType', $Provider, true));
        exit();
    }

    /**
     *
     *
     * @param RootController $Sender
     * @param $Args
     */
    public function rootController_sso_handler($Sender, $Args) {
        $Provider = $Args['DefaultProvider'];
        if (val('AuthenticationSchemeAlias', $Provider) !== 'jsconnect') {
            return;
        }

        // The default provider is jsconnect so let's redispatch there.
        $Get = [
            'client_id' => val('AuthenticationKey', $Provider),
            'target' => val('Target', $Args, '/')
        ];
        $Url = '/entry/jsconnect?'.http_build_query($Get);
        Gdn::request()->pathAndQuery($Url);
        Gdn::dispatcher()->dispatch();
        $Args['Handled'] = true;
    }

    /**
     *
     *
     * @param SettingsController $Sender
     * @param array $Args
     */
    public function settingsController_jsConnect_create($Sender, $Args = array()) {
        $Sender->addJsFile('jsconnect-settings.js', 'plugins/jsconnect');
        $Sender->permission('Garden.Settings.Manage');
        $Sender->addSideMenu();

        switch (strtolower(val(0, $Args))) {
            case 'addedit':
                $this->settings_addEdit($Sender, $Args);
                break;
            case 'delete':
                $this->settings_delete($Sender, $Args);
                break;
            default:
                $this->settings_index($Sender, $Args);
                break;
        }
    }

    /**
     *
     *
     * @param SettingsController $sender
     * @param array $Args
     */
    protected function settings_addEdit($sender, $Args) {
        $sender->addJsFile('jsconnect-settings.js', 'plugins/jsconnect');

        $client_id = $sender->Request->get('client_id');
        Gdn::locale()->setTranslation('AuthenticationKey', 'Client ID');
        Gdn::locale()->setTranslation('AssociationSecret', 'Secret');
        Gdn::locale()->setTranslation('AuthenticateUrl', 'Authentication Url');

        /* @var Gdn_Form $form */
        $form = $sender->Form;
        $model = new Gdn_AuthenticationProviderModel();
        $form->setModel($model);
        $generate = false;

        if ($form->authenticatedPostBack()) {
            if ($form->getFormValue('Generate') || $sender->Request->post('Generate')) {
                $generate = true;
                $key = mt_rand();
                $secret = md5(mt_rand());
                $sender->setFormSaved(false);
            } else {
                $form->validateRule('AuthenticationKey', 'ValidateRequired');
                $form->validateRule('AuthenticationKey', 'regex:`^[a-z0-9_-]+$`i', T('The client id must contain only letters, numbers and dashes.'));
                $form->validateRule('AssociationSecret', 'ValidateRequired');
                $form->validateRule('AuthenticateUrl', 'ValidateRequired');

                $form->setFormValue('AuthenticationSchemeAlias', 'jsconnect');

                if ($form->save(['ID' => $client_id])) {
                    $sender->RedirectUrl = url('/settings/jsconnect');
                }
            }
        } else {
            if ($client_id) {
                $provider = self::getProvider($client_id);
                touchValue('Trusted', $provider, 1);
            } else {
                $provider = array();
            }
            $form->setData($provider);
        }

        // Set up the form controls for editing the connection.
        $hashTypes = hash_algos();
        $hashTypes = array_combine($hashTypes, $hashTypes);

        $controls = [
            'AuthenticationKey' => [
                'LabelCode' => 'Client ID',
                'Description' => t('The client ID uniquely identifies the site.', 'The client ID uniquely identifies the site. You can generate a new ID with the button at the bottom of this page.')
            ],
            'AssociationSecret' => [
                'LabelCode' => 'Secret',
                'Description' => t('The secret secures the sign in process.', 'The secret secures the sign in process. Do <b>NOT</b> give the secret out to anyone.')
            ],
            'Name' => [
                'LabelCode' => 'Site Name',
                'Description' => t('Enter a short name for the site.', 'Enter a short name for the site. This is displayed on the signin buttons.')
            ],
            'AuthenticateUrl' => [
                'LabelCode' => 'Authentication URL',
                'Description' => t('The location of the JSONP formatted authentication data.')
            ],
            'SignInUrl' => [
                'LabelCode' => 'Sign In URL',
                'Description' => t('The url that users use to sign in.').' '.t('Use {target} to specify a redirect.')
            ],
            'RegisterUrl' => [
                'LabelCode' => 'Registration URL',
                'Description' => t('The url that users use to register for a new account.')
            ],
            'SignOutUrl' => [
                'LabelCode' => 'Sign Out URL',
                'Description' => t('The url that users use to sign out of your site.')
            ],
            'Trusted' => [
                'Control' => 'toggle',
                'LabelCode' => 'This is trusted connection and can sync roles & permissions.'
            ],
            'IsDefault' => [
                'Control' => 'toggle',
                'LabelCode' => 'Make this connection your default signin method.'
            ],
            'Advanced' => [
                'Control' => 'callback',
                'Callback' => function($form) {
                    return '<h2>'.t('Advanced').'</h2>';
                }
            ],
            'HashType' => [
                'Control' => 'dropdown',
                'LabelCode' => 'Hash Algorithm',
                'Items' => $hashTypes,
                'Description' => T(
                    'Choose md5 if you\'re not sure what to choose.',
                    "You can select a custom hash algorithm to sign your requests. The hash algorithm must also be used in your client library. Choose md5 if you're not sure what to choose."
                ),
                'Options' => ['Default' => 'md5']
            ],
            'TestMode' => ['Control' => 'toggle', 'LabelCode' => 'This connection is in test-mode.']
        ];
        $sender->setData('_Controls', $controls);
        $sender->setData('Title', sprintf(T($client_id ? 'Edit %s' : 'Add %s'), T('Connection')));

        // Throw a render event as this plugin so that handlers can call our methods.
        Gdn::pluginManager()->callEventHandlers($this, __CLASS__, 'addedit', 'render');
        if ($generate && $sender->deliveryType() === DELIVERY_TYPE_VIEW) {
            $sender->setJson('AuthenticationKey', $key);
            $sender->setJson('AssociationSecret', $secret);
            $sender->render('Blank', 'Utility', 'Dashboard');
        } else {
            $sender->render('Settings_AddEdit', '', 'plugins/jsconnect');

        }
    }

    /**
     *
     *
     * @param $Sender
     * @param $Args
     */
    public function settings_delete($Sender, $Args) {
        $client_id = $Sender->Request->get('client_id');
        if ($Sender->Form->authenticatedPostBack()) {
            $Model = new Gdn_AuthenticationProviderModel();
            $Model->delete(['AuthenticationKey' => $client_id]);
            $Sender->RedirectUrl = url('/settings/jsconnect');
            $Sender->render('Blank', 'Utility', 'Dashboard');
        }
    }

    /**
     *
     *
     * @param $Sender
     * @param $Args
     */
    protected function settings_index($Sender, $Args) {
        $validation = new Gdn_Validation();
        $configurationModel = new Gdn_ConfigurationModel($validation);
        $configurationModel->setField([
            'Garden.Registration.AutoConnect',
            'Garden.SignIn.Popup'
        ]);
        $Sender->Form->setModel($configurationModel);
        if ($Sender->Form->authenticatedPostback()) {
            if ($Sender->Form->save() !== false) {
                $Sender->informMessage(t('Your settings have been saved.'));
            }
        } else {
            $Sender->Form->setData($configurationModel->Data);
        }

        $Providers = self::getProvider();
        $Sender->setData('Providers', $Providers);
        $Sender->render('Settings', '', 'plugins/jsconnect');
    }
}
