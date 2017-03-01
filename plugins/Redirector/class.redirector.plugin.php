<?php
/**
 * Adds 301 redirects for Vanilla from common forum platforms.
 *
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license Proprietary
 */

// Define the plugin:
$PluginInfo['Redirector'] = [
    'Name' => 'Forum Redirector',
    'Description' => 'Adds 301 redirects for Vanilla from common forum platforms. This redirector redirects urls from IPB, phpBB, punBB, smf, vBulletin, Lithium, and Xenforo',
    'Version' => '1.2',
    'RequiredApplications' => ['Vanilla' => '2.1'],
    'Author' => 'Todd Burry',
    'AuthorEmail' => 'todd@vanillaforums.com',
    'AuthorUrl' => 'http://vanillaforums.com',
    'MobileFriendly' => true,
];

/**
 * Class RedirectorPlugin
 */
class RedirectorPlugin extends Gdn_Plugin {
    /**
     *
     * @var array
     */
    public static $Files = [
        'archive' => [__CLASS__, 'showthreadFilter'],
        'category.jspa' => [  // jive 4 category
            'categoryID' => 'CategoryID',
        ],
        'categories.aspx' => [ // Fusetalk
            'catid' => 'CategoryID',
        ],
        'index.php' => [ // smf
            'board' => [
                'CategoryID',
                'Filter' => [__CLASS__, 'smfOffset']
            ],
            'topic' => [
                'DiscussionID',
                'Filter' => [__CLASS__, 'smfOffset'],
            ],
            'action' => [
                '_',
                'Filter' => [__CLASS__, 'smfAction'],
            ],
        ],
        'forum' => [__CLASS__, 'forumFilter'],
        'forum.jspa' => [ // jive 4; forums imported as tags
            'forumID' => 'TagID',
            'start' => 'Offset'
        ],
        'forumdisplay.php' => [__CLASS__, 'forumDisplayFilter'], // vBulletin category
        'forumindex.jspa' => [ // jive 4 category
             'categoryID' => 'CategoryID',
        ],
        'forums' => [ // xenforo cateogry
            '_arg0' => [
                'CategoryID',
                'Filter' => [__CLASS__, 'xenforoID'],
            ],
            '_arg1' => [
                'Page',
                'Filter' => [__CLASS__, 'getNumber']
            ],
        ],
        'member.php' => [ // vBulletin user
            'u' => 'UserID',
            '_arg0' => [
                'UserID',
                'Filter' => [__CLASS__, 'removeID'],
            ],
        ],
        'memberlist.php' => [ // phpBB user
            'u' => 'UserID',
        ],
        'members' => [ // xenforo profile
            '_arg0' => [
                'UserID',
                'Filter' => [__CLASS__, 'xenforoID'],
            ],
        ],
        'messageview.aspx' => [ // Fusetalk
            'threadid' => 'DiscussionID',
        ],
        'thread.jspa' => [ //jive 4 comment/discussion
            'threadID' => 'DiscussionID',
        ],
        'post' => [ // punbb comment
            '_arg0' => 'CommentID',
        ],
        'profile.jspa' => [ //jive4 profile
            'userID' => 'UserID',
        ],
        'showpost.php' => [__CLASS__, 'showpostFilter'], // vBulletin comment
        'showthread.php' => [__CLASS__, 'showthreadFilter'], // vBulletin discussion
        'threads' => [ // xenforo discussion
            '_arg0' => [
                'DiscussionID',
                'Filter' => [__CLASS__, 'xenforoID'],
            ],
            '_arg1' => [
                'Page',
                'Filter' => [__CLASS__, 'getNumber'],
            ],
        ],
        't5' => [__CLASS__, 't5Filter'], // Lithium
        'topic' => [__CLASS__, 'topicFilter'],
        'viewforum.php' => [ // phpBB category
            'f' => 'CategoryID',
            'start' => 'Offset',
        ],
        'viewtopic.php' => [ // phpBB discussion/comment
            't' => 'DiscussionID',
            'p' => 'CommentID',
            'start' => 'Offset',
        ],
    ];

