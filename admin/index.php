<?php
require_once __DIR__ . '/common/header.php';

// Maintain persistent active_panel = admin
$_SESSION['active_panel'] = 'admin';
if (empty($_SESSION['panel_cookie_set']) && (!isset($_COOKIE['active_panel']) || $_COOKIE['active_panel'] !== 'admin' || !isset($_COOKIE['beastz_active_panel']) || $_COOKIE['beastz_active_panel'] !== 'admin')) {
    $_SESSION['panel_cookie_set'] = 1; // FIX: har request par cookie set nahi hogi
    setcookie('active_panel', 'admin', time() + (365 * 24 * 3600), '/');
    setcookie('beastz_active_panel', 'admin', time() + (365 * 24 * 3600), '/');
}

$req_admin_tab = sanitize($_GET['tab'] ?? 'overview');
$valid_admin_tabs = ['overview', 'tournaments', 'deposits', 'payouts', 'notifications', 'users', 'admins', 'settings'];
if (!in_array($req_admin_tab, $valid_admin_tabs)) {
    $req_admin_tab = 'overview';
}

// Ultra-Fast 0.05s Session-Cached Aggregated Statistics (15s TTL)
$now = time();
// FIX: deposit/payout/tournament action ke baad stats purane (15s stale) dikhte the -> ab turant refresh
$__ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
$__force = isset($_GET['refresh']) || (bool)preg_match('#/(deposits|payouts|withdrawals|tournament|manage_tournament|user|settings)\.php#', $__ref);
if (!isset($_SESSION['admin_stats_cache']) || !isset($_SESSION['admin_stats_cache_time']) || ($now - $_SESSION['admin_stats_cache_time'] > 15) || $__force) {
    $q = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM users) as users_cnt,
            (SELECT COUNT(*) FROM tournaments) as tourneys_cnt,
            (SELECT COALESCE(SUM(prize_pool), 0.00) FROM tournaments WHERE LOWER(status) = 'completed') as prize_dist,
            (SELECT COALESCE(SUM(amount), 0.00) FROM deposits WHERE LOWER(status) IN ('completed', 'success')) as total_collected,
            (SELECT COUNT(*) FROM deposits WHERE LOWER(status) = 'pending') as pending_deposits,
            (SELECT COUNT(*) FROM withdrawals WHERE LOWER(status) = 'pending') as pending_withdrawals
    ");
    if ($q) {
        $_SESSION['admin_stats_cache'] = $q->fetch_assoc();
        $_SESSION['admin_stats_cache_time'] = $now;
    }
}
$stats_row = $_SESSION['admin_stats_cache'] ?? [];

$users_cnt = (int)($stats_row['users_cnt'] ?? 0);
$tourneys_cnt = (int)($stats_row['tourneys_cnt'] ?? 0);
$prize_dist = (float)($stats_row['prize_dist'] ?? 0.00);
$total_collected = (float)($stats_row['total_collected'] ?? 0.00);
$total_revenue = max(0, $total_collected - $prize_dist);
$pending_deposits = (int)($stats_row['pending_deposits'] ?? 0);
$pending_withdrawals = (int)($stats_row['pending_withdrawals'] ?? 0);
?>

<style>
/* FIX: late click / 300ms tap delay & double-tap zoom hatao */
a, button, [role=button], input, select, label { touch-action: manipulation; -webkit-tap-highlight-color: transparent; }
a[onclick], [role=button] { cursor: pointer; user-select: none; }
</style>

<!-- ============================================================= -->
<!-- TAB 1: DASHBOARD OVERVIEW -->
<!-- ============================================================= -->
<div id="admin-tab-overview" class="admin-spa-view space-y-4 <?= ($req_admin_tab === 'overview' ? '' : 'hidden') ?>">
<!-- Welcome Banner -->
<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-base font-extrabold text-slate-900 font-display">Dashboard Overview</h2>
        <p class="text-[11px] text-slate-500">BEASTzBATTLE Real-Time Control Center</p>
    </div>
    <a onclick="switchAdminTab('tournaments')" role="button" class="py-2 px-3 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs shadow-xs transition flex items-center gap-1.5 active:scale-95">
        <i class="fa-solid fa-plus text-[10px]"></i> New Tourney
    </a>
</div>

<!-- Alert for Pending Requests -->
<?php if ($pending_deposits > 0 || $pending_withdrawals > 0): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-3.5 mb-4 flex items-center justify-between text-xs shadow-xs">
        <div class="flex items-center gap-2 text-amber-900 font-bold">
            <i class="fa-solid fa-bell text-amber-500 animate-bounce"></i>
            <span>Action Required: <span id="admin-pending-deposits-cnt"><?= $pending_deposits; ?></span> Deposits, <span id="admin-pending-payouts-cnt"><?= $pending_withdrawals; ?></span> Payouts pending review.</span>
        </div>
        <a onclick="switchAdminTab('deposits')" role="button" class="text-[10px] font-black bg-yellow-400 hover:bg-yellow-500 text-slate-950 px-2.5 py-1 rounded-lg transition shadow-xs">
            Review
        </a>
    </div>
