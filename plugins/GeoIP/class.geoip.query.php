<?php

class GeoipQuery {

    private $localCache = [];
    private $localCacheMax = 100;

    private static $blockTableName       = 'geoip_block';
    private static $locationTableName    = 'geoip_location';

    public  $geoExpTime = 604800; // 604800 = 1 week
    const   cachePre    = 'GeoIP-Plugin_';


    public function get($input) {
        if (empty($input)) {
            return false;
        }

        // Make sure we always have input as array:
        if (!is_array($input)) {
            $input = [$input];
        }
echo "<pre>IP List: ".print_r($input, true)."</pre>\n";

        // Get SQL Query:
        $sql     = $this->getSQL($input);
        $output  = GDN::Database()->Connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $output  = $this->assocIpsToResults($output, $input);

echo "<pre>OUTPUT IP Results: ".print_r($output, true)."</pre>\n";

        return $output;
    }

    private function runQuery($sql) {
        try{
            //GDN::Database()->ConnectionOptions[PDO::MYSQL_ATTR_LOCAL_INFILE] = true;
            $PDO = GDN::Database()->Connection();
            $output = $PDO->query($sql);
            //GDN::SQL()->ConnectionOptions[PDO::MYSQL_ATTR_LOCAL_INFILE] = true;
            //$output = GDN::SQL()->Query($sql);
        } catch(Exception $e) {
            error_log(__METHOD__."() SQL Error: ".$e->getMessage());
            return false;
        }
        return $output;
    }

    private function getSQL($input) {
        if (empty($input)) {
            return false;
        }
        if (!is_array($input)) {
            $input = [$input];
        }

        $sql  = "SELECT\n";
        $sql .= "  B.network,\n";
        $sql .= "  L.*\n";
        $sql .= "FROM ".self::$blockTableName." AS B\n";
        $sql .= "  LEFT JOIN ".self::$locationTableName." AS L ON B.geoname_id=L.geoname_id\n";
        $sql .= "WHERE\n";
        foreach ($input AS $i => $ip) {
            $sql .= ($i==0) ? '   ' : 'OR ';
            $sql .= "inet_aton('{$ip}') BETWEEN B.start AND B.end\n";
        }
        $sql .= ";\n";
echo "<pre>SQL:\n{$sql}</pre>\n";

        return $sql;
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

    private function addIpsToResults(&$data, $ips) {

    }

    private function assocIpsToResults(&$data, $ips) {

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


}
