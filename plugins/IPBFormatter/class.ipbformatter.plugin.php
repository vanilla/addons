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
    'RequiredPlugins' => false,
    'HasLocale' => false,
    'Author' => 'Todd Burry',
    'AuthorEmail' => 'todd@vanillaforums.com',
    'AuthorUrl' => 'http://vanillaforums.com/profile/todd'
);

Gdn::factoryInstall('IPBFormatter', 'IPBFormatterPlugin', __FILE__, Gdn::FactorySingleton);

class IPBFormatterPlugin extends Gdn_Plugin {

    /**
     *
     * @var BBCode
     */
    protected $_NBBC;

    protected $_Media = null;

    /// Methods ///

    public function format($string) {
        $string = str_replace(array('&quot;', '&#39;', '&#58;', 'Â'), array('"', "'", ':', ''), $string);
        $string = str_replace('<#EMO_DIR#>', 'default', $string);
        $string = str_replace('<{POST_SNAPBACK}>', '<span class="SnapBack">»</span>', $string);

        // There is an issue with using uppercase code blocks, so they're forced to lowercase here
        $string = str_replace(array('[CODE]', '[/CODE]'), array('[code]', '[/code]'), $string);

        /**
         * IPB inserts line break markup tags at line breaks.  They need to be removed in code blocks.
         * The original newline/line break should be left intact, so whitespace will be preserved in the pre tag.
         */
        $string = preg_replace_callback(
            '/\[code\].*?\[\/code\]/is',
            function ($CodeBlocks) {
                return str_replace(array('<br />'), array(''), $CodeBlocks[0]);
            },
            $string
        );

        /**
         * IPB formats some quotes as HTML.  They're converted here for the sake of uniformity in presentation.
         * Attribute order seems to be standard.  Spacing between the opening of the tag and the first attribute is variable.
         */
        $string = preg_replace_callback(
            '#<blockquote\s+(class="ipsBlockquote" )?data-author="([^"]+)" data-cid="(\d+)" data-time="(\d+)">(.*?)</blockquote>#is',
            function ($BlockQuotes) {
                $Author = $BlockQuotes[2];
                $Cid = $BlockQuotes[3];
                $Time = $BlockQuotes[4];
                $QuoteContent = $BlockQuotes[5];

                // $Time will over as a timestamp. Convert it to a date string.
                $Date = date('F j Y, g:i A', $Time);

                return "[quote name=\"{$Author}\" url=\"{$Cid}\" date=\"{$Date}\"]{$QuoteContent}[/quote]";
            },
            $string
        );

        // If there is a really long string, it could cause a stack overflow in the bbcode parser.
        // Not much we can do except try and chop the data down a touch.

        // 1. Remove html comments.
        $string = preg_replace('/<!--(.*)-->/Uis', '', $string);

        // 2. Split the string up into chunks.
        $strings = (array)$string;
        $result = '';
        foreach ($strings as $string) {
            $result .= $this->NBBC()->Parse($string);
        }

        // Linkify URLs in content
        $result = Gdn_Format::links($result);

        // Parsing mentions
        $result = Gdn_Format::mentions($result);

        // Handling emoji
        $result = Emoji::instance()->translateToHtml($result);

        // Make sure to clean filter the html in the end.
        $config = array(
            'anti_link_spam' => array('`.`', ''),
            'comment' => 1,
            'cdata' => 3,
            'css_expression' => 1,
            'deny_attribute' => 'on*',
            'elements' => '*-applet-form-input-textarea-iframe-script-style',
            // object, embed allowed
            'keep_bad' => 0,
            'schemes' => 'classid:clsid; href: aim, feed, file, ftp, gopher, http, https, irc, mailto, news, nntp, sftp, ssh, telnet; style: nil; *:file, http, https',
            // clsid allowed in class
            'valid_xml' => 2
        );

        $spec = 'object=-classid-type, -codebase; embed=type(oneof=application/x-shockwave-flash)';
        $result = htmLawed($result, $config, $spec);

        return $result;
    }

    /**
     * @return BBCode;
     */
    public function nbbc() {
        if ($this->_NBBC === null) {
            require_once PATH_PLUGINS . '/HtmLawed/htmLawed/htmLawed.php';

            $plugin = new NBBCPlugin('BBCodeRelaxed');
            $this->_NBBC = $plugin->NBBC();
            $this->_NBBC->ignore_newlines = true;
            $this->_NBBC->enable_smileys = false;

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

    public function media() {
        if ($this->_Media === null) {
            try {
                $i = Gdn::pluginManager()->getPluginInstance('FileUploadPlugin', Gdn_PluginManager::ACCESS_CLASSNAME);
                $m = $i->mediaCache();
            } catch (Exception $ex) {
                $m = array();
            }

            $media = array();
            foreach ($m as $key => $data) {
                foreach ($data as $row) {
                    $media[$row->MediaID] = $row;
                }
            }
            $this->_Media = $media;
        }

        return $this->_Media;
    }

    public function DoAttachment($bbcode, $action, $name, $default, $params, $content) {
        $medias = $this->Media();
        $parts = explode(':', $default);
        $mediaID = $parts[0];
        if (isset($medias[$mediaID])) {
            $media = $medias[$mediaID];

            $src = htmlspecialchars(Gdn_Upload::url(val('Path', $media)));
            $name = htmlspecialchars(val('Name', $media));
            if (val('ImageWidth', $media)) {
                return <<<EOT
<div class="Attachment Image"><img src="$src" alt="$name" /></div>
EOT;
            } else {
                return anchor($name, $src, 'Attachment File');
            }
        }

        return '';
    }

    /**
     * Hooks into the GetFormats event from the Advanced Editor plug-in and adds the IPB format.
     *
     * @param $sender Instance of EditorPlugin firing the event
     */
    public function editorPlugin_getFormats_handler($sender, &$args) {
        $formats =& $args['formats'];

        $formats[] = 'IPB';
    }

    /**
     * Hooks into the GetJSDefinitions event from the Advanced Editor plug-in and adds definitions related to
     * the IPB format.
     *
     * @param $sender Instance of EditorPlugin firing the event
     */
    public function editorPlugin_getJSDefinitions_handler($sender, &$args) {
        $definitions =& $args['definitions'];

        /**
         * There isn't any currently known help text for the IPB format, so it's an empty string.
         * If that changes, it can be added in the locale or changed here.
         */
        $definitions['ipbHelpText'] = t('editor.ipbHelpText', '');
    }
}
