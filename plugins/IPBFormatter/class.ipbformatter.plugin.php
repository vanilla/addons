<?php if (!defined('APPLICATION')) exit();
/**
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license Proprietary
 */

$PluginInfo['IPBFormatter'] = array(
    'Name' => 'IPB Formatter',
    'Description' => 'Formats posts imported from Invision Power Board.',
    'Version' => '1.0',
    'RequiredApplications' => array('Vanilla' => '2.0.18'),
    'RequiredPlugins' => FALSE,
    'HasLocale' => FALSE,
    'Author' => "Todd Burry",
    'AuthorEmail' => 'todd@vanillaforums.com',
    'AuthorUrl' => 'http://vanillaforums.com/profile/todd'
);

Gdn::FactoryInstall('IPBFormatter', 'IPBFormatterPlugin', __FILE__, Gdn::FactorySingleton);

class IPBFormatterPlugin extends Gdn_Plugin {
   /// Methods ///
   
   public function Format($String) {
      $String = str_replace(array('&quot;', '&#39;', '&#58;', 'Â'), array('"', "'", ':', ''), $String);
      $String = str_replace('<#EMO_DIR#>', 'default', $String);
      $String = str_replace('<{POST_SNAPBACK}>', '<span class="SnapBack">»</span>', $String);

      // There is an issue with using uppercase code blocks, so they're forced to lowercase here
      $String = str_replace(array('[CODE]', '[/CODE]'), array('[code]', '[/code]'), $String);

      /**
       * IPB inserts line break markup tags at line breaks.  They need to be removed in code blocks.
       * The original newline/line break should be left intact, so whitespace will be preserved in the pre tag.
       */
      $String = preg_replace_callback(
         '/\[code\].*?\[\/code\]/is',
         function($CodeBlocks) {
            return str_replace(array('<br />'), array(''), $CodeBlocks[0]);
         },
         $String
      );

      // If there is a really long string, it could cause a stack overflow in the bbcode parser.
      // Not much we can do except try and chop the data down a touch.

      // 1. Remove html comments.
      $String = preg_replace('/<!--(.*)-->/Uis', '', $String);

      // 2. Split the string up into chunks.
      $Strings = (array)$String;
      $Result = '';
      foreach ($Strings as $String) {
         $Result .= $this->NBBC()->Parse($String);
      }

      // Make sure to clean filter the html in the end.
      $Config = array(
       'anti_link_spam' => array('`.`', ''),
       'comment' => 1,
       'cdata' => 3,
       'css_expression' => 1,
       'deny_attribute' => 'on*',
       'elements' => '*-applet-form-input-textarea-iframe-script-style', // object, embed allowed
       'keep_bad' => 0,
       'schemes' => 'classid:clsid; href: aim, feed, file, ftp, gopher, http, https, irc, mailto, news, nntp, sftp, ssh, telnet; style: nil; *:file, http, https', // clsid allowed in class
       'valid_xml' => 2
      );

      $Spec = 'object=-classid-type, -codebase; embed=type(oneof=application/x-shockwave-flash)';
      $Result = htmLawed($Result, $Config, $Spec);

      return $Result;
   }

   /**
    *
    * @var BBCode
    */
   protected $_NBBC;

   /**
    * @return BBCode;
    */
   public function NBBC() {
      if ($this->_NBBC === NULL) {
         require_once PATH_PLUGINS.'/HtmLawed/htmLawed/htmLawed.php';
         
         $Plugin = new NBBCPlugin('BBCodeRelaxed');
         $this->_NBBC = $Plugin->NBBC();
         $this->_NBBC->ignore_newlines = TRUE;
         $this->_NBBC->enable_smileys = FALSE;
         
         $this->_NBBC->AddRule('attachment', array(
            'mode' => BBCODE_MODE_CALLBACK,
            'method' => array($this, "DoAttachment"),
            'class' => 'image',
            'allow_in' => Array('listitem', 'block', 'columns', 'inline', 'link'),
            'end_tag' => BBCODE_PROHIBIT,
            'content' => BBCODE_PROHIBIT,
            'plain_start' => "[image]",
            'plain_content' => Array(),
            ));
      }
      return $this->_NBBC;
   }
   
   protected $_Media = NULL;
   public function Media() {
      if ($this->_Media === NULL) {
         try {
            $I = Gdn::PluginManager()->GetPluginInstance('FileUploadPlugin', Gdn_PluginManager::ACCESS_CLASSNAME);
            $M = $I->MediaCache();
         } catch (Exception $Ex) {
            $M = array();
         }
         
         $Media = array();
         foreach ($M as $Key => $Data) {
            foreach ($Data as $Row) {
               $Media[$Row->MediaID] = $Row;
            }
         }
         $this->_Media = $Media;
      }
      return $this->_Media;
   }
   
   public function DoAttachment($bbcode, $action, $name, $default, $params, $content) {
      $Medias = $this->Media();
      $Parts = explode(':', $default);
      $MediaID = $Parts[0];
      if (isset($Medias[$MediaID])) {
         $Media = $Medias[$MediaID];
//         decho($Media, 'Media');
         
         $Src = htmlspecialchars(Gdn_Upload::Url(GetValue('Path', $Media)));
         $Name = htmlspecialchars(GetValue('Name', $Media));
         return <<<EOT
<div class="Attachment"><img src="$Src" alt="$Name" /></div>
EOT;
      }
      
      return '';
   }
}