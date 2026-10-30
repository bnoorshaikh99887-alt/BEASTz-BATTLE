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
    WHERE t.id = ?");
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

// -------------------------------------------------------------
// 3. Handle Anti-Cheat Hacker Rollback (Reclaim Prize Pool & Revert to Live)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rollback_hacker_prize') {
    $participant_id = (int)($_POST['participant_id'] ?? 0);
    $admin_reason = sanitize($_POST['admin_reason'] ?? 'Cheating / Hacker Detected');

    $p_stmt = $conn->prepare("
        SELECT p.*, u.username, u.wallet_balance, u.winning_balance 
        FROM participants p 
        INNER JOIN users u ON p.user_id = u.id 
        WHERE p.id = ? AND p.tournament_id = ?");
    $p_stmt->bind_param("ii", $participant_id, $tournament_id);
    $p_stmt->execute();
    $p_row = $p_stmt->get_result()->fetch_assoc();
    $p_stmt->close();

    if ($p_row) {
        $p_uid = (int)$p_row['user_id'];
        $prize_revoked = (float)$p_row['prize_won'];

        $conn->begin_transaction();
        try {
            // 1. Revoke prize from hacker's wallet balance
            if ($prize_revoked > 0) {
                $ded = $conn->prepare("UPDATE users SET wallet_balance = GREATEST(0, wallet_balance - ?), winning_balance = GREATEST(0, winning_balance - ?) WHERE id = ?");
                $ded->bind_param("ddi", $prize_revoked, $prize_revoked, $p_uid);
                $ded->execute();
                $ded->close();

                // Record ledger transaction
                $desc = "Prize Reclaimed (Anti-Cheat Violation in " . $t['title'] . "): " . $admin_reason;
                $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
                $tx->bind_param("ids", $p_uid, $prize_revoked, $desc);
                $tx->execute();
                $tx->close();
            }

            // 2. Reset participant earnings
            $rst = $conn->prepare("UPDATE participants SET prize_won = 0.00, rank_no = 0, kills = 0 WHERE id = ?");
            $rst->bind_param("i", $participant_id);
            $rst->execute();
            $rst->close();

            // 3. Move tournament back from 'completed' to 'live' and set support notice
            $notice_room_id = "MATCH UNDER REVIEW";
            $notice_pass = "Contact Customer Support within 1 hr";
            $upd_t = $conn->prepare("UPDATE tournaments SET status = 'live', room_id = ?, room_password = ?, winner_user_id = NULL WHERE id = ?");
            $upd_t->bind_param("ssi", $notice_room_id, $notice_pass, $tournament_id);
            $upd_t->execute();
            $upd_t->close();

            $conn->commit();
            $msg = "Anti-Cheat Action Applied: Revoked " . format_inr($prize_revoked) . " prize from @" . htmlspecialchars($p_row['username']) . "! Match reverted to LIVE section with 'Contact Customer Support within 1 hr' notice.";
            $msg_type = "success";
            $t['status'] = 'live';
            $t['room_id'] = $notice_room_id;
            $t['room_password'] = $notice_pass;
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Hacker rollback error: " . $e->getMessage();
            $msg_type = "error";
        }
    } else {
        $msg = "Participant record not found.";
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
            <span class="text-base font-mono font-black text-amber-500"><?= format_compact_inr($t['prize_pool']); ?></span>
        </div>
    </div>

    <!-- Match Parameters Matrix -->
    <div class="grid grid-cols-4 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-center text-xs">
        <div>
            <span class="text-[10px] text-slate-400 block">Entry Fee</span>
            <span class="font-bold text-slate-900"><?= format_inr($t['entry_fee']); ?></span>
        </div>
        <div>
            <span class="text-[10px] text-slate-400 block">Per Kill</span>
            <span class="font-bold text-emerald-600"><?= format_inr($per_kill_rate); ?></span>
        </div>
        <div>
            <span class="text-[10px] text-slate-400 block">Total Slots</span>
            <span class="font-bold text-slate-900"><?= $t['total_slots']; ?></span>
        </div>
        <div>
            <span class="text-[10px] text-slate-400 block">Joined</span>
            <span class="font-bold text-yellow-700"><?= $t['enrolled_count']; ?></span>
        </div>
    </div>
</div>

<!-- Alert Feedback -->
<?php if (!empty($msg)): ?>
    <div class="mb-5 p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2 <?= ($msg_type === 'success') ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?>"></i>
        <span><?= $msg; ?></span>
    </div>
<?php endif; ?>

<!-- Section 1: Room ID & Password Configuration -->
<div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs mb-5">
    <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-1 flex items-center gap-1.5">
        <i class="fa-solid fa-key text-yellow-500"></i> Room ID & Match Status
    </h3>
    <p class="text-[11px] text-slate-400 mb-3">Room credentials are only visible to players who joined this match</p>

    <form method="POST" action="manage_tournament.php?id=<?= $tournament_id; ?>" class="space-y-3">
        <input type="hidden" name="action" value="update_room">
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Custom Room ID</label>
                <input type="text" name="room_id" value="<?= htmlspecialchars($t['room_id'] ?? ''); ?>" placeholder="e.g. 8492019" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Room Password</label>
                <input type="text" name="room_password" value="<?= htmlspecialchars($t['room_password'] ?? ''); ?>" placeholder="e.g. 1234" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>
        </div>

        <div>
            <label class="block text-[11px] font-bold text-slate-700 mb-1">Match Stage / Status</label>
            <select name="status" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:outline-none">
                <option value="upcoming" <?= ($t['status'] === 'upcoming') ? 'selected' : ''; ?>>UPCOMING (Registration Open)</option>
                <option value="live" <?= ($t['status'] === 'live' || $t['status'] === 'ongoing') ? 'selected' : ''; ?>>LIVE / ONGOING (Match Started / Under Review)</option>
                <option value="completed" <?= ($t['status'] === 'completed') ? 'selected' : ''; ?>>COMPLETED (Finished / Results Declared)</option>
            </select>
        </div>

        <button type="submit" class="w-full py-2.5 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-xs active:scale-95 flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-save"></i>
            <span>Save Room Credentials & Status</span>
        </button>
    </form>
</div>

<!-- Section 2: Participants, Kills & Prize Pool Distribution -->
<div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs mb-6">
    <div class="flex items-center justify-between mb-2">
        <div>
            <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                <i class="fa-solid fa-crosshairs text-rose-500"></i> Enter Kills & Distribute Prizes
            </h3>
            <p class="text-[11px] text-slate-400">
                Rate: <strong class="text-slate-700"><?= format_inr($per_kill_rate); ?> / kill</strong>. Type kills and rank bonus for each player.
            </p>
        </div>
        <span class="text-[10px] font-mono font-black px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200">
            <?= $participants ? $participants->num_rows : 0; ?> Players
        </span>
    </div>

    <form method="POST" action="manage_tournament.php?id=<?= $tournament_id; ?>">
        <input type="hidden" name="action" value="distribute_winnings">

        <div class="space-y-3 my-4">
            <?php if ($participants && $participants->num_rows > 0): ?>
                <?php while ($p = $participants->fetch_assoc()): ?>
                    <?php 
                        $pid = $p['id'];
                        $has_won = ($p['prize_won'] > 0);
                    ?>
                    <div class="p-3 rounded-xl border <?= $has_won ? 'border-amber-300 bg-amber-50/40' : 'border-slate-200 bg-slate-50' ?> text-xs space-y-2">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded bg-yellow-400 text-slate-950 font-black text-[10px]">
                                        Slot #<?= $p['slot_no'] ?: 'Auto'; ?>
                                    </span>
                                    <span class="font-extrabold text-slate-900 text-xs">
                                        <?= htmlspecialchars($p['in_game_name'] ?: $p['username']); ?>
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-semibold">(@<?= htmlspecialchars($p['username']); ?>)</span>
                                </div>
                                <div class="text-[10px] text-slate-500 font-mono mt-0.5">
                                    Game UID: <strong class="text-slate-800"><?= htmlspecialchars($p['game_id'] ?: ($p['user_game_id'] ?: 'N/A')); ?></strong>
                                </div>
                            </div>

                            <div class="text-right">
                                <?php if ($has_won): ?>
                                    <span class="text-xs font-black text-amber-600 font-mono block">
                                        <?= format_inr($p['prize_won']); ?> Won
                                    </span>
                                    <span class="text-[9px] font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">
                                        Rank #<?= $p['rank_no']; ?> &bull; <?= $p['kills']; ?> Kills
                                    </span>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-400 font-bold">Pending Result</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Kills & Rank Inputs -->
                        <div class="grid grid-cols-2 gap-2 pt-1 border-t border-slate-200/60">
                            <div>
                                <label class="block text-[9px] font-bold text-slate-500 mb-0.5 uppercase">Kills (x <?= format_inr($per_kill_rate); ?>)</label>
                                <input type="number" name="kills[<?= $pid; ?>]" min="0" value="<?= (int)$p['kills']; ?>" class="w-full bg-white border border-slate-300 focus:border-yellow-400 rounded-lg px-2.5 py-1 text-xs text-slate-900 font-mono font-bold focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[9px] font-bold text-slate-500 mb-0.5 uppercase">Rank Placement Bonus (₹)</label>
                                <input type="number" step="0.5" name="bonus[<?= $pid; ?>]" min="0" value="0" class="w-full bg-white border border-slate-300 focus:border-yellow-400 rounded-lg px-2.5 py-1 text-xs text-slate-900 font-mono font-bold focus:outline-none">
                            </div>
                        </div>

                        <!-- Anti-Cheat Hacker Prize Reclaim Action -->
                        <?php if ($has_won): ?>
                            <div class="pt-1.5 border-t border-amber-200 flex items-center justify-between">
                                <span class="text-[10px] text-rose-700 font-bold flex items-center gap-1">
                                    <i class="fa-solid fa-shield-virus"></i> Suspected Cheating?
                                </span>
                                <button type="button" onclick="triggerHackerRevoke(<?= $pid; ?>, '<?= addslashes($p['username']); ?>', <?= (float)$p['prize_won']; ?>)" class="px-2 py-0.5 bg-rose-100 hover:bg-rose-200 text-rose-700 rounded text-[10px] font-bold transition flex items-center gap-1 active:scale-95">
                                    <i class="fa-solid fa-rotate-left"></i> Reclaim Prize (Hacker)
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-6 text-slate-400 text-xs">
                    No participants have joined this match yet.
                </div>
            <?php endif; ?>
        </div>

        <?php if ($participants && $participants->num_rows > 0): ?>
            <button type="submit" onclick="return confirm('Calculate and credit prize winnings to each player wallet based on kills and rank?');" class="w-full py-3 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-2">
                <i class="fa-solid fa-award text-sm"></i>
                <span>Publish Results & Credit Wallets</span>
            </button>
        <?php endif; ?>
    </form>
</div>

<!-- Modal: Hacker Prize Reclaim Confirmation -->
<div id="hackerModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-rose-200 rounded-3xl p-5 shadow-2xl relative text-left">
        <button type="button" onclick="closeHackerModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="text-center mb-3">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center text-xl font-black mb-2 shadow-xs">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h3 class="text-sm font-extrabold text-slate-900">Anti-Cheat Prize Reclaim</h3>
            <p class="text-[11px] text-slate-500">Revoke prize from hacker and move match back to Live</p>
        </div>

        <form method="POST" action="manage_tournament.php?id=<?= $tournament_id; ?>" class="space-y-3">
            <input type="hidden" name="action" value="rollback_hacker_prize">
            <input type="hidden" name="participant_id" id="hackerParticipantId" value="">

            <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs space-y-1">
                <div class="flex justify-between">
                    <span class="text-slate-500 font-semibold">Suspected Player:</span>
                    <strong class="text-slate-900" id="hackerUsernameText">@player</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500 font-semibold">Prize to Revoke:</span>
                    <strong class="text-rose-600 font-mono font-bold" id="hackerPrizeText">₹0.00</strong>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-700 mb-1">Reason for Reclaim</label>
                <input type="text" name="admin_reason" value="Cheating / Third-Party Tools Detected" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:outline-none">
            </div>

            <p class="text-[10px] text-slate-500 leading-tight">
                This will deduct the prize from the player's wallet, revert tournament status back to <strong>LIVE</strong>, and set room notice to <strong>"Contact Customer Support within 1 hr"</strong>.
            </p>

            <button type="submit" class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-shield-virus"></i>
                <span>Confirm Revoke & Move to Live</span>
            </button>
        </form>
    </div>
</div>

<script>
    function triggerHackerRevoke(pid, username, prize) {
        document.getElementById('hackerParticipantId').value = pid;
        document.getElementById('hackerUsernameText').innerText = '@' + username;
        document.getElementById('hackerPrizeText').innerText = '₹' + prize.toFixed(2);
        document.getElementById('hackerModal').classList.remove('hidden');
    }
    function closeHackerModal() {
        document.getElementById('hackerModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
