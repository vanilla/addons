<?php

class GeoipQuery {

    public  $localCache = [];
    private $localCacheMax = 100;

    private static $blockTableName       = 'geoip_block';
    private static $locationTableName    = 'geoip_location';

    public  $geoExpTime = 604800; // 604800 = 1 week
    const   cachePre    = 'GeoIP-Plugin_';


    public function get($input, $caching=true) {
        if (empty($input)) {
            return false;
        }

        // Make sure we always have input as array:
        if (!is_array($input)) {
            $input = [$input];
        }

        // Get Cached Records:
        if ($caching==true) {
            $cached = GDN::cache()->Get($this->cacheKey($input));
            if (!empty($cached)) {
                $this->addLocalCache($cached);
                return $cached;
            }
        }

        // Get SQL Query:
        $sql     = $this->getSQL($input);
        $output  = GDN::Database()->Connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $output  = $this->assocIpsToResults($output, $input);
        //echo "<pre>OUTPUT IP Results: ".print_r($output, true)."</pre>\n";

        // Store to cache:
        if ($caching == true) {
            GDN::cache()->store(self::cacheKey($input), $output);
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
        foreach ($input AS $i => $ip) {
            $sql .= ($i==0) ? '   ' : 'OR ';
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
        }

        list ($subnet, $bits) = explode('/', $range);

        $ip      = ip2long($ip);
        $subnet  = ip2long($subnet);
        $mask    = -1 << (32 - $bits);
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
    public  static function isIP($ip, $version=4) {
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
     * ip from the IP list
     *
     * @param $data Array of GeoIP result data.
     * @param $ips Array of IPs used to produce dataset
     * @return array Returns original data array with associated IP from ips list.
     */
    private function assocIpsToResults($data, $ips, $options=[]) {

        // @todo Should be making list using IPs as first loop, not data array.

        $output = [];
        foreach ($data AS $item) {
            foreach ($ips AS $ip) {
                if ($this->isInSubnet($ip, $item['network'])) {
                    $item['_ip'] = $ip;
                    break;
                }
            }

            $output[$item['_ip']] = $item;
        }

        return $output;
    }

    /**
     * Get cached records for given cache key(s).
     *
     * @param $input Target cache key(s) to load.
     * @return array|mixed Returns array
     */
    public  function getCache($input) {
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
     * Generate a cache key based on given $input. (normally an IP)
     *
     * @param $input Given input to create cache key with.
     * @return string Returns requested cache key.
     */
    public  static function cacheKey($input) {
        if (is_array($input)) {
            $input = serialize($input);
        }

        return md5(self::cachePre.$input);
    }

    /**
     * Determines if given IP is a local IP.
     *
     * @param $ip IP to be verified.
     * @return bool Returns true or false.
     */
    public  static function isLocalIP($ip) {
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

        $output  = trim( substr($response, strpos($response,':') + 2) );
        $output  = strip_tags($output);

        return $output;
    }

    /**
     * Extract IP list from given dataset.
     *
     * @param $input array Array of records containing GeoIP data.
     * @return array Returns array list of IPs
     */
    public  function extractIPList($input, $pointer='_ip') {
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

}
