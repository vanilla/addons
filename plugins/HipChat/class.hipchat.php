<?php
/**
 * A HipChat integration.
 *
 * @copyright 2008-2016 Vanilla Forums, Inc.
 * @license GNU GPLv2
 */

/**
 * Class HipChatPlugin.
 */
class HipChat {

    /**
     * Send a notification to a HipChat room.
     *
     * @param string $message
     * @param string $color
     */
    public static function say($message = '', $color = "green") {
        if (!c('HipChat.Token') || !c('HipChat.Account')) {
            return;
        }

        // Prepare our API endpoint.
        $url = sprintf('https://%1$s.hipchat.com/v2/room/%2$s/notification?auth_token=%3$s',
            c('HipChat.Account'),
            c('HipChat.Room'),
            c('HipChat.Token')
        );

        $data = ["color" => $color, "message" => $message, "notify" => false, "message_format" => "html"];
        $data = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        curl_exec($ch);
        curl_close($ch);
    }
}