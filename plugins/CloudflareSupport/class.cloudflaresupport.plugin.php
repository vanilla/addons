<?php

/**
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 */

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
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package Addons
 */
class CloudflareSupportPlugin extends Gdn_Plugin {

    // CloudFlare IP ranges listed at https://www.cloudflare.com/ips
    protected $CloudflareSourceIPs = [
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '104.16.0.0/12',
        '108.162.192.0/18',
        '131.0.72.0/22',
        '141.101.64.0/18',
        '162.158.0.0/15',
        '172.64.0.0/13',
        '173.245.48.0/20',
        '188.114.96.0/20',
        '190.93.240.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17'
    ];

    public function __construct() {
        parent::__construct();

        // If cloudflare isn't telling us a client IP, bust outta here!
        $cloudflareClientIP = val('HTTP_CF_CONNECTING_IP', $_SERVER, null);
        if (is_null($cloudflareClientIP)) {
            return;
        }

        $requestAddress = Gdn::request()->requestAddress();
        $cloudflareRequest = false;
        foreach ($this->CloudflareSourceIPs as $cloudflareIPRange) {

            // Not a cloudflare origin server
            if (!ip_in_range($requestAddress, $cloudflareIPRange)) {
                continue;
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

}
