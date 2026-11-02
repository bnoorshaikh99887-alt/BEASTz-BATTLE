<?php
/**
 * BEASTz BATTLE - Ultra-Reliable OneSignal Push Notification Engine
 * App ID: 561bd59e-8355-47c8-88cc-0766688a105a
 */

if (!defined("ONESIGNAL_APP_ID")) {
    define("ONESIGNAL_APP_ID", "561bd59e-8355-47c8-88cc-0766688a105a");
}

if (!defined("ONESIGNAL_API_KEY")) {
    define("ONESIGNAL_API_KEY", base64_decode("b3NfdjJfYXBwX2t5bjVsaHVka3ZkNHJjZ21hNXRncmNxcWxrM3A0bTR0NXliZTQ1ZnpmbWJjZ3VlYWtmb2xwdHFycWlld25ybXA2N21pNHB4azV3aHllMzZiNTVzbTQ3bDdxbXF0ZGJ2MnVmZmpzYXk="));
}

function get_onesignal_key() {
    return getenv("ONESIGNAL_API_KEY") ?: ONESIGNAL_API_KEY;
}

function send_onesignal_push($title, $message, $target_url = "/?tab=home", $player_ids = [], $user_ids = [], $extra_data = [], $allow_broadcast = false) {
    global $conn;

    $payload = [
        "app_id" => ONESIGNAL_APP_ID,
        "headings" => ["en" => (string)$title],
        "contents" => ["en" => (string)$message],
        "data" => array_merge([
            "target_url" => $target_url,
            "timestamp" => time()
        ], $extra_data),
        "priority" => 10,
        "android_visibility" => 1,
        "content_available" => true,
        "android_sound" => "notification",
        "small_icon" => "ic_stat_onesignal_default"
    ];

    if (!is_array($player_ids)) $player_ids = !empty($player_ids) ? [$player_ids] : [];
    if (!is_array($user_ids)) $user_ids = !empty($user_ids) ? [$user_ids] : [];

    $cleaned_uids = array_values(array_unique(array_filter(array_map("strval", $user_ids), function($v) {
        return $v !== "" && $v !== "0";
    })));

    if (!empty($cleaned_uids) && $conn && !$conn->connect_error) {
        $escaped_uids = array_map(function($u) use ($conn) { return (int)$u; }, $cleaned_uids);
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

    $cleaned_pids = array_values(array_unique(array_filter($player_ids, function($v) {
        return !empty($v) && is_string($v) && strlen(trim($v)) > 15;
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

    // Execute via cURL with ultra-fast non-blocking timeouts (2s connect, 3s max)
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if (empty($response) && !empty($curl_error)) {
        $opts = [
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/json; charset=utf-8\r\n" .
                            "Authorization: Basic " . $api_key . "\r\n",
                "content" => $json_payload,
                "timeout" => 3,
                "ignore_errors" => true
            ],
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false
            ]
        ];
        $ctx = stream_context_create($opts);
        $response = @file_get_contents("https://onesignal.com/api/v1/notifications", false, $ctx);
        if ($response !== false) {
            $http_code = 200;
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
    function sendOneSignalNotification($title, $message, $target_url = "/?tab=home", $player_ids = [], $user_ids = []) {
        return send_onesignal_push($title, $message, $target_url, $player_ids, $user_ids, [], false);
    }
}
?>
