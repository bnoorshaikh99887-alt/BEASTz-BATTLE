<?php
require_once __DIR__ . '/config.php';

// Strictly Enforce Admin Authentication
$is_admin = false;
if (isset($_SESSION['admin_id']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
    $is_admin = true;
}

if (!$is_admin) {
    safe_redirect("../admin/login.php");
    exit;
}

$user_wallet = 0.00;
$logged_username = $_SESSION['admin_user'] ?? $_SESSION['username'] ?? 'Admin';
$uid = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);

if ($uid > 0 && $conn && !$conn->connect_error) {
    $stmt = $conn->prepare("SELECT username, wallet_balance, status, role FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($u = $res->fetch_assoc()) {
            if (($u['role'] ?? '') === 'admin') {
                if (($u['status'] ?? 'active') !== 'active') {
                    @$conn->query("UPDATE users SET status = 'active' WHERE id = {$uid}");
                }
            } elseif (($u['status'] ?? 'active') === 'blocked') {
                safe_redirect("/logout.php");
                exit;
            }
            $user_wallet = (float)($u['wallet_balance'] ?? 0.00);
            $logged_username = $u['username'] ?? $logged_username;
        }
        $stmt->close();
    }
}

$cur_script = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Immediate Dark Mode Detection -->
    <script src="/theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Control Center - BEASTzBATTLE</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/theme.css">
    <style>
        * { font-family: 'Plus Jakarta Sans', sans-serif; }
        h1, h2, h3, .font-display { font-family: 'Outfit', sans-serif; }
        body {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
            touch-action: pan-y;
            background-color: #f1f5f9;
        }
        input, textarea, select {
            -webkit-user-select: auto !important;
            user-select: auto !important;
        }
        ::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex flex-col items-center justify-start overflow-x-hidden">
    <!-- Admin Container (White + Gray + Yellow Theme Matching User Panel) -->
    <div class="w-full max-w-md min-h-screen bg-slate-50 border-x border-slate-200 flex flex-col relative pb-20 shadow-xl">
        
        <!-- Admin Top Navigation Banner -->
        <header class="bg-white/95 backdrop-blur-md border-b border-slate-200 px-3.5 py-2.5 flex items-center justify-between text-xs sticky top-0 z-50 shadow-xs">
            <div class="flex items-center gap-2">
                <a href="index.php" class="flex items-center gap-1.5">
                    <span class="px-2 py-0.5 rounded-lg bg-yellow-400 text-slate-950 font-black text-[10px] tracking-wider uppercase">
                        ADMIN
                    </span>
                    <span class="font-black text-slate-900 tracking-tight font-display text-sm">
                        BEASTz<span class="text-yellow-500">BATTLE</span>
                    </span>
                </a>
            </div>

            <div class="flex items-center gap-1.5 sm:gap-2">
                <button type="button" onclick="toggleTheme()" title="Toggle Dark / Light Theme" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-yellow-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-700 dark:text-yellow-400 hover:border-yellow-400 transition active:scale-90 shadow-2xs">
                    <i class="fa-solid fa-moon text-xs beastz-theme-icon"></i>
                </button>
                <a href="../index.php" title="Open Player View" target="_blank" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[10px] font-bold border border-slate-200 transition flex items-center gap-1">
                    <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                    <span>Player App</span>
                </a>

                <a href="/logout.php" title="Sign Out" class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-[10px] font-bold border border-rose-200 transition flex items-center gap-1">
                    <i class="fa-solid fa-power-off text-[9px]"></i>
                    <span>Logout</span>
                </a>
            </div>
        </header>

        <!-- Quick Sub-Navigation Menu (Horizontal scrolling tabs) -->
        <div class="bg-white border-b border-slate-200 px-3 py-1.5 flex items-center gap-2 overflow-x-auto text-[11px] font-bold">
            <a href="index.php" class="px-2.5 py-1 rounded-lg transition whitespace-nowrap <?= ($cur_script === 'index.php') ? 'bg-yellow-400 text-slate-950 font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'; ?>">
                Dashboard
            </a>
            <a href="tournament.php" class="px-2.5 py-1 rounded-lg transition whitespace-nowrap <?= ($cur_script === 'tournament.php' || $cur_script === 'manage_tournament.php') ? 'bg-yellow-400 text-slate-950 font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'; ?>">
                Tournaments
            </a>
            <a href="deposits.php" class="px-2.5 py-1 rounded-lg transition whitespace-nowrap <?= ($cur_script === 'deposits.php') ? 'bg-yellow-400 text-slate-950 font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'; ?>">
                Deposits
            </a>
            <a href="withdrawals.php" class="px-2.5 py-1 rounded-lg transition whitespace-nowrap <?= ($cur_script === 'withdrawals.php') ? 'bg-yellow-400 text-slate-950 font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'; ?>">
                Payouts
            </a>
            <a href="user.php" class="px-2.5 py-1 rounded-lg transition whitespace-nowrap <?= ($cur_script === 'user.php') ? 'bg-yellow-400 text-slate-950 font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'; ?>">
                Users
            </a>
            <a href="settings.php" class="px-2.5 py-1 rounded-lg transition whitespace-nowrap <?= ($cur_script === 'settings.php') ? 'bg-yellow-400 text-slate-950 font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'; ?>">
                Settings
            </a>
        </div>

        <main class="flex-1 p-3.5">
