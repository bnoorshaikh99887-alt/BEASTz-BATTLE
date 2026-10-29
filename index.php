<?php
require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/header.php';

$message = "";
$message_type = "";

// Handle Join Now Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_tournament') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php?error=" . urlencode("Please sign in to join tournaments."));
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];
    $tournament_id = (int)$_POST['tournament_id'];

    // 1. Fetch tournament details
    $t_stmt = $conn->prepare("SELECT id, title, entry_fee, status FROM tournaments WHERE id = ?");
    $t_stmt->bind_param("i", $tournament_id);
    $t_stmt->execute();
    $t_res = $t_stmt->get_result();

    if ($t = $t_res->fetch_assoc()) {
        if ($t['status'] !== 'Upcoming') {
            $message = "This tournament has already started or completed.";
            $message_type = "error";
        } else {
            // 2. Check if already joined
            $p_stmt = $conn->prepare("SELECT id FROM participants WHERE user_id = ? AND tournament_id = ?");
            $p_stmt->bind_param("ii", $user_id, $tournament_id);
            $p_stmt->execute();
            if ($p_stmt->get_result()->num_rows > 0) {
                $message = "You have already joined this tournament!";
                $message_type = "error";
            } else {
                // 3. Check wallet balance
                $u_stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
                $u_stmt->bind_param("i", $user_id);
                $u_stmt->execute();
                $user_data = $u_stmt->get_result()->fetch_assoc();
                $balance = (float)$user_data['wallet_balance'];
                $entry_fee = (float)$t['entry_fee'];

                if ($balance < $entry_fee) {
                    $message = "Insufficient wallet balance. Please add funds to join.";
                    $message_type = "error";
                } else {
                    // 4. Atomic Deduct & Join
                    $conn->begin_transaction();
                    try {
                        // Deduct balance
                        $deduct = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
                        $deduct->bind_param("di", $entry_fee, $user_id);
                        $deduct->execute();

                        // Add to participants
                        $join = $conn->prepare("INSERT INTO participants (user_id, tournament_id) VALUES (?, ?)");
                        $join->bind_param("ii", $user_id, $tournament_id);
                        $join->execute();

                        // Record transaction
                        $desc = "Entry fee for: " . $t['title'];
                        $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
                        $tx->bind_param("ids", $user_id, $entry_fee, $desc);
                        $tx->execute();

                        $conn->commit();
                        $message = "🎉 Successfully registered for " . $t['title'] . "!";
                        $message_type = "success";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "Transaction failed: " . $e->getMessage();
                        $message_type = "error";
                    }
                }
                $u_stmt->close();
            }
            $p_stmt->close();
        }
    } else {
        $message = "Tournament not found.";
        $message_type = "error";
    }
    $t_stmt->close();
}

// Fetch all Upcoming tournaments
$tournaments_query = "
    SELECT t.*, 
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as total_joined
    FROM tournaments t 
    WHERE t.status = 'Upcoming' 
    ORDER BY t.match_time ASC";
$tourneys = $conn->query($tournaments_query);

// Fetch joined tournament IDs for current user to show joined state
$joined_ids = [];
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $j_res = $conn->query("SELECT tournament_id FROM participants WHERE user_id = $uid");
    while ($j = $j_res->fetch_assoc()) {
        $joined_ids[] = (int)$j['tournament_id'];
    }
}
?>

<!-- Alerts -->
<?php if (!empty($message)): ?>
    <div class="mb-4 p-3.5 rounded-xl text-xs font-semibold flex items-center gap-2 <?= ($message_type === 'success') ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' ?>">
        <i class="fa-solid <?= ($message_type === 'success') ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?>"></i>
        <span><?= $message; ?></span>
    </div>
<?php endif; ?>

<!-- Hero Mobile Banner -->
<div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-violet-900/60 via-purple-900/40 to-slate-900 p-4 border border-violet-500/20 mb-5 shadow-lg">
    <div class="flex items-center justify-between">
        <div class="space-y-1">
            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wider bg-violet-500/30 text-violet-300 border border-violet-500/30">
                LIVE ARENA
            </span>
            <h2 class="text-base font-extrabold text-white">Dominate & Earn</h2>
            <p class="text-[11px] text-slate-300">Join verified rooms & win automated cash prizes.</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-violet-600/30 border border-violet-500/40 flex items-center justify-center text-violet-400 text-2xl">
            <i class="fa-solid fa-fire"></i>
        </div>
    </div>
</div>

<!-- Section Header -->
<div class="flex items-center justify-between mb-3 px-1">
    <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
        <i class="fa-solid fa-trophy text-amber-400"></i> Upcoming Tournaments
    </h3>
    <span class="text-[11px] text-violet-400 font-semibold"><?= $tourneys ? $tourneys->num_rows : 0; ?> Available</span>
</div>

<!-- Tournaments Card Grid -->
<div class="space-y-4">
    <?php if ($tourneys && $tourneys->num_rows > 0): ?>
        <?php while ($row = $tourneys->fetch_assoc()): ?>
            <?php 
            $is_joined = in_array((int)$row['id'], $joined_ids);
            $match_formatted = date("d M, h:i A", strtotime($row['match_time']));
            ?>
            <div class="bg-slate-800/80 hover:bg-slate-800 border border-slate-700/80 rounded-2xl p-4 shadow-lg transition relative overflow-hidden">
                <!-- Top Card Bar -->
                <div class="flex items-start justify-between gap-2 mb-2.5">
                    <div>
                        <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-700 text-slate-300 mb-1">
                            <i class="fa-solid fa-crosshairs mr-1 text-violet-400"></i><?= htmlspecialchars($row['game_name']); ?>
                        </span>
                        <h4 class="text-sm font-bold text-white line-clamp-1"><?= htmlspecialchars($row['title']); ?></h4>
                    </div>
                    <span class="text-[11px] font-bold px-2 py-1 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 whitespace-nowrap">
                        <i class="fa-solid fa-award mr-1"></i><?= format_inr($row['prize_pool']); ?> Pool
                    </span>
                </div>

                <!-- Match Meta Info -->
                <div class="grid grid-cols-3 gap-2 bg-slate-900/60 p-2.5 rounded-xl border border-slate-800 text-[11px] mb-3">
                    <div>
                        <span class="text-slate-400 block text-[10px]">Entry Fee</span>
                        <span class="font-bold text-white font-mono"><?= format_inr($row['entry_fee']); ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px]">Match Time</span>
                        <span class="font-semibold text-slate-200"><?= $match_formatted; ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px]">Enrolled</span>
                        <span class="font-bold text-violet-400"><i class="fa-solid fa-users text-[10px] mr-1"></i><?= $row['total_joined']; ?></span>
                    </div>
                </div>

                <!-- Action Button Form -->
                <?php if ($is_joined): ?>
                    <div class="w-full py-2.5 bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 text-xs font-bold rounded-xl text-center flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-circle-check"></i> Already Joined
                    </div>
                <?php else: ?>
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="join_tournament">
                        <input type="hidden" name="tournament_id" value="<?= $row['id']; ?>">
                        <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white text-xs font-bold rounded-xl shadow-md shadow-violet-600/20 transition flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-ticket"></i> Join Now (<?= format_inr($row['entry_fee']); ?>)
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="bg-slate-800/40 border border-slate-800 rounded-2xl p-8 text-center">
            <i class="fa-solid fa-ghost text-4xl text-slate-600 mb-2"></i>
            <h4 class="text-sm font-bold text-slate-300">No Upcoming Tournaments</h4>
            <p class="text-xs text-slate-500 mt-1">Check back shortly or check My Games for ongoing matches.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/common/bottom.php'; ?>