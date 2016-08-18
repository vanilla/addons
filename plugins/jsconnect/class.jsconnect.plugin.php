<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 * @since 1.1.2b Fixed ConnectUrl to examine given url for existing querystring params and concatenate query params appropriately.
 */

// Define the plugin:
$PluginInfo['jsconnect'] = array(
    'Name' => 'Vanilla jsConnect',
    'Description' => 'Enables custom single sign-on solutions. They can be same-domain or cross-domain. See the <a href="http://vanillaforums.org/docs/jsconnect">documentation</a> for details.',
    'Version' => '1.5.3',
    'RequiredApplications' => array('Vanilla' => '2.0.18'),
    'MobileFriendly' => true,
    'Author' => 'Todd Burry',
    'AuthorEmail' => 'todd@vanillaforums.com',
    'AuthorUrl' => 'http://www.vanillaforums.org/profile/todd',
    'SettingsUrl' => '/settings/jsconnect',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'Icon' => 'jsconnect.png'
);

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

    public static function allConnectButtons($Options = array()) {
        $Result = '';

        $Providers = self::getAllProviders();
        foreach ($Providers as $Provider) {
            $Result .= self::connectButton($Provider, $Options);
        }
        return $Result;
    }

    /**
     * Opt out of popup settings page on addons page
     *
     * @param SettingsController $sender
     * @param array $args
     */
    public function settingsController_beforeAddonList_handler($sender, &$args) {
        if (val('jsconnect', $args['AvailableAddons'])) {
            $args['AvailableAddons']['jsconnect']['HasPopupFriendlySettings'] = false;
        }
    }

    public static function connectButton($Provider, $Options = array()) {
        if (!is_array($Provider))
            $Provider = self::getProvider($Provider);

        $Url = htmlspecialchars(self::connectUrl($Provider));

        $Data = $Provider;

        $Target = Gdn::Request()->Get('Target');
        if (!$Target)
            $Target = '/'.ltrim(Gdn::Request()->Path());

        if (StringBeginsWith($Target, '/entry/signin'))
            $Target = '/';

        $ConnectQuery = array('client_id' => $Provider['AuthenticationKey'], 'Target' => $Target);
        $Data['Target'] = urlencode(Url('entry/jsconnect', TRUE).'?'.http_build_query($ConnectQuery));
        $Data['Redirect'] = $Data['target'] = $Data['redirect'] = $Data['Target'];

        $SignInUrl = FormatString(GetValue('SignInUrl', $Provider, ''), $Data);
        $RegisterUrl = FormatString(GetValue('RegisterUrl', $Provider, ''), $Data);

        if ($RegisterUrl && !GetValue('NoRegister', $Options))
            $RegisterLink = ' '.Anchor(sprintf(T('Register with %s', 'Register'), $Provider['Name']), $RegisterUrl, 'Button RegisterLink');
        else
            $RegisterLink = '';

        if (IsMobile()) {
            $PopupWindow = '';
        } else {
            $PopupWindow = 'PopupWindow';
        }

        if (GetValue('NoConnectLabel', $Options)) {
            $ConnectLabel = '';
        } else {
            $ConnectLabel = '<span class="Username"></span><div class="ConnectLabel TextColor">'.sprintf(T('Sign In with %s'), $Provider['Name']).'</div>';
        }

        if (!C('Plugins.JsConnect.NoGuestCheck')) {
            $Result = '<div style="display: none" class="JsConnect-Container ConnectButton Small UserInfo" rel="'.$Url.'">';

            if (!GetValue('IsDefault', $Provider))
                $Result .= '<div class="JsConnect-Guest">'.Anchor(sprintf(T('Sign In with %s'), $Provider['Name']), $SignInUrl, 'Button Primary SignInLink').$RegisterLink.'</div>';

            $Result .=
                '<div class="JsConnect-Connect"><a class="ConnectLink">'.Img('https://cd8ba0b44a15c10065fd-24461f391e20b7336331d5789078af53.ssl.cf1.rackcdn.com/images/usericon_50.png', array('class' => 'ProfilePhotoSmall UserPhoto')).
                $ConnectLabel.
                '</a></div>';

            $Result .= '</div>';
        } else {
            if (!GetValue('IsDefault', $Provider))
                $Result = '<div class="JsConnect-Guest">'.Anchor(sprintf(T('Sign In with %s'), $Provider['Name']), $SignInUrl, 'Button Primary SignInLink').$RegisterLink.'</div>';
        }

        return $Result;
    }

    protected static function connectQueryString($provider, $target = null) {
        if ($target === null) {
            $target = Gdn::Request()->Get('Target');
            if (!$target) {
                $target = '/'.ltrim(Gdn::Request()->Path(), '/');
            }
        }

        if (StringBeginsWith($target, '/entry/signin')) {
            $target = '/';
        }

        $qs = array('client_id' => $provider['AuthenticationKey'], 'Target' => $target);
        return $qs;
    }

    public static function connectUrl($Provider, $Secure = FALSE, $Callback = TRUE) {
        if (!is_array($Provider))
            $Provider = self::getProvider($Provider);

        if (!is_array($Provider))
            return FALSE;

        $Url = $Provider['AuthenticateUrl'];
        $Query = array('client_id' => $Provider['AuthenticationKey']);

        if ($Secure) {
            include_once dirname(__FILE__).'/functions.jsconnect.php';
            $Query['timestamp'] = jsTimestamp();
            $Query['signature'] = jsHash(($Query['timestamp']).$Provider['AssociationSecret'], GetValue('HashType', $Provider));
        }

        if (($Target = Gdn::Request()->Get('Target')))
            $Query['Target'] = $Target;
        else
            $Query['Target'] = '/'.ltrim(Gdn::Request()->Path(), '/');
        if (StringBeginsWith($Query['Target'], '/entry/signin'))
            $Query['Target'] = '/';

        $Result = $Url.(strpos($Url, '?') === FALSE ? '?' : '&').http_build_query($Query);
        if ($Callback)
            $Result .= '&callback=?';
        return $Result;
    }

    public static function getAllProviders() {
        return self::getProvider();
    }

    public static function getProvider($client_id = NULL) {
        if ($client_id !== NULL) {
            $Where = array('AuthenticationKey' => $client_id);
        } else {
            $Where = array('AuthenticationSchemeAlias' => 'jsconnect');
        }

        $Result = Gdn::SQL()->GetWhere('UserAuthenticationProvider', $Where)->ResultArray();
        foreach ($Result as &$Row) {
            $Attributes = dbdecode($Row['Attributes']);
            if (is_array($Attributes))
                $Row = array_merge($Attributes, $Row);
        }

        if ($client_id)
            return GetValue(0, $Result, FALSE);
        else
            return $Result;

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
            array('{target}', '{redirect}'),
            $finalTarget,
            $signInUrl);

        return $signInUrl;
    }

    /**
     * Gets the full sign in url with the jsConnect redirect added.
     *
     * @param arrat|int $provider The authentication provider or its ID.
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
        $finalTarget = urlencode(Url('/entry/jsconnect', true).'?'.http_build_query($qs));

        $registerUrl = str_ireplace(
            array('{target}', '{redirect}'),
            $finalTarget,
            $registerUrl);

        return $registerUrl;
    }


    /// EVENT HANDLERS ///

    /**
     * If the authenticating server can share cookies, the jsConnect will try a server to server connection here.
     * @param Gdn_Dispatcher $Sender
     * @param array $Args
     */
