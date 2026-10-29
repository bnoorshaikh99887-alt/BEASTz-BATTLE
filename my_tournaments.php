<?php
require_once __DIR__ . '/common/config.php';
check_user_auth();
require_once __DIR__ . '/common/header.php';

$user_id = (int)$_SESSION['user_id'];
$active_tab = isset($_GET['tab']) && $_GET['tab'] === 'completed' ? 'completed' : 'live';

// Fetch Live & Upcoming Joined Tournaments
$live_query = "
    SELECT t.*, p.joined_at 
    FROM tournaments t
    INNER JOIN participants p ON t.id = p.tournament_id
    WHERE p.user_id = $user_id AND t.status IN ('Upcoming', 'Live')
    ORDER BY t.match_time ASC";
$live_tourneys = $conn->query($live_query);

// Fetch Completed Tournaments
$completed_query = "
    SELECT t.*, p.joined_at, u.username as winner_name
    FROM tournaments t
    INNER JOIN participants p ON t.id = p.tournament_id
    LEFT JOIN users u ON t.winner_user_id = u.id
    WHERE p.user_id = $user_id AND t.status = 'Completed'
    ORDER BY t.match_time DESC";
$completed_tourneys = $conn->query($completed_query);
?>

<!-- Tab Selector -->
<div class="grid grid-cols-2 bg-slate-800/80 p-1 rounded-xl mb-4 border border-slate-700/60">
    <a href="my_tournaments.php?tab=live" class="text-center py-2 text-xs font-bold rounded-lg transition <?= ($active_tab === 'live') ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200' ?>">
        <i class="fa-solid fa-tower-broadcast mr-1 text-[11px]"></i> Upcoming / Live
    </a>
    <a href="my_tournaments.php?tab=completed" class="text-center py-2 text-xs font-bold rounded-lg transition <?= ($active_tab === 'completed') ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200' ?>">
        <i class="fa-solid fa-flag-checkered mr-1 text-[11px]"></i> Completed History
    </a>
</div>

<!-- Tab Content: Live & Upcoming -->
<?php if ($active_tab === 'live'): ?>
    <div class="space-y-4">
        <?php if ($live_tourneys && $live_tourneys->num_rows > 0): ?>
            <?php while ($row = $live_tourneys->fetch_assoc()): ?>
                <?php 
                $has_room = !empty($row['room_id']);
                $is_live = ($row['status'] === 'Live');
                ?>
                <div class="bg-slate-800/90 border border-slate-700 rounded-2xl p-4 shadow-lg relative">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-700 text-slate-300">
                            <?= htmlspecialchars($row['game_name']); ?>
                        </span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-md <?= $is_live ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 animate-pulse' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30' ?>">
                            <?= $is_live ? '🔴 LIVE NOW' : '⏳ UPCOMING' ?>
                        </span>
                    </div>

                    <h3 class="text-sm font-bold text-white mb-2"><?= htmlspecialchars($row['title']); ?></h3>

                    <div class="bg-slate-900/60 p-2.5 rounded-xl border border-slate-800 text-xs flex justify-between mb-3">
                        <div>
                            <span class="text-slate-400 text-[10px] block">Prize Pool</span>
                            <span class="font-bold text-amber-400"><?= format_inr($row['prize_pool']); ?></span>
                        </div>
                        <div class="text-right">
                            <span class="text-slate-400 text-[10px] block">Match Schedule</span>
                            <span class="font-semibold text-slate-200"><?= date("d M, h:i A", strtotime($row['match_time'])); ?></span>
                        </div>
                    </div>

                    <!-- Room Credentials Area -->
                    <div class="p-3 rounded-xl border <?= $has_room ? 'bg-violet-950/40 border-violet-500/40' : 'bg-slate-900/80 border-slate-800' ?>">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[11px] font-bold text-slate-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-key text-violet-400"></i> Match Room Credentials
                            </span>
                            <?php if (!$has_room): ?>
                                <span class="text-[10px] text-slate-500 italic">Disclosed 15m before start</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($has_room): ?>
                            <div class="grid grid-cols-2 gap-2 text-xs mt-2">
                                <div class="bg-slate-900 p-2 rounded-lg border border-slate-800">
                                    <span class="text-[10px] text-slate-400 block">Room ID</span>
                                    <span class="font-mono font-bold text-emerald-400 select-all"><?= htmlspecialchars($row['room_id']); ?></span>
                                </div>
                                <div class="bg-slate-900 p-2 rounded-lg border border-slate-800">
                                    <span class="text-[10px] text-slate-400 block">Password</span>
                                    <span class="font-mono font-bold text-violet-400 select-all"><?= htmlspecialchars($row['room_password']); ?></span>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-[11px] text-slate-400 mt-1">Room details will be automatically populated here by the admin before the match.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="bg-slate-800/40 border border-slate-800 rounded-2xl p-8 text-center">
                <i class="fa-solid fa-gamepad text-4xl text-slate-600 mb-2"></i>
                <h4 class="text-sm font-bold text-slate-300">No Active Tournaments</h4>
                <p class="text-xs text-slate-500 mt-1 mb-4">You have not joined any upcoming games.</p>
                <a href="index.php" class="inline-block py-2 px-4 bg-violet-600 hover:bg-violet-500 text-white rounded-xl text-xs font-bold transition">
                    Browse Tournaments
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <!-- Tab Content: Completed History -->
    <div class="space-y-4">
        <?php if ($completed_tourneys && $completed_tourneys->num_rows > 0): ?>
            <?php while ($crow = $completed_tourneys->fetch_assoc()): ?>
                <?php 
                $i_won = ((int)$crow['winner_user_id'] === $user_id);
                ?>
                <div class="bg-slate-800/90 border border-slate-700 rounded-2xl p-4 shadow-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-700 text-slate-300">
                            <?= htmlspecialchars($crow['game_name']); ?>
                        </span>
                        <?php if ($i_won): ?>
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-amber-500/20 text-amber-400 border border-amber-500/30 flex items-center gap-1">
                                <i class="fa-solid fa-crown text-amber-400"></i> WINNER
                            </span>
                        <?php else: ?>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-700 text-slate-400">
                                Participated
                            </span>
                        <?php endif; ?>
                    </div>

                    <h3 class="text-sm font-bold text-white mb-2"><?= htmlspecialchars($crow['title']); ?></h3>

                    <div class="bg-slate-900/60 p-2.5 rounded-xl border border-slate-800 text-xs flex justify-between items-center">
                        <div>
                            <span class="text-slate-400 text-[10px] block">Prize Pool</span>
                            <span class="font-bold text-emerald-400"><?= format_inr($crow['prize_pool']); ?></span>
                        </div>
                        <div class="text-right">
                            <span class="text-slate-400 text-[10px] block">Winner</span>
                            <span class="font-bold text-slate-200"><?= htmlspecialchars($crow['winner_name'] ?? 'Declared'); ?></span>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="bg-slate-800/40 border border-slate-800 rounded-2xl p-8 text-center">
                <i class="fa-solid fa-clock-rotate-left text-4xl text-slate-600 mb-2"></i>
                <h4 class="text-sm font-bold text-slate-300">No Match History</h4>
                <p class="text-xs text-slate-500 mt-1">Completed tournament results will be archived here.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/common/bottom.php'; ?>