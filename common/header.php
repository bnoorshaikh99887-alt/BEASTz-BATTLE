<?php
// common/header.php - Rock-solid Top Bar for Adept Play
require_once __DIR__ . '/config.php';

$user_wallet = 0.00;
$user_display_name = 'Gamer';

if (isset($_SESSION['user_id']) && isset($conn) && $conn && !$conn->connect_error) {
    $uid = intval($_SESSION['user_id']);
    $res = @$conn->query("SELECT `name`, `username`, `wallet` FROM `users` WHERE `id` = $uid LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $u_data = $res->fetch_assoc();
        $user_wallet = floatval($u_data['wallet'] ?? 0);
        $user_display_name = htmlspecialchars(!empty($u_data['name']) ? $u_data['name'] : $u_data['username']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= APP_NAME ?> - Esports Tournaments</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
            touch-action: pan-y;
            background-color: #0b0f19;
            color: #f3f4f6;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
    </style>
</head>
<body class="min-h-screen pb-24 select-none bg-[#0b0f19] text-gray-100" oncontextmenu="return false;">
    <!-- Top Header Bar -->
    <header class="sticky top-0 z-40 bg-[#131b2e]/95 backdrop-blur border-b border-gray-800 px-4 py-3 flex items-center justify-between shadow-lg">
        <a href="/index.php" class="flex items-center space-x-2">
            <span class="w-8 h-8 rounded-lg bg-gradient-to-tr from-amber-500 to-red-600 flex items-center justify-center font-black text-white text-base shadow-md shadow-red-500/20">A</span>
            <span class="font-extrabold tracking-wider text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-red-500 text-lg">ADEPT PLAY</span>
        </a>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="flex items-center space-x-2.5">
                <a href="/wallet.php" class="flex items-center bg-gray-800/90 hover:bg-gray-800 border border-amber-500/30 px-3 py-1 rounded-full text-xs font-semibold text-amber-400 shadow-sm transition active:scale-95">
                    <i class="fa-solid fa-wallet mr-1.5 text-amber-400"></i>
                    <span>₹<?= number_format($user_wallet, 2) ?></span>
                </a>
                <a href="/profile.php" class="w-8 h-8 rounded-full bg-gradient-to-tr from-amber-500/20 to-red-500/20 border border-amber-500/40 flex items-center justify-center text-amber-400 hover:text-white transition">
                    <i class="fa-solid fa-user text-xs"></i>
                </a>
            </div>
        <?php else: ?>
            <a href="/login.php" class="text-xs font-bold bg-amber-500 hover:bg-amber-600 text-black px-4 py-1.5 rounded-full shadow transition">
                Login
            </a>
        <?php endif; ?>
    </header>
    <main class="max-w-md mx-auto px-4 py-4">
