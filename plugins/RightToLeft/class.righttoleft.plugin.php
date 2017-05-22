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
    protected $rtlLocales = array('ar', 'fa', 'he', 'ku', 'ps', 'sd', 'ug', 'ur', 'yi');

   /**
    * Add the rtl stylesheets to the page.
    *
    * The rtl stylesheets should always be added separately so that they aren't combined with other stylesheets when
    * a non-rtl language is still being displayed.
    *
    * @param Gdn_Controller $Sender
    */
    public function Base_Render_Before($Sender) {
        $currentLocale = substr(Gdn::Locale()->Current(), 0, 2);

        if (in_array($currentLocale, $this->rtlLocales)) {
            if (InSection('Dashboard')) {
               $Sender->AddCssFile('admin_rtl.css', 'plugins/RightToLeft');
            } else {
               $Sender->AddCssFile('style_rtl.css', 'plugins/RightToLeft');
            }

            $Sender->CssClass .= ' rtl';
       }
    }
}
