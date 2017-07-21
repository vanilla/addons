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

Gdn::FactoryInstall('BBCodeFormatter', 'SMFCompatibilityPlugin', __FILE__, Gdn::FactorySingleton);

class SMFCompatibilityPlugin extends Gdn_Plugin {
	/// CONSTRUCTOR ///
	public function __construct() {
      require_once(dirname(__FILE__).'/functions.smf.php');
	}

	/// PROPERTIES ///


	/// METHODS ///

   public function Base_BeforeDispatch_Handler($sender) {
      $request = Gdn::Request();
      $folder = ltrim($request->RequestFolder(), '/');
      $uri = ltrim($_SERVER['REQUEST_URI'], '/');

      // Fix the url in the request for routing.
      if (preg_match("`^{$folder}index.php/`", $uri)) {
         $request->PathAndQuery(substr($uri, strlen($folder)));
      }
   }

	public function Format($string) {
      try {
         $result = parse_bbc($string);
      } catch (Exception $ex) {
         $result = '<!-- Error: '.htmlspecialchars($ex->getMessage()).'-->'
            .Gdn_Format::Display($string);
      }
      return $result;
	}

	public function Setup() {
      $oldFormat = C('Garden.InputFormatter');

      if ($oldFormat != 'BBCode') {
         SaveToConfig([
            'Garden.InputFormatter' => 'BBCode',
            'Garden.InputFormatterBak' => $oldFormat]);
      }

      // Setup the default routes.
      $router = Gdn::Router();
      $router->SetRoute('\?board=(\d+).*$', 'categories/$1', 'Permanent');
      $router->SetRoute('index\.php/topic,(\d+).(\d+)\.html.*$', 'discussion/$1/x/$2lim', 'Permanent');
      $router->SetRoute('index\.php/board,(\d+)\.(\d+)\.html.*$', 'categories/$1/$2lim', 'Permanent');
      $router->SetRoute('\?action=profile%3Bu%3D(\d+).*$', 'profile/$1/x', 'Permanent');
      $router->SetRoute('index\.php/topic,(\d+)\.msg(\d+)\.html.*$', 'discussion/comment/$2/#Comment_$2', 'Permanent');
      $router->SetRoute('\?topic=(\d+).*$', 'discussion/$1/x/p1', 'Permanent');
	}

   public function OnDisable() {
      $oldFormat = C('Garden.InputFormatterBak');

      if ($oldFormat !== FALSE) {
         SaveToConfig('Garden.InputFormatter', $oldFormat);
      }
   }
}
