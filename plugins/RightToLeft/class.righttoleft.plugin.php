<?php

if (!defined('APPLICATION'))
   exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 */


// Define the plugin:
$PluginInfo['RightToLeft'] = array(
    'Name' => 'Right to Left (RTL) Support',
    'Description' => "Adds a css stub to pages with some tweaks for right-to-left (rtl) languages.",
    'Version' => '1.0b',
    'RequiredApplications' => array('Vanilla' => '2.0.18'),
    'MobileFriendly' => TRUE,
    'Author' => 'Todd Burry',
    'AuthorEmail' => 'todd@vanillaforums.com',
    'AuthorUrl' => 'http://www.vanillaforums.org/profile/todd'
);

class RightToLeftPlugin extends Gdn_Plugin {

    /**
    * @var $rtlLocales list locales that are rtl
    */
    protected $rtlLocales = array('ar');

   /**
    *
    * @param Gdn_Controller $Sender
    */
    public function Base_Render_Before(&$Sender) {

       $currentLocale = Gdn::Locale()->Current();
       $realLocale = C('Garden.RealLocale', $currentLocale);

       if (in_array($realLocale, $this->rtlLocales)) {
          $Sender->AddJsFile('custom-rtl.js', 'plugins/RightToLeft');
          $Sender->AddCssFile('style_rtl.css', 'plugins/RightToLeft');
          $Sender->AddCssFile('admin_rtl.css', 'plugins/RightToLeft');
       }
    }
}
