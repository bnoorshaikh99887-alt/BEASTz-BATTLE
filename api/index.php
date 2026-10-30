<?php
// api/index.php - Serverless Router for Vercel
$request_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = ltrim($request_uri, '/');

// Project root directory (one level up from /api)
$root = dirname(__DIR__);

// Default to index.php if root requested
if (empty($path) || $path === '/' || $path === 'index.php') {
    require $root . '/index.php';
    exit;
}

// APK Direct Download Handler
if ($path === 'BEASTz-BATTLE.apk' || $path === 'app.apk' || $path === 'download-apk') {
    $apk_candidates = [
        $root . '/BEASTz-BATTLE.apk',
        __DIR__ . '/BEASTz-BATTLE.apk',
        __DIR__ . '/../BEASTz-BATTLE.apk'
    ];
    foreach ($apk_candidates as $candidate) {
        if (file_exists($candidate) && is_file($candidate)) {
            header('Content-Type: application/vnd.android.package-archive');
            header('Content-Disposition: attachment; filename="BEASTz-BATTLE.apk"');
            header('Content-Length: ' . filesize($candidate));
            header('Cache-Control: public, max-age=86400');
            readfile($candidate);
            exit;
        }
    }
}

// 0. Specific Route Aliases to prevent 404s
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
    
    // Static assets
    $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'json' => 'application/json'
    ];
    if ($ext === 'js' && basename($file) === 'sw.js') {
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache, no-store, must-revalidate');
    }
    header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
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
