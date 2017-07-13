<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

class VbulletinCompatibilityPlugin extends Gdn_Plugin {
   
   public function __construct() {
   }
   
   public function Gdn_Router_BeforeLoadRoutes_Handler($Sender) {
      $VbRoutes = [
          'forumdisplay\.php\?f=(\d+)'    => ['categories/$1', 'Permanent'],
          'showthread\.php\?t=(\d+)'      => ['discussion/$1', 'Permanent'],
          'showthread\.php\?p=(\d+)'      => ['discussion/comment/$1', 'Permanent'],
          'member\.php\?u=(\d+)'          => ['profile/$1/x', 'Permanent']
      ];
      
      $Sender->EventArguments['Routes'] = array_merge($Sender->EventArguments['Routes'], $VbRoutes);
   }
   
}
