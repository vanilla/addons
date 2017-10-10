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

Gdn::factoryInstall('BBCodeFormatter', 'SMFCompatibilityPlugin', __FILE__, Gdn::FactorySingleton);

class SMFCompatibilityPlugin extends Gdn_Plugin {
	/// CONSTRUCTOR ///
	public function __construct() {
      require_once(dirname(__FILE__).'/functions.smf.php');
	}

	/// PROPERTIES ///


	/// METHODS ///

   public function base_beforeDispatch_handler($sender) {
      $request = Gdn::request();
      $folder = ltrim($request->requestFolder(), '/');
      $uri = ltrim($_SERVER['REQUEST_URI'], '/');

      // Fix the url in the request for routing.
      if (preg_match("`^{$folder}index.php/`", $uri)) {
         $request->pathAndQuery(substr($uri, strlen($folder)));
      }
   }

	public function format($string) {
      try {
         $result = parse_bbc($string);
      } catch (Exception $ex) {
         $result = '<!-- Error: '.htmlspecialchars($ex->getMessage()).'-->'
            .Gdn_Format::display($string);
      }
      return $result;
	}

	public function setup() {
      $oldFormat = c('Garden.InputFormatter');

      if ($oldFormat != 'BBCode') {
         saveToConfig([
            'Garden.InputFormatter' => 'BBCode',
            'Garden.InputFormatterBak' => $oldFormat]);
      }

      // Setup the default routes.
      $router = Gdn::router();
      $router->setRoute('\?board=(\d+).*$', 'categories/$1', 'Permanent');
      $router->setRoute('index\.php/topic,(\d+).(\d+)\.html.*$', 'discussion/$1/x/$2lim', 'Permanent');
      $router->setRoute('index\.php/board,(\d+)\.(\d+)\.html.*$', 'categories/$1/$2lim', 'Permanent');
      $router->setRoute('\?action=profile%3Bu%3D(\d+).*$', 'profile/$1/x', 'Permanent');
      $router->setRoute('index\.php/topic,(\d+)\.msg(\d+)\.html.*$', 'discussion/comment/$2/#Comment_$2', 'Permanent');
      $router->setRoute('\?topic=(\d+).*$', 'discussion/$1/x/p1', 'Permanent');
	}

   public function onDisable() {
      $oldFormat = c('Garden.InputFormatterBak');

      if ($oldFormat !== FALSE) {
         saveToConfig('Garden.InputFormatter', $oldFormat);
      }
   }
}
