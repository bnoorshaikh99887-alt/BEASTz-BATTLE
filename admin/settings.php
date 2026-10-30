<?php
require_once __DIR__ . '/common/header.php';

// If GET request, immediately forward into ultra-fast unified SPA
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    safe_redirect("index.php?tab=settings");
    exit;
}

// Process POST actions inside the view module
require_once __DIR__ . '/views/settings_view.php';

// Redirect back to unified SPA tab
safe_redirect("index.php?tab=settings");
exit;
