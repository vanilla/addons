<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

// Define the plugin:
$PluginInfo['vBulletinCompatibility'] = array(
   'Name' => 'vBulletin Compatibility Mode',
   'Description' => "This plugin hooks into Garden and applies tweaks to help ease the transition from vBulleting to Vanilla.",
   'Version' => '1.0',
   'MobileFriendly' => TRUE,
   'RequiredApplications' => FALSE,
   'RequiredTheme' => FALSE, 
   'RequiredPlugins' => FALSE,
   'HasLocale' => FALSE,
   'RegisterPermissions' => FALSE,
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com',
   'Hidden' => TRUE
);

class VbulletinCompatibilityPlugin extends Gdn_Plugin {
   
   public function __construct() {
   }
   
   public function Gdn_Router_BeforeLoadRoutes_Handler($Sender) {
      $VbRoutes = array(
          'forumdisplay\.php\?f=(\d+)'    => array('vanilla/categories/$1', 'Permanent'),
          'showthread\.php\?t=(\d+)'      => array('vanilla/discussion/$1', 'Permanent'),
          'showthread\.php\?p=(\d+)'      => array('vanilla/discussion/comment/$1', 'Permanent'),
          'member\.php\?u=(\d+)'          => array('dashboard/profile/$1/x', 'Permanent')
      );
      
      $Sender->EventArguments['Routes'] = array_merge($Sender->EventArguments['Routes'], $VbRoutes);
   }
   
}