    /**
     *
     */
    public function gdn_Dispatcher_NotFound_Handler() {
        $Path = Gdn::Request()->Path();
        $Get = Gdn::Request()->Get();
        /**
         * There may be two incoming p URL parameters.  If that is the case, we need to compensate for it.  This is done
         * by manually parsing the server's QUERY_STRING variable, if available.
         */
        $QueryString = Gdn::Request()->getValueFrom('server', 'QUERY_STRING', false);
        trace(['QUERY_STRING' => $QueryString], 'Server Variables');
        if ($QueryString && preg_match('/(^|&)p\=\/?(showpost\.php|showthread\.php|viewtopic\.php)/i', $QueryString)) {
            // Check for multiple values of p in our URL parameters
            if ($QueryString && preg_match_all('/(^|\?|&)p\=(?P<val>[^&]+)/', $QueryString, $QueryParameters) > 1) {
                trace($QueryParameters['val'], 'p Values');
                // Assume the first p is Vanilla's path
                $Path = trim($QueryParameters['val'][0], '/');
                // The second p is used for our redirects
                $Get['p'] = $QueryParameters['val'][1];
            }
        }

        trace(['Path' => $Path, 'Get' => $Get], 'Input');

        // Figure out the filename.
        $Parts = explode('/', $Path);
        $After = [];
        $Filename = '';
        while(count($Parts) > 0) {
            $V = array_pop($Parts);
            if (preg_match('`.*\.php`', $V)) {
                $Filename = $V;
                break;
            }

            array_unshift($After, $V);
        }
        if ($Filename == 'index.php') {
            // Some site have an index.php?the/path.
            $TryPath = val(0, array_keys($Get));
            if (!$Get[$TryPath]) {
                $After = array_merge(explode('/', $TryPath));
                unset($Get[$TryPath]);
                $Filename = '';
            } elseif (preg_match('#archive/index\.php$#', $Path) === 1) { // vBulletin archive
                $Filename = 'archive';
            }
        }
        if (!$Filename) {
            // There was no filename, so we can try the first folder as the filename.
            while (count($After) > 0) {
                $Filename = array_shift($After);
                if (isset(self::$Files[$Filename]))
                    break;
            }
        }

        // Add the after parts to the array.
        $i = 0;
        foreach ($After as $Arg) {
            $Get["_arg$i"] = $Arg;
            $i++;
        }

        $Url = $this->filenameRedirect($Filename, $Get);

        if ($Url) {
            if (Debug()) {
                trace($Url, 'Redirect found');
            } else {
                Redirect($Url, 301);
            }
        }
    }

