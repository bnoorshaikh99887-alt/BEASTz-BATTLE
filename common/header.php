<?php
require_once __DIR__ . '/config.php';

$user_wallet = 0.00;
$logged_username = "";

if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, wallet_balance, status FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($u = $res->fetch_assoc()) {
        if ($u['status'] === 'blocked') {
            session_destroy();
            header("Location: login.php?error=" . urlencode("Your account has been suspended."));
            exit;
        }
        $user_wallet = (float)$u['wallet_balance'];
        $logged_username = $u['username'];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Adept Play - Mobile Esports Tournaments</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f5f3ff',
                            500: '#8b5cf6',
                            600: '#7c3aed',
                            700: '#6d28d9',
                            gold: '#f59e0b',
                            dark: '#0f172a',
                            card: '#1e293b'
                        }
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Mobile Native App Hardening */
        body {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            touch-action: pan-y;
        }
        input, textarea {
            -webkit-user-select: auto !important;
            user-select: auto !important;
        }
        /* Custom scrollbar hiding for app-like presentation */
        ::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col items-center justify-start overflow-x-hidden select-none">
    
    <!-- Mobile App Container (Max Width 480px) -->
    <div class="w-full max-w-md min-h-screen bg-slate-900 border-x border-slate-800 flex flex-col relative pb-20 shadow-2xl">
        
        <!-- Top App Bar -->
        <header class="sticky top-0 z-40 bg-slate-900/95 backdrop-blur-md border-b border-slate-800/80 px-4 py-3 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-500 flex items-center justify-center text-white shadow-lg shadow-violet-500/20">
                    <i class="fa-solid fa-gamepad text-lg"></i>
                </div>
                <div>
                    <h1 class="text-lg font-black tracking-wider text-white leading-tight">ADEPT<span class="text-violet-500">PLAY</span></h1>
                    <p class="text-[10px] text-slate-400 font-medium tracking-widest uppercase">Esports Arena</p>
                </div>
            </a>

            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="wallet.php" class="flex items-center gap-2 bg-slate-800 hover:bg-slate-700/80 border border-slate-700 px-3 py-1.5 rounded-full shadow-inner transition">
                    <i class="fa-solid fa-wallet text-amber-400 text-xs"></i>
                    <span class="text-xs font-bold text-emerald-400 font-mono"><?= format_inr($user_wallet); ?></span>
                    <i class="fa-solid fa-circle-plus text-violet-400 text-xs"></i>
                </a>
            <?php else: ?>
                <a href="login.php" class="text-xs bg-violet-600 hover:bg-violet-700 text-white font-semibold px-3 py-1.5 rounded-lg shadow-md transition">
                    <i class="fa-solid fa-arrow-right-to-bracket mr-1"></i> Sign In
                </a>
            <?php endif; ?>
        </header>

        <!-- Main Body Wrapper -->
        <main class="flex-1 p-4">