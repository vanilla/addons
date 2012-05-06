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
   'Version' => '1.1.4',
   'RequiredApplications' => array('Vanilla' => '2.0.18b1'),
   'MobileFriendly' => TRUE,
   'Author' => 'Todd Burry',
   'AuthorEmail' => 'todd@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.org/profile/todd',
   'SettingsUrl' => '/dashboard/settings/jsconnect',
   'SettingsPermission' => 'Garden.Settings.Manage',
);

class JsConnectPlugin extends Gdn_Plugin {
   /// PROPERTIES ///
   
   /// METHODS ///

   public static function AllConnectButtons($Options = array()) {
      $Result = '';

      $Providers = self::GetAllProviders();
      foreach ($Providers as $Provider) {
         $Result .= self::ConnectButton($Provider, $Options);
      }
      return $Result;
   }
   
   public static function ConnectButton($Provider, $Options = array()) {
      if (!is_array($Provider))
         $Provider = self::GetProvider($Provider);
      
      $Url = htmlspecialchars(self::ConnectUrl($Provider));

      $Data = $Provider;

      $Target = Gdn::Request()->Get('Target');
      if (!$Target)
         $Target = '/'.ltrim(Gdn::Request()->Path());

      if (StringBeginsWith($Target, '/entry/signin'))
         $Target = '/';

      $ConnectQuery = array('client_id' => $Provider['AuthenticationKey'], 'Target' => $Target);
      $Data['Target'] = urlencode(Url('entry/jsconnect', TRUE).'?'.  http_build_query($ConnectQuery));

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
         $ConnectLabel = '<span class="Username"></span><div class="ConnectLabel">'.sprintf(T('Sign In with %s'), $Provider['Name']).'</div>';
      }

      $Result = '<div style="display: none" class="JsConnect-Container ConnectButton Small UserInfo" rel="'.$Url.'">
         <div class="JsConnect-Guest">'.Anchor(sprintf(T('Sign In with %s'), $Provider['Name']), $SignInUrl, 'Button SignInLink').$RegisterLink.'</div>
         <div class="JsConnect-Connect"><a class="'.$PopupWindow.' NoMSIE ConnectLink" popupHeight="300" popupWidth="600">'.Img('http://cdn.vanillaforums.com/images/usericon_50.png', array('class' => 'ProfilePhotoSmall UserPhoto')).
            $ConnectLabel.
         '</a></div>
      </div>';
      
