<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */

// Define the plugin:
$PluginInfo['GooglePlus'] = array(
   'Name' => 'Google+',
   'Description' => 'Users may sign into your site using their Google Plus account.',
   'Version' => '1.0.2',
   'RequiredApplications' => array('Vanilla' => '2.1a'),
   'MobileFriendly' => TRUE,
   'Author' => 'Todd Burry',
   'AuthorEmail' => 'todd@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.org/profile/todd',
   'SettingsUrl' => '/dashboard/social/googleplus',
   'SettingsPermission' => 'Garden.Settings.Manage',
   'Hidden' => TRUE,
   'SocialConnect' => TRUE,
   'RequiresRegistration' => FALSE
);

class GooglePlusPlugin extends Gdn_Plugin {
   /// Properties ///
   const ProviderKey = 'GooglePlus';
   const APIUrl = 'https://www.googleapis.com/oauth2/v1';
   
   /// Methods ///
   
   protected $_AccessToken = NULL;
   
   public function AccessToken($NewValue = FALSE) {
      if (!$this->IsConfigured()) 
         return FALSE;
      
      if ($NewValue !== FALSE)
         $this->_AccessToken = $NewValue;
      
      if ($this->_AccessToken === NULL) {
         $this->_AccessToken = GetValueR(self::ProviderKey.'.AccessToken', Gdn::Session()->User->Attributes);
      }
      
      return $this->_AccessToken;
   }
   
   public function API($Path, $Post = array()) {
      $Url = self::APIUrl.'/'.ltrim($Path, '/');
      if (strpos($Url, '?') === FALSE)
         $Url .= '?';
      else
         $Url .= '&';
      $Url .= 'access_token='.urlencode($this->AccessToken());
      
      $Result = $this->Curl($Url, empty($Post) ? 'GET' : 'POST', $Post);
      return $Result;
   }
   
   public function AuthorizeUri($State = FALSE) {
      $Url = 'https://accounts.google.com/o/oauth2/auth';
      $Get = array(
          'response_type' => 'code',
          'client_id' => C('Plugins.GooglePlus.ClientID'),
          'redirect_uri' => Url('/entry/googleplus', TRUE),
          'scope' => 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email'
          );
      
      if (is_array($State)) {
         $Get['state'] = http_build_query($State);
      }
      
      return $Url.'?'.http_build_query($Get);
   }
   
   public function GetAccessToken($Code) {
      $Url = 'https://accounts.google.com/o/oauth2/token';
      $Post = array(
          'code' => $Code,
          'client_id' => C('Plugins.GooglePlus.ClientID'),
          'client_secret' => C('Plugins.GooglePlus.Secret'),
          'redirect_uri' => Url('/entry/googleplus', TRUE),
          'grant_type' => 'authorization_code'
          );
      
      $Data = self::Curl($Url, 'POST', $Post);
      $AccessToken = $Data['access_token'];
      return $AccessToken;
   }
   
   public function IsConfigured() {
      $Result = C('Plugins.GooglePlus.ConsumerKey') && C('Plugins.GooglePlus.Secret');
      return $Result;
   }
   
   public function SocialSharing() {
      return C('Plugins.GooglePlus.SocialSharing', TRUE);
   }
   
   public function SocialReactions() {
      return C('Plugins.GooglePlus.SocialReactions', TRUE);
   }
   
