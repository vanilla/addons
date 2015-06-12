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
$PluginInfo['Spoilers'] = array(
   'Name' => 'Spoilers',
   'Description' => "Users may prevent accidental spoiler by wrapping text in [spoiler] tags. This requires the text to be clicked in order to read it.",
   'Version' => '1.2',
   'MobileFriendly' => TRUE,
   'RequiredApplications' => FALSE,
   'RequiredTheme' => FALSE,
   'RequiredPlugins' => FALSE,
   'HasLocale' => TRUE,
   'RegisterPermissions' => FALSE,
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com'
);

class SpoilersPlugin extends Gdn_Plugin {

   public function __construct() {
      // Whether to handle drawing quotes or leave it up to some other plugin
      $this->RenderSpoilers = C('Plugins.Spoilers.RenderSpoilers',TRUE);
   }

   public function AssetModel_StyleCss_Handler($Sender) {
      $Sender->AddCssFile('spoilers.css', 'plugins/Spoilers');
   }

   public function DiscussionController_Render_Before(&$Sender) {
      $this->PrepareController($Sender);
   }

   public function PostController_Render_Before(&$Sender) {
      $this->PrepareController($Sender);
   }

   public function MessagesController_Render_Before(&$Sender) {
      $this->PrepareController($Sender);
   }

   protected function PrepareController(&$Sender) {
      //if (!$this->RenderSpoilers) return;
      $Sender->AddJsFile('spoilers.js', 'plugins/Spoilers');
   }


   public function DiscussionController_AfterCommentFormat_Handler(&$Sender) {
      $this->RenderSpoilers($Sender);
   }

   public function PostController_AfterCommentFormat_Handler(&$Sender) {
      $this->RenderSpoilers($Sender);
   }

   public function PostController_AfterCommentPreviewFormat_Handler($Sender) {
      $Sender->EventArguments['Object']->FormatBody = &$Sender->Comment->Body;
      $this->RenderSpoilers($Sender);
   }

   public function MessagesController_BeforeConversationMessageBody_Handler(&$Sender) {
      $Sender->EventArguments['Object']->FormatBody = &$Sender->EventArguments['Message']->Body;
      $this->RenderSpoilers($Sender);
   }

   public function messagesController_beforeMessagesPopin_handler($sender, &$args) {
      if (val('Conversations', $args)) {
         foreach($args['Conversations'] as &$conversation) {
            if ($body = val('LastBody', $conversation)) {
               $conversation['LastBody'] = $this->replaceSpoilers($body,  val('LastFormat', $conversation));
            }
         }
      }
   }

   public function messagesController_beforeMessagesAll_handler($sender, &$args) {
      if (val('Conversations', $args)) {
         foreach($args['Conversations'] as &$conversation) {
            if ($body = val('LastBody', $conversation)) {
               $conversation['LastBody'] = $this->replaceSpoilers($body, val('LastFormat', $conversation));
            }
         }
      }
   }

   protected function replaceSpoilers($body, $format) {
      if (!$this->RenderSpoilers) {
         return;
      }
      $spoilerReplacement = T('Spoiler Replacement', T('Spoiler'));
      switch($format) {
         case 'Markdown':
            $body = preg_replace("/>!.*(\n|$)/", $spoilerReplacement.' ', $body);
            break;
         case 'BBCode':
            $body = preg_replace("/\[spoiler(?:=(?:&quot;)?([\d\w_',.? ]+)(?:&quot;)?)?\].*\[\/spoiler\]/siu", $spoilerReplacement, $body);
            break;
         case 'Html':
            $body = preg_replace('/<div class="Spoile[dr]">.*<\/div>/', $spoilerReplacement, $body);
            break;
      }
      return $body;
   }

   protected function RenderSpoilers(&$Sender) {
      if (!$this->RenderSpoilers || Gdn::PluginManager()->CheckPlugin('NBBC') ) {
         return;
      }

      $FormatBody = &$Sender->EventArguments['Object']->FormatBody;

      // Fix a wysiwyg but where spoilers
      $FormatBody = preg_replace('`<div>\s*(\[/?spoiler\])\s*</div>`', '$1', $FormatBody);

      $FormatBody = preg_replace_callback("/(\[spoiler(?:=(?:&quot;)?([\d\w_',.? ]+)(?:&quot;)?)?\])/siu", array($this, 'SpoilerCallback'), $FormatBody);
      $FormatBody = str_ireplace('[/spoiler]','</div></div>',$FormatBody);
   }

   protected function SpoilerCallback($Matches) {
      $Attribution = T('Spoiler: %s');
      $SpoilerText = (sizeof($Matches) > 2) ? $Matches[2] : NULL;
      if (is_null($SpoilerText)) $SpoilerText = '';
      else
         $SpoilerText = "<span>{$SpoilerText}</span>";
      $Attribution = sprintf($Attribution,$SpoilerText);
      return <<<BLOCKQUOTE
      <div class="UserSpoiler"><div class="SpoilerTitle">{$Attribution}</div><div class="SpoilerReveal"></div><div class="SpoilerText">
BLOCKQUOTE;
   }

   public function Setup() {
      // Nothing to do here!
   }

   public function Structure() {
      // Nothing to do here!
   }

}
