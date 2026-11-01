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

// Robust Role Separation: Master Admin vs Worker/Host Panel
$current_admin_user = strtolower(trim($_SESSION['admin_user'] ?? $_SESSION['username'] ?? ''));
$current_admin_id = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
$is_super = false;
if (
    !empty($_SESSION['is_super_admin']) ||
    $current_admin_user === 'anonymous.349' ||
    $current_admin_user === 'admin' ||
    $current_admin_user === 'noorshaikh99887@gmail.com' ||
    (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin') ||
    ($current_admin_id === 1 || $current_admin_id === 210001)
) {
    $is_super = true;
    $_SESSION['is_super_admin'] = true;
}

$cur_script = basename($_SERVER['PHP_SELF'] ?? '');
$cur_admin_tab = sanitize($_GET['tab'] ?? 'overview');
$act_btn = "bg-yellow-400 text-slate-950 font-black shadow-xs border border-yellow-400";
$inact_btn = "text-slate-400 hover:text-white font-bold bg-slate-900 border border-slate-800";
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
                    <?php if ($is_super): ?>
                    <span class="px-2 py-0.5 rounded-lg bg-yellow-400 text-slate-950 font-black text-[10px] tracking-wider uppercase">
                        ADMIN PANEL
                    </span>
                    <?php else: ?>
                    <span class="px-2 py-0.5 rounded-lg bg-emerald-400 text-slate-950 font-black text-[10px] tracking-wider uppercase">
                        WORKER PANEL
                    </span>
                    <?php endif; ?>
                    <span class="font-black text-slate-900 tracking-tight font-display text-sm">
                        BEASTz<span class="text-yellow-500">BATTLE</span>
                    </span>
                </a>
            </div>

            <div class="flex items-center gap-1.5 sm:gap-2">
                <button type="button" onclick="toggleTheme()" title="Toggle Dark / Light Theme" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-yellow-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-700 dark:text-yellow-400 hover:border-yellow-400 transition active:scale-90 shadow-2xs">
                    <i class="fa-solid fa-moon text-xs beastz-theme-icon"></i>
                </button>
                <a href="../index.php?view=player" title="Open Player View" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[10px] font-bold border border-slate-200 transition flex items-center gap-1">
                    <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                    <span>Player App</span>
                </a>

                <a href="/logout.php" title="Sign Out" class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-[10px] font-bold border border-rose-200 transition flex items-center gap-1">
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
            <?php if ($is_super): ?>
            <button type="button" onclick="switchAdminTab('deposits')" id="admin-nav-deposits" class="admin-tab-btn px-3 py-1.5 rounded-full whitespace-nowrap transition cursor-pointer <?= ($cur_admin_tab === 'deposits' ? $act_btn : $inact_btn); ?>">
                <i class="fa-solid fa-hand-holding-dollar mr-1"></i> Deposits
            </button>
            <button type="button" onclick="switchAdminTab('payouts')" id="admin-nav-payouts" class="admin-tab-btn px-3 py-1.5 rounded-full whitespace-nowrap transition cursor-pointer <?= ($cur_admin_tab === 'payouts' ? $act_btn : $inact_btn); ?>">
                <i class="fa-solid fa-money-bill-transfer mr-1"></i> Payouts
            </button>
            <button type="button" onclick="switchAdminTab('notifications')" id="admin-nav-notifications" class="admin-tab-btn px-3 py-1.5 rounded-full whitespace-nowrap transition cursor-pointer <?= ($cur_admin_tab === 'notifications' ? $act_btn : $inact_btn); ?>">
                <i class="fa-solid fa-bell mr-1"></i> Broadcast
            </button>
            <button type="button" onclick="switchAdminTab('users')" id="admin-nav-users" class="admin-tab-btn px-3 py-1.5 rounded-full whitespace-nowrap transition cursor-pointer <?= ($cur_admin_tab === 'users' ? $act_btn : $inact_btn); ?>">
                <i class="fa-solid fa-users mr-1"></i> Login Users
            </button>
            <?php endif; ?>
            <button type="button" onclick="switchAdminTab('admins')" id="admin-nav-admins" class="admin-tab-btn px-3 py-1.5 rounded-full whitespace-nowrap transition cursor-pointer <?= ($cur_admin_tab === 'admins' ? $act_btn : $inact_btn); ?>">
                <i class="fa-solid fa-users-gear mr-1"></i> <?= $is_super ? "Manage Admins" : "Tourney Center"; ?>
            </button>
            <?php if ($is_super): ?>
            <button type="button" onclick="switchAdminTab('settings')" id="admin-nav-settings" class="admin-tab-btn px-3 py-1.5 rounded-full whitespace-nowrap transition cursor-pointer <?= ($cur_admin_tab === 'settings' ? $act_btn : $inact_btn); ?>">
                <i class="fa-solid fa-sliders mr-1"></i> Settings
            </button>
            <?php endif; ?>
        </div>
        <main class="flex-1 p-3.5 space-y-4">
        
