<?php if (!defined('APPLICATION')) exit();

$PluginInfo['MeAction'] = array(
   'Description' => 'Gives special formatting to IRC-style /me actions.',
   'Version' => '1.0',
   'RequiredApplications' => array('Vanilla' => '2.1a'),
   'MobileFriendly' => TRUE,
   'Author' => "Lincoln Russell",
   'AuthorEmail' => 'lincoln@vanillaforums.com',
   'AuthorUrl' => 'http://lincolnwebs.com'
);

class MeActionPlugin extends Gdn_Plugin {
   public function DiscussionController_Render_Before($Sender) {
		$this->AddMeAction($Sender);
	}
	
	public function MessagesController_Render_Before($Sender) {
		$this->AddMeAction($Sender);
	}
	
	private function AddMeAction($Sender) {
		$Sender->AddJsFile('plugins/MeAction/js/meaction.js');
		$Sender->AddCssFile('plugins/MeAction/design/meaction.css');
	}

	/**
	 * Enable the formatter in Gdn_Format::Mentions.
	 */
   public function Setup() {
      SaveToConfig('Garden.Format.MeActions', TRUE);
   }
   
   /**
	 * Disable the formatter in Gdn_Format::Mentions.
	 */
   public function OnDisable() {
      SaveToConfig('Garden.Format.MeActions', FALSE);
   }
}
