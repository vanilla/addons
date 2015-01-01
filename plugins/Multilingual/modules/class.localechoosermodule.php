<?php if (!defined('APPLICATION')) exit();

/**
 * Renders links to select locale.
 */
class LocaleChooserModule extends Gdn_Module {

   /** @var string HTML links to activate locales. */
   public $Links = '';

   public function AssetTarget() {
      return 'Foot';
   }

   /**
    * Build footer link to change locale.
    */
   public function BuildLocaleLink($Name, $UrlCode) {
      $Url = 'profile/setlocale/'.$UrlCode.'/'.Gdn::Session()->TransientKey();

      return Wrap(Anchor(ucwords($Name), $Url), 'span', array('class' => 'LocaleOption '.$Name.'Locale'));
   }

   /**
    * Return HTML links of all active locales.
    *
    * @return string HTML.
    */
   public function BuildLocales() {
      // Get locales
      $LocaleModel = new LocaleModel();
      $Options = $LocaleModel->EnabledLocalePacks();
      $Locales = $LocaleModel->AvailableLocalePacks();

      // Build & add links
      $Links = '';
      foreach ($Options as $Slug => $Code) {
         $LocaleInfo = GetValue($Slug, $Locales);
         $LocaleName = str_replace(' Transifex', '' , GetValue('Name', $LocaleInfo)); // No 'Transifex' in names, pls.
         $Links .= $this->BuildLocaleLink($LocaleName, $Code);
      }

      // Hackily add English option
      $Links .= $this->BuildLocaleLink('English', 'en-CA');

      return $Links;
   }

   /**
    * Output locale links.
    *
    * @return string|void
    */
   public function ToString() {
      if (!$this->Links)
         $this->Links = $this->BuildLocales();

      echo Wrap($this->Links, 'div', array('class' => 'LocaleOptions'));
   }
}