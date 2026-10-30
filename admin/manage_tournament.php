<?php
require_once __DIR__ . '/common/header.php';

$tournament_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = "";
$msg_type = "";

// Fetch Tournament Details
$stmt = $conn->prepare("
    SELECT t.*, 
    COALESCE(t.game_name, t.game, 'Free Fire') as display_game,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
    FROM tournaments t 
    WHERE t.id = ?
");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$t = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$t) {
    echo "<div class='p-6 text-center text-slate-600'><p class='font-bold mb-3'>Tournament not found.</p><a href='tournament.php' class='text-yellow-600 underline text-xs font-bold'>&larr; Back to Tournaments</a></div>";
    require_once __DIR__ . '/common/bottom.php';
    exit;
}

$per_kill_rate = (float)($t['per_kill'] ?? 0.00);

// -------------------------------------------------------------
// 1. Handle Room Credentials & Match Status Update
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_room') {
    $room_id = sanitize($_POST['room_id'] ?? '');
    $room_password = sanitize($_POST['room_password'] ?? '');
    $status = sanitize($_POST['status'] ?? 'upcoming');

    $u_stmt = $conn->prepare("UPDATE tournaments SET room_id = ?, room_password = ?, status = ? WHERE id = ?");
    if ($u_stmt) {
        $u_stmt->bind_param("sssi", $room_id, $room_password, $status, $tournament_id);
        if ($u_stmt->execute()) {
            $msg = "Room ID, Password, and match status updated successfully!";
            $msg_type = "success";
            $t['room_id'] = $room_id;
            $t['room_password'] = $room_password;
            $t['status'] = $status;
        } else {
            $msg = "Update failed: " . $u_stmt->error;
            $msg_type = "error";
        }
        $u_stmt->close();
    }
}

// -------------------------------------------------------------
// 2. Handle Multi-Player Results & Per-Kill Reward Distribution
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'distribute_winnings') {
    $kills_arr = $_POST['kills'] ?? [];
    $bonus_arr = $_POST['bonus'] ?? [];
    $rank_arr = $_POST['rank_no'] ?? [];

    $conn->begin_transaction();
    try {
        $total_distributed = 0.00;
        $winner_u_id = null;

        // Fetch all participants to process
        $p_query = $conn->prepare("SELECT id, user_id, in_game_name FROM participants WHERE tournament_id = ?");
        $p_query->bind_param("i", $tournament_id);
        $p_query->execute();
        $participants_list = $p_query->get_result();
        $p_query->close();

        while ($p_item = $participants_list->fetch_assoc()) {
            $pid = (int)$p_item['id'];
            $p_uid = (int)$p_item['user_id'];

            $kills = isset($kills_arr[$pid]) ? max(0, (int)$kills_arr[$pid]) : 0;
            $bonus = isset($bonus_arr[$pid]) ? max(0.0, (float)$bonus_arr[$pid]) : 0.00;
            $rank_no = isset($rank_arr[$pid]) ? max(0, (int)$rank_arr[$pid]) : 0;

            if ($rank_no === 1) {
                $winner_u_id = $p_uid;
            }

            // Calculate earnings: (kills * per_kill_rate) + placement bonus
            $earned = ($kills * $per_kill_rate) + $bonus;

            // Update participant row
            $upd_p = $conn->prepare("UPDATE participants SET kills = ?, rank_no = ?, prize_won = ? WHERE id = ?");
            $upd_p->bind_param("iidi", $kills, $rank_no, $earned, $pid);
            $upd_p->execute();
            $upd_p->close();

            // Credit winnings to player wallet if > 0
            if ($earned > 0) {
                $upd_w = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ?, winning_balance = winning_balance + ? WHERE id = ?");
                $upd_w->bind_param("ddi", $earned, $earned, $p_uid);
                $upd_w->execute();
                $upd_w->close();

                // Create clear ledger transaction
                $desc = "Match Winnings: " . $t['title'] . " (" . $kills . " Kills @ " . format_inr($per_kill_rate);
                if ($rank_no > 0) {
                    $desc .= " + Rank #" . $rank_no . " Bonus " . format_inr($bonus);
                }
                $desc .= ")";

                $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
                $tx->bind_param("ids", $p_uid, $earned, $desc);
                $tx->execute();
                $tx->close();

                $total_distributed += $earned;
            }
        }

        // Set tournament to completed
        if ($winner_u_id) {
            $conn->query("UPDATE tournaments SET status = 'completed', winner_user_id = {$winner_u_id} WHERE id = {$tournament_id}");
        } else {
            $conn->query("UPDATE tournaments SET status = 'completed' WHERE id = {$tournament_id}");
        }

        $conn->commit();
        $msg = "Match results successfully published! Total " . format_inr($total_distributed) . " credited to gamer wallets based on kills and rankings.";
        $msg_type = "success";
        $t['status'] = 'completed';
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Distribution Error: " . $e->getMessage();
        $msg_type = "error";
    }
}