//   public function Base_BeforeDispatch_Handler($Sender, $Args) {
//      if (Gdn::Session()->UserID > 0)
//         return; // user signed in, don't check
//
//      // Check to see if we've already checked recently so that we don't flood every request.
//      $CookieName = C('Garden.Cookie.Name', 'Vanilla').'-ConnectFlood';
//
//      if (GetValue($CookieName, $_COOKIE)) {
//         return;
//      }
//      setcookie($CookieName, TRUE, time() + 60, '/'); // flood control 1 min
//
//      // Make a request to the external server.
//      $Providers = self::GetAllProviders();
//      @session_write_close();
//      foreach ($Providers as $Provider) {
//         $Url = self::ConnectUrl($Provider, TRUE, FALSE);
//         if (strpos($Url, 'jsConnectPHP') === FALSE)
//            continue;
//
//         echo htmlspecialchars($Url).'<br />';
//
//         try {
//            $Response = ProxyRequest($Url, 5, TRUE);
//            echo($Response."<br />\n");
//         } catch (Exception $Ex) {
//            echo "Error: ";
//            echo $Ex->getMessage()."<br />\n";
//            continue;
//         }
//         $Data = @json_decode($Response, TRUE);
//
//         if (is_array($Data)) {
//            $Data['Url'] = $Url;
//            print_r($Data);
//         }
//      }
//   }

    /**
     * Calculate the final sign in and register urls for jsConnect.
     *
     * @param object $sender Not used.
     * @param array $args Contains the provider and
     */
    public function authenticationProviderModel_calculateJsConnect_handler($sender, $args) {
        $provider =& $args['Provider'];
        $target = val('Target', $args, null);

        $provider['SignInUrlFinal'] = static::getSignInUrl($provider, $target);
        $provider['RegisterUrlFinal'] = static::getRegisterUrl($provider, $target);
    }

    public function base_beforeSignInButton_handler($Sender, $Args) {
        $Providers = self::getAllProviders();
        foreach ($Providers as $Provider) {
            echo "\n".self::connectButton($Provider);
        }
    }

    public function base_beforeSignInLink_handler($Sender) {
        if (Gdn::Session()->IsValid())
            return;

        $Providers = self::getAllProviders();
        foreach ($Providers as $Provider) {
            echo "\n".Wrap(self::connectButton($Provider, array('NoRegister' => TRUE, 'NoConnectLabel' => TRUE)), 'li', array('class' => 'Connect jsConnect'));
        }
    }

    /**
     *
     * @param EntryController $Sender
     * @param array $Args
     */
    public function base_connectData_handler($Sender, $Args) {
        if (GetValue(0, $Args) != 'jsconnect')
            return;

        include_once dirname(__FILE__).'/functions.jsconnect.php';

        $Form = $Sender->Form;

        $JsConnect = $Form->GetFormValue('JsConnect', $Form->GetFormValue('Form/JsConnect'));
        parse_str($JsConnect, $JsData);

        // Make sure the data is valid.
        $client_id = GetValue('client_id', $JsData, GetValue('clientid', $JsData, $Sender->Request->Get('client_id'), TRUE), TRUE);
        $Signature = GetValue('signature', $JsData, FALSE, TRUE);
        $String = GetValue('sigStr', $JsData, FALSE, TRUE); // debugging
        unset($JsData['string']);

        if (!$client_id)
            throw new Gdn_UserException(sprintf(T('ValidateRequired'), 'client_id'), 400);
        $Provider = self::getProvider($client_id);
        if (!$Provider)
            throw new Gdn_UserException(sprintf(T('Unknown client: %s.'), htmlspecialchars($client_id)), 400);

        if (!GetValue('TestMode', $Provider)) {
            if (!$Signature)
                throw new Gdn_UserException(sprintf(T('ValidateRequired'), 'signature'), 400);

            // Validate the signature.
            $CalculatedSignature = signJsConnect($JsData, $client_id, GetValue('AssociationSecret', $Provider), GetValue('HashType', $Provider, 'md5'));
            if ($CalculatedSignature != $Signature)
                throw new Gdn_UserException(T("Signature invalid."), 400);
        }


        // Map all of the standard jsConnect data.
        $Map = array('uniqueid' => 'UniqueID', 'name' => 'Name', 'email' => 'Email', 'photourl' => 'Photo', 'fullname' => 'FullName', 'roles' => 'Roles');
        foreach ($Map as $Key => $Value) {
            if (array_key_exists($Key, $JsData)) {
                $Form->SetFormValue($Value, $JsData[$Key]);
            }
        }

        // Now add any extended information that jsConnect might have sent.
        $ExtData = array_diff_key($JsData, $Map);

        if (class_exists('SimpleAPIPlugin')) {
            SimpleAPIPlugin::translatePost($ExtData, FALSE);
        }

        Gdn::UserModel()->DefineSchema();
        $Keys = array_keys(Gdn::UserModel()->Schema->Fields());
        $UserFields = array_change_key_case(array_combine($Keys, $Keys));

        foreach ($ExtData as $Key => $Value) {
            $lkey = strtolower($Key);
            if (array_key_exists($lkey, $UserFields)) {
                $Form->SetFormValue($UserFields[$lkey], $Value);
            } else {
                $Form->SetFormValue($Key, $Value);
            }
        }

        $Form->SetFormValue('Provider', $client_id);
        $Form->SetFormValue('ProviderName', GetValue('Name', $Provider, ''));
        $Form->AddHidden('JsConnect', $JsData);

        $Sender->SetData('ClientID', $client_id);
        $Sender->SetData('Verified', TRUE);
        $Sender->SetData('Trusted', GetValue('Trusted', $Provider, TRUE)); // this is a trusted connection.
        $Sender->SetData('SSOUser', $JsData);
    }

    public function base_getAppSettingsMenuItems_handler(&$Sender) {
        $Menu = $Sender->EventArguments['SideMenu'];
        $Menu->AddItem('Users', T('Users'));
        $Menu->AddLink('Users', 'jsConnect', 'settings/jsconnect', 'Garden.Settings.Manage', array('class' => 'nav-jsconnect'));
    }

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
        $Sender->SetData('_NoMessages', true);

        if ($Action) {
            if ($Action == 'guest') {
//            Redirect('/');
                $Sender->AddDefinition('CheckPopup', TRUE);

                $Target = $Sender->Form->GetFormValue('Target', '/');
                $Sender->RedirectUrl = $Target;

                $Sender->Render('JsConnect', '', 'plugins/jsconnect');
            } else {
                parse_str($Sender->Form->GetFormValue('JsConnect'), $JsData);

                $Error = GetValue('error', $JsData);
                $Message = GetValue('message', $JsData);

                if ($Error === 'timeout' && !$Message) {
                    $Message = T('Your sso timed out.', 'Your sso timed out during the request. Please try again.');
                }

                Gdn::Dispatcher()->PassData('Exception', $Message ? htmlspecialchars($Message) : htmlspecialchars($Error))
                    ->Dispatch('home/error');

//            $Sender->Form->AddError($Message ? htmlspecialchars($Message) : htmlspecialchars($Error));
//            $Sender->SetData('Title', T('Error'));
//            $Sender->Render('JsConnect_Error', '', 'plugins/jsconnect');
            }
        } else {
            $client_id = $Sender->SetData('client_id', $Sender->Request->Get('client_id', 0));
            $Provider = self::getProvider($client_id);

            if (empty($Provider))
                throw NotFoundException('Provider');

            $Get = ArrayTranslate($Sender->Request->Get(), array('client_id', 'display'));

            $Sender->AddDefinition('JsAuthenticateUrl', self::connectUrl($Provider, TRUE));
            $Sender->AddJsFile('jsconnect.js', 'plugins/jsconnect');
            $Sender->SetData('Title', T('Connecting...'));
            $Sender->Form->Action = Url('/entry/connect/jsconnect?'.http_build_query($Get));
            $Sender->Form->AddHidden('JsConnect', '');
            $Sender->Form->AddHidden('Target', $Target);

            $Sender->MasterView = 'empty';
            $Sender->Render('JsConnect', '', 'plugins/jsconnect');
        }
    }

    /**
     *
     * @param Gdn_Controller $Sender
     */
    public function entryController_signIn_handler($Sender, $Args) {
        $Providers = self::getAllProviders();

        foreach ($Providers as $Provider) {
            $Method = array(
                'Name' => $Provider['Name'],
                'SignInHtml' => self::connectButton($Provider)
            );

            $Sender->Data['Methods'][] = $Method;
        }
    }

