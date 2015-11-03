<?php

class GeoipQuery {

    /**
     * Local object cache of GeoIP data for certain IPs.
     *
     * @var array
     */
    public  $localCache = [];

    /**
     * Name of IP-Block tablename
     *
     * @var string
     */
    private static $blockTableName = 'geoip_block';

    /**
     * Name of IP-Location tablename
     *
     * @var string
     */
    private static $locationTableName = 'geoip_location';

    /**
     * Cache expiration time.
     * @var int
     */
    public  $geoExpTime = 604800; // 604800 = 1 week

    /**
     * Prefix for memcache entries.
     */
    const   cachePre = 'GeoIP-Plugin_';


    /**
     * Query MySQL database for info on one or many given IPs.
     *
     * @param $input array|string IP address or array of IP addresses we want to query for.
     * @param bool $caching Enables and disables caching.
     * @return array|bool Returns array of data on success, false on failure.
     */
    public function get($input, $caching = true) {
        if (empty($input)) {
            return false;
        }

        // Make sure we always have input as array:
        if (!is_array($input)) {
            $input = [$input];
        }

        // Get Cached Records:
        if ($caching == true) {

            $cached = $this->getCache($input);

            // Remove Cached IPs from queryList:
            $queryList = $this->getQueryList($input, array_keys($cached));

        } else {
            $queryList = $input;
        }

        if (!empty($queryList)) {

            // Get SQL Query:
            $sql     = $this->getSQL($queryList);
            $results = GDN::database()->connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $results = $this->assocResultsToIps($results, $queryList); // @todo with associate results to IPs for result, not other way around.

            // Merge Query Results to Cached Results:
            $output = array_merge($cached, $results);

        } else {
            $output = $cached;
        }

        // Store to cache:
        if ($caching == true) {
            $this->setResponseToCache($output);
        }

        $this->addLocalCache($output);

        return $output;
    }

    /**
     * Generates SQL query to load GeoIP info.
     *
     * @param $input Array list of IPs
     * @return bool|string
     */
    private function getSQL($input) {
        if (empty($input)) {
            return false;
        }
        if (!is_array($input)) {
            $input = [$input];
        }

        $sql  = "SELECT\n";
        $sql .= "  B.network,\n";
        $sql .= "  L.*,\n";
        $sql .= "  B.latitude,\n";
        $sql .= "  B.longitude\n";
        $sql .= "FROM ".self::$blockTableName." AS B\n";
        $sql .= "  LEFT JOIN ".self::$locationTableName." AS L ON B.geoname_id=L.geoname_id\n";
        $sql .= "WHERE\n";
        foreach ($input as $i => $ip) {
            $sql .= ($i == 0) ? '   ' : 'OR ';
            $sql .= "inet_aton('{$ip}') BETWEEN B.start AND B.end\n";
        }
        $sql .= ";\n";
        // echo "<pre>SQL:\n{$sql}</pre>\n";

        return $sql;
    }


    /**
     * Checks given if given IP is part of given subnet range.
     *
     * @param $ip Given IP to be verified.
     * @param $range Subnet to be verified agains.
     * @return bool Returns true if IP is in subnet. False if not.
     */
    public  static function isInSubnet($ip, $range) {
        if (!self::isIP($ip)) {
            return false;
        } else if (!stristr($range, '/')) {
            return false;
        }

        list ($subnet, $bits) = explode('/', $range);

        $ip     = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask   = -1 << (32 - $bits);
        $subnet &= $mask; # nb: in case the supplied subnet wasn't correctly aligned

        return ($ip & $mask) == $subnet;
    }

