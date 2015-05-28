<?php if (!defined('APPLICATION')) exit();

// Define the plugin:
$PluginInfo['GeoIP'] = array(
   'Description' => 'Provides Geo IP location functionality.',
   'Version' => '0.0.1',
   'RequiredApplications' => array('Vanilla' => '2.0.10'),
   'RequiredTheme' => FALSE, 
   'RequiredPlugins' => FALSE,
   'HasLocale' => FALSE,
   'SettingsUrl' => '/plugin/geoip',
   'SettingsPermission' => 'Garden.AdminUser.Only',
   'Author' => "Deric D. Davis",
   'AuthorEmail' => 'deric.d@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com'
);

class GeoipPlugin extends Gdn_Plugin {

    private $cacheObj;
    public  $geoExpTime = 604800; // 604800 = 1 week
    const   cachePre    = 'GeoIP-Plugin_';


    public function __construct() {

        // Make sure GeoIP tools are installed:
        if (!function_exists('geoip_record_by_name')) {
            trigger_error("GeoIP lib is not installed on this server!", E_USER_ERROR);
            return false;
        }

    }

    public function Base_Render_Before($Sender) {
        $Sender->AddJsFile('js/example.js');
    }

    public function AssetModel_StyleCss_Handler($Sender) {
        $Sender->AddCssFile('design/flags.css');
    }

    public function PluginController_GeoIP_Create($Sender) {

        $Sender->Title('DDD: GeoIP Plugin');
        $Sender->AddSideMenu('plugin/geoip');
        //$Sender->AddSideMenu();

        $this->Dispatch($Sender, $Sender->RequestArgs);
        $this->Render('geoip');

        return true;
    }


    public function Controller_Index($Sender) {

    	echo "Hello Index World!\n";
    	//exit();
    }
    public function Controller_Tester($Sender) {

        $Sender->SetData('bla', 'tester');
        $Sender->SetData('method', __METHOD__);
        $Sender->SetData('country', 'ca');
        //echo "Hello Tester World!\n";
    	//exit();

        return;
    }


    public function UserModel_AfterSignIn_Handler($Sender, $Args=[]) {

        $userID = Gdn::Session()->User->UserID;
        system("date >> /tmp/bla.txt");
        system("echo '  UserID={$userID}' >> /tmp/bla.txt");

        $this->setUserGeo($userID);

        return true;
    }

    public function Base_AuthorInfo_Handler($Sender, $Args=[]) {

        // echo "<pre>Args: ".print_r($Args,true)."</pre>\n";
        $userID    = $Args['Author']->UserID;
        $userMeta  = $this->getUserGeo($userID, 'geo_country');

        echo Img("/plugins/GeoIP/design/flags/".strtolower($userMeta['geo_country']).".png");
        //echo "<pre>User Meta: ".print_r($userMeta,true)."</pre>\n";
    }

    public function DiscussionController_BeforeDiscussionDisplay_Handler($Sender, $Args=[]) {

        echo "<p>Hello Discussion: {$Args['Discussion']->InsertIPAddress}</p>";
        //GDN::cache()->store('testkey', 'blatest');
        //echo "<p>Test Cache Data: ".GDN::cache()->get('testkey')."</p>\n";

        $ipList = [$Args['Discussion']->InsertIPAddress];
        foreach ($Sender->Data('Comments')->result() as $comment) {
            if(empty($comment->InsertIPAddress)) continue;
            $ipList[] = $comment->InsertIPAddress;
        }
        echo "<pre>IPs All: ".print_r($ipList,true)."</pre>\n";

        $geoInfo = $this->getGeoFromList($ipList);

        //echo "<pre>Geo Info: ".print_r($geoInfo,true)."</pre>\n";
        //echo "<pre>All Args: ".print_r($Args,true)."</pre>\n";
        //echo "<pre>Comments: ".print_r($Sender->Data('Comments'),true)."</pre>\n";

        return true;
    }


    /**
     * Gets GeoIP info for given IP list.
     *
     * @param $input array IP array list
     * @return bool Returns true on success
     */
    private function getGeoFromList($input) {
        if (empty($input) OR !is_array($input)) {
            return false;
        }

        // Build list of target cache Keys
        $targetKeys = [];
        foreach ($input AS $item) {
            $targetKeys[] = self::cacheKey($item);
        }
        $targetKeys = array_unique($targetKeys);
echo "<pre>Cache List: ".print_r($targetKeys,true)."</pre>\n";

        $cachedData   = $this->getCache($targetKeys);
        $cachedIPList = $this->extractIPList($cachedData);
echo "<pre>Cached IPs: ".print_r($cachedIPList,true)."</pre>\n";
echo "<pre>Cache Data: ".print_r($cachedData,true)."</pre>\n";

        $loadList = [];
        foreach ($input AS $i => $ip) {
            if (!in_array($ip, $cachedIPList)) {
                $loadList[] = $ip;
            }
        }
        $loadList = array_unique($loadList);
echo "<pre>Load IP List: ".print_r($loadList,true)."</pre>\n";

        $loadedIPs = self::ipInfo($loadList, true, true);
echo "<pre>Loaded IPs: ".print_r($loadedIPs,true)."</pre>\n";

    }

