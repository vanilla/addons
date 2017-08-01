<?php if (!defined('APPLICATION')) exit();
/*
  Copyright 2008, 2009 Vanilla Forums Inc.
  This file is part of Garden.
  Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
  Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
  You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
  Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
 */

Gdn::factoryInstall('BBCodeFormatter', 'NBBCPlugin', __FILE__, Gdn::FactorySingleton);

class NBBCPlugin extends Gdn_Plugin {

   public $Class = 'BBCode';

   /// CONSTRUCTOR ///
   public function __construct($class = 'BBCode') {
      parent::__construct();
      $this->Class = $class;
   }

   /// PROPERTIES ///
   /// METHODS ///

   public function doAttachment($bbcode, $action, $name, $default, $params, $content) {
      $Medias = $this->media();
      $MediaID = $content;
      if (isset($Medias[$MediaID])) {
         $Media = $Medias[$MediaID];
//         decho($Media, 'Media');

         $Src = htmlspecialchars(Gdn_Upload::url(getValue('Path', $Media)));
         $Name = htmlspecialchars(getValue('Name', $Media));

         if (getValue('ImageWidth', $Media)) {
            return <<<EOT
<div class="Attachment Image"><img src="$Src" alt="$Name" /></div>
EOT;
         } else {
            return anchor($Name, $Src, 'Attachment File');
         }
      }

      return anchor(t('Attachment not found.'), '#', 'Attachment NotFound');
   }

   function doImage($bbcode, $action, $name, $default, $params, $content) {
      if ($action == BBCODE_CHECK)
         return true;
      $content = trim($bbcode->unHTMLEncode(strip_tags($content)));
      if (!$content && $default)
         $content = $default;

      if ($bbcode->isValidUrl($content, false))
         return "<img src=\"" . htmlspecialchars($content) . "\" alt=\""
            . htmlspecialchars(basename($content)) . "\" class=\"bbcode_img\" />";


//      if (preg_match("/\\.(?:gif|jpeg|jpg|jpe|png)$/i", $content)) {
//         if (preg_match("/^[a-zA-Z0-9_][^:]+$/", $content)) {
//            if (!preg_match("/(?:\\/\\.\\.\\/)|(?:^\\.\\.\\/)|(?:^\\/)/", $content)) {
//               $info = @getimagesize("{$bbcode->local_img_dir}/{$content}");
//               if ($info[2] == IMAGETYPE_GIF || $info[2] == IMAGETYPE_JPEG || $info[2] == IMAGETYPE_PNG) {
//                  return "<img src=\""
//                  . htmlspecialchars("{$bbcode->local_img_url}/{$content}") . "\" alt=\""
//                  . htmlspecialchars(basename($content)) . "\" width=\""
//                  . htmlspecialchars($info[0]) . "\" height=\""
//                  . htmlspecialchars($info[1]) . "\" class=\"bbcode_img\" />";
//               }
//            }
//         } else if ($bbcode->isValidURL($content, false)) {
//            return "<img src=\"" . htmlspecialchars($content) . "\" alt=\""
//            . htmlspecialchars(basename($content)) . "\" class=\"bbcode_img\" />";
//         }
//      }
      return htmlspecialchars($params['_tag']) . htmlspecialchars($content) . htmlspecialchars($params['_endtag']);
   }

   function doVideo($bbcode, $action, $name, $default, $params, $content) {
      list($width, $height) = Gdn_Format::getEmbedSize();
      list($type, $code) = explode(';', $default);
      switch ($type) {
         case 'youtube':
            return '<div class="Video P"><iframe width="'.$width.'" height="'.$height.'" src="https://www.youtube.com/embed/' . $code . '" frameborder="0" allowfullscreen></iframe></div>';
         default:
            return $content;
      }
   }

   function doYoutube($bbcode, $action, $name, $default, $params, $content) {
       if ($action == BBCODE_CHECK) return true;

       $videoId = is_string($default) ? $default : $bbcode->unHTMLEncode(strip_tags($content));

       return '<div class="Video P"><iframe width="560" height="315" src="https://www.youtube.com/embed/' . $videoId . '" frameborder="0" allowfullscreen></iframe></div>';
   }

