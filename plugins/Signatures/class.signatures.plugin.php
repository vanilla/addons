<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

// Define the plugin:
$PluginInfo['Signatures'] = array(
   'Name' => 'Signatures',
   'Description' => 'This plugin allows users to attach their own signatures to their posts.',
   'Version' => '1.2.2',
   'RequiredApplications' => array('Vanilla' => '2.0.18b'),
   'RequiredTheme' => FALSE, 
   'RequiredPlugins' => FALSE,
   'HasLocale' => TRUE,
   'RegisterPermissions' => array('Plugins.Signatures.Edit' => 1),
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com',
   'MobileFriendly' => FALSE
);

class SignaturesPlugin extends Gdn_Plugin {
   
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
         $SideMenu->AddLink('Options', T('Signature Settings'), '/profile/signature', FALSE, array('class' => 'Popup'));
      } else {
         $SideMenu->AddLink('Options', T('Signature Settings'), '/profile/signature/'.$Sender->User->UserID.'/'.Gdn_Format::Url($Sender->User->Name), 'Garden.Users.Edit', array('class' => 'Popup'));
      }
   }
   
   public function ProfileController_Signature_Create($Sender) {
      
      $Sender->Permission(array(
         'Garden.SignIn.Allow',
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
         'Plugin.Signatures.HideMobile' => NULL
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
      
      // If seeing the form for the first time...
      if ($Sender->Form->AuthenticatedPostBack() === FALSE) {
         // Apply the config settings to the form.
         $Sender->Form->SetData($ConfigurationModel->Data);
      } else {
         $Values = $Sender->Form->FormValues();
         $FrmValues = array_intersect_key($Values, $ConfigArray);
         if (sizeof($FrmValues)) {
            foreach ($FrmValues as $UserMetaKey => $UserMetaValue) {
               $this->SetUserMeta($SigUserID, $this->TrimMetaKey($UserMetaKey), $UserMetaValue);
            }
         }
         
         $Sender->InformMessage(T("Your changes have been saved."));
      }

      $Sender->Render($this->GetView('signature.php'));
   }
   
   protected function UserPreferences($SigKey = NULL, $Default = NULL) {
      static $UserSigData = NULL;
      if (is_null($UserSigData)) {
         $UserSigData = $this->GetUserMeta(Gdn::Session()->UserID, '%');
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
         $Comments = $Sender->Data('CommentData');
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
            if (is_array($DataSignatures))
               foreach ($DataSignatures as $UserID => $UserSig)
                  $Signatures[$UserID] = GetValue($this->MakeMetaKey('Sig'), $UserSig);
         }
         
      }
      
      if (!is_null($RequestUserID))
         return GetValue($RequestUserID, $Signatures, $Default);
      
      return $Signatures;
   }
   
   public function DiscussionController_Render_Before(&$Sender) {
      $this->PrepareController($Sender);
   }
   
   public function PostController_Render_Before(&$Sender) {
      $this->PrepareController($Sender);
   }
   
   protected function PrepareController($Controller) {
      // Short circuit if not needed
      if ($this->Hide()) return;
      
      $Controller->AddCssFile($this->GetResource('design/signature.css', FALSE, FALSE));
   }
   
   public function DiscussionController_AfterCommentBody_Handler(&$Sender) {
      $this->DrawSignature($Sender);
   }
   
   public function PostController_AfterCommentBody_Handler(&$Sender) {
      $this->DrawSignature($Sender);
   }
   
   protected function DrawSignature($Sender) {

      if ($this->Hide()) return;
      
      if (isset($Sender->EventArguments['Discussion'])) 
         $Data = $Sender->EventArguments['Discussion'];
      
      if (isset($Sender->EventArguments['Comment'])) 
         $Data = $Sender->EventArguments['Comment'];
      
      $SourceUserID = GetValue('InsertUserID', $Data);
      $Signature = $this->Signatures($Sender, $SourceUserID);
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
         
         $Sender->UserSignature = Gdn_Format::Auto($Signature);
         $Display = $Sender->FetchView($this->GetView('usersig.php'));
         unset($Sender->UserSignature);
         echo $Display;
      }
   }
   
   protected function Hide() {
      
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
         
}