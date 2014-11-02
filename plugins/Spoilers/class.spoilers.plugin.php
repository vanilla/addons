<?php if (!defined('APPLICATION')) exit();
/**
 * Spoilers plugin.
 *
 * @copyright 2010-2014 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 */

// Define the plugin:
$PluginInfo['Spoilers'] = array(
   'Name' => 'Spoilers',
   'Description' => "Users may prevent accidental spoiler by wrapping text in [spoiler] tags. This requires the text to be clicked in order to read it.",
   'Version' => '1.2',
   'MobileFriendly' => TRUE,
   'HasLocale' => TRUE,
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com'
);

class SpoilersPlugin extends Gdn_Plugin {
   
   public function __construct() {
      // Whether to handle drawing spoilers or leave it up to some other plugin.
      $this->RenderSpoilers = C('Plugins.Spoilers.RenderSpoilers', TRUE);
   }

   /** Add required CSS for spoilers. */
   public function AssetModel_StyleCss_Handler($Sender) {
      $Sender->AddCssFile('spoilers.css', 'plugins/Spoilers');
   }

   /** Hook in required setup. */
   public function DiscussionController_Render_Before(&$Sender) {
      $this->PrepareController($Sender);
   }

   /** Hook in required setup. */
   public function PostController_Render_Before(&$Sender) {
      $this->PrepareController($Sender);
   }

   /** Add required Javascript to pages where spoilers are used. */
   protected function PrepareController(&$Sender) {
      $Sender->AddJsFile('spoilers.js', 'plugins/Spoilers');
   }
   
   /** Render spoilers in comments. */
   public function DiscussionController_AfterCommentFormat_Handler(&$Sender) {
      $this->RenderSpoilers($Sender);
   }

   /** Render spoilers for preview. */
   public function PostController_AfterCommentFormat_Handler(&$Sender) {
      $this->RenderSpoilers($Sender);
   }

   /**
    * Create spoiler HTML in body of comment via hooks.
    *
    * @param $Sender
    */
   protected function RenderSpoilers(&$Sender) {
      if (!$this->RenderSpoilers) {
         return;
      }
      
      $FormatBody = &$Sender->EventArguments['Object']->FormatBody;
      
      // Fix a wysiwyg but where spoilers
      $FormatBody = preg_replace('`<div>\s*(\[/?spoiler\])\s*</div>`', '$1', $FormatBody);
      
      $FormatBody = preg_replace_callback("/(\[spoiler(?:=(?:&quot;)?([\d\w_',.? ]+)(?:&quot;)?)?\])/siu", array($this, 'SpoilerCallback'), $FormatBody);
      $FormatBody = str_ireplace('[/spoiler]','</div></div>',$FormatBody);
   }

   /**
    * Callback for RenderSpoilers().
    *
    * @param $Matches
    * @return string
    */
   protected function SpoilerCallback($Matches) {
      $Attribution = T('Spoiler: %s');
      $SpoilerText = (sizeof($Matches) > 2) ? $Matches[2] : NULL;

      if (is_null($SpoilerText)) {
         $SpoilerText = '';
      } else {
         $SpoilerText = "<span>{$SpoilerText}</span>";
      }

      $Attribution = sprintf($Attribution,$SpoilerText);

      return <<<BLOCKQUOTE
      <div class="UserSpoiler"><div class="SpoilerTitle">{$Attribution}</div><div class="SpoilerReveal"></div><div class="SpoilerText">
BLOCKQUOTE;
   }
   
   public function Setup() {
      // Nothing to do here!
   }
}