    /**
     *
     *
     * @param $Filename
     * @param $Get
     * @return bool|string
     */
    public function filenameRedirect($Filename, $Get) {
        trace(['Filename' => $Filename, 'Get' => $Get], 'Testing');
        $Filename = strtolower($Filename);
        array_change_key_case($Get);

        if (!isset(self::$Files[$Filename]))
            return false;

        $Row = self::$Files[$Filename];

        if (is_callable($Row)) {
            // Use a callback to determine the translation.
            $Row = call_user_func_array($Row, [&$Get]);
        }
        trace($Get, 'New Get');

        // Translate all of the get parameters into new parameters.
        $Vars = array();
        foreach ($Get as $Key => $Value) {
            if (!isset($Row[$Key]))
                continue;

            $Opts = (array)$Row[$Key];

            if (isset($Opts['Filter'])) {
                // Call the filter function to change the value.
                $R = call_user_func($Opts['Filter'], $Value, $Opts[0]);
                if (is_array($R)) {
                    if (isset($R[0])) {
                        // The filter can change the column name too.
                        $Opts[0] = $R[0];
                        $Value = $R[1];
                    } else {
                        // The filter can return return other variables too.
                        $Vars = array_merge($Vars, $R);
                        $Value = null;
                    }
                } else {
                    $Value = $R;
                }
            }

            if ($Value !== null)
                $Vars[$Opts[0]] = $Value;
        }
        trace($Vars, 'Translated Arguments');
        // Now let's see what kind of record we have.
        // We'll check the various primary keys in order of importance.
        $Result = false;

        if (isset($Vars['CommentID'])) {
            trace("Looking up comment {$Vars['CommentID']}.");
            $CommentModel = new CommentModel();
            // If a legacy slug is provided (assigned during a merge), attempt to lookup the comment using it
            if (isset($Get['legacy']) && Gdn::Structure()->Table('Comment')->ColumnExists('ForeignID')) {
                $Comment = $CommentModel->GetWhere(['ForeignID' => $Vars['CommentID']])->FirstRow();

            } else {
                $Comment = $CommentModel->GetID($Vars['CommentID']);
            }
            if ($Comment) {
                $Result = CommentUrl($Comment, '//');
            } else {
                // vBulletin, defaulting to discussions (foreign ID) when showthread.php?p=xxxx returns no comment
                $Vars['DiscussionID'] = $Vars['CommentID'];
                unset($Vars['CommentID']);
                $Get['legacy'] = true;
            }
        }
        // Splitting the if statement to default to discussions (foreign ID) when showthread.php?p=xxxx returns no comment
        if (isset($Vars['DiscussionID'])) {
            trace("Looking up discussion {$Vars['DiscussionID']}.");
            $DiscussionModel = new DiscussionModel();
            $DiscussionID = $Vars['DiscussionID'];
            $Discussion = false;

            if (is_numeric($DiscussionID)) {
                // If a legacy slug is provided (assigned during a merge), attempt to lookup the discussion using it
                if (isset($Get['legacy']) && Gdn::Structure()->Table('Discussion')->ColumnExists('ForeignID')) {
                    $Discussion = $DiscussionModel->GetWhere(['ForeignID' => $DiscussionID])->FirstRow();
                } else {
                    $Discussion = $DiscussionModel->GetID($Vars['DiscussionID']);
                }
            } else {
                // This is a slug style discussion ID. Let's see if there is a UrlCode column in the discussion table.
                $DiscussionModel->DefineSchema();
                if ($DiscussionModel->Schema->FieldExists('Discussion', 'UrlCode')) {
                    $Discussion = $DiscussionModel->GetWhere(['UrlCode' => $DiscussionID])->FirstRow();
                }
            }

            if ($Discussion) {
                $Result = DiscussionUrl($Discussion, self::pageNumber($Vars, 'Vanilla.Comments.PerPage'), '//');
            }
        } elseif (isset($Vars['UserID'])) {
            trace("Looking up user {$Vars['UserID']}.");

            $User = Gdn::UserModel()->GetID($Vars['UserID']);
            if ($User) {
                $Result = Url(UserUrl($User), '//');
            }
        } elseif (isset($Vars['TagID'])) {
            $Tag = TagModel::instance()->GetID($Vars['TagID']);
            if ($Tag) {
                 $Result = TagUrl($Tag, self::pageNumber($Vars, 'Vanilla.Discussions.PerPage'), '//');
            }
        } elseif (isset($Vars['CategoryID'])) {
            trace("Looking up category {$Vars['CategoryID']}.");

            // If a legacy slug is provided (assigned during a merge), attempt to lookup the category ID based on it
            if (isset($Get['legacy']) && Gdn::Structure()->Table('Category')->ColumnExists('ForeignID')) {
                $CategoryModel = new CategoryModel();
                $Category = $CategoryModel->GetWhere(['ForeignID' => $Get['legacy'] . '-' . $Vars['CategoryID']])->FirstRow();
            } else {
                $Category = CategoryModel::Categories($Vars['CategoryID']);
            }
            if ($Category) {
                $Result = categoryUrl($Category, self::pageNumber($Vars, 'Vanilla.Discussions.PerPage'), '//');
            }
        } elseif (isset($Vars['CategoryCode'])) {
            trace("Looking up category {$Vars['CategoryCode']}.");

            $category = CategoryModel::instance()->getByCode($Vars['CategoryCode']);
            if ($category) {
                $pageNumber = self::pageNumber($Vars, 'Vanilla.Discussions.PerPage');
                if ($pageNumber > 1) {
                    $pageParam = '?Page='.$pageNumber;
                } else {
                    $pageParam = null;
                }
                $Result = categoryUrl($category, '', '//').$pageParam;
            }
        }

        return $Result;
    }

