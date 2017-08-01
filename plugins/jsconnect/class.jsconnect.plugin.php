<?php
/**
 * @copyright 2008-2016 Vanilla Forums, Inc.
 * @license GNU GPLv2 http://www.opensource.org/licenses/gpl-2.0.php
 * @since 1.1.2b Fixed ConnectUrl to examine given url for existing querystring params and concatenate query params appropriately.
 */

/**
 * Class JsConnectPlugin
 */
class JsConnectPlugin extends Gdn_Plugin {

    const NONCE_EXPIRATION = 5 * 60;

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
     * @param array $options
     * @return string
     */
    public static function allConnectButtons($options = []) {
        $result = '';

        $providers = self::getAllProviders();
        foreach ($providers as $provider) {
            $result .= self::connectButton($provider, $options);
        }
        return $result;
    }

    /**
     *
     *
     * @param $provider
     * @param array $options
     * @return string
     */
    public static function connectButton($provider, $options = []) {
        if (!is_array($provider)) {
            $provider = self::getProvider($provider);
        }

        $url = htmlspecialchars(self::connectUrl($provider));
        $data = $provider;

        $target = Gdn::request()->get('Target');
        if (!$target) {
            $target = '/'.ltrim(Gdn::request()->path());
        }

        if (stringBeginsWith($target, '/entry/signin')) {
            $target = '/';
        }

        $connectQuery = ['client_id' => $provider['AuthenticationKey'], 'Target' => $target];
        $data['Target'] = urlencode(url('entry/jsconnect', true).'?'.http_build_query($connectQuery));
        $data['Redirect'] = $data['target'] = $data['redirect'] = $data['Target'];

        $signInUrl = formatString(val('SignInUrl', $provider, ''), $data);
        $registerUrl = formatString(val('RegisterUrl', $provider, ''), $data);

        if ($registerUrl && !val('NoRegister', $options)) {
            $registerLink = ' '.anchor(sprintf(t('Register with %s', 'Register'), $provider['Name']), $registerUrl, 'Button RegisterLink');
        } else {
            $registerLink = '';
        }

        if (val('NoConnectLabel', $options)) {
            $connectLabel = '';
        } else {
            $connectLabel = '<span class="Username"></span><div class="ConnectLabel TextColor">'.sprintf(t('Sign In with %s'), $provider['Name']).'</div>';
        }

        if (!c('Plugins.JsConnect.NoGuestCheck')) {
            $result = '<div style="display: none" class="JsConnect-Container ConnectButton Small UserInfo" rel="'.$url.'">';

            if (!val('IsDefault', $provider)) {
                $result .= '<div class="JsConnect-Guest">'.anchor(sprintf(t('Sign In with %s'), $provider['Name']), $signInUrl, 'Button Primary SignInLink').$registerLink.'</div>';
            }
            $result .=
                '<div class="JsConnect-Connect"><a class="ConnectLink">'
                .img('https://images.v-cdn.net/usericon_50.png', ['class' => 'ProfilePhotoSmall UserPhoto'])
                .$connectLabel
                .'</a></div>';

            $result .= '</div>';
        } else {
            if (!val('IsDefault', $provider)) {
                $result = '<div class="JsConnect-Guest">'.anchor(sprintf(t('Sign In with %s'), $provider['Name']), $signInUrl, 'Button Primary SignInLink').$registerLink.'</div>';
            }
        }

        return $result;
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

        if (stringBeginsWith($target, '/entry/signin')) {
            $target = '/';
        }

        $qs = ['client_id' => $provider['AuthenticationKey'], 'Target' => $target];
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
            $nonceModel = new UserAuthenticationNonceModel();
            $nonce = uniqid('jsconnect_', true);
            $nonceModel->insert(['Nonce' => $nonce, 'Token' => 'jsConnect']);

            $Query['ip'] = Gdn::request()->ipAddress();
            $Query['nonce'] = $nonce;
            $Query['timestamp'] = jsTimestamp();

            // v2 compatible sig
            $Query['sig'] = jsHash(
                $Query['ip'].$Query['nonce'].$Query['timestamp'].$Provider['AssociationSecret'],
                val('HashType', $Provider)
            );
            // v1 compatible sig
            $Query['signature'] = jsHash(
                $Query['timestamp'].$Provider['AssociationSecret'],
                val('HashType', $Provider)
            );
        }

