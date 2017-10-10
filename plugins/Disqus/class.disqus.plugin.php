<?php
/**
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license GNU GPLv2
 */

/**
 * Class DisqusPlugin.
 */
class DisqusPlugin extends Gdn_Plugin {

    /** @var null  */
    protected $_Provider = null;

    /**
     *
     *
     * @return mixed
     */
    public function accessToken() {
        $token = val('fb_access_token', $_COOKIE);
        return $token;
    }

    /**
     *
     *
     * @param bool $query
     */
    public function authorize($query = false) {
        $uri = $this->authorizeUri($query);
        redirectTo($uri, 302, false);
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
     * @param bool $query
     * @return string
     */
    public function authorizeUri($query = FALSE) {
        $provider = $this->provider();
        if (!$provider) {
            return '';
        }

        $qs = [
            'client_id' => $provider['AuthenticationKey'],
            'scope' => 'read',
            'response_type' => 'code',
        ];

        $signinHref = 'https://disqus.com/api/oauth/2.0/authorize/?'.http_build_query($qs);

        return $signinHref;
    }

    /**
     *
     *
     * @throws Gdn_UserException
     */
    public function setup() {
        $error = '';
        if (!function_exists('curl_init')) {
            $error = concatSep("\n", $error, 'This plugin requires curl.');
        }
        if ($error) {
            throw new Gdn_UserException($error, 400);
        }
    }

    /**
     *
     *
     * @return string
     */
    private function _getButton() {
        $url = $this->authorizeUri();
        return socialSigninButton('Disqus', $url, 'icon');
    }

    /**
     * @param Gdn_Controller $sender
     */
    public function entryController_signIn_handler($sender, $args) {
        $provider = $this->provider();
        if (!$provider) {
            return;
        }

        if (isset($sender->Data['Methods'])) {
            $url = $this->authorizeUri();

            // Add the Disqus method to the controller.
            $method = [
                'Name' => 'Disqus',
                'SignInHtml' => socialSigninButton('Disqus', $url, 'button')
            ];
            $sender->Data['Methods'][] = $method;
        }
    }

    /**
     *
     *
     * @param $sender
     * @param $args
     */
    public function base_signInIcons_handler($sender, $args) {
        $provider = $this->provider();
        if (!$provider) {
            return;
        }

        echo "\n".$this->_getButton();
    }

    /**
     *
     *
     * @param $sender
     * @param $args
     */
    public function base_beforeSignInButton_handler($sender, $args) {
        $provider = $this->provider();
        if (!$provider) {
            return;
        }

        echo "\n".$this->_getButton();
    }

    /**
     *
     *
     * @param $sender
     */
    public function base_beforeSignInLink_handler($sender) {
        $provider = $this->provider();
        if (!$provider) {
            return;
        }

        if (!Gdn::session()->isValid()) {
            echo "\n".wrap($this->_getButton(), 'li', ['class' => 'Connect DisqusConnect']);
        }
    }

    /**
     *
     *
     * @param SettingsController $sender
     * @param type $args
     */
    public function settingsController_disqus_create($sender, $args) {
        $sender->permission('Garden.Settings.Manage');

        if ($sender->Form->authenticatedPostBack()) {
            $model = new Gdn_AuthenticationProviderModel();
            $sender->Form->setFormValue(Gdn_AuthenticationProviderModel::COLUMN_ALIAS, 'disqus');
            $sender->Form->setFormValue(Gdn_AuthenticationProviderModel::COLUMN_NAME, 'Disqus');
            $sender->Form->setModel($model);

            if ($sender->Form->save(['PK' => Gdn_AuthenticationProviderModel::COLUMN_ALIAS])) {
                $sender->informMessage(t("Your settings have been saved."));
            }
        } else {
            $provider = (array)$this->provider();
            $sender->Form->setData($provider);
        }

        $sender->addSideMenu();
        $sender->setData('Title', sprintf(t('%s Settings'), 'Disqus'));
        $sender->render('Settings', '', 'plugins/Disqus');
    }

    /**
     *
     *
     * @param EntryController $sender
     * @param array $args
     */
    public function base_connectData_handler($sender, $args) {
        if (val(0, $args) != 'disqus') {
            return;
        }

        if (isset($_GET['error'])) {
            throw new Gdn_UserException(val('error_description', $_GET, t('There was an error connecting to Disqus')));
        }

        $provider = $this->provider();
        if (!$provider) {
            throw new Gdn_UserException('The Disqus plugin has not been configured correctly.');
        }
        $appID = $provider['AuthenticationKey'];
        $secret = $provider['AssociationSecret'];
        $code = val('code', $_GET);
        $query = '';
        if ($sender->Request->get('display')) {
            $query = 'display='.urlencode($sender->Request->get('display'));
        }

        $form = $sender->Form;

        $accessToken = $form->getFormValue('AccessToken'); //Gdn::session()->stash('Disqus.AccessToken', NULL, NULL);

        // Get the access token.
        if ($code && !$accessToken) {
            // Exchange the token for an access token.
            $qs = [
                'grant_type' => 'authorization_code',
                'client_id' => $appID,
                'client_secret' => $secret,
                'code' => $code,
                'redirect_uri' => url('/entry/connect/disqus', true),
            ];

            $url = 'https://disqus.com/api/oauth/2.0/access_token/'; //.http_build_query($Qs);

            // Get the redirect URI.
            $c = curl_init();
            curl_setopt($c, CURLOPT_POST, true);
            curl_setopt($c, CURLOPT_POSTFIELDS, $qs);
            curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($c, CURLOPT_URL, $url);
            $contents = curl_exec($c);
            $info = curl_getinfo($c);

            if (strpos(val('content_type', $info, ''), '/json') !== false) {
                $tokens = json_decode($contents, true);
            } else {
                parse_str($contents, $tokens);
            }

            if (val('error', $tokens)) {
                throw new Gdn_UserException('Disqus returned the following error: '.valr('error.message', $tokens, 'Unknown error.'), 400);
            }

            $accessToken = val('access_token', $tokens);
            $expires = val('expires_in', $tokens, null);
            $form->addHidden('AccessToken', $accessToken);
        }

        if ($accessToken) {
            // Grab the user's profile.
            $qs = [
                'access_token' => $accessToken,
                'api_key' => $appID,
                'api_secret' => $secret
            ];

            $url = 'https://disqus.com/api/3.0/users/details.json?'.http_build_query($qs);
            $c = curl_init();
            curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($c, CURLOPT_URL, $url);
            $contents = curl_exec($c);
            $info = curl_getinfo($c);

            if (strpos(val('content_type', $info, ''), '/json') !== false) {
                $profile = json_decode($contents, true);
                $profile = $profile['response'];
            } else {
                throw new Gdn_UserException('There was an error trying to get your profile information from Disqus.');
            }
        } else {
            throw new Gdn_UserException('There was an error trying to get an access token from Disqus.');
        }

        $form->setFormValue('UniqueID', val('id', $profile));
        $form->setFormValue('Provider', 'disqus');
        $form->setFormValue('ProviderName', 'Disqus');
        $form->setFormValue('FullName', val('name', $profile));
        $form->setFormValue('Name', val('username', $profile));
        $form->setFormValue('Photo', valr('avatar.permalink', $profile));
        $sender->setData('Verified', true);
    }
}
