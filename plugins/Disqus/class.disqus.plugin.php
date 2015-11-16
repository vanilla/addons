<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license Proprietary
 */

// Define the plugin:
$PluginInfo['Disqus'] = array(
    'Name' => 'Disqus Sign In',
    'Description' => 'Users may sign into your site using their Disqus account. <b>You must <a href="https://disqus.com/api/applications/register/">register your application with Disqus</a> for this plugin to work.</b>',
    'Version' => '1.1',
    'RequiredApplications' => array('Vanilla' => '2.0.18'),
    'MobileFriendly' => TRUE,
    'SettingsUrl' => '/dashboard/settings/disqus',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'Author' => "Todd Burry",
    'AuthorEmail' => 'todd@vanillaforums.com',
    'AuthorUrl' => 'http://www.vanillaforums.org/profile/todd'
);

class DisqusPlugin extends Gdn_Plugin {
    /* Properties */

    protected $_Provider = NULL;
    protected $_RedirectUri = NULL;

    /* Methods */

    public function AccessToken() {
        $Token = GetValue('fb_access_token', $_COOKIE);
        return $Token;
    }

    public function Authorize($Query = FALSE) {
        $Uri = $this->AuthorizeUri($Query);
        Redirect($Uri);
    }

    public function Provider() {
        if ($this->_Provider === NULL) {
            $this->_Provider = Gdn_AuthenticationProviderModel::GetProviderByScheme('disqus');
        }

        return $this->_Provider;
    }

