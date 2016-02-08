<?php


/**
 * Renders links to select locale.
 */
class LocaleChooserModule extends Gdn_Module {

    /** @var string HTML links to activate locales. */
    public $Links = '';

    public function assetTarget() {
        return 'Foot';
    }

    /**
     * Build footer link to change locale.
     */
    public function buildLocaleLink($Name, $UrlCode) {
        $Url = 'profile/setlocale/'.$UrlCode.'/'.Gdn::Session()->TransientKey();

        return Wrap(Anchor($Name, $Url), 'span', array('class' => 'LocaleOption '.$Name.'Locale'));
    }

    /**
     * Return HTML links of all active locales.
     *
     * @return string HTML.
     */
    public function buildLocales() {
        $locales = MultilingualPlugin::enabledLocales();

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
    public function toString() {
        if (!$this->Links)
            $this->Links = $this->BuildLocales();

        echo Wrap($this->Links, 'div', array('class' => 'LocaleOptions'));
    }
}
