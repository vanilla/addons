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
   'Description' => "Wrapping text in [spoiler] tags requires the text to be clicked in order to read it. Adds >! spoiler for Markdown.",
   'Version' => '1.3',
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
      $this->ParseSpoilers($Sender);
   }

   /** Render spoilers for preview. */
   public function PostController_AfterCommentFormat_Handler(&$Sender) {
      $this->ParseSpoilers($Sender);
   }

   /**
    * Add spoilers to the Markdown parser's to-do list.
    *
    * The doSpoilers method and its dependencies are in our Markdown extension in core.
    *
    * @param $Sender
    * @param $Args
    */
   public function MarkdownVanilla_Init_Handler($Sender, &$Args) {
      $Args['block_gamut'] += array("doSpoilers" => 55);
   }

   /**
    * Add 'spoiler' option to Advanced Editor.
    *
    * @param $Sender
    * @param $Args
    */
   public function EditorPlugin_toolbarConfig_Handler($Sender, &$Args) {
      $Args['format'] += array(
         'spoiler' => array(
            'text' => T('Spoiler'),
            'command' => 'spoiler',
            'value' => 'spoiler',
            'class' => '',
            'sort' => 8
         )
      );
   }

   /**
    * Parse default BBCode-style spoiler in body of comment to HTML via hooks.
    *
    * @todo Non-HTML spoiler text doesn't get wrapped in 'p' tags correctly (differs from Markdown parser).
    * @param $Sender
    */
   protected function ParseSpoilers(&$Sender) {
      if (!$this->RenderSpoilers) {
         return;
      }
      
      $FormatBody = &$Sender->EventArguments['Object']->FormatBody;
      
      // Fix a wysiwyg but where spoilers
      $FormatBody = preg_replace('`<div>\s*(\[/?spoiler\])\s*</div>`', '$1', $FormatBody);
      
      $FormatBody = preg_replace_callback("/(\[spoiler(?:=(?:&quot;)?([\d\w_',.? ]+)(?:&quot;)?)?\])/siu",
         array($this, 'SpoilerCallback'), $FormatBody);
      $FormatBody = str_ireplace('[/spoiler]', SpoilerClose(), $FormatBody);
   }

   /**
    * Callback for ParseSpoilers().
    *
    * @param $Matches
    * @return string
    */
    protected function SpoilerCallback($Matches) {
      $Author = (sizeof($Matches) > 2) ? $Matches[2] : NULL;
      return SpoilerOpen($Author, FALSE);
   }
   
   public function Setup() {
      // Nothing to do here!
   }
}


if (!function_exists('FormatSpoiler')) {
   /**
    * HTML formatting for spoiler text.
    *
    * Convenience function for calling open & close together.
    *
    * @param string $SpoilerText.
    * @return string HTML.
    */
   function FormatSpoiler($SpoilerText) {
      return SpoilerOpen().$SpoilerText.SpoilerClose();
   }
}

if (!function_exists('FormatSpoilerOpen')) {
   /**
    * Opening HTML for a spoiler tag.
    *
    * @param mixed $Author
    * @return string HTML.
    */
   function SpoilerOpen($Author = NULL) {
      $Attribution = T('Spoiler: %s');

      if (is_null($Author)) {
         $Author = '';
      } else {
         $Author = "<span>{$Author}</span>";
      }

      $Attribution = sprintf($Attribution,$Author);

      return <<<BLOCKQUOTE
      <div class="UserSpoiler"><div class="SpoilerTitle">{$Attribution}</div><div class="SpoilerReveal"></div><div class="SpoilerText">
BLOCKQUOTE;
   }
}

if (!function_exists('FormatSpoilerClose')) {
   /**
    * Closing HTML for a spoiler tag.
    *
    * @return string
    */
   function SpoilerClose() {
      return '</div></div>';
   }
}