    public function AuthorizeUri($Query = FALSE) {
        $Provider = $this->Provider();
        if (!$Provider) {
            return '';
        }

        $RedirectUri = $this->RedirectUri();
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

    public function RedirectUri($NewValue = NULL) {
        if ($NewValue !== NULL)
            $this->_RedirectUri = $NewValue;
        elseif ($this->_RedirectUri === NULL) {
            $RedirectUri = Url('/entry/connect/disqus', TRUE);
            if (strpos($RedirectUri, '=') !== FALSE) {
                $p = strrchr($RedirectUri, '=');
                $Uri = substr($RedirectUri, 0, -strlen($p));
                $p = urlencode(ltrim($p, '='));
                $RedirectUri = $Uri.'='.$p;
            }

            $Path = Gdn::Request()->Path();

            $Target = GetValue('Target', $_GET, $Path ? $Path : '/');
            if (ltrim($Target, '/') == 'entry/signin' || empty($Target))
                $Target = '/';
            $Args = array('Target' => $Target);


            $RedirectUri .= strpos($RedirectUri, '?') === FALSE ? '?' : '&';
            $RedirectUri .= http_build_query($Args);
            $this->_RedirectUri = $RedirectUri;
        }

        return $this->_RedirectUri;
    }

    public function Setup() {
        $Error = '';
        if (!function_exists('curl_init')) {
            $Error = ConcatSep("\n", $Error, 'This plugin requires curl.');
        }
        if ($Error) {
            throw new Gdn_UserException($Error, 400);
        }
    }

    private function _GetButton() {
        $Url = $this->AuthorizeUri();
        return SocialSigninButton('Disqus', $Url, 'icon');
    }

    /* Events */

    /**
     * @param Gdn_Controller $Sender
     */
    public function EntryController_SignIn_Handler($Sender, $Args) {
        $Provider = $this->Provider();
        if (!$Provider) {
            return;
        }

        if (isset($Sender->Data['Methods'])) {
            $Url = $this->AuthorizeUri();

            // Add the Disqus method to the controller.
            $Method = array(
                'Name' => 'Disqus',
                'SignInHtml' => SocialSigninButton('Disqus', $Url, 'button')
            );
            $Sender->Data['Methods'][] = $Method;
        }
    }

    public function Base_SignInIcons_Handler($Sender, $Args) {
        $Provider = $this->Provider();
        if (!$Provider) {
            return;
        }

        echo "\n".$this->_GetButton();
    }

    public function Base_BeforeSignInButton_Handler($Sender, $Args) {
        $Provider = $this->Provider();
        if (!$Provider) {
            return;
        }

        echo "\n".$this->_GetButton();
    }

    public function Base_BeforeSignInLink_Handler($Sender) {
        $Provider = $this->Provider();
        if (!$Provider) {
            return;
        }

        if (!Gdn::Session()->IsValid()) {
            echo "\n".Wrap($this->_GetButton(), 'li', array('class' => 'Connect DisqusConnect'));
        }
    }

    /**
     * @param SettingsController $Sender
     * @param type $Args
     */
    public function SettingsController_Disqus_Create($Sender, $Args) {
        $Sender->Permission('Garden.Settings.Manage');

        if ($Sender->Form->AuthenticatedPostBack()) {
            $Model = new Gdn_AuthenticationProviderModel();
            $Sender->Form->SetFormValue(Gdn_AuthenticationProviderModel::COLUMN_ALIAS, 'disqus');
            $Sender->Form->SetFormValue(Gdn_AuthenticationProviderModel::COLUMN_NAME, 'Disqus');
            $Sender->Form->SetModel($Model);

            if ($Sender->Form->Save(array('PK' => Gdn_AuthenticationProviderModel::COLUMN_ALIAS))) {
                $Sender->InformMessage(T("Your settings have been saved."));
            }
        } else {
            $Provider = (array)$this->Provider();
            $Sender->Form->SetData($Provider);
        }

        $Sender->AddSideMenu();
        $Sender->SetData('Title', sprintf(t('%s Settings'), 'Disqus'));
        $Sender->Render('Settings', '', 'plugins/Disqus');
    }

    /**
     * @param EntryController $Sender
     * @param array $Args
     */
    public function Base_ConnectData_Handler($Sender, $Args) {
        if (GetValue(0, $Args) != 'disqus') {
            return;
        }

        if (isset($_GET['error'])) {
            throw new Gdn_UserException(GetValue('error_description', $_GET, T('There was an error connecting to Disqus')));
        }

        $Provider = $this->Provider();
        if (!$Provider) {
            throw new Gdn_UserException('The Disqus plugin has not been configured correctly.');
        }
        $AppID = $Provider['AuthenticationKey'];
        $Secret = $Provider['AssociationSecret'];
        $Code = GetValue('code', $_GET);
        $Query = '';
        if ($Sender->Request->Get('display')) {
            $Query = 'display='.urlencode($Sender->Request->Get('display'));
        }

        $RedirectUri = ConcatSep('&', $this->RedirectUri(), $Query);
        $Form = $Sender->Form;

        $AccessToken = $Form->GetFormValue('AccessToken'); //Gdn::Session()->Stash('Disqus.AccessToken', NULL, NULL);

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
            curl_setopt($C, CURLOPT_POST, TRUE);
            curl_setopt($C, CURLOPT_POSTFIELDS, $Qs);
            curl_setopt($C, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($C, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($C, CURLOPT_URL, $Url);
            $Contents = curl_exec($C);
            $Info = curl_getinfo($C);

            if (strpos(GetValue('content_type', $Info, ''), '/json') !== FALSE) {
                $Tokens = json_decode($Contents, TRUE);
            } else {
                parse_str($Contents, $Tokens);
            }

            if (GetValue('error', $Tokens)) {
                throw new Gdn_UserException('Disqus returned the following error: '.GetValueR('error.message', $Tokens, 'Unknown error.'), 400);
            }

            $AccessToken = GetValue('access_token', $Tokens);
            $Expires = GetValue('expires_in', $Tokens, NULL);
            $Form->AddHidden('AccessToken', $AccessToken);
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
            curl_setopt($C, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($C, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($C, CURLOPT_URL, $Url);
            $Contents = curl_exec($C);
            $Info = curl_getinfo($C);

            if (strpos(GetValue('content_type', $Info, ''), '/json') !== FALSE) {
                $Profile = json_decode($Contents, TRUE);
                $Profile = $Profile['response'];
            } else {
                throw new Gdn_UserException('There was an error trying to get your profile information from Disqus.');
            }
        } else {
            throw new Gdn_UserException('There was an error trying to get an access token from Disqus.');
        }

        $Form->SetFormValue('UniqueID', GetValue('id', $Profile));
        $Form->SetFormValue('Provider', 'disqus');
        $Form->SetFormValue('ProviderName', 'Disqus');
        $Form->SetFormValue('FullName', GetValue('name', $Profile));
        $Form->SetFormValue('Name', GetValue('username', $Profile));
        $Form->SetFormValue('Photo', GetValueR('avatar.permalink', $Profile));
        $Sender->SetData('Verified', TRUE);
    }
}
