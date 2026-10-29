<?php
require_once __DIR__ . '/common/header.php';

// Fast 1-Roundtrip Aggregated Statistics
$stats_row = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as users_cnt,
        (SELECT COUNT(*) FROM tournaments) as tourneys_cnt,
        (SELECT COALESCE(SUM(prize_pool), 0.00) FROM tournaments WHERE LOWER(status) = 'completed') as prize_dist,
        (SELECT COALESCE(SUM(t.entry_fee), 0.00) FROM participants p INNER JOIN tournaments t ON p.tournament_id = t.id) as total_collected,
        (SELECT COUNT(*) FROM deposits WHERE LOWER(status) = 'pending') as pending_deposits,
        (SELECT COUNT(*) FROM withdrawals WHERE LOWER(status) = 'pending') as pending_withdrawals
")->fetch_assoc();

$users_cnt = (int)($stats_row['users_cnt'] ?? 0);
$tourneys_cnt = (int)($stats_row['tourneys_cnt'] ?? 0);
$prize_dist = (float)($stats_row['prize_dist'] ?? 0.00);
$total_collected = (float)($stats_row['total_collected'] ?? 0.00);
$total_revenue = max(0, $total_collected - $prize_dist);
$pending_deposits = (int)($stats_row['pending_deposits'] ?? 0);
$pending_withdrawals = (int)($stats_row['pending_withdrawals'] ?? 0);
?>

<!-- Welcome Banner -->
<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-base font-bold text-white">Dashboard Overview</h2>
        <p class="text-[11px] text-slate-400">BEASTzBATTLE Real-Time Control Center</p>
    </div>
    <a href="tournament.php" class="py-2 px-3 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center gap-1.5">
        <i class="fa-solid fa-plus text-[10px]"></i> New Tourney
    </a>
</div>

<!-- Alert for Pending UPI Requests -->
<?php if ($pending_deposits > 0 || $pending_withdrawals > 0): ?>
    <div class="bg-amber-500/20 border border-amber-500/30 rounded-2xl p-3 mb-4 flex items-center justify-between text-xs">
        <div class="flex items-center gap-2 text-amber-300 font-semibold">
            <i class="fa-solid fa-bell animate-bounce text-amber-400"></i>
            <span>Action Required: <?= $pending_deposits; ?> Deposits, <?= $pending_withdrawals; ?> Payouts pending.</span>
        </div>
        <a href="deposits.php" class="text-[10px] font-bold bg-amber-500 text-slate-950 px-2.5 py-1 rounded-lg">Review</a>
    </div>
<?php endif; ?>

<!-- 2x2 Stats Grid Cards -->
<div class="grid grid-cols-2 gap-3 mb-5">
    <!-- Total Users -->
    <a href="user.php" class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-3.5 shadow-lg block hover:border-violet-500/50 transition">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-slate-400 uppercase">Users</span>
            <div class="w-7 h-7 rounded-lg bg-blue-500/20 text-blue-400 flex items-center justify-center text-xs">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
        <div class="text-xl font-black text-white font-mono"><?= $users_cnt; ?></div>
        <span class="text-[9px] text-slate-400">Registered Gamers</span>
    </a>

    <!-- Total Tournaments -->
    <a href="tournament.php" class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-3.5 shadow-lg block hover:border-violet-500/50 transition">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-slate-400 uppercase">Tournaments</span>
            <div class="w-7 h-7 rounded-lg bg-violet-500/20 text-violet-400 flex items-center justify-center text-xs">
                <i class="fa-solid fa-gamepad"></i>
            </div>
        </div>
        <div class="text-xl font-black text-white font-mono"><?= $tourneys_cnt; ?></div>
        <span class="text-[9px] text-slate-400">Created Matches</span>
    </a>

    <!-- Prize Distributed -->
    <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-3.5 shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-slate-400 uppercase">Prize Paid</span>
            <div class="w-7 h-7 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center text-xs">
                <i class="fa-solid fa-trophy"></i>
            </div>
        </div>
        <div class="text-lg font-black text-amber-400 font-mono"><?= format_inr($prize_dist); ?></div>
        <span class="text-[9px] text-slate-400">Disbursed to Winners</span>
    </div>

    <!-- Platform Revenue -->
    <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-3.5 shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-slate-400 uppercase">Revenue</span>
            <div class="w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs">
                <i class="fa-solid fa-coins"></i>
            </div>
        </div>
        <div class="text-lg font-black text-emerald-400 font-mono"><?= format_inr($total_revenue); ?></div>
        <span class="text-[9px] text-slate-400">Net Platform Income</span>
    </div>
</div>

<!-- Quick Control Actions -->
<div class="space-y-2 mb-4">
    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider px-1">Quick Actions</h3>
    <div class="grid grid-cols-2 gap-2">
        <a href="deposits.php" class="bg-slate-800 hover:bg-slate-700 border border-slate-700 p-3 rounded-xl flex items-center gap-2.5 transition">
            <i class="fa-solid fa-circle-down text-emerald-400 text-base"></i>
            <div>
                <span class="text-xs font-bold text-white block">UPI Deposits</span>
                <span class="text-[10px] text-slate-400"><?= $pending_deposits; ?> Pending</span>
            </div>
        </a>

        <a href="withdrawals.php" class="bg-slate-800 hover:bg-slate-700 border border-slate-700 p-3 rounded-xl flex items-center gap-2.5 transition">
            <i class="fa-solid fa-circle-up text-amber-400 text-base"></i>
            <div>
                <span class="text-xs font-bold text-white block">UPI Payouts</span>
                <span class="text-[10px] text-slate-400"><?= $pending_withdrawals; ?> Pending</span>
            </div>
        </a>

        <a href="tournament.php" class="bg-slate-800 hover:bg-slate-700 border border-slate-700 p-3 rounded-xl flex items-center gap-2.5 transition">
            <i class="fa-solid fa-trophy text-violet-400 text-base"></i>
            <div>
                <span class="text-xs font-bold text-white block">Tournaments</span>
                <span class="text-[10px] text-slate-400">Create & Rooms</span>
            </div>
        </a>

        <a href="settings.php" class="bg-slate-800 hover:bg-slate-700 border border-slate-700 p-3 rounded-xl flex items-center gap-2.5 transition">
            <i class="fa-solid fa-qrcode text-pink-400 text-base"></i>
            <div>
                <span class="text-xs font-bold text-white block">QR & UPI Setup</span>
                <span class="text-[10px] text-slate-400">Payment Gateway</span>
            </div>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