      return $Result;
   }
   
   public static function ConnectUrl($Provider, $Secure = FALSE, $Callback = TRUE) {
      if (!is_array($Provider))
         $Provider = self::GetProvider($Provider);
      
      if (!is_array($Provider))
         return FALSE;
      
      $Url = $Provider['AuthenticateUrl'];
      $Query = array('client_id' => $Provider['AuthenticationKey']);
      
      if ($Secure) {
         include_once dirname(__FILE__).'/functions.jsconnect.php';
         $Query['timestamp'] = JsTimestamp();
         $Query['signature'] = JsHash(($Query['timestamp']).$Provider['AssociationSecret'], GetValue('HashType', $Provider));
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

   public static function GetAllProviders() {
      return self::GetProvider();
   }
   
   public static function GetProvider($client_id = NULL) {
      if ($client_id !== NULL) {
         $Where = array('AuthenticationKey' => $client_id);
      } else {
         $Where = array('AuthenticationSchemeAlias' => 'jsconnect');
      }
      
      $Result = Gdn::SQL()->GetWhere('UserAuthenticationProvider', $Where)->ResultArray();
      foreach ($Result as &$Row) {
         $Attributes = unserialize($Row['Attributes']);
         if (is_array($Attributes))
            $Row = array_merge($Attributes, $Row);
      }
      
      if ($client_id)
         return GetValue(0, $Result, FALSE);
      else
         return $Result;
      
      return $Result;
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
   
   public function Base_BeforeSignInButton_Handler($Sender, $Args) {	
      $Providers = self::GetAllProviders();
      foreach ($Providers as $Provider) {
         echo "\n".self::ConnectButton($Provider);
      }
	}
   
   public function Base_BeforeSignInLink_Handler($Sender) {
      $Providers = self::GetAllProviders();
      foreach ($Providers as $Provider) {
         echo "\n".Wrap(self::ConnectButton($Provider, array('NoRegister' => TRUE, 'NoConnectLabel' => TRUE)), 'li', array('class' => 'Connect jsConnect'));
      }
	}

   /**
    *
    * @param EntryController $Sender
    * @param array $Args
    */
   public function Base_ConnectData_Handler($Sender, $Args) {
      if (GetValue(0, $Args) != 'jsconnect')
         return;

      include_once dirname(__FILE__).'/functions.jsconnect.php';
      
      $Form = $Sender->Form;
      parse_str($Form->GetFormValue('JsConnect'), $JsData);

      // Make sure the data is valid.
      $client_id = GetValue('client_id', $JsData, GetValue('clientid', $JsData, $Sender->Request->Get('client_id'), TRUE), TRUE);
      $Signature = GetValue('signature', $JsData, FALSE, TRUE);
      $String = GetValue('string', $JsData, FALSE, TRUE); // debugging

      if (!$client_id)
         throw new Gdn_UserException(sprintf(T('ValidateRequired'), 'client_id'), 400);
      $Provider = self::GetProvider($client_id);
      if (!$Provider)
         throw new Gdn_UserException(sprintf(T('Unknown client: %s.'), $client_id), 400);
      
      if (!GetValue('TestMode', $Provider)) {
         if (!$Signature)
            throw new Gdn_UserException(sprintf(T('ValidateRequired'), 'signature'), 400);      

         // Validate the signature.
         $CalculatedSignature = SignJsConnect($JsData, $client_id, GetValue('AssociationSecret', $Provider), GetValue('HashType', $Provider, 'md5'));
         if ($CalculatedSignature != $Signature)
            throw new Gdn_UserException(T("Signature invalid."), 400);
      }

      $Form->AddHidden('JsConnect', $JsData);
      $Form->SetFormValue('UniqueID', GetValue('uniqueid', $JsData));
      $Form->SetFormValue('Provider', $client_id);
      $Form->SetFormValue('ProviderName', GetValue('Name', $Provider, ''));
      $Form->SetFormValue('Name', GetValue('name', $JsData));
      $Form->SetFormValue('Email', GetValue('email', $JsData));
      $Form->SetFormValue('Photo', GetValue('photourl', $JsData, ''));
      $Form->SetFormValue('Roles', GetValue('roles', $JsData, ''));
      
      $Sender->SetData('Verified', TRUE);
   }

   public function Base_GetAppSettingsMenuItems_Handler(&$Sender) {
      $Menu = $Sender->EventArguments['SideMenu'];
      $Menu->AddItem('Users', T('Users'));
      $Menu->AddLink('Users', 'jsConnect', 'settings/jsconnect', 'Garden.Settings.Manage');
   }
   
   public function Base_Render_Before($Sender, $Args) {
      if (!Gdn::Session()->UserID) {
         $Sender->AddJSFile('jsconnect.js', 'plugins/jsconnect');
         $Sender->AddCssFile('jsconnect.css', 'plugins/jsconnect');
      }
   }

   /**
    *
    * @param EntryController $Sender
    * @param array $Args
    */
   public function EntryController_JsConnect_Create($Sender, $Args = array()) {
      if ($Arg = GetValue(0, $Args)) {
         if ($Arg == 'guest') {
//            Redirect('/');
            $Sender->AddDefinition('CheckPopup', TRUE);
            $Sender->RedirectUrl = '/';
            $Sender->Render('JsConnect', '', 'plugins/jsconnect');
         } else {
            parse_str($Sender->Form->GetFormValue('JsConnect'), $JsData);

            $Error = GetValue('error', $JsData);
            $Message = GetValue('message', $JsData);

            $Sender->Form->AddError($Message ? htmlspecialchars($Message) : htmlspecialchars($Error));
            $Sender->SetData('Title', T('Error'));
            $Sender->Render('JsConnect_Error', '', 'plugins/jsconnect');
         }
      } else {
         $client_id = $Sender->SetData('client_id', $Sender->Request->Get('client_id', 0));
         $Provider = self::GetProvider($client_id);

         if (empty($Provider))
            throw NotFoundException('Provider');
         
         $Get = ArrayTranslate($Sender->Request->Get(), array('client_id', 'display'));

         $Sender->AddDefinition('JsAuthenticateUrl', self::ConnectUrl($Provider, TRUE));
         $Sender->AddJsFile('jsconnect.js', 'plugins/jsconnect');
         $Sender->SetData('Title', T('Connecting...'));
         $Sender->Form->Action = Url('/entry/connect/jsconnect?'.  http_build_query($Get));
         $Sender->Form->AddHidden('JsConnect', '');
         $Sender->Form->AddHidden('Target', $Sender->Request->Get('Target', '/'));

         $Sender->MasterView = 'empty';
         $Sender->Render('JsConnect', '', 'plugins/jsconnect');
      }
   }

   /**
    *
    * @param Gdn_Controller $Sender
    */
   public function EntryController_SignIn_Handler($Sender, $Args) {
      $Providers = self::GetAllProviders();

      foreach ($Providers as $Provider) {
         $Method = array(
            'Name' => $Provider['Name'],
            'SignInHtml' => self::ConnectButton($Provider)
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
   public function ProfileController_JsConnect_Create($Sender, $Args = array()) {
      include_once dirname(__FILE__).'/functions.jsconnect.php';
      
      $client_id = $Sender->Request->Get('client_id', 0);

      $Provider = self::GetProvider($client_id);
      
      $client_id = GetValue('AuthenticationKey', $Provider);
      $Secret = GetValue('AssociationSecret', $Provider);
      if (Gdn::Session()->IsValid()) {
         $User = ArrayTranslate((array)Gdn::Session()->User, array('UserID' => 'UniqueID', 'Name', 'Email', 'PhotoUrl', 'DateOfBirth', 'Gender'));
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
      WriteJsConnect($User, $Sender->Request->Get(), $client_id, $Secret, GetValue('HashType', $Provider, TRUE));
      exit();
   }
   
   public function SettingsController_JsConnect_Create($Sender, $Args = array()) {
      $Sender->Permission('Garden.Settings.Manage');
      $Sender->AddSideMenu();

      switch (strtolower(GetValue(0, $Args))) {
         case 'addedit':
            $this->Settings_AddEdit($Sender, $Args);
            break;
         case 'delete':
            $this->Settings_Delete($Sender, $Args);
            break;
         default:
            $this->Settings_Index($Sender, $Args);
            break;
      }
   }

   /**
    * @param Gdn_Controller $Sender
    * @param array $Args
    */
   protected function Settings_AddEdit($Sender, $Args) {
      $client_id = $Sender->Request->Get('client_id');
      Gdn::Locale()->SetTranslation('AuthenticationKey', 'Client ID');
      Gdn::Locale()->SetTranslation('AssociationSecret', 'Secret');
      Gdn::Locale()->SetTranslation('AuthenticateUrl', 'Authentication Url');
      
      $Form = new Gdn_Form();
      $Sender->Form = $Form;

      if ($Form->AuthenticatedPostBack()) {
         if ($Form->GetFormValue('Generate') || $Sender->Request->Post('Generate')) {
            $Form->SetFormValue('AuthenticationKey', mt_rand());
            $Form->SetFormValue('AssociationSecret', md5(mt_rand()));

            $Sender->SetFormSaved(FALSE);
         } else {
            $Form->ValidateRule('AuthenticationKey', 'ValidateRequired');
            $Form->ValidateRule('AssociationSecret', 'ValidateRequired');
            $Form->ValidateRule('AuthenticateUrl', 'ValidateRequired');


            $Values = $Form->FormValues();

            $Values = ArrayTranslate($Values, array('Name', 'AuthenticationKey', 'URL', 'AssociationSecret', 'AuthenticateUrl', 'SignInUrl', 'RegisterUrl'));
            $Values['AuthenticationSchemeAlias'] = 'jsconnect';
            $Values['AssociationHashMethod'] = 'md5';
            $Values['Attributes'] = serialize(array('HashType' => $Form->GetFormValue('HashType'), 'TestMode' => $Form->GetFormValue('TestMode')));

            if ($Form->ErrorCount() == 0) {
               if ($client_id) {
                  Gdn::SQL()->Put('UserAuthenticationProvider', $Values, array('AuthenticationKey' => $client_id));
               } else {
                  Gdn::SQL()->Options('Ignore', TRUE)->Insert('UserAuthenticationProvider', $Values);
               }
               
               $Sender->RedirectUrl = Url('/settings/jsconnect');
            }
         }
      } else {
         if ($client_id) {
            $Provider = self::GetProvider($client_id);
            $Form->SetData($Provider);
         }
      }

      $Sender->SetData('Title', sprintf(T($client_id ? 'Edit %s' : 'Add %s'), T('Connection')));
      $Sender->Render('Settings_AddEdit', '', 'plugins/jsconnect');
   }
   
   public function Settings_Delete($Sender, $Args) {
      $client_id = $Sender->Request->Get('client_id');
      $Provider = self::GetProvider($client_id);
      
      $Sender->Form->InputPrefix = FALSE;
      
      if ($Sender->Form->IsPostBack()) {
         if ($Sender->Form->GetFormValue('Yes')) {
            $Model = new Gdn_AuthenticationProviderModel();
            $Model->Delete(array('AuthenticationKey' => $client_id));
         }
         $Sender->RedirectUrl = '/settings/jsconnect';
         $Sender->Render('Blank', 'Utility', 'Dashboard');
      } else {
         $Sender->Render('ConfirmDelete', '', 'plugins/jsconnect');
      }
   }

   protected function Settings_Index($Sender, $Args) {
      $Providers = self::GetProvider();
      $Sender->SetData('Providers', $Providers);
      $Sender->Render('Settings', '', 'plugins/jsconnect');
   }
}