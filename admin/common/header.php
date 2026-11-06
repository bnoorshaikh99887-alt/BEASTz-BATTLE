<?php
require_once __DIR__ . '/config.php';

// Strictly Enforce Admin Authentication
$is_admin = false;
if (!empty($_SESSION['admin_id']) || (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') || !empty($_SESSION['admin_logged_in'])) {
    $is_admin = true;
}

if (!$is_admin) {
    safe_redirect("../login.php");
    exit;
}

// Robust Role Separation: Master Admin vs Worker/Host Panel
$current_admin_user = strtolower(trim($_SESSION['admin_user'] ?? $_SESSION['username'] ?? ''));
$logged_username = $current_admin_user;
$current_admin_id = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
$is_super = false;
if (
    !empty($_SESSION['is_super_admin']) ||
    $current_admin_user === 'anonymous.349' ||
    $current_admin_user === 'admin' ||
    $current_admin_user === 'noorshaikh99887@gmail.com' ||
    (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin') ||
    ($current_admin_id === 1 || $current_admin_id === 210001 || $current_admin_id === 30002)
) {
    $is_super = true;
    $_SESSION['is_super_admin'] = true;
}

$cur_script = basename($_SERVER['PHP_SELF'] ?? '');
$cur_admin_tab = sanitize($_GET['tab'] ?? 'overview');
$act_btn = "bg-blue-600 text-white font-black shadow-md border border-blue-500";
$inact_btn = "text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800";
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
    <script src="https://cdn.tailwindcss.com" defer></script>
    <script>
        tailwind = window.tailwind || {};
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/theme.css?v=2.5.6">
    <style>
        * { font-family: 'Plus Jakarta Sans', sans-serif; }
        h1, h2, h3, .font-display { font-family: 'Outfit', sans-serif; }
        body {
            -webkit-touch-callout: none;
            touch-action: manipulation;
            background-color: #f1f5f9;
        }
        input, textarea, select, button, a {
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
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
                    <?php if ($is_super): ?>
                    <span class="px-2 py-0.5 rounded-lg bg-blue-600 text-slate-950 font-black text-[10px] tracking-wider uppercase">
                        ADMIN PANEL
                    </span>
                    <?php else: ?>
                    <span class="px-2 py-0.5 rounded-lg bg-emerald-400 text-slate-950 font-black text-[10px] tracking-wider uppercase">
                        WORKER PANEL
                    </span>
                    <?php endif; ?>
                    <span class="font-black text-slate-900 tracking-tight font-display text-sm">
                        BEASTz<span class="text-blue-400">BATTLE</span>
                    </span>
                </a>
            </div>

            <div class="flex items-center gap-1.5 sm:gap-2">
                <button type="button" onclick="toggleTheme()" title="Toggle Dark / Light Theme" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-blue-950/40 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-700 dark:text-sky-400 hover:border-blue-500 transition active:scale-90 shadow-2xs">
                    <i class="fa-solid fa-moon text-xs beastz-theme-icon"></i>
                </button>
                <a href="../index.php?view=player" title="Open Player View" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[10px] font-bold border border-slate-200 transition flex items-center gap-1">
                    <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                    <span>Player App</span>
                </a>

                <a href="/logout.php" onclick="return confirm('Are you sure you want to log out?');" title="Sign Out" class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-[10px] font-bold border border-rose-200 transition flex items-center gap-1">
                    <i class="fa-solid fa-power-off text-[9px]"></i>
                    <span>Logout</span>
                </a>
            </div>
        </header>

        <!-- Quick Sub-Navigation Menu -->
        <div class="bg-white/95 dark:bg-[#0b0f19]/95 border-b border-slate-200 dark:border-slate-800 px-3 py-2 flex items-center gap-1.5 overflow-x-auto text-[11px] font-bold no-scrollbar sticky top-12 z-40">
            <button type="button" onclick="switchAdminTab('overview')" id="admin-nav-overview" class="admin-tab-btn px-3 py-1.5 rounded-full whitespace-nowrap transition cursor-pointer <?= ($cur_admin_tab === 'overview' ? $act_btn : $inact_btn); ?>">
                <i class="fa-solid fa-gauge mr-1"></i> Dashboard
            </button>
            <button type="button" onclick="switchAdminTab('tournaments')" id="admin-nav-tournaments" class="admin-tab-btn px-3 py-1.5 rounded-full whitespace-nowrap transition cursor-pointer <?= ($cur_admin_tab === 'tournaments' ? $act_btn : $inact_btn); ?>">
                <i class="fa-solid fa-trophy mr-1"></i> Tourneys
            </button>
            <button type="button" onclick="switchAdminTab('deposits')" id="admin-nav-deposits" class="admin-tab-btn px-3 py-1.5 rounded-full whitespace-nowrap transition cursor-pointer <?= ($cur_admin_tab === 'deposits' ? $act_btn : $inact_btn); ?>">
                <i class="fa-solid fa-hand-holding-dollar mr-1"></i> Deposits
            </button>
            <button type="button" onclick="switchAdminTab('payouts')" id="admin-nav-payouts" class="admin-tab-btn px-3 py-1.5 rounded-full whitespace-nowrap transition cursor-pointer <?= ($cur_admin_tab === 'payouts' ? $act_btn : $inact_btn); ?>">
                <i class="fa-solid fa-money-bill-transfer mr-1"></i> Payouts
            </button>
            <button type="button" onclick="switchAdminTab('notifications')" id="admin-nav-notifications" class="admin-tab-btn px-3 py-1.5 rounded-full whitespace-nowrap transition cursor-pointer <?= ($cur_admin_tab === 'notifications' ? $act_btn : $inact_btn); ?>">
                <i class="fa-solid fa-paper-plane mr-1"></i> Push Alerts
            </button>
            <button type="button" onclick="switchAdminTab('admins')" id="admin-nav-admins" class="admin-tab-btn px-3 py-1.5 rounded-full whitespace-nowrap transition cursor-pointer <?= ($cur_admin_tab === 'admins' ? $act_btn : $inact_btn); ?>">
                <i class="fa-solid fa-user-shield mr-1"></i> Worker Accounts
            </button>
            <button type="button" onclick="switchAdminTab('users')" id="admin-nav-users" class="admin-tab-btn px-3 py-1.5 rounded-full whitespace-nowrap transition cursor-pointer <?= ($cur_admin_tab === 'users' ? $act_btn : $inact_btn); ?>">
                <i class="fa-solid fa-users mr-1"></i> Gamers
            </button>
            <a href="/worker.php" target="_blank" class="px-3 py-1.5 rounded-full whitespace-nowrap transition cursor-pointer text-amber-300 hover:text-amber-200 font-bold bg-blue-600/20 border border-amber-500/40 flex items-center gap-1">
                <img src="/logo.png" alt="BEASTz" class="w-5 h-5 object-contain"> Worker Panel <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
            </a>
            <?php if ($is_super): ?>
            <button type="button" onclick="switchAdminTab('settings')" id="admin-nav-settings" class="admin-tab-btn px-3 py-1.5 rounded-full whitespace-nowrap transition cursor-pointer <?= ($cur_admin_tab === 'settings' ? $act_btn : $inact_btn); ?>">
                <i class="fa-solid fa-sliders mr-1"></i> Settings
            </button>
            <?php endif; ?>
        </div>
        <main class="flex-1 p-3.5 space-y-4">
        
