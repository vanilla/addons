<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

$PluginInfo['NBBC'] = array(
   'Description' => 'Adapts The New BBCode Parser to work with Vanilla.',
   'Version' => '1.0a',
   'RequiredApplications' => array('Vanilla' => '2.0.2a'),
   'RequiredTheme' => FALSE,
   'RequiredPlugins' => FALSE,
   'HasLocale' => FALSE,
   'Author' => "Todd Burry",
   'AuthorEmail' => 'todd@vanillaforums.com',
   'AuthorUrl' => 'http://vanillaforums.com/profile/todd'
);

Gdn::FactoryInstall('BBCodeFormatter', 'NBBCPlugin', __FILE__, Gdn::FactorySingleton);

class NBBCPlugin extends Gdn_Plugin {
	/// CONSTRUCTOR ///
	public function __construct() {
      require_once(dirname(__FILE__).'/nbbc/nbbc.php');
      $BBCode = new BBCode();
      $BBCode->smiley_url = Url('/plugins/NBBC/nbbc/smileys');
      $BBCode->SetAllowAmpersand(TRUE);

      $this->_BBCode = $BBCode;
	}

	/// PROPERTIES ///

   /** @var BBCode */
   protected $_BBCode;

	/// METHODS ///

   public function Base_Render_Before($Sender) {
      $Sender->AddCssFile('nbbc.css', 'plugins/NBBC');
   }

	public function Format($String) {
      $Result = $this->_BBCode->Parse($String);
      return $Result;
	}

	public function Setup() {
	}
}