    /**
     * Get cached records for given cache key(s).
     *
     * @param $input Target cache key(s) to load.
     * @return array|mixed Returns array
     */
    private function getCache($input) {
        if (empty($input)) {
            return [];
        } else if (!Gdn::cache()->activeEnabled()) {
            return [];
        }

        $output = GDN::cache()->Get($input);

        return $output;
    }

    /**
     * Extract IP list from given dataset.
     *
     * @param $input array Array of records containing GeoIP data.
     * @return array Returns array list of IPs
     */
    private function extractIPList($input) {
        if (empty($input)) {
            return [];
        }

        $output = [];
        foreach ($input AS $item) {
            if (!empty($item['_ip'])) {
                $output[] = $item['_ip'];
            }
        }

        return $output;
    }

    /**
     * Sets the user GeoIP information to UserMeta data.
     *
     * @param $userID
     * @return bool
     */
    private function setUserGeo($userID) {
        if (empty($userID) OR !is_numeric($userID)) {
            tigger_error("Invalid UserID passed to ".__METHOD__."()");
            return false;
        }

        $userInfo = GDN::UserModel()->GetID($userID);
        if (empty($userInfo) OR (!is_array($userID) && !is_object($userInfo))) {
            trigger_error("Could not load user info for given UserID={$userID} in ".__METHOD__."()!", E_USER_WARNING);
            return false;
        }

        $userIP = $userInfo->LastIPAddress;
        if (empty($userIP)) {
            trigger_error("No IP address on record for target userID={$userID} in ".__METHOD__."()!", E_USER_NOTICE);
            return false;
        }
        //echo "<p>User IP: '{$userIP}'</p>\n";

        $ipInfo = self::ipInfo($userIP,true);
        if (empty($ipInfo)) {
            trigger_error("Failed to get IP info in ".__METHOD__."()");
            return false;
        }
        //echo "<pre>IP Info ".print_r($ipInfo,true)."</pre>\n";

        GDN::userMetaModel()->setUserMeta($userID, 'geo_country', $ipInfo['country_code']);
        GDN::userMetaModel()->setUserMeta($userID, 'geo_latitude', $ipInfo['latitude']);
        GDN::userMetaModel()->setUserMeta($userID, 'geo_longitude', $ipInfo['longitude']);
        GDN::userMetaModel()->setUserMeta($userID, 'geo_city', utf8_encode($ipInfo['city']));
        GDN::userMetaModel()->setUserMeta($userID, 'geo_updated', time());

        return true;
    }

    /**
     * Gets user's GeoIP based information from user-meta data.
     *
     * @param $userID Target user's ID number.
     * @return array|bool Returns array of information or false on failure.
     */
    private function getUserGeo($userID, $field='geo_%') {
        if (empty($userID) OR !is_numeric($userID)) {
            tigger_error("Invalid UserID passed to ".__METHOD__."()", E_USER_WARNING);
            return false;
        }

        $meta  = GDN::userMetaModel()->getUserMeta($userID, $field);
        if (empty($meta)) {
            return false;
        }

        $output = [];
        foreach ($meta as $var => $value) {
            if (substr($var, 0, strlen('geo_')) == 'geo_') { // Make sure only to return geo_ info...
                // $output[substr($var,strlen('geo_'))] = $value;
                $output[$var] = $value;
            }
        }
/* Yeah no... this will be way to slow.
        $output = [
            'country'    =>  GDN::userMetaModel()->getUserMeta($userID, 'geo_country'),
            'latitude'   =>  GDN::userMetaModel()->getUserMeta($userID, 'geo_latitude'),
            'longitude'  =>  GDN::userMetaModel()->getUserMeta($userID, 'geo_longitude'),
            'city'       =>  GDN::userMetaModel()->getUserMeta($userID, 'geo_city'),
            'updated'    =>  GDN::userMetaModel()->getUserMeta($userID, 'geo_updated'),
        ];
*/
        return $output;
    }


