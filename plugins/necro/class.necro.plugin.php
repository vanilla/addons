<?php
/**
 * Necro.
 *
 * @copyright 2008-2016 Vanilla Forums, Inc.
 * @license GNU GPLv2
 */

/**
 * Class NecroPlugin.
 */
class NecroPlugin extends Gdn_Plugin {

    /**
     * Show Necro meta tag on discussions list.
     *
     * @return void
     */
    public function base_beforeDiscussionMeta_handler($sender, $args) {
        if ($what = val('DateRevived', val('Discussion', $args))) {
            echo ' <span class="Tag Tag-Necro">'.t('Necro').'</span> ';
        }
    }

    /**
     * When a comment is added, re-evaluate our necro status.
     *
     * @param CommentModel $sender
     * @param array $args
     */
    public function commentModel_beforeUpdateCommentCount_handler($sender, $args) {
        $lastPost = strtotime(val('DateLastComment', $args['Discussion']));
        $discussionID = val('DiscussionID', $args['Discussion']);

        if ($revived = val('DateRevived', $args['Discussion'])) {
            // Necro discussion found! Is it alive again?
            if ($this->isRisen($discussionID, $revived)) {
                $this->unsetNecro($discussionID);
            }
        }

        if ($this->isDead($lastPost)) {
            // Some one necro'd this discussion!
            $this->setNecro($discussionID);
        }
    }

    /**
     * Remove necro date from discussion.
     *
     * @param int $discussionID
     * @throws Exception
     */
    protected function unsetNecro($discussionID) {
        Gdn::sql()->update('Discussion')
            ->set('DateRevived', null)
            ->where('DiscussionID', $discussionID)
            ->put();
    }

    /**
     * Add necro date to discussion.
     *
     * @param int $discussionID
     * @throws Exception
     */
    protected function setNecro($discussionID) {
        Gdn::sql()->update('Discussion')
            ->set('DateRevived', Gdn_Format::toDateTime())
            ->where('DiscussionID', $discussionID)
            ->put();
    }

    /**
     * Determine if this discussion has risen from the dead.
     *
     * @param int $discussionID
     * @param string MySQL datetime.
     * @return bool
     */
    protected function isRisen($discussionID, $revived) {
        $results = Gdn::sql()->select('CommentID', 'count', 'NumComments')
            ->from('Comment')
            ->where('DiscussionID', $discussionID)
            ->where('DateInserted >', $revived)
            ->get()
            ->firstRow();

        return (val('NumComments', $results) >= c('Necro.CommentsToRevive', 10));
    }

    /**
     * Determine if thread death has occurred.
     *
     * @param int $lastPost Timestamp.
     * @return bool
     */
    protected function isDead($lastPost) {
        if (!$lastPost) {
            return false;
        }
        $daysSince = round((time() - $lastPost) / (3600*24));
        return ($daysSince > c('Necro.DaysTilDeath', 300));
    }

    /**
     * Run once on enable.
     */
    public function setup() {
        $this->structure();
    }

    /**
     * Run on utility/update.
     *
     * @throws Exception
     */
    public function structure() {
        Gdn::structure()
            ->table('Discussion')
            ->column('DateRevived', 'datetime', true)
            ->set();
    }
}
