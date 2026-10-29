<?php
require_once __DIR__ . '/common/header.php';

$alert_msg = "";
$alert_type = "";

if (isset($_GET['welcome'])) {
    $alert_msg = "Welcome to BEASTzBATTLE! Your gamer profile is ready.";
    $alert_type = "success";
}

// -------------------------------------------------------------
// Join Tournament Action
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_tournament') {
    if (!isset($_SESSION['user_id'])) {
        safe_redirect("login.php");
        exit;
    }

    $curr_uid = (int)$_SESSION['user_id'];
    $tid = (int)($_POST['tournament_id'] ?? 0);
    $in_game_name = sanitize($_POST['in_game_name'] ?? '');

    if ($tid <= 0 || empty($in_game_name)) {
        $alert_msg = "Please enter your in-game character name (IGN).";
        $alert_type = "error";
    } else {
        // Fetch tournament details safely
        $t_stmt = $conn->prepare("
            SELECT t.id, t.title, COALESCE(t.game_name, t.game, 'BGMI') as game_name, t.entry_fee, t.prize_pool, t.status, t.total_slots,
            (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
            FROM tournaments t 
            WHERE t.id = ?
        ");
        $t_stmt->bind_param("i", $tid);
        $t_stmt->execute();
        $tourney = $t_stmt->get_result()->fetch_assoc();
        $t_stmt->close();

        if (!$tourney) {
            $alert_msg = "Tournament not found or no longer active.";
            $alert_type = "error";
        } elseif (strtolower($tourney['status']) === 'completed') {
            $alert_msg = "This tournament has already ended.";
            $alert_type = "error";
        } elseif ((int)$tourney['enrolled_count'] >= (int)($tourney['total_slots'] ?? 100)) {
            $alert_msg = "Match is full! All slots have been booked.";
            $alert_type = "error";
        } else {
            // Check if user already enrolled
            $chk_stmt = $conn->prepare("SELECT id FROM participants WHERE tournament_id = ? AND user_id = ?");
            $chk_stmt->bind_param("ii", $tid, $curr_uid);
            $chk_stmt->execute();
            if ($chk_stmt->get_result()->num_rows > 0) {
                $alert_msg = "You are already registered in this tournament!";
                $alert_type = "warning";
                $chk_stmt->close();
            } else {
                $chk_stmt->close();

                // Fetch user balance
                $u_stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                $u_stmt->bind_param("i", $curr_uid);
                $u_stmt->execute();
                $user_data = $u_stmt->get_result()->fetch_assoc();
                $u_stmt->close();

                $user_bal = (float)($user_data['wallet_balance'] ?? 0.00);
                $fee = (float)$tourney['entry_fee'];

                if ($user_bal < $fee) {
                    $needed = $fee - $user_bal;
                    $alert_msg = "Insufficient wallet balance. Please add " . format_inr($needed) . " to your wallet to join.";
                    $alert_type = "error";
                } else {
                    // Atomic Registration Transaction
                    $conn->begin_transaction();
                    try {
                        // 1. Deduct fee if > 0
                        if ($fee > 0) {
                            $ded = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
                            $ded->bind_param("di", $fee, $curr_uid);
                            $ded->execute();
                            $ded->close();

                            // 2. Add Debit Transaction
                            $t_desc = "Joined Tournament #" . $tid . " (" . $tourney['title'] . ")";
                            $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
                            $tx->bind_param("ids", $curr_uid, $fee, $t_desc);
                            $tx->execute();
                            $tx->close();
                        }

                        // 3. Insert Participant Record
                        $slot_assigned = (int)$tourney['enrolled_count'] + 1;
                        $part = $conn->prepare("INSERT INTO participants (tournament_id, user_id, in_game_name, game_name, slot_no) VALUES (?, ?, ?, ?, ?)");
                        $part->bind_param("iissi", $tid, $curr_uid, $in_game_name, $in_game_name, $slot_assigned);
                        $part->execute();
                        $part->close();

                        // 4. Increment joined slots
                        $conn->query("UPDATE tournaments SET joined_slots = joined_slots + 1 WHERE id = $tid");

                        $conn->commit();
                        $alert_msg = "🎉 Slot booked successfully! You are confirmed for " . htmlspecialchars($tourney['title']) . " (Slot #" . $slot_assigned . ").";
                        $alert_type = "success";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $alert_msg = "Registration failed: " . $e->getMessage();
                        $alert_type = "error";
                    }
                }
            }
        }
    }
}

// -------------------------------------------------------------
// Admin Summary Metrics (Combined 1-Roundtrip Query)
// -------------------------------------------------------------
$pending_deposits = 0;
$pending_withdrawals = 0;
$users_cnt = 0;
$tourneys_cnt = 0;
$prize_dist = 0.00;

if ($is_admin) {
    $stats_row = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM deposits WHERE LOWER(status) = 'pending') as pending_deposits,
            (SELECT COUNT(*) FROM withdrawals WHERE LOWER(status) = 'pending') as pending_withdrawals,
            (SELECT COUNT(*) FROM users) as users_cnt,
            (SELECT COUNT(*) FROM tournaments) as tourneys_cnt,
            (SELECT COALESCE(SUM(prize_pool), 0.00) FROM tournaments WHERE LOWER(status) = 'completed') as prize_dist
    ")->fetch_assoc();

    $pending_deposits = (int)($stats_row['pending_deposits'] ?? 0);
    $pending_withdrawals = (int)($stats_row['pending_withdrawals'] ?? 0);
    $users_cnt = (int)($stats_row['users_cnt'] ?? 0);
    $tourneys_cnt = (int)($stats_row['tourneys_cnt'] ?? 0);
    $prize_dist = (float)($stats_row['prize_dist'] ?? 0.00);
}

