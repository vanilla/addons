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
    'Description' => "Adds a css stub to pages with some tweaks for right-to-left (rtl) languages and adds 'rtl' to body css class.",
    'Version' => '1.0',
    'RequiredApplications' => array('Vanilla' => '2.0.18'),
    'MobileFriendly' => TRUE,
    'Author' => 'Todd Burry, Becky Van Bussel',
    'AuthorEmail' => 'todd@vanillaforums.com',
    'AuthorUrl' => 'http://www.vanillaforums.org/profile/todd'
);

class RightToLeftPlugin extends Gdn_Plugin {

   /**
    * @var $rtlLocales list locales that are rtl
    */
    protected $rtlLocales = array('ar', 'he');

   /**
    *
    * @param Gdn_Controller $Sender
    */
    public function Base_Render_Before(&$Sender) {

       $currentLocale = substr(Gdn::Locale()->Current(), 0, 2);

       if (in_array($currentLocale, $this->rtlLocales)) {
          $Sender->AddJsFile('custom-rtl.js', 'plugins/RightToLeft');
          $Sender->AddCssFile('style_rtl.css', 'plugins/RightToLeft');
          $Sender->AddCssFile('admin_rtl.css', 'plugins/RightToLeft');

          $Sender->CssClass .= ' rtl';
       }
    }
}
