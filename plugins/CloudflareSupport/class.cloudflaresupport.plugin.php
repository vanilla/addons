<?php if (!defined('APPLICATION')) exit();

/**
 * Cloudflare Support Plugin
 * 
 * This plugin inspect the incoming request headers and applies CF-Connecting-IP
 * to the request object.
 * 
 * Changes: 
 *  1.0     Initial release
 * 
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2003 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Addons
 */

// Define the plugin:
$PluginInfo['CloudflareSupport'] = array(
   'Description' => 'This plugin modifies the Request object to work with Cloudflare.',
   'Version' => '1.0',
   'RequiredApplications' => array('Vanilla' => '2.1a'),
   'RequiredTheme' => FALSE, 
   'RequiredPlugins' => FALSE,
   'HasLocale' => FALSE,
   'SettingsUrl' => FALSE,
   'SettingsPermission' => 'Garden.Settings.Manage',
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com'
);

class CloudflareSupportPlugin extends Gdn_Plugin {
   
   public function __construct() {
      
      $CloudflareSourceIP = GetValue('HTTP_CF_CONNECTING_IP', $_SERVER, NULL);
      if (!is_null($CloudflareSourceIP))
         $this->RequestAddress($CloudflareSourceIP);
      
   }
   
}