   function doQuote($bbcode, $action, $name, $default, $params, $content) {
      if ($action == BBCODE_CHECK)
         return true;

      if (is_string($default)) {
         $defaultParts = explode(';', $default); // support vbulletin style quoting.
         $Url = array_pop($defaultParts);
         if (count($defaultParts) == 0) {
            $params['name'] = $Url;
         } else {
            $params['name'] = implode(';', $defaultParts);
            $params['url'] = $Url;
         }
      }

      $title = '';

      if (isset($params['name'])) {
         $username = trim($params['name']);
         $username = html_entity_decode($username, ENT_QUOTES, 'UTF-8');

         $User = Gdn::userModel()->getByUsername($username);
         if ($User)
            $UserAnchor = userAnchor($User);
         else
            $UserAnchor = anchor(htmlspecialchars($username, NULL, 'UTF-8'), '/profile/' . rawurlencode($username));

         $title = concatSep(' ', $title, $UserAnchor, t('Quote wrote', 'wrote'));
      }

      if (isset($params['date']))
         $title = concatSep(' ', $title, t('Quote on', 'on'), htmlspecialchars(trim($params['date'])));

      if ($title)
         $title = $title . ':';

      if (isset($params['url'])) {
         $url = trim($params['url']);

         if (is_numeric($url))
            $url = "/discussion/comment/$url#Comment_{$url}";
         elseif (!$bbcode->isValidURL($url))
            $url = '';

         if ($url)
            $title = concatSep(' ', $title, anchor('<span class="ArrowLink">»</span>', $url, ['class' => 'QuoteLink']));
      }

      if ($title)
         $title = "<div class=\"QuoteAuthor\">$title</div>";

      return "\n<blockquote class=\"Quote UserQuote\">\n"
      . $title . "\n<div class=\"QuoteText\">"
      . $content . "</div>\n</blockquote>\n";
   }

    /**
     * Renders spoilers.
     *
     * @param object $bbCode Instance of NBBC parsing
     * @param int $action Value of one of NBBC's defined constants.  Typically, this will be BBCODE_CHECK.
     * @param string $name Name of the tag
     * @param string $default Value of the _default parameter, from the $params array
     * @param array $params A standard set parameters related to the tag
     * @param string $content Value between the open and close tags, if any
     *
     * @return string HTML-formatted spoiler value
     */
    public function doSpoiler($bbCode, $action, $name, $default, $params, $content) {
        return Gdn_Format::spoilerHtml($content);
    }

    /**
     * Perform formatting against a string for the size tag
     *
     * @param object $bbCode Instance of NBBC parsing
     * @param int $action Value of one of NBBC's defined constants.  Typically, this will be BBCODE_CHECK.
     * @param string $name Name of the tag
     * @param string $default Value of the _default parameter, from the $params array
     * @param array $params A standard set parameters related to the tag
     * @param string $content Value between the open and close tags, if any
     *
     * @return string Formatted value
     */
   public function doSize($bbCode, $action, $name, $default, $params, $content) {
       // px and em are invalid modifiers for this value.  Lose 'em.
       $default = preg_replace('/(px|em)/i', '', $default);

       switch ($default) {
           case '0': $size = '.5em'; break;
           case '1': $size = '.67em'; break;
           case '2': $size = '.83em'; break;
           default:
           case '3': $size = '1.0em'; break;
           case '4': $size = '1.17em'; break;
           case '5': $size = '1.5em'; break;
           case '6': $size = '2.0em'; break;
           case '7': $size = '2.5em'; break;
       }

       return "<span style=\"font-size:$size\">$content</span>";
   }

