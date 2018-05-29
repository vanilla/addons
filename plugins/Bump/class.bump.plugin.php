<?php

class BumpPlugin extends Gdn_Plugin {
    /**
     * Add bump option to discussion options.
     *
     * @param GardenController $sender Sending controller instance.
     * @param array            $args   Event arguments.
     *
     * @return void.
     */
    public function base_discussionOptions_handler($sender, $args) {
        $discussion = $args['Discussion'];
        if (checkPermission('Garden.Moderation.Manage')) {
            $label = t('Bump');
            $url = "discussion/bump?discussionid={$discussion->DiscussionID}";
            // Deal with inconsistencies in how options are passed
            if (isset($sender->Options)) {
                $sender->Options .= wrap(anchor($label, $url, 'Bump'), 'li');
            } else {
                $args['DiscussionOptions']['Bump'] = [
                    'Label' => $label,
                    'Url' => $url,
                    'Class' => 'Bump'
                ];
            }
        }
    }

    /**
     * Handle discussion option menu bump action.
     *
     * @param DiscussionController $sender Sending controller instance.
     * @param array                $args   Event arguments.
     *
     * @return void.
     */
    public function discussionController_bump_create($sender, $args) {
        $sender->permission('Garden.Moderation.Manage');

        // Get discussion
        $discussionID = $sender->Request->get('discussionid');
        $discussion = $sender->DiscussionModel->getID($discussionID);
        if (!$discussion) {
            throw notFoundException('Discussion');
        }

        // Update DateLastComment & redirect
        $sender->DiscussionModel->setProperty($discussionID, 'DateLastComment', Gdn_Format::toDateTime());
        redirectTo(discussionUrl($discussion));
    }
}
