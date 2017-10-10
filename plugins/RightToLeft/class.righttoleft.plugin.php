<?php

if (!defined('APPLICATION'))
   exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 */

/**
 * Class RightToLeftPlugin
 */
class RightToLeftPlugin extends Gdn_Plugin {

    /**
    * @var array $rtlLocales List the locales that are rtl.
    */
    protected $rtlLocales = ['ar', 'fa', 'he', 'ku', 'ps', 'sd', 'ug', 'ur', 'yi'];

   /**
    * Add the rtl stylesheets to the page.
    *
    * The rtl stylesheets should always be added separately so that they aren't combined with other stylesheets when
    * a non-rtl language is still being displayed.
    *
    * @param Gdn_Controller $sender
    */
    public function base_render_before($sender) {
        $currentLocale = substr(Gdn::locale()->current(), 0, 2);

        if (in_array($currentLocale, $this->rtlLocales)) {
            if (inSection('Dashboard')) {
               $sender->addCssFile('admin_rtl.css', 'plugins/RightToLeft');
            } else {
               $sender->addCssFile('style_rtl.css', 'plugins/RightToLeft');
            }

            $sender->CssClass .= ' rtl';
       }
    }
}
