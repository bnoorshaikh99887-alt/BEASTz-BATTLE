<?php
// api/index.php - Serverless Router for Vercel with Sensitive Files Protection
$request_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = ltrim($request_uri, '/');
// Block Path Traversal
if (str_contains($path, '..') || str_contains($path, '\\')) {
    http_response_code(403);
    exit('403 Forbidden: Invalid Path');
}


// Project root directory (one level up from /api)
$root = dirname(__DIR__);

// Default to index.php if root requested
if (empty($path) || $path === '/' || $path === 'index.php') {
    require $root . '/index.php';
    exit;
}

// SECURITY GUARD: Block direct HTTP access to sensitive files
$basename = basename($path);
if (
    str_starts_with($basename, '.') ||
    str_contains($path, '.env') ||
    str_contains($path, 'common/config') ||
    str_ends_with($basename, '.sql') ||
    str_ends_with($basename, '.md') ||
    str_ends_with($basename, '.json') && $basename !== 'manifest.json'
) {
    http_response_code(403);
    echo "403 Forbidden: Access to sensitive file is restricted.";
    exit;
}

// APK Direct Download Handler (Serves binary if present >500KB or redirects to GitHub Releases v2.6)
if ($path === 'new release update.apk' || $path === 'new.release.update.apk' || $path === 'new_release_update.apk' || $path === 'BEASTz-BATTLE.apk' || $path === 'app.apk' || $path === 'download-apk') {
    $apk_candidates = [
        $root . '/BEASTz-BATTLE.apk',
        __DIR__ . '/BEASTz-BATTLE.apk',
        __DIR__ . '/../BEASTz-BATTLE.apk'
    ];
    foreach ($apk_candidates as $candidate) {
        if (file_exists($candidate) && is_file($candidate) && filesize($candidate) > 500000) {
            header('Content-Type: application/vnd.android.package-archive');
            header('Content-Disposition: attachment; filename="BEASTz-BATTLE.apk"');
            header('Content-Length: ' . filesize($candidate));
            header('Cache-Control: public, max-age=86400');
            readfile($candidate);
            exit;
        }
    }
    // High-speed direct release CDN download
    header('Location: https://github.com/bnoorshaikh99887-alt/BEASTz-BATTLE/releases/download/latest/new.release.update.apk', true, 302);
    exit;
}

// 0. Specific Route Aliases to prevent 404s
if ($path === 'check_update' || $path === 'check_update.php') {
    require __DIR__ . '/check_update.php';
    exit;
}
if ($path === 'private_match' || $path === 'private_match.php') {
    require __DIR__ . '/private_match.php';
    exit;
}
if ($path === 'maintenance_toggle' || $path === 'maintenance_toggle.php') {
    require $root . '/admin/maintenance_toggle.php';
    exit;
}
if ($path === 'apk_update_toggle' || $path === 'apk_update_toggle.php') {
    require $root . '/admin/apk_update_toggle.php';
    exit;
}

if ($path === 'my_matches' || $path === 'my_matches.php') {
    require $root . '/my_tournaments.php';
    exit;
}

if ($path === 'leaderboard' || $path === 'leaderboard.php') {
    require $root . '/leaderboard.php';
    exit;
}

if ($path === 'tournament.php' || $path === 'tournament' || $path === 'tournaments.php' || $path === 'tournaments') {
    if (file_exists($root . '/admin/tournament.php')) {
        require $root . '/admin/tournament.php';
        exit;
    }
}

if ($path === 'manage_tournament.php' || $path === 'manage_tournament') {
    if (file_exists($root . '/admin/manage_tournament.php')) {
        require $root . '/admin/manage_tournament.php';
        exit;
    }
}

$file = $root . '/' . $path;

// 1. Direct file match (.php or static files like images/css/manifest/sw.js)
if (is_file($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        require $file;
        exit;
    }
    
    // Strict Static Assets Allow-List (Blocks arbitrary file disclosures)
    $allowedStaticExts = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'webp'  => 'image/webp',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'apk'   => 'application/vnd.android.package-archive'
    ];

    if ($basename === 'manifest.json') {
        header('Content-Type: application/manifest+json');
        readfile($file);
        exit;
    }

    if (!isset($allowedStaticExts[$ext])) {
        http_response_code(403);
        echo "403 Forbidden: Access to this file extension is restricted.";
        exit;
    }

    if ($ext === 'js' && basename($file) === 'sw.js') {
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache, no-store, must-revalidate');
    }

    header('Content-Type: ' . $allowedStaticExts[$ext]);
    readfile($file);
    exit;
}

// 2. If .php extension was omitted in URL (e.g. /login or /wallet or /leaderboard)
if (is_file($file . '.php')) {
    require $file . '.php';
    exit;
}

// 3. Folder index (e.g. /admin or /admin/)
if (is_dir($file) && is_file($file . '/index.php')) {
    require $file . '/index.php';
    exit;
}

// 4. Admin folder fallback (e.g. /admin/tournament or /deposits -> /admin/deposits.php)
if (is_file($root . '/admin/' . $path)) {
    require $root . '/admin/' . $path;
    exit;
}

if (is_file($root . '/admin/' . $path . '.php')) {
    require $root . '/admin/' . $path . '.php';
    exit;
}

// 404 Fallback
http_response_code(404);
echo "404 Not Found: " . htmlspecialchars($request_uri);
exit;