    /**
     * Looks up GeoIP information for given IP.
     *
     * If $checkLocal is true, function will attempt to get public
     * info if given IP is a local network IP.
     *
     * @param $ip IP address we are looking up
     * @param bool $checkLocal Enable checking of public IP on private subnet.
     * @param bool $caching Enable caching in this method.
     * @return array|bool
     */
    public static function ipInfo($ip, $checkLocal = false, $caching = true) {

        // IF given IP input is an array of IPs:
        if (is_array($ip)) {
            $output = [];
            foreach ($ip as $item) {
                $output[] = self::ipInfo($item, $checkLocal, $caching);
            }
            return $output;
        }

        // Check if given IP is an actualy IP:
echo "<p>Checking IP</p>\n";
        if(!self::isIP($ip)) {
            trigger_error("Invalid IP passed to ".__METHOD__."()", E_USER_NOTICE);
            return false;
        }

        // Check Cache:
        $cached = GDN::cache()->get(self::cacheKey($ip));
echo "<pre>Cached IP Info (".self::cacheKey($ip)."): ".print_r($cached,true)."</pre>\n";

        // Return cached info IF it exists:
        if (!empty($cached)) {
            return $cached;
        }

        // If user's IP is local, get public IP address:
        if ($checkLocal == true && self::isLocalIP($ip)) {
            //echo "Getting Public IP<br/>\n";
            $pubIP = self::myIP();
            if (empty($pubIP)) {
                trigger_error("Failed to lookup public IP in ".__METHOD__."()!");
                return false;
            }
        }

        $searchIP = !empty($pubIP) ? $pubIP : $ip;
        $output   = geoip_record_by_name($searchIP);

        $output['_ip'] = $ip;
        $output['_checkLocal'] = $checkLocal;
echo "<pre>Loaded GeoIP Info: ".print_r($output,true)."</pre>\n";


        if ($caching == true) {
echo "<p>Store to Cache!</p>\n";
            GDN::cache()->store(self::cacheKey($ip), $output);
echo "<pre>Cached Data Saved (".self::cacheKey($ip)."): ".print_r(GDN::cache()->get(self::cacheKey($ip)),true)."</pre>\n";
        }

        return $output;
    } // Closes ipInfo().

    /**
     * Determines if given IP is a local IP.
     *
     * @param $ip IP to be verified.
     * @return bool Returns true or false.
     */
    public static function isLocalIP($ip) {
        if (empty($ip) OR !self::isIP($ip)) {
            trigger_error("Invalid IP passed to ".__METHOD__."()", E_USER_NOTICE);
            return false;
        }

        // Make sure Input is not in private range of IPs:
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
            return false;
        }

        return true;
    }

    /**
     * Checks given if given IP is part of given subnet range.
     *
     * @param $ip Given IP to be verified.
     * @param $range Subnet to be verified agains.
     * @return bool Returns true if IP is in subnet. False if not.
     */
    public static function isInSubnet($ip, $range) {
        if (!self::isIP($ip)) {
            return false;
        }

        list ($subnet, $bits) = explode('/', $range);

        $ip      = ip2long($ip);
        $subnet  = ip2long($subnet);
        $mask    = -1 << (32 - $bits);
        $subnet &= $mask; # nb: in case the supplied subnet wasn't correctly aligned

        return ($ip & $mask) == $subnet;
    }

    /**
     * Gets current public IP address.
     *
     * This is used if working in local installation and we want to determine public IP address.
     *
     * @return string
     */
    public static function myIP() {

        if (!self::isLocalIP($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        // Get curl handle:
        $ch = curl_init('http://checkip.dyndns.org');
        //curl_setopt($ch, CURLOPT_HEADER, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);
        curl_close($ch);

        $output  = trim( substr($response, strpos($response,':') + 2) );
        $output  = strip_tags($output);

        return $output;
    }

    /**
     * Verifies that given IP is an actual IP.
     *
     * @param $ip IP being verified
     * @param int $version IP version we are verifying
     * @return bool Returns true if given IP is a proper IP. False if not.
     */
    public static function isIP($ip, $version=4) {
        if (empty($ip)) {
            return false;
        } else if (!in_array($version,[4,6])) {
            return false;
        }

        if (strlen($ip) < 7 OR strlen($ip) > 15) {
            return false;
        }

        if ($version==4) {
            $parts = explode('.', $ip);
            if (empty($parts) OR count($parts) != 4) {
                return false;
            }

            foreach ($parts AS $part) {
                if ($part > 255 OR $part < 0) {
                    return false;
                }
            }
        }
        else {
            trigger_error("Only IPv4 supported in ".__METHOD__."()");
            return false;
        }

        return true;
    }

    /**
     * Generate a cache key based on given $input. (normally an IP)
     *
     * @param $input Given input to create cache key with.
     * @return string Returns requested cache key.
     */
    public static function cacheKey($input) {
        return self::cachePre.$input;
    }

} // Closes GeoipPlugin.
