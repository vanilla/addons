<?php
/**
 * @copyright 2008-2015 Vanilla Forums Inc.
 * @license GNU GPLv2
 */

$PluginInfo['Disqus'] = array(
    'Name' => 'Disqus Sign In',
    'Description' => 'Users may sign into your site using their Disqus account. <b>You must <a href="https://disqus.com/api/applications/register/">register your application with Disqus</a> for this plugin to work.</b>',
    'Version' => '1.2',
    'RequiredApplications' => array('Vanilla' => '2.2'),
    'MobileFriendly' => true,
    'SettingsUrl' => '/dashboard/settings/disqus',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'Author' => "Todd Burry",
    'AuthorEmail' => 'todd@vanillaforums.com',
    'AuthorUrl' => 'http://www.vanillaforums.org/profile/todd'
);

/**
 * Class DisqusPlugin.
 */
class DisqusPlugin extends Gdn_Plugin {

    /** @var null  */
    protected $_Provider = null;

    /** @var null  */
    protected $_RedirectUri = null;

    /**
     *
     *
     * @return mixed
     */
    public function accessToken() {
        $Token = val('fb_access_token', $_COOKIE);
        return $Token;
    }

    /**
     *
     *
     * @param bool $Query
     */
    public function authorize($Query = false) {
        $Uri = $this->authorizeUri($Query);
        redirect($Uri);
    }

    /**
     *
     *
     * @return array|bool|null|stdClass
     */
    public function provider() {
        if ($this->_Provider === null) {
            $this->_Provider = Gdn_AuthenticationProviderModel::getProviderByScheme('disqus');
        }

        return $this->_Provider;
    }

    /**
     *
     *
     * @param bool $Query
     * @return string
     */
    public function authorizeUri($Query = FALSE) {
        $Provider = $this->provider();
        if (!$Provider) {
            return '';
        }

        $RedirectUri = $this->redirectUri();
        if ($Query) {
            $RedirectUri .= '&'.$Query;
        }

        $Qs = array(
            'client_id' => $Provider['AuthenticationKey'],
            'scope' => 'read',
            'response_type' => 'code',
            'redirect_uri' => $RedirectUri);

        $SigninHref = 'https://disqus.com/api/oauth/2.0/authorize/?'.http_build_query($Qs);

        return $SigninHref;
    }

    /**
     *
     *
     * @param null $NewValue
     * @return null|string
     */
    public function redirectUri($NewValue = null) {
        if ($NewValue !== null)
            $this->_RedirectUri = $NewValue;
        elseif ($this->_RedirectUri === null) {
            $RedirectUri = Url('/entry/connect/disqus', true);
            if (strpos($RedirectUri, '=') !== false) {
                $p = strrchr($RedirectUri, '=');
                $Uri = substr($RedirectUri, 0, -strlen($p));
                $p = urlencode(ltrim($p, '='));
                $RedirectUri = $Uri.'='.$p;
            }

            $Path = Gdn::request()->path();

            $Target = val('Target', $_GET, $Path ? $Path : '/');
            if (ltrim($Target, '/') == 'entry/signin' || empty($Target))
                $Target = '/';
            $Args = array('Target' => $Target);


            $RedirectUri .= strpos($RedirectUri, '?') === false ? '?' : '&';
            $RedirectUri .= http_build_query($Args);
            $this->_RedirectUri = $RedirectUri;
        }

        return $this->_RedirectUri;
    }

    /**
     *
     *
     * @throws Gdn_UserException
     */
    public function setup() {
        $Error = '';
        if (!function_exists('curl_init')) {
            $Error = concatSep("\n", $Error, 'This plugin requires curl.');
        }
        if ($Error) {
            throw new Gdn_UserException($Error, 400);
        }
    }

    /**
     *
     *
     * @return string
     */
    private function _getButton() {
        $Url = $this->authorizeUri();
        return socialSigninButton('Disqus', $Url, 'icon');
    }

    /**
     * @param Gdn_Controller $Sender
     */
    public function entryController_signIn_handler($Sender, $Args) {
        $Provider = $this->provider();
        if (!$Provider) {
            return;
        }

        if (isset($Sender->Data['Methods'])) {
            $Url = $this->authorizeUri();

            // Add the Disqus method to the controller.
            $Method = array(
                'Name' => 'Disqus',
                'SignInHtml' => socialSigninButton('Disqus', $Url, 'button')
            );
            $Sender->Data['Methods'][] = $Method;
        }
    }

    /**
     *
     *
     * @param $Sender
     * @param $Args
     */
    public function base_signInIcons_handler($Sender, $Args) {
        $Provider = $this->provider();
        if (!$Provider) {
            return;
        }

        echo "\n".$this->_getButton();
    }

    /**
     *
     *
     * @param $Sender
     * @param $Args
     */
    public function base_beforeSignInButton_handler($Sender, $Args) {
        $Provider = $this->provider();
        if (!$Provider) {
            return;
        }

        echo "\n".$this->_getButton();
    }