   function doURL($bbcode, $action, $name, $default, $params, $content) {
      if ($action == BBCODE_CHECK) return true;

      $url = is_string($default) ? $default : $bbcode->unHTMLEncode(strip_tags($content));

      if ($bbcode->isValidURL($url)) {
         if ($bbcode->debug)
            print "ISVALIDURL<br />";
         if ($bbcode->url_targetable !== false && isset($params['target']))
            $target = " target=\"" . htmlspecialchars($params['target']) . "\"";
         else
            $target = "";

         if ($bbcode->url_target !== false)
            if (!($bbcode->url_targetable == 'override' && isset($params['target'])))
               $target = " target=\"" . htmlspecialchars($bbcode->url_target) . "\"";
            return '<a href="' . htmlspecialchars($url) . '" rel="nofollow" class="bbcode_url"' . $target . '>' . $content . '</a>';
      } else
         return htmlspecialchars($params['_tag']) . $content . htmlspecialchars($params['_endtag']);
   }

   public function format($result) {
      $result = str_replace(['[CODE]', '[/CODE]'], ['[code]', '[/code]'], $result);
      $result = $this->nBBC()->parse($result);
      return $result;
   }

   protected $_Media = NULL;
   public function media() {
      if ($this->_Media === NULL) {
         try {
            $i = Gdn::pluginManager()->getPluginInstance('FileUploadPlugin', Gdn_PluginManager::ACCESS_CLASSNAME);
            $m = $i->mediaCache();
         } catch (Exception $ex) {
            $m = [];
         }

         $media = [];
         foreach ($m as $key => $data) {
            foreach ($data as $row) {
               $media[$row->MediaID] = $row;
            }
         }
         $this->_Media = $media;
      }
      return $this->_Media;
   }

