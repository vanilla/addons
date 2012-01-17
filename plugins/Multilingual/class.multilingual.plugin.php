<?php if (!defined('APPLICATION')) exit();
/**
 * Multilingual Plugin
 * 
 * @author Matt Lincoln Russell <lincoln@vanillaforums.com>
 * @copyright 2011 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Addons
 */

$PluginInfo['Multilingual'] = array(
   'Name' => 'Multilingual',
   'Description' => "Allow user-selectable & queryable locales.",
   'Version' => '1.0',
   'RequiredApplications' => array('Vanilla' => '2.0.18'),
   'MobileFriendly' => TRUE,
   'Author' => "Matt Lincoln Russell",
   'AuthorEmail' => 'lincoln@vanillaforums.com',
   'AuthorUrl' => 'http://lincolnwebs.com'
);

/* Changelog
   1.0 - Make MobileFriendly //Lincoln 2012-01-13
*/

/**
 * Allows multiple locales to work in Vanilla.
 *
 * You can trigger an alternate locale by adding 'locale' in the query string,
 * setting var vanilla_embed_locale in an embedded forum, or selecting one of the 
 * language links added to the footer. User-selected locale takes precedence.
 * The selected locale is stored in the session. If it is user-selected AND the
 * user is logged in, it is stored in UserMeta.
 *
 * @example http://example.com/discussions?locale=de-DE
 * @example <script>var vanilla_embed_locale = 'de-DE';</script>
 */
class MultilingualPlugin extends Gdn_Plugin {
   /**
    * Set user's preferred locale. 
    */
   public function Gdn_Dispatcher_AppStartup_Handler($Sender) {
      // Set user preference
      if ($TempLocale = $this->GetAlternateLocale()) {
         Gdn::Locale()->Set($TempLocale, Gdn::ApplicationManager()->EnabledApplicationFolders(), Gdn::PluginManager()->EnabledPluginFolders());
      }
   }
   
   /**
    * Show alternate locale options in Foot.
    */
   public function Base_Render_Before($Sender) {
      // Not in Dashboard
      if ($Sender->MasterView == 'admin')
         return;
      
      // Get locales
      $LocaleModel = new LocaleModel();
      $Options = $LocaleModel->EnabledLocalePacks();
      $Options['English'] = 'en-CA'; // Hackily include the default
      
      // Build & add links
      $Links = T('Change language').': ';
      foreach ($Options as $LocaleName => $Code) {
         $Links .= Wrap(Anchor(ucwords($LocaleName), 'profile/setlocale/'.$Code.'/'.Gdn::Session()->TransientKey()), 'span', array('class' => 'LocaleOption'));
      }
      $Links = Wrap($Links, 'div', array('class' => 'LocaleOptions'));
      $Sender->AddAsset('Foot', $Links);
      
      // Add a simple style
      $Sender->AddAsset('Head', '<style>.LocaleOption { padding-right: 7px; } .Dashboard .LocaleOptions { display: none; }</style>');
   }
   
   /**
    * Get user preference or queried locale.
    */
   protected function GetAlternateLocale() {
      $Locale = FALSE;
      
      // User preference
      if (!$Locale) {
         $Locale = $this->GetUserMeta(Gdn::Session()->UserID, 'Locale', FALSE);
         $Locale = GetValue('Plugin.Multilingual.Locale', $Locale, FALSE);
      }
      // Query string
      if (!$Locale) {
         $Locale = $this->ValidateLocale(GetValue('locale', $_GET, FALSE));
         if ($Locale)
            Gdn::Session()->Stash('Locale', $Locale);
      }
      // Session
      if (!$Locale) {
         $Locale = Gdn::Session()->Stash('Locale', '', FALSE);
      }
      
      return $Locale;
   }
   
   /**
    * Allow user to set their preferred locale via link-click.
    */
   public function ProfileController_SetLocale_Create($Sender, $Args = array()) {
      // Check intent
      if (isset($Args[1]))
         Gdn::Session()->ValidateTransientKey($Args[1]);
      else return;
      
      // If we got a valid locale, save their preference
      if (isset($Args[0])) {
         $Locale = $this->ValidateLocale($Args[0]);
         if ($Locale) {
            Gdn::Session()->Stash('Locale', $Locale);
            if (CheckPermission('Garden.SignIn.Allow'))
               $this->SetUserMeta(Gdn::Session()->UserID, 'Locale', $Locale);
         }
      }
      
      // Back from whence we came
      Redirect($_SERVER['HTTP_REFERER']);
   }
   
   /**
    * Confirm selected locale is valid and available.
    * 
    * @param string $Locale Locale code.
    * @return $Locale or FALSE.
    */
   protected function ValidateLocale($Locale) {
      $LocaleModel = new LocaleModel();
      $Options = $LocaleModel->EnabledLocalePacks();
      $Options['English'] = 'en-CA'; // Hackily include the default
      return (in_array($Locale, $Options)) ? $Locale : FALSE;
   }
}