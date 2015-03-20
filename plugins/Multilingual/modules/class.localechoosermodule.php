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
      $locales = MultilingualPlugin::EnabledLocales();

      $Links = '';
      foreach ($locales as $code => $name) {
         $Links .= $this->BuildLocaleLink($name, $code);
      }

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