<?php endif; ?>

<!-- 2x2 Stats Grid Cards (White Card + Yellow / Slate Accents) -->
<div class="grid grid-cols-2 gap-3 mb-5">
    <!-- Total Users -->
    <a onclick="switchAdminTab('users')" role="button" class="bg-white border border-slate-200 rounded-2xl p-3.5 shadow-sm block hover:border-yellow-400 hover:shadow transition group cursor-pointer">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Gamers</span>
            <div class="w-7 h-7 rounded-lg bg-yellow-100 text-yellow-700 flex items-center justify-center text-xs group-hover:bg-yellow-400 group-hover:text-slate-950 transition">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
        <div id="admin-stat-users-cnt" class="text-2xl font-black text-slate-900 font-mono"><?= $users_cnt; ?></div>
        <span class="text-[10px] text-slate-400">Registered Accounts</span>
    </a>

    <!-- Total Tournaments -->
    <a onclick="switchAdminTab('tournaments')" role="button" class="bg-white border border-slate-200 rounded-2xl p-3.5 shadow-sm block hover:border-yellow-400 hover:shadow transition group">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Tournaments</span>
            <div class="w-7 h-7 rounded-lg bg-yellow-100 text-yellow-700 flex items-center justify-center text-xs group-hover:bg-yellow-400 group-hover:text-slate-950 transition">
                <i class="fa-solid fa-trophy"></i>
            </div>
        </div>
        <div id="admin-stat-tourneys-cnt" class="text-2xl font-black text-slate-900 font-mono"><?= $tourneys_cnt; ?></div>
        <span class="text-[10px] text-slate-400">Matches Created</span>
    </a>

    <!-- Prize Distributed -->
    <div class="bg-white border border-slate-200 rounded-2xl p-3.5 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Prize Paid</span>
            <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-xs">
                <i class="fa-solid fa-crown"></i>
            </div>
        </div>
        <div class="text-xl font-black text-amber-500 font-mono"><?= format_inr($prize_dist); ?></div>
        <span class="text-[10px] text-slate-400">Disbursed to Winners</span>
    </div>

    <!-- Platform Revenue -->
    <div class="bg-white border border-slate-200 rounded-2xl p-3.5 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Net Revenue</span>
            <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs">
                <i class="fa-solid fa-coins"></i>
            </div>
        </div>
        <div class="text-xl font-black text-emerald-600 font-mono"><?= format_inr($total_revenue); ?></div>
        <span class="text-[10px] text-slate-400">Net Platform Income</span>
    </div>
</div>

<!-- Quick Control Actions (White Cards with Yellow Hover) -->
<div class="space-y-2 mb-4">
    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider px-1">Quick Actions</h3>
    <div class="grid grid-cols-2 gap-2.5">
        <a onclick="switchAdminTab('deposits')" role="button" class="bg-white hover:bg-yellow-50/60 border border-slate-200 hover:border-yellow-400 p-3 rounded-2xl flex items-center gap-2.5 transition shadow-xs group">
            <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs group-hover:bg-emerald-500 group-hover:text-white transition">
                <i class="fa-solid fa-arrow-down"></i>
            </div>
            <div>
                <span class="text-xs font-bold text-slate-900 block">UPI Deposits</span>
                <span class="text-[10px] text-slate-400"><?= $pending_deposits; ?> Pending</span>
            </div>
        </a>

        <a onclick="switchAdminTab('payouts')" role="button" class="bg-white hover:bg-yellow-50/60 border border-slate-200 hover:border-yellow-400 p-3 rounded-2xl flex items-center gap-2.5 transition shadow-xs group">
            <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-xs group-hover:bg-yellow-400 group-hover:text-slate-950 transition">
                <i class="fa-solid fa-arrow-up"></i>
            </div>
            <div>
                <span class="text-xs font-bold text-slate-900 block">UPI Payouts</span>
                <span class="text-[10px] text-slate-400"><?= $pending_withdrawals; ?> Pending</span>
            </div>
        </a>

        <a onclick="switchAdminTab('tournaments')" role="button" class="bg-white hover:bg-yellow-50/60 border border-slate-200 hover:border-yellow-400 p-3 rounded-2xl flex items-center gap-2.5 transition shadow-xs group">
            <div class="w-8 h-8 rounded-xl bg-yellow-100 text-yellow-700 flex items-center justify-center text-xs group-hover:bg-yellow-400 group-hover:text-slate-950 transition">
                <i class="fa-solid fa-gamepad"></i>
            </div>
            <div>
                <span class="text-xs font-bold text-slate-900 block">Tournaments</span>
                <span class="text-[10px] text-slate-400">Create & Rooms</span>
            </div>
        </a>

        <a onclick="switchAdminTab('settings')" role="button" class="bg-white hover:bg-yellow-50/60 border border-slate-200 hover:border-yellow-400 p-3 rounded-2xl flex items-center gap-2.5 transition shadow-xs group">
            <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-xs group-hover:bg-yellow-400 group-hover:text-slate-950 transition">
                <i class="fa-solid fa-sliders"></i>
            </div>
            <div>
                <span class="text-xs font-bold text-slate-900 block">Settings</span>
                <span class="text-[10px] text-slate-400">UPI & Gateway</span>
            </div>
        </a>

        <!-- ============================================== -->
        <!-- ALWAYS VISIBLE: PUSH NOTIFICATIONS & WORKER PANEL -->
        <!-- ============================================== -->
        <a onclick="switchAdminTab('notifications')" role="button" class="bg-white dark:bg-slate-900 hover:bg-yellow-50/60 border border-slate-200 dark:border-slate-800 hover:border-yellow-400 p-3 rounded-2xl flex items-center gap-2.5 transition shadow-xs group">
            <div class="w-8 h-8 rounded-xl bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-400 flex items-center justify-center text-xs group-hover:bg-yellow-400 group-hover:text-slate-950 transition">
                <i class="fa-solid fa-paper-plane"></i>
            </div>
            <div>
                <span class="text-xs font-bold text-slate-900 dark:text-white block">Push Alerts Studio</span>
                <span class="text-[10px] text-slate-400">Send Announcements</span>
            </div>
        </a>
        <a href="/worker.php" target="_blank" class="bg-white dark:bg-slate-900 hover:bg-yellow-50/60 border border-slate-200 dark:border-slate-800 hover:border-yellow-400 p-3 rounded-2xl flex items-center gap-2.5 transition shadow-xs group">
            <div class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 flex items-center justify-center text-xs group-hover:bg-yellow-400 group-hover:text-slate-950 transition">
                <i class="fa-solid fa-gamepad"></i>
            </div>
            <div>
                <span class="text-xs font-bold text-slate-900 dark:text-white block">Worker Panel <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i></span>
                <span class="text-[10px] text-slate-400">Manage Tourneys & IDP</span>
            </div>
        </a>

    </div>
