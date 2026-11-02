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

/**
 * Dispatch Push Notification via OneSignal REST API
 * Supports targeted device tokens (player_ids), user IDs (external_user_ids), or general broadcast.
 *
 * @param string $title
 * @param string $message
 * @param string $target_url
 * @param array $player_ids   Array of OneSignal device player_id UUIDs
 * @param array $user_ids     Array of database user IDs
 * @param array $extra_data   Additional custom payload data
 * @param bool  $allow_broadcast If false, notifications with empty targets will NEVER broadcast to all users!
 * @return array
 */
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
        "android_sound" => "notification"
    ];

    if (!is_array($player_ids)) $player_ids = !empty($player_ids) ? [$player_ids] : [];
    if (!is_array($user_ids)) $user_ids = !empty($user_ids) ? [$user_ids] : [];

    // Filter user IDs
    $cleaned_uids = array_values(array_unique(array_filter(array_map("strval", $user_ids), function($v) {
        return $v !== "" && $v !== "0";
    })));

    // Deep token lookup: If database connection is active, query both user_devices and users
    if (!empty($cleaned_uids) && $conn && !$conn->connect_error) {
        $escaped_uids = array_map(function($u) use ($conn) { return (int)$u; }, $cleaned_uids);
        $escaped_uids = array_filter($escaped_uids, function($u) { return $u > 0; });
        if (!empty($escaped_uids)) {
            $uid_list = implode(",", $escaped_uids);
            
            // 1. Fetch all registered devices for these users
            $q_dev = @$conn->query("SELECT player_id FROM user_devices WHERE user_id IN ({$uid_list}) AND player_id IS NOT NULL AND player_id != ''");
            if ($q_dev) {
                while ($r = $q_dev->fetch_assoc()) {
                    if (!empty($r['player_id'])) $player_ids[] = trim($r['player_id']);
                }
            }

            // 2. Fetch primary token from users table
            $q_usr = @$conn->query("SELECT player_id FROM users WHERE id IN ({$uid_list}) AND player_id IS NOT NULL AND player_id != ''");
            if ($q_usr) {
                while ($r = $q_usr->fetch_assoc()) {
                    if (!empty($r['player_id'])) $player_ids[] = trim($r['player_id']);
                }
            }
        }
    }

    $has_direct_targets = false;

    // 1. Direct Device Tokens / Player IDs
    $cleaned_pids = array_values(array_unique(array_filter($player_ids, function($v) {
        return !empty($v) && is_string($v) && strlen(trim($v)) > 15;
    })));

    if (!empty($cleaned_pids)) {
        $payload["include_player_ids"] = $cleaned_pids;
        $has_direct_targets = true;
    }

    // 2. User ID mapping (External User IDs)
    if (!empty($cleaned_uids)) {
        $payload["include_external_user_ids"] = $cleaned_uids;
        $has_direct_targets = true;
    }

    // 3. STRICT TARGETING ENFORCEMENT:
    // When broadcasting is disallowed (for auto-push events), NEVER include included_segments!
    if (!$has_direct_targets) {
        if ($allow_broadcast) {
            $payload["included_segments"] = ["Total Subscriptions"];
        } else {
            // Targeted push with 0 participants or unmapped tokens - DO NOT SPAM OTHER GAMERS!
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

    // Execute via cURL
    $ch = curl_init("https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json; charset=utf-8",
        "Authorization: Key " . $api_key
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $res_data = json_decode($response, true) ?? $response;

    return [
        "success" => ($http_code >= 200 && $http_code < 300),
        "http_code" => $http_code,
        "response" => $res_data,
        "notif_id" => is_array($res_data) ? ($res_data["id"] ?? "") : ""
    ];
}

// Backwards compatibility alias
if (!function_exists("send_player_push_notification")) {
    function send_player_push_notification($player_ids, $title, $message, $target_url = "/?tab=wallet", $extra_data = [], $allow_broadcast = false) {
        return send_onesignal_push($title, $message, $target_url, $player_ids, [], $extra_data, $allow_broadcast);
    }
}
?>