   protected $_NBBC = NULL;
   /**
    *
    * @return BBCode
    */
   public function nBBC() {
      if ($this->_NBBC === NULL) {
         require_once(dirname(__FILE__) . '/nbbc/nbbc.php');
         $BBCode = new $this->class();
         $BBCode->enable_smileys = false;
         $BBCode->setAllowAmpersand(TRUE);

         $BBCode->addRule('attach', [
            'mode' => BBCODE_MODE_CALLBACK,
            'method' => [$this, "DoAttachment"],
            'class' => 'image',
            'allow_in' => ['listitem', 'block', 'columns', 'inline', 'link'],
            'end_tag' => BBCODE_REQUIRED,
            'content' => BBCODE_REQUIRED,
            'plain_start' => "[image]",
            'plain_content' => [],
            ]);

         $BBCode->addRule('code', [
             'mode' => BBCODE_MODE_ENHANCED,
             'template' => "\n<pre>{\$_content/v}\n</pre>\n",
             'class' => 'code',
             'allow_in' => ['listitem', 'block', 'columns'],
             'content' => BBCODE_VERBATIM,
             'before_tag' => "sns",
             'after_tag' => "sn",
             'before_endtag' => "sn",
             'after_endtag' => "sns",
             'plain_start' => "\n<b>Code:</b>\n",
             'plain_end' => "\n",
         ]);


         $BBCode->addRule('quote', ['mode' => BBCODE_MODE_CALLBACK,
             'method' => [$this, "DoQuote"],
             'allow_in' => ['listitem', 'block', 'columns'],
             'before_tag' => "sns",
             'after_tag' => "sns",
             'before_endtag' => "sns",
             'after_endtag' => "sns",
             'plain_start' => "\n<b>Quote:</b>\n",
             'plain_end' => "\n",
         ]);
//
         $BBCode->addRule('spoiler', [
             'mode' => BBCODE_MODE_CALLBACK,
             'method' => [$this, "doSpoiler"],
             'allow_in' => ['listitem', 'block', 'columns'],
             'before_tag' => "sns",
             'after_tag' => "sns",
             'before_endtag' => "sns",
             'after_endtag' => "sns",
             'plain_start' => "\n",
             'plain_end' => "\n"
             ]);

         $BBCode->addRule('img', [
            'mode' => BBCODE_MODE_CALLBACK,
            'method' => [$this, "DoImage"],
            'class' => 'image',
            'allow_in' => ['listitem', 'block', 'columns', 'inline', 'link'],
            'end_tag' => BBCODE_REQUIRED,
            'content' => BBCODE_REQUIRED,
            'plain_start' => "[image]",
            'plain_content' => [],
            ]);

         $BBCode->addRule('snapback', [
             'mode' => BBCODE_MODE_ENHANCED,
             'template' => ' <a href="'.url('/discussion/comment/{$_content/v}#Comment_{$_content/v}', TRUE).'" class="SnapBack">»</a> ',
             'class' => 'code',
             'allow_in' => ['listitem', 'block', 'columns'],
             'content' => BBCODE_VERBATIM,
             'before_tag' => "sns",
             'after_tag' => "sn",
             'before_endtag' => "sn",
             'after_endtag' => "sns",
             'plain_start' => "\n<b>Snapback:</b>\n",
             'plain_end' => "\n",
         ]);

         $BBCode->addRule('video', ['mode' => BBCODE_MODE_CALLBACK,
             'method' => [$this, "DoVideo"],
             'allow_in' => ['listitem', 'block', 'columns'],
             'before_tag' => "sns",
             'after_tag' => "sns",
             'before_endtag' => "sns",
             'after_endtag' => "sns",
             'plain_start' => "\n<b>Video:</b>\n",
             'plain_end' => "\n",
         ]);

          $BBCode->addRule('youtube', [
              'mode' => BBCODE_MODE_CALLBACK,
              'method' => [$this, 'DoYouTube'],
              'class' => 'link',
              'allow_in' => ['listitem', 'block', 'columns', 'inline'],
              'content' => BBCODE_REQUIRED,
              'plain_start' => "\n<b>Video:</b>\n",
              'plain_end' => "\n",
              'plain_content' => ['_content', '_default'],
              'plain_link' => ['_default', '_content']
          ]);

          $BBCode->addRule('hr', [
              'simple_start' => "",
              'simple_end' => "",
              'allow_in' => ['listitem', 'block', 'columns'],
              'before_tag' => "sns",
              'after_tag' => "sns",
              'before_endtag' => "sns",
              'after_endtag' => "sns",
              'plain_start' => "\n",
              'plain_end' => "\n"
          ]);

          $BBCode->addRule('attachment', [
              'mode' => BBCODE_MODE_CALLBACK,
              'method' => [$this, "RemoveAttachment"],
              'class' => 'image',
              'allow_in' => ['listitem', 'block', 'columns', 'inline', 'link'],
              'end_tag' => BBCODE_REQUIRED,
              'content' => BBCODE_REQUIRED,
              'plain_start' => "[image]",
              'plain_content' => [],
          ]);


          $BBCode->addRule('url', [
             'mode' => BBCODE_MODE_CALLBACK,
             'method' => [$this, 'DoURL'],
             'class' => 'link',
             'allow_in' => ['listitem', 'block', 'columns', 'inline'],
             'content' => BBCODE_REQUIRED,
             'plain_start' => "<a rel=\"nofollow\" href=\"{\$link}\">",
             'plain_end' => "</a>",
             'plain_content' => ['_content', '_default'],
             'plain_link' => ['_default', '_content']
         ]);

          /**
           * Default size tag needs to be a little more flexible.  The original NBBC rule was copied here and the regex
           * was updated to meet our new criteria.
           */
          $BBCode->addRule('size', [
              'mode' => BBCODE_MODE_CALLBACK,
              'allow' => ['_default' => '/^[0-9.]+(em|px)?$/D'],
              'method' => [$this, 'doSize'],
              'class' => 'inline',
              'allow_in' => ['listitem', 'block', 'columns', 'inline', 'link'],
          ]);

          // Prevent unsupported tags from displaying
          $BBCode->addRule('table', []);
          $BBCode->addRule('tr', []);
          $BBCode->addRule('td', []);

         $this->EventArguments['BBCode'] = $BBCode;
         $this->fireEvent('AfterNBBCSetup');
         $this->_NBBC = $BBCode;
      }
      return $this->_NBBC;
   }

   public function removeAttachment() {
       // We dont need this since we show attachments.
       return '<!-- PhpBB Attachments -->';
   }

   public function setup() {

   }

}