        if (($Target = Gdn::request()->get('Target'))) {
            $Query['Target'] = $Target;
        } else {
            $Query['Target'] = '/'.ltrim(Gdn::request()->path(), '/');
        }

        if (stringBeginsWith($Query['Target'], '/entry/signin')) {
            $Query['Target'] = '/';
        }

        $Result = $Url.(strpos($Url, '?') === false ? '?' : '&').'v=2&'.http_build_query($Query);
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
            $where = ['AuthenticationKey' => $client_id];
        } else {
            $where = ['AuthenticationSchemeAlias' => 'jsconnect'];
        }

        $result = Gdn::sql()->getWhere('UserAuthenticationProvider', $where)->resultArray();
        foreach ($result as &$row) {
            $attributes = dbdecode($row['Attributes']);
            if (is_array($attributes)) {
                $row = array_merge($attributes, $row);
            }
        }

        if ($client_id) {
            return val(0, $result, false);
        } else {
            return $result;
        }

        return $result;
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
        $finalTarget = urlencode(url('/entry/jsconnect', true).'?'.http_build_query($qs));

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
            ['{target}', '{redirect}'],
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
     * @param Gdn_Controller $sender
     * @param $args
     */
    public function base_beforeSignInButton_handler($sender, $args) {
        $providers = self::getAllProviders();
        foreach ($providers as $provider) {
            echo "\n".self::connectButton($provider);
        }
    }

    /**
     *
     *
     * @param Gdn_Controller $sender
     */
    public function base_beforeSignInLink_handler($sender) {
        if (Gdn::session()->isValid()) {
            return;
        }

        $providers = self::getAllProviders();
        foreach ($providers as $provider) {
            echo "\n".wrap(self::connectButton($provider, ['NoRegister' => true, 'NoConnectLabel' => true]), 'li', ['class' => 'Connect jsConnect']);
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
        $version = val('v', $JsData, null);
        $client_id = val('client_id', $JsData, val('clientid', $JsData, $Sender->Request->get('client_id')));
        $Signature = val('sig', $JsData, val('signature', $JsData, false));
        $String = val('sigStr', $JsData, false); // debugging
        unset($JsData['v'], $JsData['client_id'], $JsData['clientid'], $JsData['signature'], $JsData['sig'],
                $JsData['sigStr'], $JsData['string']);

        if (!$client_id) {
            throw new Gdn_UserException(sprintf(t('ValidateRequired'), 'client_id'), 400);
        }
        $Provider = self::getProvider($client_id);
        if (!$Provider) {
            throw new Gdn_UserException(sprintf(t('Unknown client: %s.'), htmlspecialchars($client_id)), 400);
        }

        if (!val('TestMode', $Provider)) {
            if (!$Signature) {
                throw new Gdn_UserException(sprintf(t('ValidateRequired'), 'signature'), 400);
            }

            if ($version === '2') {
                // Verify IP Address.
                if (Gdn::request()->ipAddress() !== val('ip', $JsData, null)) {
                    throw new Gdn_UserException(t('IP address invalid.'), 400);
                }

                // Verify nonce.
                $nonceModel = new UserAuthenticationNonceModel();
                $nonce = val('nonce', $JsData, null);
                if ($nonce === null) {
                    throw new Gdn_UserException(t('Nonce not found.'), 400);
                }

                // Grab the nonce from the session's stash.
                $foundNonce = Gdn::session()->stash('jsConnectNonce', '', false);
                $grabbedFromStash = (bool)$foundNonce;
                if (!$grabbedFromStash) {
                    $foundNonce = $nonceModel->getWhere(['Nonce' => $nonce])->firstRow(DATASET_TYPE_ARRAY);
                }
                if (!$foundNonce) {
                    throw new Gdn_UserException(t('Nonce not found.'), 400);
                }

                // Clear nonce from the database.
                $nonceModel->delete(['Nonce' => $nonce]);
                if (strtotime($foundNonce['Timestamp']) < time() - self::NONCE_EXPIRATION) {
                    throw new Gdn_UserException(t('Nonce expired.'), 400);
                }

                if (!$grabbedFromStash) {
                    // Stash nonce in case we post back!
                    Gdn::session()->stash('jsConnectNonce', $foundNonce);
                }
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
                $Form->setFormValue($Value, $JsData[$Key]);
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
     * @param Gdn_Controller $sender
     */
    public function base_getAppSettingsMenuItems_handler($sender) {
        $menu = $sender->EventArguments['SideMenu'];
        $menu->addItem('Users', t('Users'));
        $menu->addLink('Users', 'jsConnect', 'settings/jsconnect', 'Garden.Settings.Manage', ['class' => 'nav-jsconnect']);
    }

    /**
     *
     *
     * @param Gdn_Controller $sender
     * @param $args
     */
    public function base_render_before($sender, $args) {
        if (!Gdn::session()->UserID) {
            $sender->addJSFile('jsconnect.js', 'plugins/jsconnect');
            $sender->addCssFile('jsconnect.css', 'plugins/jsconnect');
        } else {
            // Unset the nonce!
            Gdn::session()->stash('jsConnectNonce');
        }
    }

    /**
     * An intermediate page for jsConnect that checks SSO against and then posts the information to /entry/connect.
     *
     * @param EntryController $sender
     * @param string $action A specific action. It can be one of the following:
     *
     * - blank: The default action.
     * - guest: There is no user signed in.
     * -
     * @param string $target The url to redirect to after a successful connect.
     * @throws /Exception Throws an exception when the jsConnect provider is not found.
     */
    public function entryController_jsConnect_create($sender, $action = '', $target = '') {
        // Clear the nonce from the stash if any!
        Gdn::session()->stash('jsConnectNonce');

        $sender->setData('_NoMessages', true);

        if ($action) {
            if ($action == 'guest') {
                $sender->addDefinition('CheckPopup', true);

                $target = $sender->Form->getFormValue('Target', '/');
                $sender->setRedirectTo($target, false);

                $sender->render('JsConnect', '', 'plugins/jsconnect');
            } else {
                parse_str($sender->Form->getFormValue('JsConnect'), $jsData);

                $error = val('error', $jsData);
                $message = val('message', $jsData);

                if ($error === 'timeout' && !$message) {
                    $message = t('Your sso timed out.', 'Your sso timed out during the request. Please try again.');
                }

                Gdn::dispatcher()
                    ->passData('Exception', $message ? htmlspecialchars($message) : htmlspecialchars($error))
                    ->dispatch('home/error');
            }
        } else {
            $client_id = $sender->setData('client_id', $sender->Request->get('client_id', 0));
            $provider = self::getProvider($client_id);

            if (empty($provider)) {
                throw notFoundException('Provider');
            }

            $get = arrayTranslate($sender->Request->get(), ['client_id', 'display']);

            $sender->addDefinition('JsAuthenticateUrl', self::connectUrl($provider, true));
            $sender->addJsFile('jsconnect.js', 'plugins/jsconnect');
            $sender->setData('Title', t('Connecting...'));
            $sender->Form->Action = url('/entry/connect/jsconnect?'.http_build_query($get));
            $sender->Form->addHidden('JsConnect', '');
            $sender->Form->addHidden('Target', $target);

            $sender->MasterView = 'empty';
            $sender->render('JsConnect', '', 'plugins/jsconnect');
        }
    }

    /**
     *
     *
     * @param Gdn_Controller $sender
     */
    public function entryController_signIn_handler($sender, $args) {
        $providers = self::getAllProviders();

        foreach ($providers as $provider) {
            $method = [
                'Name' => $provider['Name'],
                'SignInHtml' => self::connectButton($provider)
            ];

            $sender->Data['Methods'][] = $method;
        }
    }

    /**
     *
     *
     * @param Gdn_Controller $Sender
     * @param array $Args
     */
    public function profileController_jsConnect_create($Sender, $Args = []) {
        include_once dirname(__FILE__).'/functions.jsconnect.php';

        $client_id = $Sender->Request->get('client_id', 0);

        $Provider = self::getProvider($client_id);

        $client_id = val('AuthenticationKey', $Provider);
        $Secret = val('AssociationSecret', $Provider);

        if (Gdn::session()->isValid()) {
            $User = arrayTranslate((array)Gdn::session()->User, ['UserID' => 'UniqueID', 'Name', 'Email', 'PhotoUrl', 'DateOfBirth', 'Gender']);

            // Grab the user's roles.
            $Roles = Gdn::userModel()->getRoles(Gdn::session()->UserID);
            $Roles = array_column($Roles, 'Name');
            $User['Roles'] = '';
            if (is_array($Roles) && sizeof($Roles)) {
                $User['Roles'] = implode(',', $Roles);
            }

            if (!$User['PhotoUrl'] && function_exists('UserPhotoDefaultUrl')) {
                $User['PhotoUrl'] = url(userPhotoDefaultUrl(Gdn::session()->User), true);
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
     * @param RootController $sender
     * @param $args
     */
    public function rootController_sso_handler($sender, $args) {
        $provider = $args['DefaultProvider'];
        if (val('AuthenticationSchemeAlias', $provider) !== 'jsconnect') {
            return;
        }

        // The default provider is jsconnect so let's redispatch there.
        $get = [
            'client_id' => val('AuthenticationKey', $provider),
            'target' => val('Target', $args, '/')
        ];
        $url = '/entry/jsconnect?'.http_build_query($get);
        Gdn::request()->pathAndQuery($url);
        Gdn::dispatcher()->dispatch();
        $args['Handled'] = true;
    }

    /**
     *
     *
     * @param SettingsController $sender
     * @param array $args
     */
    public function settingsController_jsConnect_create($sender, $args = []) {
        $sender->addJsFile('jsconnect-settings.js', 'plugins/jsconnect');
        $sender->permission('Garden.Settings.Manage');
        $sender->addSideMenu();

        switch (strtolower(val(0, $args))) {
            case 'addedit':
                $this->settings_addEdit($sender, $args);
                break;
            case 'delete':
                $this->settings_delete($sender, $args);
                break;
            default:
                $this->settings_index($sender, $args);
                break;
        }
    }

    /**
     *
     *
     * @param SettingsController $sender
     * @param array $args
     */
    protected function settings_addEdit($sender, $args) {
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
                $form->validateRule('AuthenticationKey', 'regex:`^[a-z0-9_-]+$`i', t('The client id must contain only letters, numbers and dashes.'));
                $form->validateRule('AssociationSecret', 'ValidateRequired');
                $form->validateRule('AuthenticateUrl', 'ValidateRequired');

                $form->setFormValue('AuthenticationSchemeAlias', 'jsconnect');

                if ($form->save(['ID' => $client_id])) {
                    $sender->setRedirectTo('/settings/jsconnect');
                }
            }
        } else {
            if ($client_id) {
                $provider = self::getProvider($client_id);
                touchValue('Trusted', $provider, 1);
            } else {
                $provider = [];
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
                    return subheading(t('Advanced'));
                }
            ],
            'HashType' => [
                'Control' => 'dropdown',
                'LabelCode' => 'Hash Algorithm',
                'Items' => $hashTypes,
                'Description' => t(
                    'Choose md5 if you\'re not sure what to choose.',
                    "You can select a custom hash algorithm to sign your requests. The hash algorithm must also be used in your client library. Choose md5 if you're not sure what to choose."
                ),
                'Options' => ['Default' => 'md5']
            ],
            'TestMode' => ['Control' => 'toggle', 'LabelCode' => 'This connection is in test-mode.']
        ];
        $sender->setData('_Controls', $controls);
        $sender->setData('Title', sprintf(t($client_id ? 'Edit %s' : 'Add %s'), t('Connection')));

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
     * @param SettingsController $sender
     * @param array $args
     */
    public function settings_delete($sender, $args) {
        $client_id = $sender->Request->get('client_id');
        if ($sender->Form->authenticatedPostBack()) {
            $model = new Gdn_AuthenticationProviderModel();
            $model->delete(['AuthenticationKey' => $client_id]);
            $sender->setRedirectTo('/settings/jsconnect');
            $sender->render('Blank', 'Utility', 'Dashboard');
        }
    }

    /**
     *
     *
     * @param $sender
     * @param $args
     */
    protected function settings_index($sender, $args) {
        $validation = new Gdn_Validation();
        $configurationModel = new Gdn_ConfigurationModel($validation);
        $configurationModel->setField([
            'Garden.Registration.AutoConnect',
            'Garden.SignIn.Popup'
        ]);
        $sender->Form->setModel($configurationModel);
        if ($sender->Form->authenticatedPostback()) {
            if ($sender->Form->save() !== false) {
                $sender->informMessage(t('Your settings have been saved.'));
            }
        } else {
            $sender->Form->setData($configurationModel->Data);
        }

        $providers = self::getProvider();
        $sender->setData('Providers', $providers);
        $sender->render('Settings', '', 'plugins/jsconnect');
    }
}