    /**
     *
     *
     * @param $Get
     * @return array
     */
    public static function forumFilter(&$Get) {
        if (val('_arg2', $Get) == 'page') {
            // This is a punbb style forum.
            return [
                '_arg0' => 'CategoryID',
                '_arg3' => 'Page',
            ];
        } elseif (val('_arg1', $Get) == 'page') {
            // This is a bbPress style forum.
            return [
                '_arg0' => 'CategoryID',
                '_arg2' => 'Page',
            ];
        } else {
            // This is an ipb style topic.
            return [
                '_arg0' => [
                    'CategoryID',
                    'Filter' => [__CLASS__, 'removeID'],
                ],
                '_arg1' => [
                    'Page',
                    'Filter' => [__CLASS__, 'IPBPageNumber'],
                ],
            ];
        }
    }

    /**
     * Filter parameters properly.
     *
     * @param array $get Request parameters
     * @return array
     */
    public static function t5Filter(&$get) {
        $result = false;

        if (val('_arg0', $get) == 'user' && val('_arg2', $get) == 'user-id') {
            $result = [
                '_arg3' => [
                    'UserID'
                ],
            ];

            if (val('_arg3', $get) == 'page') {
                $result['_arg4'] = 'Page';
            }
        } elseif (val('_arg1', $get) == 'bd-p') { // Board = Category
            $result = [
                '_arg2' => [
                    'CategoryCode',
                    'Filter' => [__CLASS__, 'lithiumCategoryCodeFilter']
                ],
            ];

            if (val('_arg3', $get) == 'page') {
                $result['_arg4'] = 'Page';
            }
        } elseif (val('_arg2', $get) == 'm-p') { // Message => Comment
            $result = [
                '_arg3' => 'CommentID',
            ];
        } elseif (val('_arg2', $get) == 'td-p') { // Thread = Discussion
            $result = [
                '_arg3' => 'DiscussionID',
            ];

            if (val('_arg4', $get) == 'page') {
                $result['_arg5'] = 'Page';
            }
        }

        return $result;
    }

    /**
     * Filter vBulletin category requests, specifically to handle "friendly URLs".
     *
     * @param $Get Request parameters
     *
     * @return array Mapping of vB parameters
     */
    public static function forumDisplayFilter(&$Get) {
        self::vbFriendlyUrlID($Get, 'f');

        return [
            'f' => 'CategoryID',
            'page' => 'Page',
            '_arg0' => [
                'CategoryID',
                'Filter' => [__CLASS__, 'removeID']
            ],
            '_arg1' => [
                'Page',
                'Filter' => [__CLASS__, 'getNumber']
            ],
        ];
    }

    /**
     *
     *
     * @param $Value
     * @return null
     */
    public static function getNumber($Value) {
        if (preg_match('`(\d+)`', $Value, $Matches))
            return $Matches[1];
        return null;
    }

    /**
     *
     *
     * @param $Value
     * @return array|null
     */
    public static function IPBPageNumber($Value) {
        if (preg_match('`page__st__(\d+)`i', $Value, $Matches))
            return ['Offset', $Matches[1]];
        return self::getNumber($Value);
    }

    /**
     * Return the page number from the given variables that may have an offset or a page.
     *
     * @param array $Vars The variables that should contain an Offset or Page key.
     * @param int|string $PageSize The pagesize or the config key of the pagesize.
     * @return int
     */
    public static function pageNumber($Vars, $PageSize) {
        if (isset($Vars['Page']))
            return $Vars['Page'];
        if (isset($Vars['Offset'])) {
            if (is_numeric($PageSize))
                return pageNumber($Vars['Offset'], $PageSize, false, Gdn::Session()->IsValid());
            else
                return pageNumber($Vars['Offset'], C($PageSize, 30), false, Gdn::Session()->IsValid());
        }
        return 1;
    }

    /**
     *
     *
     * @param $Value
     * @return null
     */
    public static function removeID($Value) {
        if (preg_match('`^(\d+)`', $Value, $Matches))
            return $Matches[1];
        return null;
    }

    /**
     * Filter vBulletin comment requests, specifically to handle "friendly URLs".
     *
     * @param $Get Request parameters
     *
     * @return array Mapping of vB parameters
     */
    public static function showpostFilter(&$Get) {
        self::vbFriendlyUrlID($Get, 'p');

        return array(
            'p' => 'CommentID'
        );

    }

