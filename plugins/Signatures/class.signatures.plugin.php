<?php if (!defined('APPLICATION')) exit();

/**
 * Signatures Plugin
 * 
 * This plugin allows users to maintain a 'Signature' which is automatically
 * appended to all discussions and comments they make.
 * 
 * Changes: 
 *  1.0     Initial release
 *  1.4     Add SimpleAPI hooks
 *  1.4.1   Allow self-API access
 * 
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2003 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Addons
 */

$PluginInfo['Signatures'] = array(
   'Name' => 'Signatures',
   'Description' => 'Users may create custom signatures that appear after each of their comments.',
   'Version' => '1.4.1',
   'RequiredApplications' => array('Vanilla' => '2.0.18b'),
   'RequiredTheme' => FALSE, 
   'RequiredPlugins' => FALSE,
   'HasLocale' => TRUE,
   'RegisterPermissions' => array('Plugins.Signatures.Edit' => 1),
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com',
   'MobileFriendly' => FALSE,
   'SettingsUrl' => '/settings/signatures',
   'SettingsPermission' => 'Garden.Settings.Manage'
);

class SignaturesPlugin extends Gdn_Plugin {
   public $Disabled = FALSE;
   
   /**
    * Add mapper methods
    * 
    * @param SimpleApiPlugin $Sender
    */
   public function SimpleApiPlugin_Mapper_Handler($Sender) {
      switch ($Sender->Mapper->Version) {
         case '1.0':
            $Sender->Mapper->AddMap(array(
               'signature/get'         => 'dashboard/profile/signature/modify',
               'signature/set'         => 'dashboard/profile/signature/modify',
            ), NULL, array(
               'signature/get'         => array('Signature'),
               'signature/set'         => array('Success'),
            ));
            break;
      }
   }
   
   public function ProfileController_AfterAddSideMenu_Handler($Sender) {
      if (!Gdn::Session()->CheckPermission(array(
         'Garden.SignIn.Allow',
         'Plugins.Signatures.Edit'
      ))) {
         return;
      }
   
      $SideMenu = $Sender->EventArguments['SideMenu'];
      $ViewingUserID = Gdn::Session()->UserID;
      
      if ($Sender->User->UserID == $ViewingUserID) {
         $SideMenu->AddLink('Options', Sprite('SpSignatures').' '.T('Signature Settings'), '/profile/signature', FALSE, array('class' => 'Popup'));
      } else {
         $SideMenu->AddLink('Options', Sprite('SpSignatures').' '.T('Signature Settings'), UserUrl($Sender->User, '', 'signature'), 'Garden.Users.Edit', array('class' => 'Popup'));
      }
   }
   
