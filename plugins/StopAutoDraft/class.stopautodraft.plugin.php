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
$PluginInfo['StopAutoDraft'] = array(
   'Name' => 'Stop Auto Draft',
   'Description' => 'Comments are auto-saved as a user types. This plugin disables that feature so that drafts are only saved if the "Save Draft" button is clicked.',
   'Version' => '1.1',
   'Author' => "Mark O'Sullivan",
   'AuthorEmail' => 'mark@vanillaforums.com',
   'AuthorUrl' => 'http://markosullivan.ca'
);

class StopAutoDraftPlugin extends Gdn_Plugin {

   public function DiscussionController_Render_Before($Sender) {
		$this->_NoDrafting($Sender);
	}
   public function PostController_Render_Before($Sender) {
		$this->_NoDrafting($Sender);
	}
	private function _NoDrafting($Sender) {
	   $Sender->RemoveJsFile('autosave.js');
		$Sender->Head->AddString('
<script type="text/javascript">
jQuery(document).ready(function($) {
   $.fn.autosave = function(opts) {
		return;
	}
});
</script>
');
   }
	
   public function OnDisable() { }
   public function Setup() { }
	
}