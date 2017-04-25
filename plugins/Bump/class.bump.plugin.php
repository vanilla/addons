<?php

$PluginInfo['Bump'] = [
    'Name' => 'Bump',
    'Description' => "Allows moderators to bump a discussion without commenting.",
    'Version' => '1.0',
    'RequiredApplications' => ['Vanilla' => '2.1'],
    'MobileFriendly' => true,
    'Author' => "Lincoln Russell",
    'AuthorEmail' => 'lincolnwebs@gmail.com',
    'AuthorUrl' => 'http://lincolnwebs.com'
];

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
            $url = url("discussion/bump?discussionid={$discussion->DiscussionID}");
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
        $sender->Permission('Garden.Moderation.Manage');

        // Get discussion
        $discussionID = $sender->Request->get('discussionid');
        $discussion = $sender->DiscussionModel->getID($discussionID);
        if (!$discussion) {
            throw notFoundException('Discussion');
        }

        // Update DateLastComment & redirect
        $sender->DiscussionModel->setProperty($discussionID, 'DateLastComment', Gdn_Format::toDateTime());
        redirect(discussionUrl($discussion));
    }
}