   /**
    * Profile settings
    * 
    * @param ProfileController $Sender 
    */
   public function ProfileController_Signature_Create($Sender) {
      $Sender->Permission('Garden.SignIn.Allow');
      $Sender->Title('Signature Settings');
      
      $this->Dispatch($Sender);
   }
   
   
   public function Controller_Index($Sender) {
      $Sender->Permission(array(
         'Garden.Profiles.Edit',
         'Plugins.Signatures.Edit'
      ));
      
      $Args = $Sender->RequestArgs;
      if (sizeof($Args) < 2)
         $Args = array_merge($Args, array(0,0));
      elseif (sizeof($Args) > 2)
         $Args = array_slice($Args, 0, 2);
      
      list($UserReference, $Username) = $Args;
      
      $Sender->Permission('Plugins.Signatures.Edit');
      $Sender->GetUserInfo($UserReference, $Username);
      $UserPrefs = Gdn_Format::Unserialize($Sender->User->Preferences);
      if (!is_array($UserPrefs))
         $UserPrefs = array();
      
      $Validation = new Gdn_Validation();
      $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
      $ConfigArray = array(
         'Plugin.Signatures.Sig'          => NULL,
         'Plugin.Signatures.HideAll'      => NULL,
         'Plugin.Signatures.HideImages'   => NULL,
         'Plugin.Signatures.HideMobile'   => NULL,
         'Plugin.Signatures.Format'       => NULL
      );
      $SigUserID = $ViewingUserID = Gdn::Session()->UserID;
      
      if ($Sender->User->UserID != $ViewingUserID) {
         $Sender->Permission('Garden.Users.Edit');
         $SigUserID = $Sender->User->UserID;
      }
      
      $Sender->SetData('Plugin-Signatures-ForceEditing', ($SigUserID == Gdn::Session()->UserID) ? FALSE : $Sender->User->Name);
      $UserMeta = $this->GetUserMeta($SigUserID, '%');
      
      if ($Sender->Form->AuthenticatedPostBack() === FALSE && is_array($UserMeta))
         $ConfigArray = array_merge($ConfigArray, $UserMeta);
      
      $ConfigurationModel->SetField($ConfigArray);
      
      // Set the model on the form.
      $Sender->Form->SetModel($ConfigurationModel);
      
      $Data = $ConfigurationModel->Data;
      $Sender->SetData('Signature', $Data);
      
      // If seeing the form for the first time...
      if ($Sender->Form->IsPostBack() === FALSE) {
         $Data['Body'] = GetValue('Plugin.Signatures.Sig', $Data);
         $Data['Format'] = GetValue('Plugin.Signatures.Format', $Data);
         
         // Apply the config settings to the form.
         $Sender->Form->SetData($Data);
      } else {
         $Values = $Sender->Form->FormValues();
         $Values['Plugin.Signatures.Sig'] = GetValue('Body', $Values, NULL);
         $Values['Plugin.Signatures.Format'] = GetValue('Format', $Values, NULL);
         $FrmValues = array_intersect_key($Values, $ConfigArray);
         
         if (sizeof($FrmValues)) {
            
            if (!GetValue($this->MakeMetaKey('Sig'), $FrmValues)) {
               // Delete the signature. 
               $FrmValues[$this->MakeMetaKey('Sig')] = NULL;
               $FrmValues[$this->MakeMetaKey('Format')] = NULL;
            }
            
            foreach ($FrmValues as $UserMetaKey => $UserMetaValue) {
               $Key = $this->TrimMetaKey($UserMetaKey);
               
               switch ($Key) {
                  case 'Format':
                     if (strcasecmp($UserMetaValue, 'Raw') == 0)
                        $UserMetaValue = NULL; // don't allow raw signatures.
                  break;
               }
               
               $this->SetUserMeta($SigUserID, $Key, $UserMetaValue);
            }
         }
         
         $Sender->InformMessage(T("Your changes have been saved."));
      }

      $Sender->Render('signature', '', 'plugins/Signatures');
   }
   
   /*
    * API METHODS
    */
   
   public function Controller_Modify($Sender) {
      $Sender->DeliveryMethod(DELIVERY_METHOD_JSON);
      $Sender->DeliveryType(DELIVERY_TYPE_DATA);
      
      $UserID = Gdn::Request()->Get('UserID');
      if ($UserID != Gdn::Session()->UserID)
         $Sender->Permission('Garden.Users.Edit');
      
      $User = Gdn::UserModel()->GetID($UserID);
      if (!$User)
         throw new Exception("No such user '{$UserID}'", 404);
         
      $Translation = array(
         'Plugin.Signatures.Sig'          => 'Body',
         'Plugin.Signatures.Format'       => 'Format',
         'Plugin.Signatures.HideAll'      => 'HideAll',
         'Plugin.Signatures.HideImages'   => 'HideImages',
         'Plugin.Signatures.HideMobile'   => 'HideMobile'
      );
         
      $UserMeta = $this->GetUserMeta($UserID, '%');
      $SigData = array();
      foreach ($Translation as $TranslationField => $TranslationShortcut)
         $SigData[$TranslationShortcut] = GetValue($TranslationField, $UserMeta, NULL);

      $Sender->SetData('Signature', $SigData);
      
      if ($Sender->Form->IsPostBack()) {
         $Sender->SetData('Success', FALSE);
         foreach ($Translation as $TranslationField => $TranslationShortcut) {
            $UserMetaValue = $Sender->Form->GetValue($TranslationShortcut, NULL);
            if (is_null($UserMetaValue)) continue;
            
            if ($TranslationShortcut == 'Body' && empty($UserMetaValue))
               $UserMetaValue = NULL;
            
            $Key = $this->TrimMetaKey($TranslationField);

            switch ($Key) {
               case 'Format':
                  if (strcasecmp($UserMetaValue, 'Raw') == 0)
                     $UserMetaValue = NULL; // don't allow raw signatures.
               break;
            }

            $this->SetUserMeta($UserID, $Key, $UserMetaValue);
         }
         $Sender->SetData('Success', TRUE);
      }
      
      $Sender->Render();
   }
   
