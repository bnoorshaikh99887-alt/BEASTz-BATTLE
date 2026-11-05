<?php
/**
 * BEASTz BATTLE - Public App Update & System Status Endpoint
 */
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate");

if (file_exists(__DIR__ . "/../common/config.php")) {
    require_once __DIR__ . "/../common/config.php";
}

$maintenance = (get_setting('maintenance_mode', '0') === '1');
$force_update = (get_setting('apk_force_update', '0') === '1');
$min_version = get_setting('apk_min_version', '2.5.0');
$latest_version = get_setting('apk_latest_version', '2.5.0');
$download_url = get_setting('apk_download_url', 'https://github.com/bnoorshaikh99887-alt/beast-battle/releases/latest');

echo json_encode([
    "app_name"        => "BEASTz BATTLE",
    "maintenance"     => $maintenance,
    "maintenance_msg" => "Under Maintenance. We are upgrading our systems for a better experience. Please check back shortly.",
    "force_update"    => $force_update,
    "min_version"     => $min_version,
    "latest_version"  => $latest_version,
    "download_url"    => $download_url,
    "timestamp"       => time()
]);
