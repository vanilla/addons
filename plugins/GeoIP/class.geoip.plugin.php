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

    public  $geoExpTime = 604800; // 604800 = 1 week
    const   cachePre    = 'GeoIP-Plugin_';

    private $localCache = [];
    private $localCacheMax = 100;


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

        $this->setUserMetaGeo($userID);

        return true;
    }

    public function Base_AuthorInfo_Handler($Sender, $Args=[]) {

        // Get IP based on context:
        if (!empty($Args['Comment']->InsertIPAddress)) { // If author is from comment.
            $targetIP = $Args['Comment']->InsertIPAddress;
        } else if (!empty($Args['Discussion']->InsertIPAddress)) { // If author is from discussion.
            $targetIP = $Args['Discussion']->InsertIPAddress;
        } else {
            return false;
        }

        // Make sure target IP is in local cache:
        if (!isset($this->localCache[$targetIP])) {
            $this->ipInfo($targetIP);
        }

        // Get Country Code:
        $country_code  = strtolower($this->localCache[$targetIP]['country_code']);
        $country_name  = $this->localCache[$targetIP]['country_name'];

        // Echo Image:
        echo Img("/plugins/GeoIP/design/flags/{$country_code}.png", ['alt'=>"({$country_name})", 'title'=>$country_name]);

        return;
    }

    public function DiscussionController_BeforeDiscussionDisplay_Handler($Sender, $Args=[]) {

        // Create list of IPs from this discussion we want to look up.
        $ipList = [$Args['Discussion']->InsertIPAddress]; // Add discussion IP.
        foreach ($Sender->Data('Comments')->result() as $comment) {
            if(empty($comment->InsertIPAddress)) continue;
            $ipList[] = $comment->InsertIPAddress;
        }

        // Get IP information for given IP list:
        $this->ipInfo($ipList);
echo "<pre>localCache: ".print_r($this->localCache,true)."</pre>\n";

        return true;
    }


    /**
     * Gets GeoIP info for given IP list.
     *
     * @param $input array IP array list
     * @return bool Returns true on success
     */
    private function ipInfo($input) {
        if (empty($input)) {
            return false;
        }
        if (!is_array($input)) {
            $input = [$input];
        }

        // Build list of target cache Keys:
        $targetKeys = [];
        foreach ($input AS $item) {
            $targetKeys[] = self::cacheKey($item);
        }
        $targetKeys = array_unique($targetKeys);

        // Get data that is already cached:
        $cachedData   = $this->getCache($targetKeys);

        // Get list of IPs from data that is already cached:
        $cachedIPList = $this->extractIPList($cachedData);

        // Build list of IPs to load:
        $loadList = [];
        foreach ($input AS $i => $ip) {
            if (!in_array($ip, $cachedIPList)) {
                $loadList[] = $ip;
            }
        }
        $loadList   = array_unique($loadList);

        // Load target IP info from loadList (uncached):
        $loadedInfo = self::ipQuery($loadList, true, true); // Do not look in cache...
        $info       = array_merge($cachedData, $loadedInfo);

        // Make sure IP is pointer in array:
        $output = [];
        foreach ($info AS $item) {
            $output[$item['_ip']] = $item;
        }

        // Merge output/results with existing localCache:
        //$this->localCache = array_merge($this->localCache, $output);
        $this->addLocalCache($output);

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
    public static function ipQuery($ip, $checkLocal = false, $caching = true) {
        // IF given IP input is an array of IPs:

        if (is_array($ip)) {
            $output = [];
            foreach ($ip as $item) {
                $output[] = self::ipQuery($item, $checkLocal, $caching);
            }
            return $output;
        }

        // Check if given IP is an actualy IP:
        if(!self::isIP($ip)) {
            trigger_error("Invalid IP passed to ".__METHOD__."()", E_USER_NOTICE);
            return false;
        }

        // IF caching is true, check cache first:
        if ($caching==true) {
            // Check Cache:
            $cached = GDN::cache()->get(self::cacheKey($ip));
            // echo "<pre>Cached IP Info (".self::cacheKey($ip)."): ".print_r($cached,true)."</pre>\n";

            // Return cached info IF it exists:
            if (!empty($cached)) {
                return $cached;
            }
        }

        // If user's IP is local, get public IP address:
        if ($checkLocal == true && self::isLocalIP($ip)) {
            //echo "Getting Private IP<br/>\n";
            $pubIP = self::myIP();
            $checkedLocal = true;
            if (empty($pubIP)) {
                trigger_error("Failed to lookup public IP in ".__METHOD__."()!");
                return false;
            }
        } else {
            $checkedLocal = false;
        }

        // Query GeoIP database:
        $searchIP = !empty($pubIP) ? $pubIP : $ip;
        $output   = geoip_record_by_name($searchIP);

        // Store target IP in data set as well as whether checkLocal is enabled.
        $output['_ip'] = $ip;
        $output['_checkedLocal'] = $checkedLocal;
        $output['_time'] = microtime(true);

        // Store to cache:
        if ($caching == true) {
            GDN::cache()->store(self::cacheKey($ip), $output);
            // echo "<pre>Cached Data Saved (".self::cacheKey($ip)."): ".print_r(GDN::cache()->get(self::cacheKey($ip)),true)."</pre>\n";
        }

        return $output;
    } // Closes ipQuery().


    /**
     * Add IP information to local cache.
     *
     * Merges given input with this->localCache.
     *
     * @todo Verify size of local cache is smaller than this->localCacheMax.
     *
     * @param $input array Data being added to localCache.
     * @return bool Returns true/false upon success.
     */
    private function addLocalCache($input) {
        if (empty($input) || !is_array($input)) {
            return false;
        }

        $this->localCache = array_merge($this->localCache, $input);

        return $this->localCache;
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

        // Check Local Cache:
        $local = [];
        foreach ($input AS $i => $targetItem) {
            if (isset($this->localCache[$targetItem])) {
                $local[] = $targetItem;
                //unset($input[$i]);
            }
        }
        //$input = array_values($input); // @todo remove localCache items from input array for optimization...

        // Get Cached Records:
        $cached = GDN::cache()->Get($input);

        // Merge local and cached records:
        $output = array_merge($local, $cached);

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
    private function setUserMetaGeo($userID) {
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

        $ipInfo = self::ipQuery($userIP,true,true);
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
    private function getUserMetaGeo($userID, $field='geo_%') {
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

        return $output;
    }


    /**
     * Determines if given IP is a local IP.
     *
     * @param $ip IP to be verified.
     * @return bool Returns true or false.
     */
    private static function isLocalIP($ip) {
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
    private static function isInSubnet($ip, $range) {
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
    private static function myIP() {

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
    private static function isIP($ip, $version=4) {
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
    private static function cacheKey($input) {
        return self::cachePre.$input;
    }

} // Closes GeoipPlugin.