   protected function UserPreferences($SigKey = NULL, $Default = NULL) {
      static $UserSigData = NULL;
      if (is_null($UserSigData)) {
         $UserSigData = $this->GetUserMeta(Gdn::Session()->UserID, '%');
         
//         decho($UserSigData);
      }
      
      if (!is_null($SigKey))
         return GetValue($SigKey, $UserSigData, $Default);
      
      return $UserSigData;
   }
   
   protected function Signatures($Sender, $RequestUserID = NULL, $Default = NULL) {
      static $Signatures = NULL;
      
      if (is_null($Signatures)) {
         $Signatures = array();
         
         // Short circuit if not needed.
         if ($this->Hide()) return $Signatures;
      
         $Discussion = $Sender->Data('Discussion');
         $Comments = $Sender->Data('Comments');
         $UserIDList = array();
         
         if ($Discussion)
            $UserIDList[GetValue('InsertUserID', $Discussion)] = 1;
            
         if ($Comments && $Comments->NumRows()) {
            $Comments->DataSeek(-1);
            while ($Comment = $Comments->NextRow())
               $UserIDList[GetValue('InsertUserID', $Comment)] = 1;
         }
         
         if (sizeof($UserIDList)) {
            $DataSignatures = $this->GetUserMeta(array_keys($UserIDList), 'Sig');
            $Formats = (array)$this->GetUserMeta(array_keys($UserIDList), 'Format');
            
            if (is_array($DataSignatures)) {
               foreach ($DataSignatures as $UserID => $UserSig) {
                  $Sig = GetValue($this->MakeMetaKey('Sig'), $UserSig);
                  if (isset($Formats[$UserID])) {
                     $Format = GetValue($this->MakeMetaKey('Format'), $Formats[$UserID], C('Garden.InputFormatter'));
                  } else {
                     $Format = C('Garden.InputFormatter');
                  }
                  
                  $Signatures[$UserID] = array($Sig, $Format); 
               }
            }
         }
         
      }
      
      if (!is_null($RequestUserID))
         return GetValue($RequestUserID, $Signatures, $Default);
      
      return $Signatures;
   }
   
   public function DiscussionController_Render_Before($Sender) {
      $this->PrepareController($Sender);
   }
   
   public function PostController_Render_Before($Sender) {
      $this->PrepareController($Sender);
   }
   
   protected function PrepareController($Controller) {
      // Short circuit if not needed
      if ($this->Hide()) return;
      
      $Controller->AddCssFile($this->GetResource('design/signature.css', FALSE, FALSE));
   }
   
   /** Deprecated in 2.1. */
   public function DiscussionController_AfterCommentBody_Handler($Sender) {
      if ($this->Disabled)
         return;
      
      $this->DrawSignature($Sender);
   }
   
   /** New call for 2.1. */
   public function DiscussionController_AfterDiscussionBody_Handler($Sender) {
      if ($this->Disabled)
         return;
      $this->DrawSignature($Sender);
   }
      