    /**
     * Verifies that given IP is an actual IP.
     *
     * @param $ip IP being verified
     * @param int $version IP version we are verifying
     * @return bool Returns true if given IP is a proper IP. False if not.
     */
    public  static function isIP($ip, $version = 4) {
        if (empty($ip)) {
            return false;
        } else if (!in_array($version,[4,6])) {
            return false;
        }

        if (strlen($ip) < 7 || strlen($ip) > 15) {
            return false;
        }

        if ($version == 4) {
            $parts = explode('.', $ip);
            if (empty($parts) || count($parts) != 4) {
                return false;
            }

            foreach ($parts as $part) {
                if ($part > 255 || $part < 0) {
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
     * Takes result data array of GeoIP results and adds it's associated
     * ip from the IP list.
     *
     * NOTE: Since some IPs in IP list might be part of same network. We therefore
     * need to build the output of this method by adding data to ip list and not
     * the other way around.
     *
     * @param $data Array of GeoIP result data.
     * @param $ips Array of IPs used to produce dataset
     * @return array Returns original data array with associated IP from ips list.
     */
    private function assocResultsToIps($data, $ips) {

        $output = [];
        foreach ($ips as $ip) {
            if (empty($ip) || !$this->isIP($ip)) {
                continue;
            }

            $targetItem = false;
            foreach ($data as $item) {
                if ($this->isInSubnet($ip, $item['network'])) {
                    $targetItem = $item;
                    break;
                }
            }

            $output[$ip] = $targetItem;
            $output[$ip]['_ip'] = $ip;
        }

        return $output;
    }

    /**
     * Generate a cache key based on given $input. If array is passed, cacheKey()
     * will generate cache key for all the array elements and return new array.
     *
     * @param $input Given input to create cache key with.
     * @return string Returns requested cache key(s).
     */
    public  static function cacheKey($input) {
        if (is_array($input)) {
            $output = [];
            foreach ($input as $item) {
                $output[] = self::cacheKey($item);
            }
        }
        else if (is_string($input) || is_numeric($input)) {
            $output = self::cachePre.$input;
        }
        else {
            error_log("Invalid INPUT passed to ".__METHOD__."()!");
            return false;
        }

        return $output;
    }

    /**
     * Gets IP from given cache key.
     *
     * @param $input
     * @return array|bool|mixed
     */
    public  static function getIpFromKey($input) {
        if (empty($input)) {
            error_log("Empty INPUT passed to ".__METHOD__."()");
            return false;
        }
        else if (is_array($input)) {
            $output = [];
            foreach ($input as $item) {
                $output[] = self::getIpFromKey($item);
            }
        }
        else if (is_string($input) || is_numeric($input)) {
            $output = str_replace(self::cachePre, '', $input);
        }
        else {
            error_log("Invalid INPUT passed to ".__METHOD__."()");
            return false;
        }

        return $output;
    }

    /**
     * Determines if given IP is a local IP.
     *
     * @param $ip IP to be verified.
     * @return bool Returns true or false.
     */
    public  static function isLocalIP($ip) {
        if (empty($ip) || !self::isIP($ip)) {
            trigger_error("Invalid IP passed to ".__METHOD__."()", E_USER_NOTICE);
            return false;
        }

        // Make sure Input is not in private range of IPs:
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
            return false;
        }

        // Make sure Input is not in local range of IPs:
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE)) {
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
    public  static function myIP() {

        if (!self::isLocalIP($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        // Get curl handle:
        $ch = curl_init('http://checkip.dyndns.org');
        //curl_setopt($ch, CURLOPT_HEADER, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);
        curl_close($ch);

        $output = trim( substr($response, strpos($response,':') + 2) );
        $output = strip_tags($output);

        return $output;
    }

    /**
     * Extract IP list from given dataset.
     *
     * @param $input array Array of records containing GeoIP data.
     * @return array Returns array list of IPs
     */
    public  function extractIPList($input, $pointer = '_ip') {
        if (empty($input)) {
            return [];
        }

        $output = [];
        foreach ($input as $item) {
            if (isset($item[$pointer])) {
                $output[] = $item[$pointer];
            }
        }

        return $output;
    }

    /**
     * Get cached entries for given $input list of IPs
     *
     * @param $input List of IPs to be checked.
     * @param $cleanKeys bool Sets whether or not we need to clean IPs out of returned keys.
     * @return bool|array Returns array list of cached IP info on success, false on failure.
     */
    private function getCache($input, $cleanKeys = true) {
        if (empty($input)) {
            error_log("Invalid INPUT Array passed to ".__METHOD__."()");
            return false;
        } else if (!is_array($input)) {
            $input = [$input];
        }

        // Get Cached Records:
        $results = GDN::cache()->get($this->cacheKey($input));

        if ($cleanKeys == true) {
            $output = [];
            if (is_array($results)) {
                foreach ($results as $key => $item) {
                    if (empty($key) || empty($item)) {
                        continue;
                    }
                    $output[$item['_ip']] = $item;
                }
            }
        } else {
            $output = $results;
        }

        return $output;
    }

    /**
     * Sets given result data into cache.
     *
     * @param $input array Given data to cache
     * @return bool Returns true on success, false on failure.
     */
    private function setResponseToCache($input) {
        if (empty($input) || !is_array($input)) {
            error_log("Invalid INPUT Array passed to ".__METHOD__."()");
            return false;
        }

        // echo "<pre>Target SET Cache: ".print_r($input, true)."</pre>\n";
        foreach ($input as $ip => $item) {
            if (empty($item) || !$this->isIP($ip)) {
                continue;
            }
            GDN::cache()->store($this->cacheKey($ip), $item);
        }

        return true;
    }

    /**
     * Prepares query $list of IPs by removing local/private IPs as well as IPs from $removeList.
     *
     * @param $list array Target list of IPs
     * @param $removeList array  List of IPs to remove from target list.
     * @return array|bool array Returns target list adjusted.
     */
    private function getQueryList($list, $removeList) {
        if (empty($list)) {
            return [];
        }
        else if (!is_array($list) || !is_array($list)) {
            trigger_error("Invalid INPUT or LIST array passed to ".__METHOD__."()!", E_USER_NOTICE);
            return false;
        }

        $output = [];
        foreach ($list as $item) {

            // Remove Local IPs:
            if ($this->isLocalIP($item)) {
                continue;
            }

            if (!in_array($item, $removeList)) {
                $output[] = $item;
            }
        }

        return $output;
    }

}