// -------------------------------------------------------------
// Fetch Available Tournaments for Players
// -------------------------------------------------------------
$tourneys_query = "
    SELECT t.*, 
    COALESCE(t.game_name, t.game, 'BGMI') as display_game,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
    FROM tournaments t 
    WHERE LOWER(t.status) IN ('upcoming', 'live', 'ongoing')
    ORDER BY CASE WHEN LOWER(t.status) IN ('live','ongoing') THEN 1 ELSE 2 END, t.match_time ASC
";
$active_matches = $conn->query($tourneys_query);
?>

<!-- Alert Feedback -->
<?php if (!empty($alert_msg)): ?>
    <div class="mb-4 p-3.5 rounded-xl text-xs font-semibold flex items-center gap-2 <?= ($alert_type === 'success') ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : (($alert_type === 'warning') ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30') ?>">
        <i class="fa-solid <?= ($alert_type === 'success') ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-amber-400' ?>"></i>
        <span><?= htmlspecialchars($alert_msg); ?></span>
    </div>
<?php endif; ?>

<?php if ($is_admin): ?>
    <!-- Admin Statistics Summary Card -->
    <div class="bg-slate-800/80 border border-rose-500/30 rounded-2xl p-3.5 mb-4 shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-black uppercase tracking-wider text-rose-400">BEASTz Admin Metrics</span>
            <span class="text-[10px] text-slate-400">Live Control</span>
        </div>
        <div class="grid grid-cols-4 gap-2 text-center">
            <div class="bg-slate-900/80 p-2 rounded-xl border border-slate-700/60">
                <span class="text-[9px] text-slate-400 block">Users</span>
                <span class="text-xs font-bold text-white"><?= $users_cnt; ?></span>
            </div>
            <div class="bg-slate-900/80 p-2 rounded-xl border border-slate-700/60">
                <span class="text-[9px] text-slate-400 block">Tourneys</span>
                <span class="text-xs font-bold text-violet-400"><?= $tourneys_cnt; ?></span>
            </div>
            <div class="bg-slate-900/80 p-2 rounded-xl border border-slate-700/60">
                <span class="text-[9px] text-slate-400 block">Deposits</span>
                <span class="text-xs font-bold text-amber-400"><?= $pending_deposits; ?></span>
            </div>
            <div class="bg-slate-900/80 p-2 rounded-xl border border-slate-700/60">
                <span class="text-[9px] text-slate-400 block">Payouts</span>
                <span class="text-xs font-bold text-rose-400"><?= $pending_withdrawals; ?></span>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Hero Gaming Banner -->
<div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-violet-900 via-indigo-900 to-slate-900 p-5 mb-5 shadow-xl border border-violet-500/20">
    <div class="relative z-10">
        <div class="flex items-center gap-1.5 mb-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
            <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-widest">BEASTz Daily Tournaments</span>
        </div>
        <h1 class="text-lg font-black text-white leading-snug">
            Battle. Win Cash.<br>Dominate the Arena.
        </h1>
        <p class="text-[11px] text-violet-200 mt-1 max-w-[260px]">
            Compete in BGMI & Free Fire matches, earn cash per kill and grab huge prize pools.
        </p>
    </div>
    <div class="absolute right-2 -bottom-2 opacity-20 text-7xl text-white pointer-events-none">
        <i class="fa-solid fa-crosshairs"></i>
    </div>
</div>

<!-- Tournament Matches Section Header -->
<div class="flex items-center justify-between mb-3">
    <div>
        <h2 class="text-base font-black text-white flex items-center gap-1.5">
            <i class="fa-solid fa-fire text-amber-400"></i> Battle Tournaments
        </h2>
        <p class="text-[10px] text-slate-400">Join real money esports matches & win cash</p>
    </div>
    <?php if ($is_admin): ?>
        <a href="admin/tournament.php" class="py-1.5 px-3 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl text-[11px] shadow transition">
            + New Match
        </a>
    <?php endif; ?>
