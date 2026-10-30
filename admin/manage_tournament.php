<?php
require_once __DIR__ . '/common/header.php';

$tournament_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = "";
$msg_type = "";

// 1. Handle Room Credentials Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_room') {
    $room_id = sanitize($_POST['room_id'] ?? '');
    $room_password = sanitize($_POST['room_password'] ?? '');
    $status = sanitize($_POST['status'] ?? 'upcoming');

    $u_stmt = $conn->prepare("UPDATE tournaments SET room_id = ?, room_password = ?, status = ? WHERE id = ?");
    if ($u_stmt) {
        $u_stmt->bind_param("sssi", $room_id, $room_password, $status, $tournament_id);
        if ($u_stmt->execute()) {
            $msg = "Room ID and Password updated successfully! Status set to " . htmlspecialchars($status) . ".";
            $msg_type = "success";
        } else {
            $msg = "Update failed: " . $u_stmt->error;
            $msg_type = "error";
        }
        $u_stmt->close();
    }
}

// 2. Handle Declare Winner & Distribute Prize
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'declare_winner') {
    $winner_id = (int)($_POST['winner_user_id'] ?? 0);

    $tp_stmt = $conn->prepare("SELECT prize_pool, title, status FROM tournaments WHERE id = ?");
    $tp_stmt->bind_param("i", $tournament_id);
    $tp_stmt->execute();
    $tourney_row = $tp_stmt->get_result()->fetch_assoc();
    $tp_stmt->close();

    if (!$tourney_row) {
        $msg = "Tournament not found.";
        $msg_type = "error";
    } elseif (strtolower($tourney_row['status']) === 'completed') {
        $msg = "Winner has already been declared for this match.";
        $msg_type = "error";
    } elseif ($winner_id <= 0) {
        $msg = "Please select a valid participant as winner.";
        $msg_type = "error";
    } else {
        $prize_amount = (float)$tourney_row['prize_pool'];
        $title = $tourney_row['title'];

        $conn->begin_transaction();
        try {
            // 1. Credit winner's wallet
            $add_w = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $add_w->bind_param("di", $prize_amount, $winner_id);
            $add_w->execute();
            $add_w->close();

            // 2. Add credit transaction
            $t_desc = "Prize Won: 1st Place - " . $title . " (Match #" . $tournament_id . ")";
            $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
            $tx->bind_param("ids", $winner_id, $prize_amount, $t_desc);
            $tx->execute();
            $tx->close();

            // 3. Mark tournament as completed with winner
            $end_t = $conn->prepare("UPDATE tournaments SET status = 'completed', winner_user_id = ? WHERE id = ?");
            $end_t->bind_param("ii", $winner_id, $tournament_id);
            $end_t->execute();
            $end_t->close();

            $conn->commit();
            $msg = "🎉 Winner declared and " . format_inr($prize_amount) . " credited to the winner's wallet successfully!";
            $msg_type = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Winner declaration failed: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

// Fetch Tournament Details
$t_stmt = $conn->prepare("
    SELECT t.*, u.username as winner_name
    FROM tournaments t
    LEFT JOIN users u ON t.winner_user_id = u.id
    WHERE t.id = ?
");
$t_stmt->bind_param("i", $tournament_id);
$t_stmt->execute();
$t = $t_stmt->get_result()->fetch_assoc();
$t_stmt->close();

if (!$t) {
    echo "<div class='p-4 text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 rounded-2xl'>Tournament not found. <a href='tournament.php' class='underline ml-2 text-slate-800'>Back to Tournaments</a></div>";
    require_once __DIR__ . '/common/bottom.php';
    exit;
}

// Fetch Participants
$part_stmt = $conn->prepare("
    SELECT p.*, u.username, u.email, u.phone, u.wallet_balance
    FROM participants p
    INNER JOIN users u ON p.user_id = u.id
    WHERE p.tournament_id = ?
    ORDER BY p.id ASC
");
$part_stmt->bind_param("i", $tournament_id);
$part_stmt->execute();
$participants = $part_stmt->get_result();
$part_stmt->close();
?>

<div class="mb-4 flex items-center justify-between">
    <div>
        <a href="tournament.php" class="text-xs text-slate-500 hover:text-slate-800 font-bold flex items-center gap-1 mb-1 transition">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> All Tournaments
        </a>
        <h2 class="text-base font-extrabold text-slate-900 font-display">Manage Match #<?= $tournament_id; ?></h2>
    </div>
    <span class="px-2.5 py-1 rounded-xl text-xs font-bold uppercase <?= (strtolower($t['status']) === 'completed') ? 'bg-slate-100 text-slate-600' : 'bg-yellow-100 text-yellow-800 border border-yellow-200'; ?>">
        <?= htmlspecialchars($t['status']); ?>
    </span>
</div>

<!-- Alerts -->
<?php if (!empty($msg)): ?>
    <div class="mb-4 p-3 rounded-xl text-xs font-semibold flex items-center gap-2 <?= ($msg_type === 'success') ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-500' : 'fa-circle-exclamation text-rose-500' ?>"></i>
        <span><?= $msg; ?></span>
    </div>
<?php endif; ?>

<!-- Tournament Overview Card -->
<div class="bg-white border border-slate-200 rounded-3xl p-4 shadow-sm mb-4 space-y-2">
    <div class="flex justify-between items-start">
        <div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700 uppercase">
                <?= htmlspecialchars($t['game_name'] ?? $t['game'] ?? 'Free Fire'); ?> &bull; <?= htmlspecialchars($t['match_type']); ?>
            </span>
            <h3 class="text-sm font-black text-slate-900 mt-1"><?= htmlspecialchars($t['title']); ?></h3>
            <p class="text-[11px] text-slate-400 font-mono mt-0.5"><?= date('d M Y, h:i A', strtotime($t['match_time'])); ?></p>
        </div>
        <div class="text-right">
            <span class="text-[10px] text-slate-400 block font-bold">Prize Pool</span>
            <span class="text-base font-black text-amber-500 font-mono"><?= format_inr($t['prize_pool']); ?></span>
        </div>
    </div>
</div>

<!-- Section 1: Room ID & Password Update -->
<div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm mb-4">
    <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <i class="fa-solid fa-key text-yellow-500"></i> Custom Room Credentials
    </h3>
    <form method="POST" action="manage_tournament.php?id=<?= $tournament_id; ?>" class="space-y-3">
        <input type="hidden" name="action" value="update_room">

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Room ID</label>
                <input type="text" name="room_id" value="<?= htmlspecialchars($t['room_id'] ?? ''); ?>" placeholder="e.g. 912036645" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Room Password</label>
                <input type="text" name="room_password" value="<?= htmlspecialchars($t['room_password'] ?? ''); ?>" placeholder="e.g. 9" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>
        </div>

        <div>
            <label class="block text-[11px] font-bold text-slate-700 mb-1">Match Status</label>
            <select name="status" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:outline-none">
                <option value="upcoming" <?= ($t['status'] === 'upcoming') ? 'selected' : ''; ?>>Upcoming (Pending Room ID)</option>
                <option value="live" <?= ($t['status'] === 'live' || $t['status'] === 'ongoing') ? 'selected' : ''; ?>>Live (Match Running)</option>
                <option value="completed" <?= ($t['status'] === 'completed') ? 'selected' : ''; ?>>Completed (Match Finished)</option>
            </select>
        </div>

        <button type="submit" class="w-full py-2.5 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-xs active:scale-95">
            Save Room Credentials
        </button>
    </form>
</div>

<!-- Section 2: Declare Match Winner -->
<?php if (strtolower($t['status']) !== 'completed'): ?>
    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm mb-4">
        <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-2 flex items-center gap-1.5">
            <i class="fa-solid fa-trophy text-amber-500"></i> Declare 1st Place Winner
        </h3>
        <p class="text-[11px] text-slate-500 mb-3">Selecting a winner will automatically credit <?= format_inr($t['prize_pool']); ?> to their wallet and mark the match completed.</p>

        <form method="POST" action="manage_tournament.php?id=<?= $tournament_id; ?>" onsubmit="return confirm('Confirm declaring this player as the winner and disbursing the prize?');" class="space-y-3">
            <input type="hidden" name="action" value="declare_winner">

            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Select Participant *</label>
                <select name="winner_user_id" required class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:outline-none">
                    <option value="">-- Choose from joined players --</option>
                    <?php if ($participants && $participants->num_rows > 0): ?>
                        <?php while ($p = $participants->fetch_assoc()): ?>
                            <option value="<?= $p['user_id']; ?>">
                                @<?= htmlspecialchars($p['username']); ?> (IGN: <?= htmlspecialchars($p['in_game_name'] ?? 'Player'); ?>)
                            </option>
                        <?php endwhile; ?>
                        <?php $participants->data_seek(0); // Reset pointer ?>
                    <?php endif; ?>
                </select>
            </div>

            <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold rounded-xl text-xs transition shadow-xs active:scale-95">
                Declare Winner & Disburse <?= format_inr($t['prize_pool']); ?>
            </button>
        </form>
    </div>
<?php else: ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-3xl p-4 shadow-sm mb-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-crown text-emerald-600 text-lg"></i>
            <div>
                <span class="text-xs font-black text-emerald-900 block">Match Completed & Prize Disbursed</span>
                <span class="text-[11px] text-emerald-700">Winner: @<?= htmlspecialchars($t['winner_name'] ?? 'Player'); ?></span>
            </div>
        </div>
        <span class="text-xs font-black font-mono text-emerald-700"><?= format_inr($t['prize_pool']); ?></span>
    </div>
<?php endif; ?>

<!-- Section 3: Registered Participants Table -->
<div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm mb-6">
    <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
        <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
            <i class="fa-solid fa-users text-yellow-500"></i> Joined Gamers (<?= $participants ? $participants->num_rows : 0; ?>)
        </h3>
    </div>

    <div class="divide-y divide-slate-100">
        <?php if ($participants && $participants->num_rows > 0): ?>
            <?php while ($p = $participants->fetch_assoc()): ?>
                <div class="py-2.5 flex items-center justify-between text-xs">
                    <div>
                        <span class="font-bold text-slate-900 block leading-tight">
                            @<?= htmlspecialchars($p['username']); ?>
                        </span>
                        <span class="text-[10px] text-slate-500 font-medium">
                            IGN: <strong class="text-slate-800"><?= htmlspecialchars($p['in_game_name'] ?? 'N/A'); ?></strong> &bull; <?= htmlspecialchars($p['phone'] ?? ''); ?>
                        </span>
                    </div>
                    <span class="text-[10px] text-slate-400 font-mono">
                        <?= date('d M, h:i A', strtotime($p['created_at'])); ?>
                    </span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-xs text-slate-400 text-center py-4">No gamers joined this tournament yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
