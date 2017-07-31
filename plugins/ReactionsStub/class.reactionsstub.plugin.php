<?php if (!defined('APPLICATION')) exit();

class ReactionsStubPlugin extends Gdn_Plugin {

   public function __construct() {}

   public function RootController_React_Create($sender, $recordType, $reaction, $iD) {
      $sender->Render('blank', 'utility', 'dashboard');
   }

   private function AddJs($sender) {
      $sender->AddJsFile('jquery-ui.js');
      $sender->AddJsFile('reactions.js', 'plugins/ReactionsStub');
   }

   public function DiscussionController_Render_Before($sender) {
      $sender->AddCssFile('reactions.css', 'plugins/ReactionsStub');
      $this->AddJs($sender);
   }

   public function Setup() {}

}

if (!function_exists('WriteReactions')) {

   function WriteReactions($row) {
      $reactions = <<<REACTIONS
<div class="Reactions"><span class="Flag ToggleFlyout"><a class="Hijack ReactButton ReactButton-Flag" href="" title="Flag" rel="nofollow"><span class="ReactSprite ReactFlag"></span> <span class="ReactLabel">Flag</span></a>
   <ul class="Flyout MenuItems Flags" style="display: none;"><li><a class="Hijack ReactButton ReactButton-Spam" href="/react/comment/spam?id=25011203" title="Spam" rel="nofollow"><span class="ReactSprite ReactSpam"></span> <span class="ReactLabel">Spam</span></a>
   </li><li><a class="Hijack ReactButton ReactButton-Abuse" href="/react/comment/abuse?id=25011203" title="Abuse" rel="nofollow"><span class="ReactSprite ReactAbuse"></span> <span class="ReactLabel">Abuse</span></a>
   </li></ul></span>&nbsp;<span class="React"><span class="ReactButtons"><a class="Hijack ReactButton ReactButton-Promote" href="/react/comment/promote?id=25011203" title="Promote" rel="nofollow"><span class="ReactSprite ReactPromote"></span> <span class="ReactLabel">Promote</span></a>
   <a class="Hijack ReactButton ReactButton-Insightful" href="/react/comment/insightful?id=25011203" title="Insightful" rel="nofollow"><span class="ReactSprite ReactInsightful"></span> <span class="ReactLabel">Insightful</span></a>
   <a class="Hijack ReactButton ReactButton-Awesome" href="/react/comment/awesome?id=25011203" title="Awesome" rel="nofollow"><span class="ReactSprite ReactAwesome"></span> <span class="ReactLabel">Awesome</span></a>
   <a class="Hijack ReactButton ReactButton-LOL" href="/react/comment/lol?id=25011203" title="LOL" rel="nofollow"><span class="ReactSprite ReactLOL"></span> <span class="ReactLabel">LOL</span></a>
   </span></span></div>
REACTIONS;

      echo $reactions;
   }

}

