<?php
/**
 * PostNumbering Plugin
 *
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU GPL v2
 */

$PluginInfo['postnumbering'] = [
    'Name' => 'Post Numbering',
    'Description' => 'Add numerical indices, beside posts, that link to the post itself.',
    'Version' => '1.0',
    'RequiredApplications' => ['Vanilla' => '2.2'],
    'License' => 'GNU GPL2',
    'SettingsUrl' => '/settings/roletracker',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'Author' => 'Alexandre (DaazKu) Chouinard',
    'AuthorEmail' => 'alexandre.c@vanillaforums.com',
    'AuthorUrl' => 'https://github.com/DaazKu',
];

/**
 * Class PostNumberingPlugin
 */
class PostNumberingPlugin extends Gdn_Plugin {
    /**
     * Add numbering index to discussion.
     *
     * @param DiscussionController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionController_discussionInfo_handler($sender, $args) {
        echo wrap(
            anchor('#1', discussionUrl($args['Discussion'])),
            'span',
            ['Class' => 'MItem PostNumbering Num-1']
        );
    }

    /**
     * Add numbering index to discussion's comments.
     *
     * @param DiscussionController $sender Sending controller instance.
     * @param array $args Event arguments.
     */
    public function discussionController_commentInfo_handler($sender, $args) {
        static $number = 2;

        echo wrap(
            anchor('#'.$number, commentUrl($args['Comment'])),
            'span',
            ['Class' => 'MItem PostNumbering Num-'.$number]
        );

        $number += 1;
    }
}
