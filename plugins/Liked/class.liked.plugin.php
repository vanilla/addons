<?php if (!defined('APPLICATION')) exit();

class LikedPlugin extends Gdn_Plugin {

	public function DiscussionController_Render_Before($sender) {
      $fB_SDK = <<<EOD
<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_GB/all.js#xfbml=1";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
EOD;
      $sender->AddAsset('Panel', $fB_SDK);
	}

	public function DiscussionController_AfterDiscussionBody_Handler($sender) {
      echo '<div class="fb-like" data-href="';
      echo Gdn_Url::Request(true, true, true);
      echo '" data-send="false" data-width="450" data-show-faces="false" data-font="lucida grande"></div>';
	}

   public function Setup() {
      // No setup required.
   }
}