   public static function Curl($Url, $Method = 'GET', $Data = array()) {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_HEADER, false);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_URL, $Url);

      if ($Method == 'POST') {
         curl_setopt($ch, CURLOPT_POST, true);
         curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($Data)); 
         Trace("  POST $Url");
      } else {
         Trace("  GET  $Url");
      }

      $Response = curl_exec($ch);

      $HttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $ContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
      curl_close($ch);
      
      $Result = @json_decode($Response, TRUE);
      if (!$Result) {
         $Result = $Response;
      }
      
      if ($HttpCode != 200) {
         $Error = GetValue('error', $Result, $Response);
         
         throw new Gdn_UserException($Error, $HttpCode);
      }

      return $Result;
   }
   
   public function Setup() {
      $this->Structure();
   }
   
   public function Structure() {
      // Save the facebook provider type.
      Gdn::SQL()->Replace('UserAuthenticationProvider',
         array('AuthenticationSchemeAlias' => 'Google+', 'URL' => '...', 'AssociationSecret' => '...', 'AssociationHashMethod' => '...'),
         array('AuthenticationKey' => self::ProviderKey), TRUE);
   }
   
   /// Event Handlers ///
   
   /**
    * Add 'Google+' option to the row.
    */
   public function Base_AfterReactions_Handler($Sender, $Args) {
      echo Gdn_Theme::BulletItem('Share');
//      if ($this->AccessToken()) {
//         $Url = Url("post/twitter/{$Args['RecordType']}?id={$Args['RecordID']}", TRUE);
//         $CssClass = 'ReactButton Hijack';
//      } else {
         $Url = Url("post/googleplus/{$Args['RecordType']}?id={$Args['RecordID']}", TRUE);
         $CssClass = 'ReactButton PopupWindow';
//      }
      
      echo ' '.Anchor(Sprite('ReactGooglePlus', 'ReactSprite'), $Url, $CssClass).' ';
   }
   
   public function Base_GetConnections_Handler($Sender, $Args) {
      $Profile = GetValueR('User.Attributes.'.self::ProviderKey.'.Profile', $Args);
      
      $Sender->Data['Connections'][self::ProviderKey] = array(
         'Icon' => $this->GetWebResource('icon.png'),
         'Name' => 'Google+',
         'ProviderKey' => self::ProviderKey,
         'ConnectUrl' => $this->AuthorizeUri(array('r' => 'profile', 'uid' => Gdn::Session()->UserID)),
         'Profile' => array(
            'Name' => GetValue('name', $Profile),
            'Photo' => GetValue('picture', $Profile)
            )
       );
   }
   
   
   /**
    * 
    * @param EntryController $Sender
    * @param type $Code
    * @param type $State
    * @throws Gdn_UserException
    */
   public function EntryController_GooglePlus_Create($Sender, $Code = FALSE, $State = FALSE) {
      if ($Error = $Sender->Request->Get('error')) {
         throw new Gdn_UserException($Error);
      }
      
      // Get an access token.
      $AccessToken = $this->GetAccessToken($Code);
      $this->AccessToken($AccessToken);
      
      // Get the user's information.
      $Profile = $this->API('/userinfo');
      
      if ($State) {
         parse_str($State, $State);
      } else {
         $State = array('r' => 'entry', 'uid' => NULL);
      }
      
      switch ($State['r']) {
         case 'profile':
            // This is a connect request from the user's profile.
            
            $User = Gdn::UserModel()->GetID($State['uid']);
            if (!$User) {
               decho($State);
               die();
               throw NotFoundException('User');
            }
            // Save the authentication.
            Gdn::UserModel()->SaveAuthentication(array(
               'UserID' => $User->UserID,
               'Provider' => self::ProviderKey,
               'UniqueID' => $Profile['id']));

            // Save the information as attributes.
            $Attributes = array(
                'AccessToken' => $AccessToken,
                'Profile' => $Profile
            );
            Gdn::UserModel()->SaveAttribute($User->UserID, self::ProviderKey, $Attributes);

            $this->EventArguments['Provider'] = self::ProviderKey;
            $this->EventArguments['User'] = $Sender->User;
            $this->FireEvent('AfterConnection');

            Redirect(UserUrl($User, '', 'connections'));
            break;
         case 'entry':
         default:
            break;
      }
   }
   
   /**
    * 
    * @param PostController $Sender
    * @param type $RecordType
    * @param type $ID
    * @throws type
    */
   public function PostController_GooglePlus_Create($Sender, $RecordType, $ID) {
      $Row = GetRecord($RecordType, $ID);
      if ($Row) {
         $Message = SliceParagraph(Gdn_Format::PlainText($Row['Body'], $Row['Format']), 160);
         
         $Get = array(
            'url' => $Row['ShareUrl']
          );

         $Url = 'https://plus.google.com/share?'.http_build_query($Get);
         Redirect($Url);
      }
      
      $Sender->Render('Blank', 'Utility', 'Dashboard');
   }
   
   public function SocialController_GooglePlus_Create($Sender, $Args) {
      $Sender->Permission('Garden.Settings.Manage');

      $Conf = new ConfigurationModule($Sender);
      $Conf->Initialize(array(
          'Plugins.GooglePlus.ClientID',
          'Plugins.GooglePlus.Secret',
          'Plugins.GooglePlus.SocialReactions'  => $this->SocialReactions(),
          'Plugins.GooglePlus.SocialSharing'    => $this->SocialSharing()
      ));

      $Sender->AddSideMenu('dashboard/social');
      $Sender->SetData('Title', sprintf(T('%s Settings'), 'Google+'));
      $Sender->ConfigurationModule = $Conf;
      $Conf->RenderAll();
   }
}