   protected function DrawSignature($Sender) {
      if ($this->Hide()) return;
      
      if (isset($Sender->EventArguments['Discussion'])) 
         $Data = $Sender->EventArguments['Discussion'];
      
      if (isset($Sender->EventArguments['Comment'])) 
         $Data = $Sender->EventArguments['Comment'];
      
      $SourceUserID = GetValue('InsertUserID', $Data);
      $User = Gdn::UserModel()->GetID($SourceUserID, DATASET_TYPE_ARRAY);
      if (GetValue('HideSignature', $User, FALSE))
         return;
      
      
      $Signature = $this->Signatures($Sender, $SourceUserID);
      
      if (is_array($Signature))
         list($Signature, $SigFormat) = $Signature;
      else
         $SigFormat = C('Garden.InputFormatter');
      
      if (!$SigFormat)
         $SigFormat = C('Garden.InputFormatter');
      
      $this->EventArguments = array(
         'UserID'    => $SourceUserID,
         'Signature' => &$Signature
      );
//      $this->FireEvent('BeforeDrawSignature');
      
      if (!is_null($Signature)) {
         $HideImages = $this->UserPreferences('Plugin.Signatures.HideImages', FALSE);
         
         if ($HideImages) {
            // Strip img tags
            $Signature = $this->_StripOnly($Signature, array('img'));
         
            // Remove blank lines and spare whitespace
            $Signature = preg_replace('/^\S*\n\S*/m','',str_replace("\r\n","\n",$Signature));
            $Signature = trim($Signature);
         }
         
         // Don't show empty sigs
         if ($Signature == '') return;
         
         $UserSignature = Gdn_Format::To($Signature, $SigFormat)."<!-- $SigFormat -->";
         
         
         if ($UserSignature) {
            echo '<div class="Signature UserSignature">'.$UserSignature.'</div>';
         }
      }
   }
   
   protected function Hide() {
      if ($this->Disabled)
         return TRUE;
      
      if (!Gdn::Session()->IsValid() && C('Plugins.Signatures.HideGuest'))
         return TRUE;
      
      if (strcasecmp(Gdn::Controller()->RequestMethod, 'embed') == 0 && C('Plugin.Signatures.HideEmbed', TRUE))
         return TRUE;
      
      if ($this->UserPreferences('Plugin.Signatures.HideAll', FALSE))
         return TRUE;
      
      if (IsMobile() && $this->UserPreferences('Plugin.Signatures.HideMobile', FALSE))
         return TRUE;
      
      return FALSE;
   }
   
   protected function _StripOnly($str, $tags, $stripContent = false) {
      $content = '';
      if(!is_array($tags)) {
         $tags = (strpos($str, '>') !== false ? explode('>', str_replace('<', '', $tags)) : array($tags));
         if(end($tags) == '') array_pop($tags);
      }
      foreach($tags as $tag) {
         if ($stripContent)
             $content = '(.+</'.$tag.'[^>]*>|)';
          $str = preg_replace('#</?'.$tag.'[^>]*>'.$content.'#is', '', $str);
      }
      return $str;
   }

   public function Setup() {
      // Nothing to do here!
   }
   
   public function Structure() {
      // Nothing to do here!
   }
   
   
   public function SettingsController_Signatures_Create($Sender) {
      $Sender->Permission('Garden.Settings.Manage');

      $Conf = new ConfigurationModule($Sender);
      $Conf->Initialize(array(
          'Plugins.Signatures.HideGuest' => array('Control' => 'CheckBox', 'LabelCode' => 'Hide signatures for guests'),
          'Plugins.Signatures.HideEmbed' => array('Control' => 'CheckBox', 'LabelCode' => 'Hide signatures on embedded comments', 'Default' => TRUE)
      ));

      $Sender->AddSideMenu();
      $Sender->SetData('Title', sprintf(T('%s Settings'), T('Signature')));
      $Sender->ConfigurationModule = $Conf;
      $Conf->RenderAll();
//      $Sender->Render('Settings', '', 'plugins/AmazonS3');
   }
}