    /**
     *
     *
     * @param $Sender
     */
    public function base_beforeSignInLink_handler($Sender) {
        $Provider = $this->provider();
        if (!$Provider) {
            return;
        }

        if (!Gdn::session()->isValid()) {
            echo "\n".wrap($this->_getButton(), 'li', array('class' => 'Connect DisqusConnect'));
        }
    }

    /**
     *
     *
     * @param SettingsController $Sender
     * @param type $Args
     */
    public function settingsController_disqus_create($Sender, $Args) {
        $Sender->permission('Garden.Settings.Manage');

        if ($Sender->Form->authenticatedPostBack()) {
            $Model = new Gdn_AuthenticationProviderModel();
            $Sender->Form->setFormValue(Gdn_AuthenticationProviderModel::COLUMN_ALIAS, 'disqus');
            $Sender->Form->setFormValue(Gdn_AuthenticationProviderModel::COLUMN_NAME, 'Disqus');
            $Sender->Form->setModel($Model);

            if ($Sender->Form->save(array('PK' => Gdn_AuthenticationProviderModel::COLUMN_ALIAS))) {
                $Sender->informMessage(t("Your settings have been saved."));
            }
        } else {
            $Provider = (array)$this->provider();
            $Sender->Form->setData($Provider);
        }

        $Sender->addSideMenu();
        $Sender->setData('Title', sprintf(t('%s Settings'), 'Disqus'));
        $Sender->render('Settings', '', 'plugins/Disqus');
    }

    /**
     *
     *
     * @param EntryController $Sender
     * @param array $Args
     */
    public function base_connectData_handler($Sender, $Args) {
        if (val(0, $Args) != 'disqus') {
            return;
        }

        if (isset($_GET['error'])) {
            throw new Gdn_UserException(val('error_description', $_GET, t('There was an error connecting to Disqus')));
        }

        $Provider = $this->provider();
        if (!$Provider) {
            throw new Gdn_UserException('The Disqus plugin has not been configured correctly.');
        }
        $AppID = $Provider['AuthenticationKey'];
        $Secret = $Provider['AssociationSecret'];
        $Code = val('code', $_GET);
        $Query = '';
        if ($Sender->Request->get('display')) {
            $Query = 'display='.urlencode($Sender->Request->get('display'));
        }

        $RedirectUri = concatSep('&', $this->redirectUri(), $Query);
        $Form = $Sender->Form;

        $AccessToken = $Form->getFormValue('AccessToken'); //Gdn::Session()->Stash('Disqus.AccessToken', NULL, NULL);

        // Get the access token.
        if ($Code && !$AccessToken) {
            // Exchange the token for an access token.
            $Qs = array(
                'grant_type' => 'authorization_code',
                'client_id' => $AppID,
                'client_secret' => $Secret,
                'redirect_uri' => $RedirectUri,
                'code' => $Code
            );

            $Url = 'https://disqus.com/api/oauth/2.0/access_token/'; //.http_build_query($Qs);

            // Get the redirect URI.
            $C = curl_init();
            curl_setopt($C, CURLOPT_POST, true);
            curl_setopt($C, CURLOPT_POSTFIELDS, $Qs);
            curl_setopt($C, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($C, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($C, CURLOPT_URL, $Url);
            $Contents = curl_exec($C);
            $Info = curl_getinfo($C);

            if (strpos(val('content_type', $Info, ''), '/json') !== false) {
                $Tokens = json_decode($Contents, true);
            } else {
                parse_str($Contents, $Tokens);
            }

            if (val('error', $Tokens)) {
                throw new Gdn_UserException('Disqus returned the following error: '.valr('error.message', $Tokens, 'Unknown error.'), 400);
            }

            $AccessToken = val('access_token', $Tokens);
            $Expires = val('expires_in', $Tokens, null);
            $Form->addHidden('AccessToken', $AccessToken);
        }

        if ($AccessToken) {
            // Grab the user's profile.
            $Qs = array(
                'access_token' => $AccessToken,
                'api_key' => $AppID,
                'api_secret' => $Secret
            );

            $Url = 'https://disqus.com/api/3.0/users/details.json?'.http_build_query($Qs);
            $C = curl_init();
            curl_setopt($C, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($C, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($C, CURLOPT_URL, $Url);
            $Contents = curl_exec($C);
            $Info = curl_getinfo($C);

            if (strpos(val('content_type', $Info, ''), '/json') !== false) {
                $Profile = json_decode($Contents, true);
                $Profile = $Profile['response'];
            } else {
                throw new Gdn_UserException('There was an error trying to get your profile information from Disqus.');
            }
        } else {
            throw new Gdn_UserException('There was an error trying to get an access token from Disqus.');
        }

        $Form->setFormValue('UniqueID', val('id', $Profile));
        $Form->setFormValue('Provider', 'disqus');
        $Form->setFormValue('ProviderName', 'Disqus');
        $Form->setFormValue('FullName', val('name', $Profile));
        $Form->setFormValue('Name', val('username', $Profile));
        $Form->setFormValue('Photo', valr('avatar.permalink', $Profile));
        $Sender->setData('Verified', true);
    }
}
