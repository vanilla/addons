<?php if (!defined('APPLICATION')) exit();

require_once 'class.geoip.import.php';
require_once 'class.geoip.query.php';

// Define the plugin:
$PluginInfo['GeoIP'] = array(
    'Name' => 'Carmen Sandiego (GeoIP)',
    'Description' => 'Provides Geo IP location functionality. This product includes GeoLite2 data created by MaxMind, available from <a href="http://www.maxmind.com">http://www.maxmind.com</a>.',
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


    private $pdo;



    private $query;
    private $import;


    public function __construct() {

        // Make sure GeoIP tools are installed:
        if (!function_exists('geoip_record_by_name')) {
            trigger_error("GeoIP lib is not installed on this server!", E_USER_ERROR);
            return false;
        }

        // Instantiate Query Object:
        $this->query  = new GeoipQuery();

        // Instantiate Import Object:
        $this->import = new GeoipImport();
    }

    public function Base_Render_Before($Sender) {
        $Sender->AddJsFile('js/example.js');
    }

    public function AssetModel_StyleCss_Handler($Sender) {
        $Sender->AddCssFile('design/flags.css');
    }

    public function PluginController_GeoIP_Create($Sender) {

        $Sender->Title('Carmen Sandiego Plugin (GeoIP)');
        $Sender->AddSideMenu('plugin/geoip');
        $Sender->Form = new Gdn_Form();

        $this->Dispatch($Sender, $Sender->RequestArgs);
        $this->Render('geoip');

        return true;
    }

    public function Controller_index($Sender) {

        // echo "<p>Do Login:".C('Plugin.GeoIP.doLogin')."</p>";

        $Sender->Permission('Garden.Settings.Manage');
        $Sender->SetData('PluginDescription',$this->GetPluginKey('Description'));

        $Validation = new Gdn_Validation();
        $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
        $ConfigurationModel->SetField(array(
            'Plugin.GeoIP.doLogin'       => false,
            'Plugin.GeoIP.doDiscussions' => false,
        ));

        // Set the model on the form.
        $Sender->Form->SetModel($ConfigurationModel);

        // If seeing the form for the first time...
        if ($Sender->Form->AuthenticatedPostBack() === false) {
            // Apply the config settings to the form.
            $Sender->Form->SetData($ConfigurationModel->Data);

        } else {

            // @todo Set proper validation rules.
            //$ConfigurationModel->Validation->ApplyRule('Plugin.Example.RenderCondition', 'Required');
            //$ConfigurationModel->Validation->ApplyRule('Plugin.Example.TrimSize', 'Required');
            //$ConfigurationModel->Validation->ApplyRule('Plugin.Example.TrimSize', 'Integer');

            $Saved = $Sender->Form->Save();
            if ($Saved) {
                $Sender->StatusMessage = T("Your changes have been saved.");
            }
        }


        $Sender->Render($this->GetView('geoip.php'));
    }

    public function Controller_import($Sender) {

        echo "<p>Importing GeoIP CSV into MySQL!</p>\n";

        $imported = $this->import();
        if ($imported == false) {
            trigger_error("Failed to Import GeoIP data into MySQL in ".__METHOD__."()!", E_USER_WARNING);
            return false;
        }

        exit(__METHOD__);
    }


    /**
     * Load GeoIP data upon login.
     *
     * @param $Sender Referencing object
     * @param array $Args Arguments provided
     * @return bool Returns true on success, false on failure.
     */
    public function UserModel_AfterSignIn_Handler($Sender, $Args=[]) {

        // Check IF feature is enabled for this plugin:
        if (C('Plugin.GeoIP.doLogin')==false) {
            return false;
        }

        $userID = Gdn::Session()->User->UserID;
        if (empty($user)) {
            return false;
        }

        $this->setUserMetaGeo($userID);

        return true;
    }

    public function Base_AuthorInfo_Handler($Sender, $Args=[]) {

        // Check IF feature is enabled for this plugin:
        if (C('Plugin.GeoIP.doDiscussions')==false) {
            return false;
        }

        // Get IP based on context:
        if (!empty($Args['Comment']->InsertIPAddress)) { // If author is from comment.
            $targetIP = $Args['Comment']->InsertIPAddress;
        } else if (!empty($Args['Discussion']->InsertIPAddress)) { // If author is from discussion.
            $targetIP = $Args['Discussion']->InsertIPAddress;
        } else {
            return false;
        }

        // Make sure target IP is in local cache:
        if (!isset($this->query->localCache[$targetIP])) {
            //$this->ipInfo($targetIP);
            return false;
        }

        // Get Country Code:
        if (!empty($this->query->localCache[$targetIP])) {
            $countryCode  = strtolower($this->query->localCache[$targetIP]['country_code']);
            $countryName  = $this->query->localCache[$targetIP]['country_name'];

            // Echo Image:
            if (!empty($country_code)) {
                echo Img("/plugins/GeoIP/design/flags/{$countryCode}.png", ['alt'=>"({$countryName})", 'title'=>$countryName]);
            }
        }

        return;
    }

    public function DiscussionController_BeforeDiscussionDisplay_Handler($Sender, $Args=[]) {

        // Check IF feature is enabled for this plugin:
        if (C('Plugin.GeoIP.doDiscussions')==false) {
            return false;
        }

        // Create list of IPs from this discussion we want to look up.
        $ipList = [$Args['Discussion']->InsertIPAddress]; // Add discussion IP.
        foreach ($Sender->Data('Comments')->result() as $comment) {
            if(empty($comment->InsertIPAddress)) continue;
            $ipList[] = $comment->InsertIPAddress;
        }

        // Get IP information for given IP list:
        //$this->ipInfo($ipList);
        $this->query->get($ipList);
        //echo "<pre>localCache: ".print_r($this->localCache,true)."</pre>\n";

        return true;
    }


    /**
     * Import GeoIP City Lite from Max Mind into MySQL.
     *
     * @return bool Returns TRUE on Success, FALSE on failure.
     */
    private function import() {
        // Do Import:
        return $this->import->run();;
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

        // Get list of IPs from data that are already cached:
        $cachedIPList = $this->extractIPList($cachedData);

        // Build list of IPs to load:
        $loadList = [];
        foreach ($input AS $i => $ip) {
            if (!in_array($ip, $cachedIPList)) {
                $loadList[] = $ip;
            }
        }
        $loadList   = array_unique($loadList);
echo "<pre>Load List: ".print_r($loadList,true)."</pre>\n";

        // Load target IP info from loadList (uncached):
        $loadedInfo = !empty($loadList) ? self::ipQuery2($loadList, true, true) : []; // Do not look in cache...
        $info       = array_merge($cachedData, $loadedInfo);
echo "<pre>IP Info: ".print_r($info,true)."</pre>\n";

        // Make sure IP is pointer in array:
        $output = [];
        foreach ($info AS $item) {
            $output[$item['_ip']] = $item;
        }
echo "<pre>IP OUTPUT: ".print_r($output,true)."</pre>\n";

        // Merge output/results with existing localCache:
        //$this->localCache = array_merge($this->localCache, $output);
        $this->addLocalCache($output);

        return $output;
    }


    private function ipQuery2($input) {
        if (empty($input)) {
            return false;
        }
        if (!is_array($input)) {
            $input = [$input];
        }
echo "<pre>INPUT Load List: ".print_r($input,true)."</pre>\n";

        $sql  = "SELECT\n";
        $sql .= "  B.*\n";
        $sql .= "FROM ".self::$blockTableName." AS B\n";
        $sql .= "  LEFT JOIN ".self::$locationTableName." AS L ON B.geoname_id=L.geoname_id\n";
        $sql .= "WHERE\n";
        foreach ($input AS $i => $ip) {
            $sql .= ($i==0) ? '  ' : 'OR ';
            $sql .= " inet_aton('{$ip}') BETWEEN B.start AND B.end\n";
        }
        $sql .= ";\n";
echo "<pre>SQL:\n{$sql}</pre>\n";

        $output = [];
        $PDO = GDN::Database()->Connection();
        foreach ($PDO->query($sql, PDO::FETCH_COLUMN) AS $row) {
            $output[] = $row;
        }

        //$results  = $this->runQuery($sql);
echo "<pre>IP Results: ".print_r($output, true)."</pre>\n";

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
            trigger_error("Invalid IP passed to ".__METHOD__."()");
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
echo "<pre>IP Query Output: ".print_r($output, true)."</pre>\n";
        return $output;
    } // Closes ipQuery().


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
    private function extractIPList($input, $pointer='_ip') {
        if (empty($input)) {
            return [];
        }

        $output = [];
        foreach ($input AS $item) {
            if (isset($item[$pointer])) {
                $output[] = $item[$pointer];
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
     * Generate a cache key based on given $input. (normally an IP)
     *
     * @param $input Given input to create cache key with.
     * @return string Returns requested cache key.
     */
    private static function cacheKey($input) {
        return self::cachePre.$input;
    }


} // Closes GeoipPlugin.
