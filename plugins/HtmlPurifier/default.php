<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

/* Note on updating the HtmlPurifier version.
 * Vanilla includes a Vimeo filter which will not work unless HtmlPurifier is rebuilt. Here are instructions:
 * 1. Download the full distribution of Html Purifier.
 * 2. Copy the following files from the existing plugin to HTML Purifier:
 *  a) Filter/Vimeo.php
 *  b) ConfigSchema/schema/Filter.Vimeo.txt
 * 3. From the command line run the following commands from HTML Purifier's maintenance directory:
 *  a) php generate-schema-cache.php
 *  b) php generate-standalone.php
 * 4. Copy the resulting standalone files back to vanilla's plugin.
 *
 */

$PluginInfo['HTMLPurifier'] = array(
   'Description' => 'DEPRECATED. Adapts HtmlPurifier to work with Garden. This plugin is maintained at http://github.com/vanillaforums/Addons.',
   'Version' => '1.4.2.0.1',
   'RequiredApplications' => NULL, 
   'RequiredTheme' => FALSE, 
   'RequiredPlugins' => FALSE,
   'HasLocale' => FALSE,
   'Author' => "Todd Burry",
   'AuthorEmail' => 'todd@vanillaforums.com',
   'AuthorUrl' => 'http://vanillaforums.org/profile/todd'
);
Gdn::FactoryInstall('HtmlFormatter', 'HTMLPurifierPlugin', __FILE__, Gdn::FactorySingleton);

class HTMLPurifierPlugin extends Gdn_Plugin {
	/// CONSTRUCTOR ///
	public function __construct() {
      require_once(dirname(__FILE__).'/htmlpurifier/HTMLPurifier.standalone.php');
      spl_autoload_register(array('HTMLPurifier_Bootstrap', 'autoload'));
	}
	
	/// PROPERTIES ///
	protected $_HtmlPurifier;
	
	/// METHODS ///

   public function SetupHTMLPurifier() {
      $HPConfig = HTMLPurifier_Config::createDefault();
		$HPConfig->set('HTML.Doctype', 'XHTML 1.0 Strict');
		// Get HtmlPurifier configuration settings from Garden
		$HPSettings = Gdn::Config('HtmlPurifier');
		if(is_array($HPSettings)) {
			foreach ($HPSettings as $Namespace => $Setting) {
				foreach ($Setting as $Name => $Value) {
					// Assign them to htmlpurifier
					$HPConfig->set($Namespace.'.'.$Name, $Value);
				}
			}
		}
		$this->_HtmlPurifier = new HTMLPurifier($HPConfig);
   }

	public function Format($Html) {
      if (!$this->_HtmlPurifier)
         $this->SetupHtmlPurifier();
		return $this->_HtmlPurifier->purify($Html);
	}
	
	public function Setup() {
		if (!file_exists(PATH_CACHE.'/HtmlPurifier')) mkdir(PATH_CACHE.'/HtmlPurifier');
	}
}