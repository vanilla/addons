<?php
/**
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */
class Cleanspeak extends Gdn_Pluggable {

    /// Properties ///

    /**
     * Used when generating random UUID's for content and users.
     * Will be used when routing requests from HUB to proper site.
     *    First Item reserved for SITE ID
     * @var array
     */
    public $uuidSeed = array(0, 0, 0, 0);

    /**
     * @var Cleanspeak
     */
    public static $Instance;

    function __construct() {
        parent::__construct();
        $this->FireEvent('Init');
    }

    /**
     * Get an instance of the model.
     *
     * @return Cleanspeak
     */
    public static function Instance() {
        if (isset(self::$Instance)) {
            return self::$Instance;
        }
        self::$Instance = new Cleanspeak();
        return self::$Instance;
    }


    /**
     * Send post to cleanspeak to see if content requires moderation.
     *
     * @param $UUID
     * @param $content
     * @param bool $forceModeration
     * @return array|mixed
     */
    public function moderation($UUID, $content, $forceModeration = false) {

        if ($forceModeration) {
            $content['moderation'] = 'requiresApproval';
        }

        $response = $this->apiRequest('/content/item/moderate/' . urlencode($UUID), $content);

        return $response;

    }

    /**
     *
     * Generate UUIDs for Content.
     * see uuidSeed property for pattern to be used.
     *
     * @return string UUID
     */
    public function getRandomUUID() {
        $seed = $this->uuidSeed;
        foreach ($seed as &$int) {
            if (!$int) {
                $int = QueueModel::get32BitRand();
            }
        }

        return static::generateUUIDFromInts($seed);
    }

    public function getUserUUID($userID) {
        return $this->generateUUIDFromInts(array($this->uuidSeed[0], 0, 0, $userID));
    }

    public static function getUserIDFromUUID($UUID) {
        $ints = QueueModel::getIntsFromUUID($UUID);
        if ($ints[3] == 0 || !is_numeric($ints[3])) {
            return false;
        }
        return $ints[3];
    }

    /**
     * Given an array of 4 numbers create a UUID
     *
     * @param arrat ints Ints to be converted to UUID.  4 numbers; last 3 default to 0
     * @return string UUID
     *
     * @throws Gdn_UserException
     */
    public static function generateUUIDFromInts($ints) {
        if (sizeof($ints) != 4 && !isset($ints[0])) {
            throw new Gdn_UserException('Invalid arguments passed to ' . __METHOD__);
        }
        if (!isset($ints[1])) {
            $ints[1] = 0;
        }
        if (!isset($ints[2])) {
            $ints[2] = 0;
        }
        if (!isset($ints[3])) {
            $ints[3] = 0;
        }
        $result = QueueModel::hexInt($ints[0]) . '-' . QueueModel::hexInt($ints[1], true) . '-'
            . QueueModel::hexInt($ints[2], true) . QueueModel::hexInt($ints[3]);
        return $result;
    }

    /**
     * Send API request to cleanspeak.
     *
     * @param string $url URL with Port number included
     * @param array $post Post data.
     * @return mixed Response from server. If json response will be decoded.
     *
     * @throws Gdn_UserException
     */
    public function apiRequest($url, $post) {

        $proxyRequest = new ProxyRequest();
        $options = array(
            'Url' => C('Plugins.Cleanspeak.ApiUrl') . '/'. ltrim($url, '/'),
//            'Timeout' => 30, //connection was timing out.
//            'ConnectTimeout' => 30,
        );
        $queryParams = array();
        if ($post != null) {
            $options['Method'] = 'POST';
            $options['PreEncodePost'] = false;
            $queryParams = json_encode($post);
        }
        $headers['Content-Type'] = 'application/json';
        file_put_contents('/tmp/cleanspeak.log', var_export($queryParams, true) . "\n", FILE_APPEND);

        $response = $proxyRequest->Request($options, $queryParams, null, $headers);

        if ($proxyRequest->ResponseStatus == 400) {
            file_put_contents('/tmp/cleanspeak.log', var_export($response, true), FILE_APPEND);
            throw new Gdn_UserException('Error in cleanspeak request.');
        }

        // check for timeouts.
        if ($proxyRequest->ResponseStatus == 0) {
            //fake response.
//            return array(
//                'content' => array(),
//                'applicationId' => 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6',
//                'id' => 'ae34fae-7dec-11d0-a765-13a0c91e6829',
//                'moderationAction' => 'requiresApproval',
//                'contentAction' => 'queuedForApproval',
//                'stored' => true
//            );
            //cant seem to catch...
            throw new Gdn_UserException('Error communicating with the cleanspeak server.', 500);
        }

        if ($proxyRequest->ResponseStatus != 200) {
            file_put_contents('/tmp/cleanspeak.log', var_export($response, true) . "\n", FILE_APPEND);
            throw new Gdn_UserException('Error communicating with the cleanspeak server.');
        }

        if (stristr($proxyRequest->ResponseHeaders['Content-Type'], 'application/json') != false) {
            $response = json_decode($response, true);
        }

        return $response;

    }

    /**
     * Split data into Parts as read by Cleanspeak.
     *
     * @param $data
     * @return array
     * @throws Gdn_UserException
     */
    public function getParts($data) {

        if (GetValue('Name', $data)) {
            $parts[] = array(
                'content' => Gdn_Format::Text($data['Name'], false),
                'name' => 'Name',
                'type' => 'text'
            );
        }
        if (GetValue('Body', $data)) {
            $parts[] = array(
                'content' => Gdn_Format::Text($data['Body'], false),
                'name' => 'Body',
                'type' => 'text'
            );
        }
        if (GetValue('Story', $data)) {
            $parts[] = array(
                'content' => Gdn_Format::Text($data['Story'], false),
                'name' => 'WallPost',
                'type' => 'text'
            );
        }

        if (sizeof($parts) == 0) {
            throw new Gdn_UserException('Error getting parts from content');
        }
        return $parts;

    }

    /**
     * The PHP $_POST and $_REQUEST global variables cannot be used in their default form to handle
     * notifications from CleanSpeak due to PHP replacing dots with underscores.  This corrects that.
     *
     * @param post array $target
     * @param $source
     */
    public static function fix(&$target, $source) {
        //this doesnt appear to be working...
        return;

        if (!$source) return;
        $target = array();
        $source = preg_replace_callback('/(^|(?<=&))[^=[]+/', function($key) {
                return bin2hex(urldecode($key[0]));
            }, $source);
        parse_str($source, $post);
        foreach($post as $key => $val)
            $target[ hex2bin($key) ] = $val;
    }


}

class CleanspeakNoResponseException extends Exception {

}