</div>
</div>

<!-- ============================================================= -->
<!-- TAB 2: TOURNAMENTS MANAGEMENT -->
<!-- ============================================================= -->
<div id="admin-tab-tournaments" class="admin-spa-view space-y-4 <?= ($req_admin_tab === 'tournaments' ? '' : 'hidden') ?>">
    <?php require_once __DIR__ . '/views/tournaments_view.php'; ?>
</div>

<!-- ============================================================= -->
<!-- TAB 3: DEPOSITS MANAGEMENT -->
<!-- ============================================================= -->
<div id="admin-tab-deposits" class="admin-spa-view space-y-4 <?= ($req_admin_tab === 'deposits' ? '' : 'hidden') ?>">
    <?php require_once __DIR__ . '/views/deposits_view.php'; ?>
</div>

<!-- ============================================================= -->
<!-- TAB 4: PAYOUTS (WITHDRAWALS) MANAGEMENT -->
<!-- ============================================================= -->
<div id="admin-tab-payouts" class="admin-spa-view space-y-4 <?= ($req_admin_tab === 'payouts' ? '' : 'hidden') ?>">
    <?php require_once __DIR__ . '/views/payouts_view.php'; ?>
</div>

<!-- ============================================================= -->
<!-- TAB 5: PUSH NOTIFICATIONS STUDIO  -->
<!-- ============================================================= -->
<div id="admin-tab-notifications" class="admin-spa-view space-y-4 <?= ($req_admin_tab === 'notifications' ? '' : 'hidden') ?>">
    <?php require_once __DIR__ . '/views/notifications_tab.php'; ?>
</div>

<!-- ============================================================= -->
<!-- TAB 6: "LOGIN USERS" REGISTERED GAMERS DIRECTORY -->
<!-- ============================================================= -->
<div id="admin-tab-users" class="admin-spa-view space-y-4 <?= ($req_admin_tab === 'users' ? '' : 'hidden') ?>">
    <?php require_once __DIR__ . '/views/users_view.php'; ?>
</div>

<!-- ============================================================= -->
<!-- TAB 7: MULTI-ADMIN & HOST REVENUE STUDIO -->
<!-- ============================================================= -->
<div id="admin-tab-admins" class="admin-spa-view space-y-4 <?= ($req_admin_tab === 'admins' ? '' : 'hidden') ?>">
    <?php require_once __DIR__ . '/views/admins_view.php'; ?>
</div>

<!-- ============================================================= -->
<!-- TAB 8: SETTINGS & GATEWAY CONFIG (SUPER ADMIN ONLY) -->
<!-- ============================================================= -->
<div id="admin-tab-settings" class="admin-spa-view space-y-4 <?= ($req_admin_tab === 'settings' ? '' : 'hidden') ?>">
    <?php require_once __DIR__ . '/views/settings_view.php'; ?>
</div>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
