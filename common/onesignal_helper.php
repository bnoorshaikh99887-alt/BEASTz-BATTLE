<?php
/**
 * BEASTz BATTLE - OneSignal Push Notification Engine
 * High-reliability targeted & broadcast push dispatcher
 */

if (!defined("ONESIGNAL_APP_ID")) {
    define("ONESIGNAL_APP_ID", "561bd59e-8355-47c8-88cc-0766688a105a");
}

function get_onesignal_key() {
    return getenv("ONESIGNAL_API_KEY") ?: base64_decode("b3NfdjJfYXBwX2t5bjVsaHVka3ZkNHJjZ21hNXRncmNxcWxrM3A0bTR0NXliZTQ1ZnpmbWJjZ3VlYWtmb2xwdHFycWlld25ybXA2N21pNHB4azV3aHllMzZiNTVzbTQ3bDdxbXF0ZGJ2MnVmZmpzYXk=");
}

function send_player_push_notification($player_ids, $title, $message, $target_url = "/?tab=wallet", $extra_data = []) {
    if (empty($player_ids)) {
        return ["success" => false, "error" => "No player IDs provided"];
    }

    if (!is_array($player_ids)) {
        $player_ids = [$player_ids];
    }
    $player_ids = array_values(array_filter($player_ids));
    if (empty($player_ids)) {
        return ["success" => false, "error" => "Empty player IDs after filtering"];
    }

    $payload = [
        "app_id" => ONESIGNAL_APP_ID,
        "include_player_ids" => $player_ids,
        "headings" => ["en" => $title],
        "contents" => ["en" => $message],
        "data" => array_merge([
            "target_url" => $target_url,
            "timestamp" => time()
        ], $extra_data),
        "priority" => 10,
        "android_visibility" => 1,
        "content_available" => true,
        "android_sound" => "notification"
    ];

    $ch = curl_init("https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json; charset=utf-8",
        "Authorization: Key " . get_onesignal_key()
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        "success" => ($http_code >= 200 && $http_code < 300),
        "http_code" => $http_code,
        "response" => json_decode($response, true) ?? $response
    ];
}
?>