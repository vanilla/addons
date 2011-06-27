<?php

if (!defined('APPLICATION'))
   exit();
/*
  Copyright 2008, 2009 Vanilla Forums Inc.
  This file is part of Garden.
  Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
  Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
  You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
  Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
 */

$PluginInfo['NBBC'] = array(
    'Description' => 'Adapts The New BBCode Parser to work with Vanilla.',
    'Version' => '1.0.3b',
    'RequiredApplications' => array('Vanilla' => '2.0.2a'),
    'RequiredTheme' => FALSE,
    'RequiredPlugins' => FALSE,
    'HasLocale' => FALSE,
    'Author' => "Todd Burry",
    'AuthorEmail' => 'todd@vanillaforums.com',
    'AuthorUrl' => 'http://vanillaforums.com/profile/todd'
);

Gdn::FactoryInstall('BBCodeFormatter', 'NBBCPlugin', __FILE__, Gdn::FactorySingleton);

class NBBCPlugin extends Gdn_Plugin {

   /// CONSTRUCTOR ///
   public function __construct() {
      parent::__construct();
      
      require_once(dirname(__FILE__) . '/nbbc/nbbc.php');
      $BBCode = new BBCode();
      $BBCode->smiley_url = Url('/plugins/NBBC/design/smileys');
      $BBCode->SetAllowAmpersand(TRUE);


      $BBCode->AddRule('code',
      Array(
          'mode' => BBCODE_MODE_ENHANCED,
          'template' => "\n<pre>{\$_content/v}\n</pre>\n",
          'class' => 'code',
          'allow_in' => Array('listitem', 'block', 'columns'),
          'content' => BBCODE_VERBATIM,
          'before_tag' => "sns",
          'after_tag' => "sn",
          'before_endtag' => "sn",
          'after_endtag' => "sns",
          'plain_start' => "\n<b>Code:</b>\n",
          'plain_end' => "\n",
      ));

      $BBCode->AddRule('quote',
      array('mode' => BBCODE_MODE_CALLBACK,
          'method' => array($this, "DoQuote"),
          'allow_in' => Array('listitem', 'block', 'columns'),
          'before_tag' => "sns",
          'after_tag' => "sns",
          'before_endtag' => "sns",
          'after_endtag' => "sns",
          'plain_start' => "\n<b>Quote:</b>\n",
          'plain_end' => "\n",
      ));

      $BBCode->AddRule('spoiler',
      Array(
          'mode' => BBCODE_MODE_ENHANCED,
          'template' => "\n".'<div class="UserSpoiler">
<div class="SpoilerTitle">'.T('Spoiler').': </div>
<div class="SpoilerReveal"></div>
<div class="SpoilerText" style="display: none;">{$_content/v}</div></div>'."\n",
          'class' => 'code',
          'allow_in' => Array('listitem', 'block', 'columns'),
          'content' => BBCODE_VERBATIM,
          'before_tag' => "sns",
          'after_tag' => "sn",
          'before_endtag' => "sn",
          'after_endtag' => "sns",
          'plain_start' => "\n<b>Code:</b>\n",
          'plain_end' => "\n",
      ));

      $this->_BBCode = $BBCode;
   }

   /// PROPERTIES ///

   /** @var BBCode */
   protected $_BBCode;

   /// METHODS ///

   function DoQuote($bbcode, $action, $name, $default, $params, $content) {
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
         $title = ConcatSep(' ', $title, Anchor(htmlspecialchars($username, NULL, 'UTF-8'), '/profile/'.rawurlencode($username)), T('Quote wrote', 'wrote'));
      }

      if (isset($params['date']))
         $title = ConcatSep(' ', $title, T('Quote on', 'on'), htmlspecialchars(trim($params['date'])));

      if ($title)
         $title = $title.':';

      if (isset($params['url'])) {
         $url = trim($params['url']);

         if (is_numeric($url))
            $url = "/discussion/comment/$url#Comment_{$url}";
         elseif (!$bbcode->IsValidURL($url))
            $url = '';

         if ($url)
            $title = ConcatSep(' ', $title, Anchor('<span class="ArrowLink">»</span>', $url, array('class' => 'QuoteLink')));
      }

      if ($title)
         $title = "<div class=\"QuoteAuthor\">$title</div>";
      
      return "\n<blockquote class=\"UserQuote\">\n"
      . $title . "\n<div class=\"QuoteText\">"
      . $content . "</div>\n</blockquote>\n";
   }

   public function Format($String) {
      $Result = $this->_BBCode->Parse($String);
      return "<!-- BBCode -->\n".$Result."<!-- End BBCode -->";
   }

   public function Setup() {
      
   }

}