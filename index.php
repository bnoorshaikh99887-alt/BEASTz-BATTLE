<?php
require_once __DIR__ . '/common/header.php';

$alert_msg = "";
$alert_type = "";

if (isset($_GET['welcome'])) {
    $alert_msg = "Welcome to Adept Play! Your gamer profile is ready.";
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
        $alert_msg = "Please enter your in-game character name.";
        $alert_type = "error";
    } else {
        // Fetch tournament details
        $t_stmt = $conn->prepare("SELECT id, title, game_name, entry_fee, prize_pool, status FROM tournaments WHERE id = ?");
        $t_stmt->bind_param("i", $tid);
        $t_stmt->execute();
        $tourney = $t_stmt->get_result()->fetch_assoc();
        $t_stmt->close();

        if (!$tourney || $tourney['status'] === 'Completed') {
            $alert_msg = "This tournament is no longer open for joining.";
            $alert_type = "error";
        } else {
            // Check if user already joined
            $chk_stmt = $conn->prepare("SELECT id FROM participants WHERE tournament_id = ? AND user_id = ?");
            $chk_stmt->bind_param("ii", $tid, $curr_uid);
            $chk_stmt->execute();
            if ($chk_stmt->get_result()->num_rows > 0) {
                $chk_stmt->close();
                $alert_msg = "You have already registered for this tournament! Check 'My Games' for Room ID.";
                $alert_type = "warning";
            } else {
                $chk_stmt->close();

                // Check wallet balance
                $u_stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                $u_stmt->bind_param("i", $curr_uid);
                $u_stmt->execute();
                $u_bal = (float)$u_stmt->get_result()->fetch_assoc()['wallet_balance'];
                $u_stmt->close();

                $fee = (float)$tourney['entry_fee'];
                if ($u_bal < $fee) {
                    $need_amt = $fee - $u_bal;
                    safe_redirect("wallet.php?error=" . urlencode("Insufficient balance. Add " . format_inr($need_amt) . " to join."));
                    exit;
                } else {
                    // Deduct wallet and enroll
                    $conn->begin_transaction();
                    try {
                        // Debit wallet
                        $deb = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
                        $deb->bind_param("di", $fee, $curr_uid);
                        $deb->execute();
                        $deb->close();

                        // Passbook transaction entry
                        $tx_desc = "Entry Fee for " . $tourney['title'];
                        $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
                        $tx->bind_param("ids", $curr_uid, $fee, $tx_desc);
                        $tx->execute();
                        $tx->close();

                        // Get current slot number
                        $cnt_res = $conn->query("SELECT COUNT(*) as cnt FROM participants WHERE tournament_id = {$tid}")->fetch_assoc();
                        $slot_no = (int)($cnt_res['cnt'] ?? 0) + 1;

                        // Insert participant
                        $part = $conn->prepare("INSERT INTO participants (tournament_id, user_id, game_name, slot_no) VALUES (?, ?, ?, ?)");
                        $part->bind_param("iisi", $tid, $curr_uid, $in_game_name, $slot_no);
                        $part->execute();
                        $part->close();

                        $conn->commit();
                        $alert_msg = "🎉 Registered successfully! Assigned Slot #{$slot_no}. View room details in My Games.";
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
// Admin Stats (Only computed if user is admin)
// -------------------------------------------------------------
$pending_deposits = 0;
$pending_withdrawals = 0;
if ($is_admin) {
    $pending_deposits = (int)($conn->query("SELECT COUNT(*) as cnt FROM deposits WHERE status = 'Pending'")->fetch_assoc()['cnt'] ?? 0);
    $pending_withdrawals = (int)($conn->query("SELECT COUNT(*) as cnt FROM withdrawals WHERE status = 'Pending'")->fetch_assoc()['cnt'] ?? 0);
    $users_cnt = (int)($conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'] ?? 0);
    $tourneys_cnt = (int)($conn->query("SELECT COUNT(*) as cnt FROM tournaments")->fetch_assoc()['cnt'] ?? 0);
    $prize_dist = (float)($conn->query("SELECT COALESCE(SUM(prize_pool), 0.00) as total FROM tournaments WHERE status = 'Completed'")->fetch_assoc()['total'] ?? 0);
}

// -------------------------------------------------------------
// Fetch Available Tournaments for Players
// -------------------------------------------------------------
$tourneys_query = "
    SELECT t.*, 
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
    FROM tournaments t 
    WHERE t.status IN ('Upcoming', 'Live')
    ORDER BY t.status DESC, t.match_time ASC
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
            <span class="text-[10px] font-black uppercase tracking-wider text-rose-400">Admin Metrics</span>
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
            <a href="deposits.php" class="bg-slate-900/80 p-2 rounded-xl border border-slate-700/60 block hover:border-emerald-500/40">
                <span class="text-[9px] text-slate-400 block">Deposits</span>
                <span class="text-xs font-bold text-emerald-400"><?= $pending_deposits; ?> <span class="text-[8px] font-normal">req</span></span>
            </a>
            <a href="withdrawals.php" class="bg-slate-900/80 p-2 rounded-xl border border-slate-700/60 block hover:border-amber-500/40">
                <span class="text-[9px] text-slate-400 block">Payouts</span>
                <span class="text-xs font-bold text-amber-400"><?= $pending_withdrawals; ?> <span class="text-[8px] font-normal">req</span></span>
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Tournament Matches Section Header -->
<div class="flex items-center justify-between mb-3">
    <div>
        <h2 class="text-base font-black text-white flex items-center gap-1.5">
            <i class="fa-solid fa-fire text-amber-400"></i> Battle Tournaments
        </h2>
        <p class="text-[10px] text-slate-400">Join real money esports matches & win cash</p>
    </div>
    <?php if ($is_admin): ?>
        <a href="tournament.php" class="py-1.5 px-3 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl text-[11px] shadow transition">
            + New Match
        </a>
    <?php endif; ?>
</div>

<!-- Tournaments List -->
<div class="space-y-4">
    <?php if ($active_matches && $active_matches->num_rows > 0): ?>
        <?php while ($t = $active_matches->fetch_assoc()): ?>
            <?php
            $is_live = ($t['status'] === 'Live');
            $user_joined = false;
            if (isset($_SESSION['user_id'])) {
                $uid = (int)$_SESSION['user_id'];
                $check_join = $conn->query("SELECT id FROM participants WHERE tournament_id = {$t['id']} AND user_id = {$uid}");
                if ($check_join && $check_join->num_rows > 0) {
                    $user_joined = true;
                }
            }
            ?>
            <div class="bg-slate-800/90 border border-slate-700 rounded-2xl p-4 shadow-xl hover:border-violet-500/50 transition">
                
                <!-- Match Top Bar -->
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-md bg-violet-950/80 text-violet-300 border border-violet-500/30">
                            <?= htmlspecialchars($t['game_name']); ?>
                        </span>
                        <h3 class="text-sm font-bold text-white mt-1"><?= htmlspecialchars($t['title']); ?></h3>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $is_live ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 animate-pulse' : 'bg-amber-500/20 text-amber-400 border border-amber-500/40' ?>">
                        <?= $is_live ? '● LIVE' : 'Upcoming'; ?>
                    </span>
                </div>

                <!-- Match Timing & Details -->
                <div class="flex items-center gap-3 text-[11px] text-slate-400 mb-3">
                    <span class="flex items-center gap-1">
                        <i class="fa-regular fa-clock text-slate-500"></i>
                        <?= date("d M, h:i A", strtotime($t['match_time'])); ?>
                    </span>
                    <span>&bull;</span>
                    <span class="flex items-center gap-1">
                        <i class="fa-solid fa-users text-slate-500"></i>
                        <span class="text-violet-400 font-bold"><?= $t['enrolled_count']; ?></span> Players
                    </span>
                </div>

                <!-- Prize & Entry Grid -->
                <div class="grid grid-cols-2 gap-2 bg-slate-900/80 p-2.5 rounded-xl border border-slate-800 text-center mb-3">
                    <div>
                        <span class="text-[10px] text-slate-400 block">Total Prize Pool</span>
                        <span class="text-sm font-black text-amber-400 font-mono"><?= format_inr($t['prize_pool']); ?></span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 block">Entry Fee</span>
                        <span class="text-sm font-black text-emerald-400 font-mono"><?= format_inr($t['entry_fee']); ?></span>
                    </div>
                </div>

                <!-- Action Button -->
                <?php if ($user_joined): ?>
                    <a href="my_tournaments.php" class="w-full py-2.5 bg-emerald-600/20 hover:bg-emerald-600/30 border border-emerald-500/40 text-emerald-300 font-bold rounded-xl text-xs flex items-center justify-center gap-1.5 transition">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Enrolled &bull; View Room Credentials</span>
                    </a>
                <?php else: ?>
                    <button type="button" onclick="openJoinModal(<?= $t['id']; ?>, '<?= htmlspecialchars(addslashes($t['title'])); ?>', '<?= format_inr($t['entry_fee']); ?>')" class="w-full py-2.5 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-bold rounded-xl text-xs shadow-md shadow-violet-600/30 transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-bolt text-amber-300"></i>
                        <span>Join Match (<?= format_inr($t['entry_fee']); ?>)</span>
                    </button>
                <?php endif; ?>

            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="bg-slate-800/50 border border-slate-800 rounded-2xl p-6 text-center text-slate-400 text-xs">
            <i class="fa-solid fa-gamepad text-2xl mb-2 text-slate-600"></i>
            <p>No active tournaments open right now. Check back soon!</p>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Join Tournament Character Prompt -->
<div id="joinModal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-slate-900 border border-slate-700 rounded-3xl p-5 shadow-2xl relative">
        <div class="flex items-center justify-between mb-3 border-b border-slate-800 pb-2">
            <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                <i class="fa-solid fa-crosshairs text-violet-400"></i> Confirm Tournament Entry
            </h3>
            <button type="button" onclick="closeJoinModal()" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="index.php" class="space-y-3">
            <input type="hidden" name="action" value="join_tournament">
            <input type="hidden" name="tournament_id" id="modalTournamentId">

            <div class="bg-slate-800/80 p-3 rounded-xl border border-slate-700/80 text-xs">
                <p id="modalTourneyTitle" class="font-bold text-white truncate"></p>
                <p class="text-slate-400 mt-1">Fee: <span id="modalTourneyFee" class="font-bold text-emerald-400 font-mono"></span> (Deducted from wallet)</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">
                    Your In-Game Character Name / ID:
                </label>
                <input type="text" name="in_game_name" required placeholder="e.g. Mortal_007 or UID" class="w-full bg-slate-800 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none">
                <p class="text-[10px] text-slate-500 mt-1">Must match your game lobby name.</p>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeJoinModal()" class="w-1/2 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs transition">
                    Cancel
                </button>
                <button type="submit" class="w-1/2 py-2.5 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-violet-600/30">
                    Pay & Join
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openJoinModal(id, title, fee) {
    document.getElementById('modalTournamentId').value = id;
    document.getElementById('modalTourneyTitle').textContent = title;
    document.getElementById('modalTourneyFee').textContent = fee;
    document.getElementById('joinModal').classList.remove('hidden');
}

function closeJoinModal() {
    document.getElementById('joinModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
