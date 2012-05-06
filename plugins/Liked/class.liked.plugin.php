<?php if (!defined('APPLICATION')) exit();

// Define the plugin:
$PluginInfo['Liked'] = array(
   'Name' => 'Liked',
   'Description' => 'Adds the facebook like feature to your discussions.',
   'Version' => '1.5',
   'Author' => "Gary Mardell",
   'AuthorEmail' => 'gary@vanillaplugins.com',
   'AuthorUrl' => 'http://garymardell.co.uk'
);

class LikedPlugin extends Gdn_Plugin {
	
	public function DiscussionController_Render_Before(&$Sender) {
   	$Sender->AddJsFile('http://connect.facebook.net/en_US/all.js#xfbml=1');
	}
	
	public function DiscussionController_BeforeDiscussionOptions_Handler(&$Sender) {
      echo '<fb:like href="';
      echo Gdn_Url::Request(true, true, true);
      echo '" layout="button_count" width="60" show_faces="false" font="lucida grande"></fb:like>';
	}

   public function Setup() {
      // No setup required.
   }
}