    /**
     * Filter vBulletin discussion requests, specifically to handle "friendly URLs".
     *
     * @param $Get Request parameters
     *
     * @return array Mapping of vB parameters
     */
    public static function showthreadFilter(&$Get) {
        $data = array(
            'p' => 'CommentID',
            'page' => 'Page',
            '_arg0' => [
                'DiscussionID',
                'Filter' => [__CLASS__, 'removeID']
            ],
            '_arg1' => [
                'Page',
                'Filter' => [__CLASS__, 'getNumber']
            ]
        );

        if (isset($Get['t'])) {
            $data['t'] = [
                'DiscussionID',
                'Filter' => [__CLASS__, 'removeID']
            ];
            self::vbFriendlyUrlID($Get, 't');
        }
        return $data;

    }

    /**
     *
     *
     * @param $Value
     * @return array
     */
    public static function smfAction($Value) {
        $result = null;

        if (preg_match('`(\w+);(\w+)=(\d+)`', $Value, $M)) {
            if (strtolower($M[1]) === 'profile') {
                $result = ['UserID', $M[3]];
            }
        }

        return $result;
    }

    /**
     *
     *
     * @param $Value
     * @param $Key
     * @return array
     */
    public static function smfOffset($Value, $Key) {
        $result = null;

        if (preg_match('/(\d+)\.(\d+)/', $Value, $M)) {
            $result = [$Key => $M[1], 'Offset' => $M[2]];
        } elseif (preg_match('/\d+\.msg(\d+)/', $Value, $M)) {
            $result = ['CommentID' => $M[1]];
        }

        return $result;
    }

    /**
     *
     *
     * @param $Get
     * @return array
     */
    public static function topicFilter(&$Get) {
        if (val('_arg2', $Get) == 'page') {
            // This is a punbb style topic.
            return [
                '_arg0' => 'DiscussionID',
                '_arg3' => 'Page',
            ];
        } elseif (val('_arg1', $Get) == 'page') {
            // This is a bbPress style topc.
            return [
                '_arg0' => 'DiscussionID',
                '_arg2' => 'Page'
            ];
        } else {
            // This is an ipb style topic.
            return [
                'p' => 'CommentID',
                '_arg0' => [
                    'DiscussionID',
                    'Filter' => [__CLASS__, 'removeID'],
                ],
                '_arg1' => [
                    'Page',
                    'Filter' => [__CLASS__, 'IPBPageNumber'],
                ],
            ];
        }
    }

    /**
     * Attempt to retrieve record ID from request parameters, if target parameter isn't already populated.
     *
     * @param $Get Request parameters
     * @param string $TargetParam Name of the request parameter the record value should be stored in
     *
     * @return bool True if value saved, False if not (including if value was already set in target parameter)
     */
    private static function vbFriendlyUrlID(&$Get, $TargetParam) {
        /**
         * vBulletin 4 added "friendly URLs" that don't pass IDs as a name-value pair.  We need to extract the ID from
         * this format, if we don't already have it.
         * Ex: domain.com/showthread.php?0001-example-thread
         */
        if (!empty($Get) && !isset($Get[$TargetParam])) {
            /**
             * The thread ID should be the very first item in the query string.  PHP interprets these identifiers as keys
             * without values.  We need to extract the first key and see if it's a match for the format.
             */
            $FriendlyURLID = array_shift(array_keys($Get));
            if (preg_match('/^(?P<RecordID>\d+)(-[^\/]+)?(\/page(?P<Page>\d+))?/', $FriendlyURLID, $FriendlyURLParts)) {
                // Seems like we have a match.  Assign it as the value of t in our query string.
                $Get[$TargetParam] = $FriendlyURLParts['RecordID'];

                if (!empty($FriendlyURLParts['Page'])) {
                    $Get['page'] = $FriendlyURLParts['Page'];
                }

                return true;
            }
        }

        return false;
    }

    /**
     *
     * @param $value
     * @return string
     */
    public static function xenforoID($value) {
        if (preg_match('/(\d+)$/', $value, $matches)) {
            $value = $matches[1];
        }

        return $value;
    }

    /**
     * Convert category code from lithium to vanilla.
     *
     * @param string $categoryCode
     * @return string
     */
    public static function lithiumCategoryCodeFilter($categoryCode) {
        return str_replace('_', '-', $categoryCode);
    }
}
