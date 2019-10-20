<?php

/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 */

use Garden\Http;

/**
 * Cloudflare Support Plugin
 *
 * This plugin inspect the incoming request headers and applies CF-Connecting-IP
 * to the request object.
 *
 * Changes:
 *  1.0     Initial release
 *  1.2     Fix bad method call
 *  1.2.1   Fix bad method call again
 *  1.2.2   Update CF IP list
 *  1.2.3   Update CF IP list
 *  1.2.4   Update CF IP list
 *  1.3.0   Significant rewrite to fix missing function and make proxy IP list "live"
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package Addons
 */
class CloudflareSupportPlugin extends Gdn_Plugin {

    const CF_IPV4_URL = 'https://www.cloudflare.com/ips-v4';
    const CF_IPV6_URL = 'https://www.cloudflare.com/ips-v6';

    const CF_IPV4 = 'ipv4';
    const CF_IPV6 = 'ipv6';
    const CF_IP_LIST_KEY = 'Plugins.CloudflareSupport.List';
    const CF_IP_LIST_LAST = 'Plugins.CloudflareSupport.Lastfetch';

    const CF_CACHE_TTL = 86400;

    /**
     * CloudflareSupportPlugin constructor.
     *
     * Business logic happens in here, since the request is available at plugin runtime.
     */
    public function __construct() {
        parent::__construct();

        // If cloudflare isn't telling us a client IP, bust outta here!
        $cloudflareClientIP = val('HTTP_CF_CONNECTING_IP', $_SERVER, null);
        if (is_null($cloudflareClientIP)) {
            return;
        }

        $cloudflareRequest = false;

        // Get directly connecting IP
        $requestAddress = Gdn::request()->requestAddress();
        $ipType = substr_count($requestAddress, ':') > 0 ? self::CF_IPV6 : self::CF_IPV4;

        // Get current list of Cloudflare Source IPs (based on IP type)
        $currentRanges = $this->getCurrent($ipType);

        // Iterate and see if connecting IP is from Cloudflare
        foreach ($currentRanges as $cloudflareIPRange) {

            // Check if calling IP is a Cloudflare proxy
            switch ($ipType) {

                // IPv4
                case self::CF_IPV4:
                    if (!$this->ipv4_in_range($requestAddress, $cloudflareIPRange)) {
                        continue;
                    }
                    break;

                // IPv6
                case self::CF_IPV6:
                    if (!$this->ipv6_in_range($requestAddress, $cloudflareIPRange)) {
                        continue;
                    }
                    break;

                // Unknown IP type. Break out.
                default:
                    break 2;
            }

            Gdn::request()->requestAddress($cloudflareClientIP);
            $cloudflareRequest = true;
            break;
        }

        // Let people know that the CF plugin is turned on.
        if ($cloudflareRequest) {
            safeHeader("X-CF-Powered-By: CF-Vanilla v".$this->getPluginKey('Version'));
        }
    }

    /**
     * Get list of current Cloudflare IPv4 ranges
     *
     * @return mixed
     */
    private function getCurrent(string $ipType) {
        $lastFetch = c(self::CF_IP_LIST_LAST, 0);
        $expires = $lastFetch + self::CF_CACHE_TTL;

        $current = c(self::CF_IP_LIST_KEY, []);

        if ($expires > time()) {

            $new = $current;
            $http = new Http\HttpClient();

            foreach ([self::CF_IPV4, self::CF_IPV6] as $fetchIPType) {
                switch ($fetchIPType) {
                    case self::CF_IPV4:
                        $response = $http->get(self::CF_IPV4_URL);
                        break;

                    case self::CF_IPV6:
                        $response = $http->get(self::CF_IPV6_URL);
                        break;
                }

                if ($response && $response->isSuccessful()) {
                    $ips = $response->getBody();
                    $current = explode("\n", trim($ips));
                    $new[$ipType] = $current;
                }
            }

            if (count($new)) {
                // Save to config
                saveToConfig(self::CF_IP_LIST_KEY, $new);
                saveToConfig(self::CF_IP_LIST_LAST, time());
                $current = $new;
            }
        }

        return $current[$ipType] ?? [];
    }

    /**
     * ipv4_in_range
     *
     * This function takes 2 arguments, an IP address and a "range" in several
     * different formats.
     *
     * Network ranges can be specified as:
     * 1. Wildcard format:     1.2.3.*
     * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
     * 3. Start-End IP format: 1.2.3.0-1.2.3.255
     *
     * The function will return true if the supplied IP is within the range.
     *
     * Note little validation is done on the range inputs - it expects you to
     * use one of the above 3 formats.
     *
     * @param $ip
     * @param $range
     * @return bool
     */
    private function ipv4_in_range($ip, $range) {
        if (strpos($range, '/') !== false) {
            // $range is in IP/NETMASK format
            list($range, $netmask) = explode('/', $range, 2);

            if (strpos($netmask, '.') !== false) {
                // $netmask is a 255.255.0.0 format
                $netmask = str_replace('*', '0', $netmask);
                $netmask_dec = ip2long($netmask);
                return ( (ip2long($ip) & $netmask_dec) == (ip2long($range) & $netmask_dec) );
            } else {
                // $netmask is a CIDR size block
                // fix the range argument
                $x = explode('.', $range);
                while(count($x)<4) $x[] = '0';
                list($a,$b,$c,$d) = $x;
                $range = sprintf("%u.%u.%u.%u", empty($a)?'0':$a, empty($b)?'0':$b,empty($c)?'0':$c,empty($d)?'0':$d);
                $range_dec = ip2long($range);
                $ip_dec = ip2long($ip);

                # Strategy 1 - Create the netmask with 'netmask' 1s and then fill it to 32 with 0s
                #$netmask_dec = bindec(str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0'));

                # Strategy 2 - Use math to create it
                $wildcard_dec = pow(2, (32-$netmask)) - 1;
                $netmask_dec = ~ $wildcard_dec;

                return (($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec));
            }
        } else {
            // range might be 255.255.*.* or 1.2.3.0-1.2.3.255
            if (strpos($range, '*') !==false) { // a.b.*.* format
                // Just convert to A-B format by setting * to 0 for A and 255 for B
                $lower = str_replace('*', '0', $range);
                $upper = str_replace('*', '255', $range);
                $range = "$lower-$upper";
            }

            if (strpos($range, '-')!==false) { // A-B format
                list($lower, $upper) = explode('-', $range, 2);
                $lower_dec = (float)sprintf("%u",ip2long($lower));
                $upper_dec = (float)sprintf("%u",ip2long($upper));
                $ip_dec = (float)sprintf("%u",ip2long($ip));
                return ( ($ip_dec>=$lower_dec) && ($ip_dec<=$upper_dec) );
            }
            return false;
        }
    }