</div>

<!-- Tournaments List -->
<div class="space-y-4">
    <?php if ($active_matches && $active_matches->num_rows > 0): ?>
        <?php while ($t = $active_matches->fetch_assoc()): ?>
            <?php
            $status_lower = strtolower($t['status'] ?? 'upcoming');
            $is_live = ($status_lower === 'live' || $status_lower === 'ongoing');
            $user_joined = false;
            if (isset($_SESSION['user_id'])) {
                $uid = (int)$_SESSION['user_id'];
                $check_join = $conn->query("SELECT id FROM participants WHERE tournament_id = {$t['id']} AND user_id = {$uid}");
                if ($check_join && $check_join->num_rows > 0) {
                    $user_joined = true;
                }
            }

            $slots_total = (int)($t['total_slots'] ?? 100);
            $slots_joined = (int)$t['enrolled_count'];
            $slots_left = max(0, $slots_total - $slots_joined);
            $progress_pct = min(100, round(($slots_joined / max(1, $slots_total)) * 100));
            ?>

            <div class="bg-slate-800/80 border <?= $is_live ? 'border-emerald-500/50 shadow-emerald-500/10' : 'border-slate-700/80' ?> rounded-2xl p-4 shadow-lg transition">
                <!-- Top Row: Game Badge, Title & Status -->
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-lg bg-violet-600/30 text-violet-300 border border-violet-500/30">
                                <?= htmlspecialchars($t['display_game']); ?>
                            </span>
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded-lg bg-slate-700/60 text-slate-300">
                                <?= htmlspecialchars($t['match_type'] ?? 'Squad'); ?> &bull; <?= htmlspecialchars($t['map'] ?? 'Erangel'); ?>
                            </span>
                        </div>
                        <h3 class="text-sm font-black text-white mt-1.5"><?= htmlspecialchars($t['title']); ?></h3>
                    </div>

                    <?php if ($is_live): ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 animate-pulse">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> LIVE
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">
                            Upcoming
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Match Schedule Time -->
                <div class="flex items-center text-[10px] text-slate-400 gap-1.5 mb-3">
                    <i class="fa-regular fa-clock text-slate-500"></i>
                    <span><?= date("d M Y &bull; h:i A", strtotime($t['match_time'])); ?></span>
                </div>

                <!-- Metrics Grid: Prize Pool, Entry Fee, Per Kill -->
                <div class="grid grid-cols-3 gap-2 bg-slate-900/80 p-2.5 rounded-xl border border-slate-700/50 mb-3 text-center">
                    <div>
                        <span class="text-[9px] text-slate-400 block uppercase font-medium">Prize Pool</span>
                        <span class="text-xs font-black text-amber-400 font-mono"><?= format_inr($t['prize_pool']); ?></span>
                    </div>
                    <div>
                        <span class="text-[9px] text-slate-400 block uppercase font-medium">Entry Fee</span>
                        <span class="text-xs font-black text-white font-mono"><?= ((float)$t['entry_fee'] == 0) ? '<span class="text-emerald-400">FREE</span>' : format_inr($t['entry_fee']); ?></span>
                    </div>
                    <div>
                        <span class="text-[9px] text-slate-400 block uppercase font-medium">Per Kill</span>
                        <span class="text-xs font-black text-emerald-400 font-mono"><?= format_inr($t['per_kill'] ?? 0); ?></span>
                    </div>
                </div>

                <!-- Slots Progress Bar -->
                <div class="space-y-1 mb-3">
                    <div class="flex items-center justify-between text-[10px]">
                        <span class="text-slate-400">Spots Filled</span>
                        <span class="font-bold text-slate-200"><?= $slots_joined; ?> / <?= $slots_total; ?> <span class="text-violet-400">(<?= $slots_left; ?> left)</span></span>
                    </div>
                    <div class="w-full bg-slate-900 h-2 rounded-full overflow-hidden border border-slate-700/50">
                        <div class="bg-gradient-to-r from-violet-500 to-indigo-500 h-full rounded-full transition-all duration-500" style="width: <?= $progress_pct; ?>%"></div>
                    </div>
                </div>

                <!-- Live Room Credentials (if user is joined and match is live or admin) -->
                <?php if (($is_live || $is_admin) && ($user_joined || $is_admin)): ?>
                    <div class="bg-gradient-to-r from-violet-950/60 to-slate-900 border border-violet-500/40 rounded-xl p-3 mb-3">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[10px] font-bold text-violet-300 uppercase tracking-wider flex items-center gap-1">
                                <i class="fa-solid fa-key"></i> Match Room Credentials
                            </span>
                            <span class="text-[9px] text-emerald-400 font-bold bg-emerald-500/20 px-2 py-0.5 rounded">Active</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs font-mono mt-2">
                            <div class="bg-slate-900/80 p-2 rounded-lg border border-slate-700">
                                <span class="text-[9px] text-slate-400 block font-sans">Room ID</span>
                                <span class="font-bold text-white select-all"><?= !empty($t['room_id']) ? htmlspecialchars($t['room_id']) : 'Pending Release'; ?></span>
                            </div>
                            <div class="bg-slate-900/80 p-2 rounded-lg border border-slate-700">
                                <span class="text-[9px] text-slate-400 block font-sans">Password</span>
                                <span class="font-bold text-amber-400 select-all"><?= !empty($t['room_password']) ? htmlspecialchars($t['room_password']) : 'Pending Release'; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Action Button -->
                <?php if ($user_joined): ?>
                    <button type="button" disabled class="w-full py-2.5 bg-emerald-600/30 border border-emerald-500/40 text-emerald-300 font-bold rounded-xl text-xs flex items-center justify-center gap-1.5 cursor-default">
                        <i class="fa-solid fa-circle-check"></i> Enrolled & Registered
                    </button>
                <?php elseif ($slots_left <= 0): ?>
                    <button type="button" disabled class="w-full py-2.5 bg-slate-800 text-slate-500 font-bold rounded-xl text-xs flex items-center justify-center gap-1.5 cursor-not-allowed">
                        <i class="fa-solid fa-lock"></i> Match Full
                    </button>
                <?php else: ?>
                    <button type="button" onclick="openJoinModal(<?= $t['id']; ?>, '<?= addslashes(htmlspecialchars($t['title'])); ?>', <?= (float)$t['entry_fee']; ?>)" class="w-full py-2.5 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-extrabold rounded-xl text-xs shadow-lg shadow-violet-600/20 transition flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-bolt"></i> Join Tournament
                    </button>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="bg-slate-800/40 border border-slate-800 rounded-2xl p-8 text-center">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-800 flex items-center justify-center text-slate-400 text-xl mb-3">
                <i class="fa-solid fa-gamepad"></i>
            </div>
            <h4 class="text-sm font-bold text-white mb-1">No Active Tournaments</h4>
            <p class="text-xs text-slate-400">Upcoming matches will appear here shortly. Check back soon!</p>
            <?php if ($is_admin): ?>
                <a href="admin/tournament.php" class="inline-block mt-3 px-4 py-2 bg-violet-600 hover:bg-violet-500 text-white rounded-xl text-xs font-bold">
                    Create Match
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Join Tournament Confirmation -->
<div id="joinModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeJoinModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="flex items-center gap-2 mb-3">
            <div class="w-8 h-8 rounded-xl bg-violet-600/20 text-violet-400 flex items-center justify-center text-xs">
                <i class="fa-solid fa-ticket"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-white">Confirm Tournament Entry</h3>
                <p id="modalTourneyTitle" class="text-[11px] text-slate-400 line-clamp-1"></p>
            </div>
        </div>

        <form method="POST" action="index.php" class="space-y-3.5 mt-2">
            <input type="hidden" name="action" value="join_tournament">
            <input type="hidden" name="tournament_id" id="modalTourneyId" value="">

            <div class="bg-slate-950/60 p-3 rounded-2xl border border-slate-800 flex items-center justify-between">
                <span class="text-xs text-slate-400 font-medium">Entry Fee</span>
                <span id="modalTourneyFee" class="text-sm font-black text-amber-400 font-mono"></span>
            </div>

            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Your In-Game Character Name (IGN) *</label>
                <input type="text" name="in_game_name" required placeholder="Exact character name in BGMI/Free Fire" class="w-full bg-slate-950 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
                <span class="text-[9px] text-slate-500 mt-1 block">Ensure this matches your in-game name so scores & kills can be verified.</span>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <button type="button" onclick="closeJoinModal()" class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs transition">
                    Cancel
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-violet-600 hover:bg-violet-500 text-white font-extrabold rounded-xl text-xs shadow-md shadow-violet-600/30 transition">
                    Confirm & Join
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openJoinModal(id, title, fee) {
    document.getElementById('modalTourneyId').value = id;
    document.getElementById('modalTourneyTitle').innerText = title;
    document.getElementById('modalTourneyFee').innerText = fee > 0 ? '₹' + fee.toFixed(2) : 'FREE';
    document.getElementById('joinModal').classList.remove('hidden');
}
function closeJoinModal() {
    document.getElementById('joinModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
