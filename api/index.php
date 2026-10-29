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

$file = $root . '/' . $path;

// 1. Direct file match (.php or static files like images/css)
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
    header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
    readfile($file);
    exit;
}

// 2. If .php extension was omitted in URL (e.g. /login or /wallet)
if (is_file($file . '.php')) {
    require $file . '.php';
    exit;
}

// 3. Folder index (e.g. /admin or /admin/)
if (is_dir($file) && is_file($file . '/index.php')) {
    require $file . '/index.php';
    exit;
}

// 4. 404 Fallback
http_response_code(404);
echo "404 Not Found: " . htmlspecialchars($request_uri);
exit;
