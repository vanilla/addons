<?php

/**
 * Multilingual Plugin
 *
 * @author Lincoln Russell <lincoln@vanillaforums.com>
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Addons
 */

$PluginInfo['Multilingual'] = array(
    'Name' => 'Multilingual',
    'Description' => "Allows use of multiple languages. Users can select their preferred language via a link in the footer, and administrators may embed their forum in different languages in different places.",
    'Version' => '1.3.1',
    'RequiredApplications' => array('Vanilla' => '2.0.18'),
    'MobileFriendly' => true,
    'Author' => "Lincoln Russell",
    'AuthorEmail' => 'lincoln@vanillaforums.com',
    'AuthorUrl' => 'http://lincolnwebs.com'
);

/* Change Log
   1.0 - Make MobileFriendly //Lincoln 2012-01-13
   1.1 - Move locale setting to later in startup for Embed //Lincoln 2012-02-22
   1.2 - Create locale chooser module //Lincoln 2014-08-13
   1.3 - Updates to accommodate locale canonicalization //Todd 2015-03-20
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
    protected static $overrides = [
        'nso' => ['Name' => 'Sesotho sa Leboa'],
        'zu_ZA' => ['Name' => 'isiZulu']
    ];

    /**
     * Return the enabled locales suitable for local choosing.
     *
     * @return array Returns an array in the form `[locale => localeName]`.
     */
    public static function enabledLocales() {
        $defaultLocale = Gdn_Locale::Canonicalize(C('Garden.Locale'));

        $localeModel = new LocaleModel();
        if (class_exists('Locale')) {
            $localePacks = $localeModel->EnabledLocalePacks(false);
            $locales = array();
            foreach ($localePacks as $locale) {
                if (isset(static::$overrides[$locale]['Name'])) {
                    $locales[$locale] = static::$overrides[$locale]['Name'];
                } else {
                    $locales[$locale] = ucwords(Locale::getDisplayName($locale, $locale));
                }
            }
            $defaultName = Locale::getDisplayName($defaultLocale, $defaultLocale);
        } else {
            $locales = $localeModel->EnabledLocalePacks(true);
            $locales = array_column($locales, 'Name', 'Locale');
            $defaultName = $defaultLocale === 'en' ? 'English' : $defaultLocale;
        }
        asort($locales);

        if (!array_key_exists($defaultLocale, $locales)) {
            $locales = array_merge(
                array($defaultLocale => $defaultName),
                $locales
            );
        }

        return $locales;
    }


    /**
     * Set user's preferred locale.
     *
     * Moved event from AppStart to AfterAnalyzeRequest to allow Embed to set P3P header first.
     */
    public function gdn_dispatcher_afterAnalyzeRequest_handler($Sender) {
        // Set user preference
        if ($TempLocale = $this->getAlternateLocale()) {
            Gdn::Locale()->Set($TempLocale, Gdn::ApplicationManager()->EnabledApplicationFolders(), Gdn::PluginManager()->EnabledPluginFolders());
        }
    }

    /**
     * Show alternate locale options in Foot.
     */
    public function base_render_before($Sender) {
        // Not in Dashboard
        // Block guests until guest sessions are restored
        if ($Sender->MasterView == 'admin' || !CheckPermission('Garden.SignIn.Allow'))
            return;

        $Sender->AddModule('LocaleChooserModule');

        // Add a simple style
        $Sender->AddAsset('Head', '<style>.LocaleOption { padding-left: 10px; } .LocaleOptions { padding: 10px; } .Dashboard .LocaleOptions { display: none; }</style>');
    }

    /**
     * Get user preference or queried locale.
     */
    protected function getAlternateLocale() {
        $Locale = false;

        // User preference
        if (Gdn::Session()->IsValid()) {
            $Locale = $this->GetUserMeta(Gdn::Session()->UserID, 'Locale', false);
            $Locale = val('Plugin.Multilingual.Locale', $Locale, false);
        }
        // Query string
        if (!$Locale) {
            $Locale = $this->validateLocale(Gdn::Request()->Get('locale'));
            if ($Locale) {
                Gdn::Session()->Stash('Locale', $Locale);
            }
        }
        // Session
        if (!$Locale) {
            $Locale = Gdn::Session()->Stash('Locale', '', false);
        }

        return $Locale;
    }

    /**
     * Allow user to set their preferred locale via link-click.
     */
    public function profileController_setLocale_create($Sender, $locale, $TK) {
        if (!Gdn::Session()->UserID) {
            throw PermissionException('Garden.SignIn.Allow');
        }

        // Check intent.
        if (!Gdn::Session()->ValidateTransientKey($TK)) {
            safeRedirect($_SERVER['HTTP_REFERER']);
        }

        // If we got a valid locale, save their preference
        if (isset($locale)) {
            $locale = $this->validateLocale($locale);
            if ($locale) {
                $this->SetUserMeta(Gdn::Session()->UserID, 'Locale', $locale);
            }
        }

        $successRedirect = $_SERVER['HTTP_REFERER'];
        $target = gdn::request()->get('Target');
        if ($target) {
           $successRedirect = $target;
        }
        // Back from whence we came.
        safeRedirect($successRedirect);
    }

    /**
     * Confirm selected locale is valid and available.
     *
     * @param string $Locale Locale code.
     * @return string Returns the canonical version of the locale on success or an empty string otherwise.
     */
    protected function validateLocale($Locale) {
        $canonicalLocale = Gdn_Locale::Canonicalize($Locale);
        $locales = static::enabledLocales();

        $result = isset($locales[$canonicalLocale]) ? $canonicalLocale : '';
        return $result;
    }
}
