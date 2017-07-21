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
    public function buildLocaleLink($name, $urlCode) {
        $url = 'profile/setlocale/'.$urlCode.'/'.Gdn::Session()->TransientKey();

        return Wrap(Anchor($name, $url), 'span', ['class' => 'LocaleOption '.$name.'Locale']);
    }

    /**
     * Return HTML links of all active locales.
     *
     * @return string HTML.
     */
    public function buildLocales() {
        $locales = MultilingualPlugin::enabledLocales();

        $links = '';
        foreach ($locales as $code => $name) {
            $links .= $this->BuildLocaleLink($name, $code);
        }

        return $links;
    }

    /**
     * Output locale links.
     *
     * @return string|void
     */
    public function toString() {
        if (!$this->Links)
            $this->Links = $this->BuildLocales();

        echo Wrap($this->Links, 'div', ['class' => 'LocaleOptions']);
    }
}
