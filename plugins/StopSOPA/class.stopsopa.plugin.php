<?php if (!defined('APPLICATION')) exit();

/**
 * Stop SOPA Plugin
 * 
 * Blacks out a site and displays a SOPA information screen.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2012 Tim Gunter
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Addons
 * @since 1.0
 */

// Define the plugin:
$PluginInfo['StopSOPA'] = array(
	'Name' => 'Stop SOPA Blackout',
   'Description' => 'Blacks out a site and displays a SOPA information screen.',
   'Version' => '1.3',
   'MobileFriendly' => TRUE,
   'RequiredApplications' => array('Vanilla' => '2.0.18'),
   'RequiredTheme' => FALSE, 
   'RequiredPlugins' => FALSE,
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com'
);

class StopSOPAPlugin extends Gdn_Plugin {
   
   public function __construct() {
      parent::__construct();
   }
   
   public function Gdn_Dispatcher_BeforeDispatch_Handler($Sender) {
      
      // Admins not affected
      if (Gdn::Session()->IsValid() && Gdn::Session()->CheckPermission('Garden.Settings.Manage'))
         return;
      
      // Allow signing in
      $PathRequest = Gdn::Request()->Path();
      if (preg_match('/entry(\/.*)?$/', $PathRequest))
         return;
      
      // Send proper headers
      header('Status: 503 Service Unavailable', TRUE, 503);
      header('Retry-After: Wed, 18 Jan 2012 23:59:59 GMT', TRUE);
      Gdn::Request()->WithURI("/plugin/stopsopa");
   }
   
   public function PluginController_StopSOPA_Create($Sender) {
      $this->Dispatch($Sender, $Sender->RequestArgs);
   }
   
   /**
    * Blocker method
    * @param PluginController $Sender 
    */
   public function Controller_Index($Sender) {
      $Sender->Title('Website Blocked');
      $Sender->AddCssFile('stopsopa.css', 'plugins/StopSOPA');
      $Sender->RemoveCssFile('style.css');
      $Sender->RemoveCssFile('admin.css');
      $Sender->MasterView = 'empty';
      
      
      
      $Sender->Render('block','','plugins/StopSOPA');
   }
   
}