    /**
     * Convert ipv6 to decimal
     *
     * @param $ip
     * @return string
     */
    private function ip2long6($ip) {
        if (substr_count($ip, '::')) {
            $ip = str_replace('::', str_repeat(':0000', 8 - substr_count($ip, ':')) . ':', $ip);
        }

        $ip = explode(':', $ip);
        $r_ip = '';
        foreach ($ip as $v) {
            $r_ip .= str_pad(base_convert($v, 16, 2), 16, 0, STR_PAD_LEFT);
        }

        return base_convert($r_ip, 2, 10);
    }

    /**
     * Get the ipv6 full format and return it as a decimal value.
     *
     * @param $ip
     * @return mixed
     */
    private function get_ipv6_full($ip)
    {
        $pieces = explode ("/", $ip, 2);
        $left_piece = $pieces[0];
        $right_piece = $pieces[1];

        // Extract out the main IP pieces
        $ip_pieces = explode("::", $left_piece, 2);
        $main_ip_piece = $ip_pieces[0];
        $last_ip_piece = $ip_pieces[1];

        // Pad out the shorthand entries.
        $main_ip_pieces = explode(":", $main_ip_piece);
        foreach($main_ip_pieces as $key=>$val) {
            $main_ip_pieces[$key] = str_pad($main_ip_pieces[$key], 4, "0", STR_PAD_LEFT);
        }

        // Check to see if the last IP block (part after ::) is set
        $last_piece = "";
        $size = count($main_ip_pieces);
        if (trim($last_ip_piece) != "") {
            $last_piece = str_pad($last_ip_piece, 4, "0", STR_PAD_LEFT);

            // Build the full form of the IPV6 address considering the last IP block set
            for ($i = $size; $i < 7; $i++) {
                $main_ip_pieces[$i] = "0000";
            }
            $main_ip_pieces[7] = $last_piece;
        }
        else {
            // Build the full form of the IPV6 address
            for ($i = $size; $i < 8; $i++) {
                $main_ip_pieces[$i] = "0000";
            }
        }

        // Rebuild the final long form IPV6 address
        $final_ip = implode(":", $main_ip_pieces);

        return $this->ip2long6($final_ip);
    }

    /**
     * Determine whether the IPV6 address is within range.
     *
     * $ip is the IPV6 address in decimal format to check if its within the IP range created by the cloudflare IPV6 address, $range_ip.
     * $ip and $range_ip are converted to full IPV6 format.
     * Returns true if the IPV6 address, $ip,  is within the range from $range_ip.  False otherwise.
     *
     * @param $ip
     * @param $range_ip
     * @return bool
     */
    private function ipv6_in_range($ip, $range_ip)
    {
        $pieces = explode ("/", $range_ip, 2);
        $left_piece = $pieces[0];
        $right_piece = $pieces[1];

        // Extract out the main IP pieces
        $ip_pieces = explode("::", $left_piece, 2);
        $main_ip_piece = $ip_pieces[0];
        $last_ip_piece = $ip_pieces[1];

        // Pad out the shorthand entries.
        $main_ip_pieces = explode(":", $main_ip_piece);
        foreach($main_ip_pieces as $key=>$val) {
            $main_ip_pieces[$key] = str_pad($main_ip_pieces[$key], 4, "0", STR_PAD_LEFT);
        }

        // Create the first and last pieces that will denote the IPV6 range.
        $first = $main_ip_pieces;
        $last = $main_ip_pieces;

        // Check to see if the last IP block (part after ::) is set
        $last_piece = "";
        $size = count($main_ip_pieces);
        if (trim($last_ip_piece) != "") {
            $last_piece = str_pad($last_ip_piece, 4, "0", STR_PAD_LEFT);

            // Build the full form of the IPV6 address considering the last IP block set
            for ($i = $size; $i < 7; $i++) {
                $first[$i] = "0000";
                $last[$i] = "ffff";
            }
            $main_ip_pieces[7] = $last_piece;
        }
        else {
            // Build the full form of the IPV6 address
            for ($i = $size; $i < 8; $i++) {
                $first[$i] = "0000";
                $last[$i] = "ffff";
            }
        }

        // Rebuild the final long form IPV6 address
        $first = $this->ip2long6(implode(":", $first));
        $last = $this->ip2long6(implode(":", $last));
        $in_range = ($ip >= $first && $ip <= $last);

        return $in_range;
    }

}