//   public function PluginController_JsConnectInfo($Sender, $Args) {
//      $Args = array_change_key_case($Args);
//
//      $Providers = self::GetProvider(GetValue('client_id', $Args));
//      $Result = array();
//      foreach ($Providers as $Provider) {
//         $Info = ArrayTranslate($Provider, array('AuthenticationKey' => 'client_id'));
//         $Info['ConnectUrl'] = self::ConnectUrl($Provider);
//         $Info['SigninUrl'] = $Provider['SignInUrl'];
//
//      }
//   }

    /**
     *
     * @param Gdn_Controller $Sender
     * @param array $Args
     */
    public function profileController_jsConnect_create($Sender, $Args = array()) {
        include_once dirname(__FILE__).'/functions.jsconnect.php';

        $client_id = $Sender->Request->Get('client_id', 0);

        $Provider = self::getProvider($client_id);

        $client_id = GetValue('AuthenticationKey', $Provider);
        $Secret = GetValue('AssociationSecret', $Provider);
        if (Gdn::Session()->IsValid()) {
            $User = ArrayTranslate((array)Gdn::Session()->User, array('UserID' => 'UniqueID', 'Name', 'Email', 'PhotoUrl', 'DateOfBirth', 'Gender'));

            // Grab the user's roles.
            $Roles = Gdn::UserModel()->GetRoles(Gdn::Session()->UserID);
            $Roles = array_column($Roles, 'Name');
            $User['Roles'] = '';
            if (is_array($Roles) && sizeof($Roles))
                $User['Roles'] = implode(',', $Roles);

//         $Sfx = 'F';
//         $User['UniqueID'] .= $Sfx;
//         $User['Name'] .= $Sfx;
//         $User['Email'] = str_replace('@', '+'.$Sfx.'@', $User['Email']);
            if (!$User['PhotoUrl'] && function_exists('UserPhotoDefaultUrl')) {
                $User['PhotoUrl'] = Url(UserPhotoDefaultUrl(Gdn::Session()->User), TRUE);
            }
        } else
            $User = array();

        ob_clean();
        writeJsConnect($User, $Sender->Request->Get(), $client_id, $Secret, GetValue('HashType', $Provider, TRUE));
        exit();
    }

    public function rootController_sso_handler($Sender, $Args) {
        $Provider = $Args['DefaultProvider'];
        if (GetValue('AuthenticationSchemeAlias', $Provider) !== 'jsconnect')
            return;

        // The default provider is jsconnect so let's redispatch there.
        $Get = array(
            'client_id' => GetValue('AuthenticationKey', $Provider),
            'target' => GetValue('Target', $Args, '/'));
        $Url = '/entry/jsconnect?'.http_build_query($Get);
        Gdn::Request()->PathAndQuery($Url);
        Gdn::Dispatcher()->Dispatch();
        $Args['Handled'] = TRUE;
    }

    public function settingsController_jsConnect_create($Sender, $Args = array()) {
        $Sender->addJsFile('jsconnect-settings.js', 'plugins/jsconnect');
        $Sender->Permission('Garden.Settings.Manage');
        $Sender->AddSideMenu();

        switch (strtolower(GetValue(0, $Args))) {
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
     * @param SettingsController $sender
     * @param array $Args
     */
    protected function settings_addEdit($sender, $Args) {
        $sender->addJsFile('jsconnect-settings.js', 'plugins/jsconnect');

        $client_id = $sender->Request->Get('client_id');
        Gdn::Locale()->SetTranslation('AuthenticationKey', 'Client ID');
        Gdn::Locale()->SetTranslation('AssociationSecret', 'Secret');
        Gdn::Locale()->SetTranslation('AuthenticateUrl', 'Authentication Url');

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
                $sender->setFormSaved(FALSE);
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
                'Description' => T('The client ID uniquely identifies the site.', 'The client ID uniquely identifies the site. You can generate a new ID with the button at the bottom of this page.')
            ],
            'AssociationSecret' => [
                'LabelCode' => 'Secret',
                'Description' => T('The secret secures the sign in process.', 'The secret secures the sign in process. Do <b>NOT</b> give the secret out to anyone.')
            ],
            'Name' => [
                'LabelCode' => 'Site Name',
                'Description' => T('Enter a short name for the site.', 'Enter a short name for the site. This is displayed on the signin buttons.')
            ],
            'AuthenticateUrl' => [
                'LabelCode' => 'Authentication URL',
                'Description' => T('The location of the JSONP formatted authentication data.')
            ],
            'SignInUrl' => [
                'LabelCode' => 'Sign In URL',
                'Description' => T('The url that users use to sign in.').' '.T('Use {target} to specify a redirect.')
            ],
            'RegisterUrl' => [
                'LabelCode' => 'Registration URL',
                'Description' => T('The url that users use to register for a new account.')
            ],
            'SignOutUrl' => [
                'LabelCode' => 'Sign Out URL',
                'Description' => T('The url that users use to sign out of your site.')
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
                    return '<h2>'.T('Advanced').'</h2>';
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

    public function settings_delete($Sender, $Args) {
        $client_id = $Sender->Request->Get('client_id');
        if ($Sender->Form->authenticatedPostBack()) {
            $Model = new Gdn_AuthenticationProviderModel();
            $Model->Delete(array('AuthenticationKey' => $client_id));
            $Sender->RedirectUrl = url('/settings/jsconnect');
            $Sender->Render('Blank', 'Utility', 'Dashboard');
        }
    }

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
