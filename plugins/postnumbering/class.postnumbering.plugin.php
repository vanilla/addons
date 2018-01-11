<?php
/**
 * PostNumbering Plugin
 *
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU GPL v2
 */

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

        $offset = val('Offset', $sender, 0);
        $commentNumber = $offset + $number;

        echo wrap(
            anchor('#'.$commentNumber, commentUrl($args['Comment'])),
            'span',
            ['Class' => 'MItem PostNumbering Num-'.$commentNumber]
        );

        $number += 1;
    }
}