// Fetch all enrolled participants with Game UID and user details
$participants = $conn->query("
    SELECT p.*, u.username, u.email, u.phone, u.wallet_balance, u.game_id as user_game_id
    FROM participants p
    INNER JOIN users u ON p.user_id = u.id
    WHERE p.tournament_id = {$tournament_id}
    ORDER BY p.slot_no ASC, p.id ASC
");
?>

<!-- Breadcrumb & Back -->
<div class="mb-4 flex items-center justify-between">
    <a href="tournament.php" class="text-xs font-bold text-slate-600 hover:text-slate-900 flex items-center gap-1.5 transition">
        <i class="fa-solid fa-arrow-left text-[10px]"></i>
        <span>Back to Tournaments</span>
    </a>
    <span class="text-xs font-black px-2.5 py-0.5 rounded-full <?= ($t['status'] === 'completed') ? 'bg-slate-200 text-slate-800' : (($t['status'] === 'ongoing' || $t['status'] === 'live') ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-yellow-100 text-yellow-900 border border-yellow-300') ?>">
        <?= strtoupper($t['status']); ?>
    </span>
</div>

<!-- Match Overview Card -->
<div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs mb-5 space-y-3">
    <div class="flex items-start justify-between">
        <div>
            <span class="text-[10px] font-bold text-yellow-800 bg-yellow-100 border border-yellow-300 px-2 py-0.5 rounded uppercase">
                <?= htmlspecialchars($t['display_game']); ?> &bull; <?= htmlspecialchars($t['match_type']); ?> &bull; <?= htmlspecialchars($t['map']); ?>
            </span>
            <h2 class="text-base font-extrabold text-slate-900 mt-1.5">
                <?= htmlspecialchars($t['title']); ?>
            </h2>
            <p class="text-xs text-slate-500">
                <i class="fa-regular fa-clock mr-1 text-slate-400"></i><?= date("d M Y, h:i A", strtotime($t['match_time'])); ?>
            </p>
        </div>
        <div class="text-right">
            <span class="text-[10px] text-slate-400 block font-semibold">Total Prize Pool</span>
            <span class="text-base font-mono font-black text-amber-500"><?= format_inr($t['prize_pool']); ?></span>
        </div>
    </div>

    <!-- Match Parameters Matrix -->
    <div class="grid grid-cols-4 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-center text-xs">
        <div>
            <span class="text-[10px] text-slate-400 block">Entry Fee</span>
            <span class="font-extrabold text-slate-900"><?= format_inr($t['entry_fee']); ?></span>
        </div>
        <div>
            <span class="text-[10px] text-slate-400 block">Per Kill Rate</span>
            <span class="font-extrabold text-emerald-600 font-mono"><?= format_inr($t['per_kill']); ?></span>
        </div>
        <div>
            <span class="text-[10px] text-slate-400 block">Slots</span>
            <span class="font-extrabold text-slate-900"><?= $t['enrolled_count']; ?> / <?= $t['total_slots']; ?></span>
        </div>
        <div>
            <span class="text-[10px] text-slate-400 block">Match ID</span>
            <span class="font-extrabold font-mono text-slate-600">#<?= $t['id']; ?></span>
        </div>
    </div>
</div>

<?php if (!empty($msg)): ?>
    <div class="mb-5 p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2.5 <?= ($msg_type === 'success') ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600'; ?> text-sm"></i>
        <span><?= htmlspecialchars($msg); ?></span>
    </div>
<?php endif; ?>

<!-- Form 1: Room ID & Password Publisher -->
<div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs mb-5">
    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <i class="fa-solid fa-key text-yellow-500"></i> Custom Room Credentials
    </h3>
    
    <form method="POST" action="manage_tournament.php?id=<?= $tournament_id; ?>" class="space-y-3">
        <input type="hidden" name="action" value="update_room">

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Room ID</label>
                <input type="text" name="room_id" value="<?= htmlspecialchars($t['room_id'] ?? ''); ?>" placeholder="e.g. 192837" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Room Password</label>
                <input type="text" name="room_password" value="<?= htmlspecialchars($t['room_password'] ?? ''); ?>" placeholder="e.g. 1234" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Match Status</label>
            <select name="status" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:outline-none">
                <option value="upcoming" <?= ($t['status'] === 'upcoming') ? 'selected' : ''; ?>>Upcoming (Registration Open)</option>
                <option value="live" <?= ($t['status'] === 'live' || $t['status'] === 'ongoing') ? 'selected' : ''; ?>>Live / Ongoing (Players in Room)</option>
                <option value="completed" <?= ($t['status'] === 'completed') ? 'selected' : ''; ?>>Completed (Match Finished)</option>
            </select>
        </div>

        <button type="submit" class="w-full py-2.5 bg-yellow-400 hover:bg-yellow-500 text-black font-extrabold rounded-xl text-xs transition shadow-xs flex items-center justify-center gap-1.5 active:scale-95">
            <i class="fa-solid fa-paper-plane"></i> Save & Publish Room Credentials
        </button>
    </form>
</div>

<!-- Form 2: Player Scorecard & Results Distribution (Per Kill Calculation) -->
<div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs mb-8">
    <div class="flex items-center justify-between mb-3">
        <div>
            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                <i class="fa-solid fa-trophy text-yellow-500"></i> Match Scorecard & Prize Distribution
            </h3>
            <p class="text-[11px] text-slate-500">Enter kills per player & rank bonus to calculate winnings automatically</p>
        </div>
        <span class="text-xs font-bold px-2.5 py-1 bg-yellow-100 text-yellow-900 border border-yellow-300 rounded-full">
            <?= $t['enrolled_count']; ?> Gamers
        </span>
    </div>

    <?php if ($participants && $participants->num_rows > 0): ?>
        <form method="POST" action="manage_tournament.php?id=<?= $tournament_id; ?>" onsubmit="return confirm('Confirm match results submission? Winnings will be calculated and credited to player balances immediately.');">
            <input type="hidden" name="action" value="distribute_winnings">

            <div class="space-y-3 mb-4">
                <?php $i = 0; while ($p = $participants->fetch_assoc()): $i++; ?>
                    <?php
                        $pid = (int)$p['id'];
                        $slot_display = $p['slot_no'] ?: $i;
                        $ign_display = !empty($p['in_game_name']) ? $p['in_game_name'] : 'N/A';
                        $uid_display = !empty($p['game_id']) ? $p['game_id'] : (!empty($p['user_game_id']) ? $p['user_game_id'] : 'N/A');
                        $current_kills = (int)($p['kills'] ?? 0);
                        $current_rank = (int)($p['rank_no'] ?? 0);
                        $current_won = (float)($p['prize_won'] ?? 0.00);
                        $calculated_bonus = max(0.0, $current_won - ($current_kills * $per_kill_rate));
                    ?>
                    <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl space-y-2.5 hover:border-yellow-300 transition">
                        <!-- Player Identity Bar -->
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black px-2 py-0.5 rounded-lg bg-yellow-400 text-black">
                                    Slot #<?= $slot_display; ?>
                                </span>
                                <div>
                                    <span class="text-xs font-bold text-slate-900 block leading-tight">
                                        IGN: <strong class="text-yellow-700"><?= htmlspecialchars($ign_display); ?></strong>
                                    </span>
                                    <span class="text-[10px] text-slate-500 font-mono">
                                        UID: <?= htmlspecialchars($uid_display); ?> &bull; @<?= htmlspecialchars($p['username']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] text-slate-400 block font-semibold">Total Won</span>
                                <span id="total_payout_<?= $pid; ?>" class="text-sm font-mono font-black text-emerald-600">
                                    <?= format_inr($current_won); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Score Inputs Row -->
                        <div class="grid grid-cols-3 gap-2 pt-1 border-t border-slate-200/60">
                            <!-- Kills Input -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 mb-0.5">
                                    Kills (× <?= format_inr($per_kill_rate); ?>)
                                </label>
                                <input type="number" min="0" name="kills[<?= $pid; ?>]" id="kills_<?= $pid; ?>" 
                                       value="<?= $current_kills; ?>" 
                                       oninput="updateRow(<?= $pid; ?>)" 
                                       class="w-full bg-white border border-slate-300 focus:border-yellow-400 rounded-xl px-2.5 py-1.5 text-xs font-black text-center text-slate-900 focus:outline-none">
                            </div>

                            <!-- Rank Placement -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 mb-0.5">Position / Rank</label>
                                <select name="rank_no[<?= $pid; ?>]" id="rank_<?= $pid; ?>" onchange="updateRow(<?= $pid; ?>)" class="w-full bg-white border border-slate-300 focus:border-yellow-400 rounded-xl px-2 py-1.5 text-xs font-bold text-slate-900 focus:outline-none">
                                    <option value="0" <?= ($current_rank === 0) ? 'selected' : ''; ?>>-</option>
                                    <option value="1" <?= ($current_rank === 1) ? 'selected' : ''; ?>>#1 (Booyah)</option>
                                    <option value="2" <?= ($current_rank === 2) ? 'selected' : ''; ?>>#2 (Runner-Up)</option>
                                    <option value="3" <?= ($current_rank === 3) ? 'selected' : ''; ?>>#3 (3rd Place)</option>
                                </select>
                            </div>

                            <!-- Placement Bonus ₹ -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 mb-0.5">Rank Bonus (₹)</label>
                                <input type="number" step="0.5" min="0" name="bonus[<?= $pid; ?>]" id="bonus_<?= $pid; ?>" 
                                       value="<?= $calculated_bonus; ?>" 
                                       placeholder="₹0"
                                       oninput="updateRow(<?= $pid; ?>)" 
                                       class="w-full bg-white border border-slate-300 focus:border-yellow-400 rounded-xl px-2.5 py-1.5 text-xs font-mono font-bold text-center text-slate-900 focus:outline-none">
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Distribution Submit Button -->
            <button type="submit" class="w-full py-3 bg-yellow-400 hover:bg-yellow-500 text-black font-extrabold rounded-xl text-xs shadow-xs transition active:scale-95 flex items-center justify-center gap-2">
                <i class="fa-solid fa-trophy"></i>
                <span>Submit Results & Distribute Winnings</span>
            </button>
        </form>
    <?php else: ?>
        <p class="text-xs text-slate-500 text-center py-6">No players have joined this match yet.</p>
    <?php endif; ?>
</div>

<script>
    const perKillRate = <?= $per_kill_rate; ?>;

    function updateRow(pid) {
        const killsInput = document.getElementById('kills_' + pid);
        const bonusInput = document.getElementById('bonus_' + pid);
        const totalSpan = document.getElementById('total_payout_' + pid);

        const kills = parseInt(killsInput.value) || 0;
        const bonus = parseFloat(bonusInput.value) || 0.00;

        const total = (kills * perKillRate) + bonus;
        totalSpan.innerText = '₹' + total.toFixed(2);
    }
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
