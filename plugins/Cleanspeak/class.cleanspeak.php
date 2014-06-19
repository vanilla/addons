<?php
/**
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */
class Cleanspeak extends Gdn_Pluggable {

    /// Properties ///

    /**
     * Used when generating random UUID's for content.
     * Will be used when routing requests from HUB to proper site.
     * @var array
     */
    public $uuidSeed = array(6969, 0, 0, 0);

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
                $int = static::get32BitRand();
            }
        }

        return static::generateUUIDFromInts($seed);
    }

    public static function getUserUUID($userID) {
        return static::generateUUIDFromInts(array($userID, 0, 0, 0));
    }

    public static function getUserIDFromUUID($UUID) {
        $ints = static::getIntsFromUUID($UUID);
        return $ints[0];
    }

    /**
     * @param string $UUID Universal Unique Identifier.
     * @return array Containing the 4 numbers used to generate generateUUIDFromInts
     */
    public static function getIntsFromUUID($UUID) {
        $parts = str_split(str_replace('-', '', $UUID), 8);
        $parts = array_map('hexdec', $parts);
        return $parts;
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
        $result = static::hexInt($ints[0]) . '-' . static::hexInt($ints[1], true) . '-'
            . static::hexInt($ints[2], true).static::hexInt($ints[3]);
        return $result;
    }

    /**
     * Used to help generate UUIDs; pad and convert from decimal to hexadecimal; and split if neeeded
     *
     * @param $int Integer to be converted
     * @param bool $split Split result into parts.
     * @return string
     */
    public static function hexInt($int, $split = false) {
        $result = substr(str_pad(dechex($int), 8, '0', STR_PAD_LEFT), 0, 8);
        if ($split) {
            $result = implode('-', str_split($result, 4));
        }
        return $result;
    }

    /**
     * Get a random 32bit integer.  0x80000000 to 0xFFFFFFFF were not being tested with rand().
     *
     * @return int randon 32bi integer.
     */
    public static function get32BitRand() {
        return mt_rand(0, 0xFFFF) | (mt_rand(0, 0xFFFF) << 16);
    }

    /**
     * Tests for generateUUIDFromInts and getIntsFromUUID
     */
    public function testUUID() {
        $cs = new Cleanspeak();
        $pass = true;
        for ($i=0; $i < 10000; $i++) {
            $a = array($cs->get32BitRand(), $cs->get32BitRand(), $cs->get32BitRand(), $cs->get32BitRand());
            $uuid = $cs->generateUUIDFromInts($a);
            $ints = $cs->getIntsFromUUID($uuid);
            if ($a != $ints) {
                $pass = false;
                echo "Test FAILED $i Random combinations\n";
                echo "UID: $uuid\n";
                echo "Input:" . var_export($a, true) . "\n";
                echo "Output:" . var_export($ints, true) . "\n";
            }
        }
        if ($pass) {
            echo "Test PASSED. $i Random combinations [0 - 0xFFFFFFFF]\n";
        }

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

        $response = $proxyRequest->Request($options, $queryParams, null, $headers);

        if ($proxyRequest->ResponseStatus == 400) {
            file_put_contents('/tmp/cleanspeak.log', var_export($response, true), FILE_APPEND);
            throw new Gdn_UserException('Error in cleanspeak request.');
        }

        if ($proxyRequest->ResponseStatus != 200) {
            file_put_contents('/tmp/cleanspeak.log', var_export($response, true), FILE_APPEND);
            throw new Gdn_UserException('Error communicating with the cleanspeak server.');
        }

        // check for timeouts.

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
                'content' => Gdn_Format::Text($data['Name']),
                'name' => 'Name',
                'type' => 'text'
            );
        }
        if (GetValue('Body', $data)) {
            $parts[] = array(
                'content' => Gdn_Format::Text($data['Body']),
                'name' => 'Body',
                'type' => 'text'
            );
        }
        if (GetValue('Story', $data)) {
            $parts[] = array(
                'content' => Gdn_Format::Text($data['Story']),
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