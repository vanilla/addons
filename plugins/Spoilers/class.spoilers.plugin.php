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
        $this->renderSpoilers = C('Plugins.Spoilers.RenderSpoilers',TRUE);
    }

    public function assetModel_styleCss_handler($sender) {
        $sender->addCssFile('spoilers.css', 'plugins/Spoilers');
    }

    public function discussionController_render_before(&$sender) {
        $this->prepareController($sender);
    }

    public function postController_render_before(&$sender) {
        $this->prepareController($sender);
    }

    public function messagesController_render_before(&$sender) {
        $this->prepareController($sender);
    }

    protected function prepareController(&$sender) {
        //if (!$this->RenderSpoilers) return;
        $sender->addJsFile('spoilers.js', 'plugins/Spoilers');
    }

    public function discussionController_afterCommentFormat_handler(&$sender) {
        $this->renderSpoilers($sender);
    }

    public function postController_afterCommentFormat_handler(&$sender) {
        $this->renderSpoilers($sender);
    }

    public function postController_afterCommentPreviewFormat_handler($sender) {
        $sender->EventArguments['Object']->FormatBody = &$sender->Comment->Body;
        $this->renderSpoilers($sender);
    }

    public function messagesController_beforeConversationMessageBody_handler(&$sender) {
        $sender->EventArguments['Object']->FormatBody = &$sender->EventArguments['Message']->Body;
        $this->renderSpoilers($sender);
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

    /**
     * Replaces spoiler body and tags with a string.
     *
     * @param string $body Text to replace spoilers in.
     * @param string $format Format of $body.
     * @return string Text with spoilers replaced.
     */
    protected function replaceSpoilers($body, $format) {
        if (!$this->renderSpoilers) {
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

    protected function renderSpoilers(&$sender) {
        if (!$this->renderSpoilers || Gdn::PluginManager()->checkPlugin('NBBC') ) {
            return;
        }

        $formatBody = &$sender->EventArguments['Object']->FormatBody;

        // Fix a wysiwyg but where spoilers
        $formatBody = preg_replace('`<div>\s*(\[/?spoiler\])\s*</div>`', '$1', $formatBody);

        $formatBody = preg_replace_callback("/(\[spoiler(?:=(?:&quot;)?([\d\w_',.? ]+)(?:&quot;)?)?\])/siu", array($this, 'spoilerCallback'), $formatBody);
        $formatBody = str_ireplace('[/spoiler]','</div></div>',$formatBody);
    }

    protected function spoilerCallback($matches) {
        $attribution = T('Spoiler: %s');
        $spoilerText = (sizeof($matches) > 2) ? $matches[2] : NULL;
        if (is_null($spoilerText)) {
            $spoilerText = '';
        } else {
            $spoilerText = "<span>{$spoilerText}</span>";
        }
        $attribution = sprintf($attribution, $spoilerText);
        return <<<BLOCKQUOTE
        <div class="UserSpoiler"><div class="SpoilerTitle">{$attribution}</div><div class="SpoilerReveal"></div><div class="SpoilerText">
BLOCKQUOTE;
    }

    public function setup() {
        // Nothing to do here!
    }

    public function structure() {
        // Nothing to do here!
    }

}
