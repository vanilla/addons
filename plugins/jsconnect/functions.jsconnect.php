<?php
/**
 * This file contains the client code for Vanilla jsConnect single sign on.
 *
 * @author Todd Burry <todd@vanillaforums.com>
 * @version 2.0
 * @copyright 2008-2017 Vanilla Forums, Inc.
 * @license GNU GPLv2 http://www.opensource.org/licenses/gpl-2.0.php
 */

define('JS_TIMEOUT', 24 * 60);

/**
 * Write the jsConnect string for single sign on.
 *
 * @param array $user An array containing information about the currently signed on user. If no user is signed in then this should be an empty array.
 * @param array $request An array of the $_GET request.
 * @param string $clientID The string client ID that you set up in the jsConnect settings page.
 * @param string $secret The string secret that you set up in the jsConnect settings page.
 * @param string|bool $secure Whether or not to check for security. This is one of these values.
 *  - true: Check for security and sign the response with an md5 hash.
 *  - false: Don't check for security, but sign the response with an md5 hash.
 *  - string: Check for security and sign the response with the given hash algorithm. See hash_algos() for what your server can support.
 *  - null: Don't check for security and don't sign the response.
 * @since 1.1b Added the ability to provide a hash algorithm to $secure.
 */
function writeJsConnect($user, $request, $clientID, $secret, $secure = true) {
    $user = array_change_key_case($user);

    // Error checking.
    if ($secure) {
        // Check the client.
        if (!isset($request['v'])) {
            $error = array('error' => 'invalid_request', 'message' => 'Missing the v parameter.');
        } elseif ($request['v'] !== '2') {
            $error = array('error' => 'invalid_request', 'message' => "Unsupported version {$request['v']}.");
        } elseif (!isset($request['client_id'])) {
            $error = array('error' => 'invalid_request', 'message' => 'The client_id parameter is missing.');
        } elseif ($request['client_id'] != $clientID) {
            $error = array('error' => 'invalid_client', 'message' => "Unknown client {$request['client_id']}.");
        } elseif (!isset($request['timestamp']) && !isset($request['sig'])) {
            if (is_array($user) && count($user) > 0) {
                // This isn't really an error, but we are just going to return public information when no signature is sent.
                $error = array('name' => (string)@$user['name'], 'photourl' => @$user['photourl'], 'signedin' => true);
            } else {
                $error = array('name' => '', 'photourl' => '');
            }
        } elseif (!isset($request['timestamp']) || !is_numeric($request['timestamp'])) {
            $error = array('error' => 'invalid_request', 'message' => 'The timestamp parameter is missing or invalid.');
        } elseif (!isset($request['sig'])) {
            $error = array('error' => 'invalid_request', 'message' => 'Missing sig parameter.');
        } // Make sure the timestamp hasn't timedout
        elseif (abs($request['timestamp'] - JsTimestamp()) > JS_TIMEOUT) {
            $error = array('error' => 'invalid_request', 'message' => 'The timestamp is invalid.');
        } elseif (!isset($request['nonce'])) {
            $error = array('error' => 'invalid_request', 'message' => 'Missing nonce parameter.');
        } elseif (!isset($request['ip'])) {
            $error = array('error' => 'invalid_request', 'message' => 'Missing ip parameter.');
        } else {
            $signature = jsHash($request['ip'].$request['nonce'].$request['timestamp'].$secret, $secure);
            if ($signature != $request['sig']) {
                $error = array('error' => 'access_denied', 'message' => 'Signature invalid.');
            }
        }
    }

    if (isset($error)) {
        $result = $error;
    } elseif (is_array($user) && count($user) > 0) {
        if ($secure === null) {
            $result = $user;
        } else {
            $user['ip'] = $request['ip'];
            $user['nonce'] = $request['nonce'];
            $result = signJsConnect($user, $clientID, $secret, $secure, true);
            $result['v'] = '2';
        }
    } else {
        $result = array('name' => '', 'photourl' => '');
    }

    $json = json_encode($result);

    if (isset($request['callback'])) {
        echo "{$request['callback']}($json)";
    } else {
        echo $json;
    }
}

/**
 *
 *
 * @param $data
 * @param $clientID
 * @param $secret
 * @param $hashType
 * @param bool $returnData
 * @return array|string
 */
function signJsConnect($data, $clientID, $secret, $hashType, $returnData = false) {
    $normalizedData = array_change_key_case($data);
    ksort($normalizedData);

    foreach ($normalizedData as $Key => $value) {
        if ($value === null) {
            $normalizedData[$Key] = '';
        }
    }

    $stringifiedData = http_build_query($normalizedData, null, '&');
    $signature = jsHash($stringifiedData.$secret, $hashType);
    if ($returnData) {
        $normalizedData['client_id'] = $clientID;
        $normalizedData['sig'] = $signature;
        return $normalizedData;
    } else {
        return $signature;
    }
}

/**
 * Return the hash of a string.
 *
 * @param string $string The string to hash.
 * @param string|bool $secure The hash algorithm to use. true means md5.
 * @return string
 */
function jsHash($string, $secure = true) {
    if ($secure === true) {
        $secure = 'md5';
    }

    switch ($secure) {
        case 'sha1':
            return sha1($string);
            break;
        case 'md5':
        case false:
            return md5($string);
        default:
            return hash($secure, $string);
    }
}

/**
 *
 *
 * @return int
 */
function jsTimestamp() {
    return time();
}

/**
 * Generate an SSO string suitable for passing in the url for embedded SSO.
 *
 * @param array $user The user to sso.
 * @param string $clientID Your client ID.
 * @param string $secret Your secret.
 * @return string
 */
function jsSSOString($user, $clientID, $secret) {
    if (!isset($user['client_id'])) {
        $user['client_id'] = $clientID;
    }

    $string = base64_encode(json_encode($user));
    $timestamp = time();
    $hash = hash_hmac('sha1', "$string $timestamp", $secret);

    $result = "$string $hash $timestamp hmacsha1";
    return $result;
}
