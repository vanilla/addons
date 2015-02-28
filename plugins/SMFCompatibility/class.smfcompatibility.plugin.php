<?php if (!defined('APPLICATION')) exit();
/**********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
**********************************************************************************/

$PluginInfo['SMFCompatibility'] = array(
   'Name' => 'SMF Compatibility',
   'Description' => 'Adds some compatibility functionality for forums imported from SMF.',
   'Version' => '1.1',
   'RequiredApplications' => array('Vanilla' => '2.0.18'),
   'RequiredTheme' => FALSE,
   'RequiredPlugins' => FALSE,
   'HasLocale' => FALSE,
   'Author' => "Todd Burry",
   'AuthorEmail' => 'todd@vanillaforums.com',
   'AuthorUrl' => 'http://vanillaforums.com/profile/todd',
   'License' => 'Simple Machines license'
);

Gdn::FactoryInstall('BBCodeFormatter', 'SMFCompatibilityPlugin', __FILE__, Gdn::FactorySingleton);

class SMFCompatibilityPlugin extends Gdn_Plugin {
	/// CONSTRUCTOR ///
	public function __construct() {
      require_once(dirname(__FILE__).'/functions.smf.php');
	}

	/// PROPERTIES ///


	/// METHODS ///

   public function Base_BeforeDispatch_Handler($Sender) {
      $Request = Gdn::Request();
      $Folder = ltrim($Request->RequestFolder(), '/');
      $Uri = ltrim($_SERVER['REQUEST_URI'], '/');

      // Fix the url in the request for routing.
      if (preg_match("`^{$Folder}index.php/`", $Uri)) {
         $Request->PathAndQuery(substr($Uri, strlen($Folder)));
      }
   }

	public function Format($String) {
      try {
         $Result = parse_bbc($String);
      } catch (Exception $Ex) {
         $Result = '<!-- Error: '.htmlspecialchars($Ex->getMessage()).'-->'
            .Gdn_Format::Display($String);
      }
      return $Result;
	}

	public function Setup() {
      $OldFormat = C('Garden.InputFormatter');

      if ($OldFormat != 'BBCode') {
         SaveToConfig(array(
            'Garden.InputFormatter' => 'BBCode',
            'Garden.InputFormatterBak' => $OldFormat));
      }

      // Setup the default routes.
      $Router = Gdn::Router();
      $Router->SetRoute('\?board=(\d+).*$', 'categories/$1', 'Permanent');
      $Router->SetRoute('index\.php/topic,(\d+).(\d+)\.html.*$', 'discussion/$1/x/$2lim', 'Permanent');
      $Router->SetRoute('index\.php/board,(\d+)\.(\d+)\.html.*$', 'categories/$1/$2lim', 'Permanent');
      $Router->SetRoute('\?action=profile%3Bu%3D(\d+).*$', 'profile/$1/x', 'Permanent');
      $Router->SetRoute('index\.php/topic,(\d+)\.msg(\d+)\.html.*$', 'discussion/comment/$2/#Comment_$2', 'Permanent');
      $Router->SetRoute('\?topic=(\d+).*$', 'discussion/$1/x/p1', 'Permanent');
	}

   public function OnDisable() {
      $OldFormat = C('Garden.InputFormatterBak');

      if ($OldFormat !== FALSE) {
         SaveToConfig('Garden.InputFormatter', $OldFormat);
      }
   }
}