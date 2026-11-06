<?php
require_once __DIR__ . '/env.php';

/**
 * BEASTz BATTLE - Ultra-Reliable OneSignal Push Notification Engine
 * App ID: 561bd59e-8355-47c8-88cc-0766688a105a
 */

if (!defined("ONESIGNAL_APP_ID")) {
    define("ONESIGNAL_APP_ID", env("ONESIGNAL_APP_ID", "561bd59e-8355-47c8-88cc-0766688a105a"));
}

if (!defined("ONESIGNAL_DEFAULT_REST_KEY")) {
    define("ONESIGNAL_DEFAULT_REST_KEY", base64_decode("b3NfdjJfYXBwX2t5bjVsaHVka3ZkNHJjZ21hNXRncmNxcWxrM3A0bTR0NXliZTQ1ZnpmbWJjZ3VlYWtmb2xwdHFycWlld25ybXA2N21pNHB4azV3aHllMzZiNTVzbTQ3bDdxbXF0ZGJ2MnVmZmpzYXk="));
}

if (!defined("ONESIGNAL_API_KEY")) {
    define("ONESIGNAL_API_KEY", env("ONESIGNAL_API_KEY", ONESIGNAL_DEFAULT_REST_KEY));
}

function get_onesignal_key() {
    $k = getenv("ONESIGNAL_API_KEY") ?: (defined("ONESIGNAL_API_KEY") ? ONESIGNAL_API_KEY : "");
    if (empty($k)) {
        $k = ONESIGNAL_DEFAULT_REST_KEY;
    }
    return trim($k);
}

/**
 * Dispatches push notification via OneSignal REST API v1
 * 
 * @param string $title Header of the push notification
 * @param string $message Body text
 * @param string $target_url Deep link inside the app (e.g. /?tab=matches, /?tab=home)
 * @param array $player_ids Array of OneSignal UUID device tokens
 * @param array $user_ids Array of internal user IDs (e.g. [480001, 600001])
 * @param array $extra_data Custom key-value pairs (e.g. badge_type, tournament_id)
 * @param bool $allow_broadcast If true, broadcasts to "Total Subscriptions" when no targets provided
 * @return array Result summary with success, http_code, notif_id
 */
function send_onesignal_push($title, $message, $target_url = "/?tab=home", $player_ids = [], $user_ids = [], $extra_data = [], $allow_broadcast = false) {
    global $conn;

    $app_id = defined("ONESIGNAL_APP_ID") ? ONESIGNAL_APP_ID : "561bd59e-8355-47c8-88cc-0766688a105a";

    $payload = [
        "app_id" => $app_id,
        "headings" => ["en" => (string)$title],
        "contents" => ["en" => (string)$message],
        "data" => array_merge([
            "target_url" => $target_url,
            "timestamp" => time()
        ], $extra_data),
        "priority" => 10,
        "android_visibility" => 1,
        "content_available" => true,
        "small_icon" => "ic_stat_onesignal_default"
    ];

    if (!is_array($player_ids)) $player_ids = !empty($player_ids) ? [$player_ids] : [];
    if (!is_array($user_ids)) $user_ids = !empty($user_ids) ? [$user_ids] : [];

    // Clean user IDs
    $cleaned_uids = array_values(array_unique(array_filter(array_map("strval", $user_ids), function($v) {
        return $v !== "" && $v !== "0";
    })));

    // Look up device tokens from user_devices and users table for any targeted user IDs
    if (!empty($cleaned_uids) && $conn && !$conn->connect_error) {
        $escaped_uids = array_map(function($u) { return (int)$u; }, $cleaned_uids);
        $escaped_uids = array_filter($escaped_uids, function($u) { return $u > 0; });
        if (!empty($escaped_uids)) {
            $uid_list = implode(",", $escaped_uids);
            
            $q_dev = @$conn->query("SELECT player_id FROM user_devices WHERE user_id IN ({$uid_list}) AND player_id IS NOT NULL AND player_id != ''");
            if ($q_dev) {
                while ($r = $q_dev->fetch_assoc()) {
                    if (!empty($r['player_id'])) $player_ids[] = trim($r['player_id']);
                }
            }

            $q_usr = @$conn->query("SELECT player_id FROM users WHERE id IN ({$uid_list}) AND player_id IS NOT NULL AND player_id != ''");
            if ($q_usr) {
                while ($r = $q_usr->fetch_assoc()) {
                    if (!empty($r['player_id'])) $player_ids[] = trim($r['player_id']);
                }
            }
        }
    }

    $has_direct_targets = false;

    // OneSignal strictly requires valid UUID format for include_player_ids
    $cleaned_pids = array_values(array_unique(array_filter($player_ids, function($v) {
        return is_string($v) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($v));
    })));

    if (!empty($cleaned_pids)) {
        $payload["include_player_ids"] = $cleaned_pids;
        $has_direct_targets = true;
    }

    if (!empty($cleaned_uids)) {
        $payload["include_external_user_ids"] = $cleaned_uids;
        $payload["channel_for_external_user_ids"] = "push";
        $has_direct_targets = true;
    }

    if (!$has_direct_targets) {
        if ($allow_broadcast) {
            $payload["included_segments"] = ["Total Subscriptions"];
        } else {
            return [
                "success" => false,
                "reason" => "No registered player IDs or user tokens enrolled for this targeted event",
                "http_code" => 200,
                "skipped_broadcast" => true
            ];
        }
    }

    $json_payload = json_encode($payload);
    $api_key = get_onesignal_key();

    $response = "";
    $http_code = 0;
    $curl_error = "";

    // Execute via cURL with 2s connect and 4s execution timeout
    if (function_exists("curl_init")) {
        $ch = curl_init("https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json; charset=utf-8",
            "Authorization: Basic " . $api_key
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
    }

    // Stream context fallback if cURL is unavailable or failed
    if (empty($response) || $http_code === 0) {
        $opts = [
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/json; charset=utf-8\r\n" .
                            "Authorization: Basic " . $api_key . "\r\n",
                "content" => $json_payload,
                "timeout" => 4,
                "ignore_errors" => true
            ],
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false
            ]
        ];
        $ctx = stream_context_create($opts);
        $stream_res = @file_get_contents("https://onesignal.com/api/v1/notifications", false, $ctx);
        if ($stream_res !== false) {
            $response = $stream_res;
            $http_code = 200;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $hdr) {
                    if (preg_match("#HTTP/[0-9\.]+\s+([0-9]+)#i", $hdr, $m)) {
                        $http_code = (int)$m[1];
                    }
                }
            }
        }
    }

    $res_data = json_decode($response, true) ?? $response;

    return [
        "success" => ($http_code >= 200 && $http_code < 300),
        "http_code" => $http_code,
        "response" => $res_data,
        "notif_id" => is_array($res_data) ? ($res_data["id"] ?? "") : ""
    ];
}

if (!function_exists("send_player_push_notification")) {
    function send_player_push_notification($player_ids, $title, $message, $target_url = "/?tab=wallet", $extra_data = [], $allow_broadcast = false) {
        return send_onesignal_push($title, $message, $target_url, $player_ids, [], $extra_data, $allow_broadcast);
    }
}

if (!function_exists("sendOneSignalNotification")) {
    function sendOneSignalNotification($title, $message, $target_url = "/?tab=home", $player_ids = [], $user_ids = [], $extra_data = [], $allow_broadcast = false) {
        return send_onesignal_push($title, $message, $target_url, $player_ids, $user_ids, $extra_data, $allow_broadcast);
    }
}
?>
