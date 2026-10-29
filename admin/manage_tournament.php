<?php
require_once __DIR__ . '/common/header.php';

$tournament_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = "";
$msg_type = "";

// 1. Handle Room Credentials Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_room') {
    $room_id = sanitize($_POST['room_id']);
    $room_password = sanitize($_POST['room_password']);
    $status = sanitize($_POST['status']);

    $u_stmt = $conn->prepare("UPDATE tournaments SET room_id = ?, room_password = ?, status = ? WHERE id = ?");
    $u_stmt->bind_param("sssi", $room_id, $room_password, $status, $tournament_id);
    if ($u_stmt->execute()) {
        $msg = "Room ID and Password updated successfully!";
        $msg_type = "success";
    }
    $u_stmt->close();
}

// 2. Handle Declare Winner & Distribute Prize
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'declare_winner') {
    $winner_id = (int)$_POST['winner_user_id'];

    // Fetch prize pool
    $tp_stmt = $conn->prepare("SELECT prize_pool, title, status FROM tournaments WHERE id = ?");
    $tp_stmt->bind_param("i", $tournament_id);
    $tp_stmt->execute();
    $tourney_row = $tp_stmt->get_result()->fetch_assoc();
    $tp_stmt->close();

    if ($tourney_row['status'] === 'Completed') {
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

            // 2. Add credit transaction
            $t_desc = "Prize Winner for tournament: " . $title;
            $t_tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
            $t_tx->bind_param("ids", $winner_id, $prize_amount, $t_desc);
            $t_tx->execute();

            // 3. Mark tournament as Completed
            $mark = $conn->prepare("UPDATE tournaments SET status = 'Completed', winner_user_id = ? WHERE id = ?");
            $mark->bind_param("ii", $winner_id, $tournament_id);
            $mark->execute();

            $conn->commit();
            $msg = "Prize of " . format_inr($prize_amount) . " successfully credited to winner's wallet!";
            $msg_type = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Failed to declare winner: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

// Fetch Tournament Details
$stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$tourney = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tourney) {
    echo "<div class='text-center p-6 text-xs text-rose-400'>Tournament not found. <a href='tournament.php' class='underline'>Go Back</a></div>";
    require_once __DIR__ . '/common/bottom.php';
    exit;
}

// Fetch Participants
$part_stmt = $conn->prepare("
    SELECT u.id, u.username, u.email, p.joined_at 
    FROM participants p
    INNER JOIN users u ON p.user_id = u.id
    WHERE p.tournament_id = ?
    ORDER BY p.joined_at ASC");
$part_stmt->bind_param("i", $tournament_id);
$part_stmt->execute();
$participants = $part_stmt->get_result();
$part_array = [];
while ($p = $participants->fetch_assoc()) {
    $part_array[] = $p;
}
?>

<!-- Back Link -->
<div class="mb-3">
    <a href="tournament.php" class="text-xs text-slate-400 hover:text-white flex items-center gap-1">
        <i class="fa-solid fa-arrow-left"></i> All Tournaments
    </a>
</div>

<!-- Header Info Card -->
<div class="bg-slate-800 border border-slate-700 rounded-2xl p-4 shadow-lg mb-4">
    <div class="flex items-center justify-between mb-1">
        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-violet-600/30 text-violet-300">
            <?= htmlspecialchars($tourney['game_name']); ?>
        </span>
        <span class="text-[11px] font-bold text-amber-400 font-mono">
            Pool: <?= format_inr($tourney['prize_pool']); ?>
        </span>
    </div>
    <h2 class="text-sm font-bold text-white"><?= htmlspecialchars($tourney['title']); ?></h2>
    <p class="text-[10px] text-slate-400 mt-1">Status: <span class="font-bold text-emerald-400"><?= $tourney['status']; ?></span></p>
</div>

<!-- Alerts -->
<?php if (!empty($msg)): ?>
    <div class="mb-4 p-3 rounded-xl text-xs font-semibold flex items-center gap-2 <?= ($msg_type === 'success') ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?>"></i>
        <span><?= $msg; ?></span>
    </div>
<?php endif; ?>

<!-- Form 1: Room ID and Password -->
<div class="bg-slate-800/80 border border-slate-700 rounded-2xl p-4 shadow-lg mb-4">
    <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <i class="fa-solid fa-key text-violet-400"></i> Set Match Room Credentials
    </h3>

    <form method="POST" action="manage_tournament.php?id=<?= $tournament_id; ?>" class="space-y-3">
        <input type="hidden" name="action" value="update_room">

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Room ID</label>
                <input type="text" name="room_id" value="<?= htmlspecialchars($tourney['room_id'] ?? ''); ?>" placeholder="e.g. 583921" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Room Password</label>
                <input type="text" name="room_password" value="<?= htmlspecialchars($tourney['room_password'] ?? ''); ?>" placeholder="e.g. 1234" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
        </div>

        <div>
            <label class="block text-[11px] font-medium text-slate-300 mb-1">Tournament Status</label>
            <select name="status" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
                <option value="Upcoming" <?= ($tourney['status'] === 'Upcoming') ? 'selected' : '' ?>>Upcoming</option>
                <option value="Live" <?= ($tourney['status'] === 'Live') ? 'selected' : '' ?>>Live (Room credentials visible)</option>
                <option value="Completed" <?= ($tourney['status'] === 'Completed') ? 'selected' : '' ?>>Completed</option>
            </select>
        </div>

        <button type="submit" class="w-full py-2 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl text-xs transition">
            Save Room Information
        </button>
    </form>
</div>

<!-- Form 2: Declare Winner & Distribute Prize -->
<?php if ($tourney['status'] !== 'Completed'): ?>
    <div class="bg-gradient-to-r from-amber-950/40 via-slate-800 to-slate-800 border border-amber-500/30 rounded-2xl p-4 shadow-lg mb-4">
        <h3 class="text-xs font-bold text-amber-300 uppercase tracking-wider mb-2 flex items-center gap-1.5">
            <i class="fa-solid fa-crown text-amber-400"></i> Declare Winner & Distribute Prize
        </h3>
        <p class="text-[10px] text-slate-400 mb-3">Selecting a winner will automatically disburse <?= format_inr($tourney['prize_pool']); ?> to their wallet and close the tournament.</p>

        <?php if (!empty($part_array)): ?>
            <form method="POST" action="manage_tournament.php?id=<?= $tournament_id; ?>" onsubmit="return confirm('Distribute prize and declare winner? This action cannot be reversed.');" class="space-y-3">
                <input type="hidden" name="action" value="declare_winner">

                <div>
                    <label class="block text-[11px] font-medium text-slate-300 mb-1">Select Winning Participant</label>
                    <select name="winner_user_id" required class="w-full bg-slate-900 border border-slate-700 focus:border-amber-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
                        <option value="">-- Choose Winner --</option>
                        <?php foreach ($part_array as $p): ?>
                            <option value="<?= $p['id']; ?>"><?= htmlspecialchars($p['username']); ?> (<?= htmlspecialchars($p['email']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black rounded-xl text-xs shadow-lg transition">
                    Declare Winner & Distribute Prize
                </button>
            </form>
        <?php else: ?>
            <p class="text-xs text-slate-400 italic">No participants have enrolled in this match yet.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="bg-emerald-950/40 border border-emerald-500/30 rounded-2xl p-4 text-center mb-4">
        <i class="fa-solid fa-circle-check text-2xl text-emerald-400 mb-1"></i>
        <h4 class="text-xs font-bold text-white">Winner Declared & Prize Disbursed</h4>
        <p class="text-[10px] text-slate-400 mt-1">This tournament is archived under Completed status.</p>
    </div>
<?php endif; ?>

<!-- Participants List -->
<div class="bg-slate-800/80 border border-slate-700 rounded-2xl p-4 shadow-lg">
    <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-3 flex items-center justify-between">
        <span><i class="fa-solid fa-users text-violet-400 mr-1"></i> Joined Players</span>
        <span class="text-violet-400 font-mono"><?= count($part_array); ?></span>
    </h3>

    <?php if (!empty($part_array)): ?>
        <div class="space-y-2">
            <?php foreach ($part_array as $index => $player): ?>
                <div class="bg-slate-900/60 p-2.5 rounded-xl border border-slate-800 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2.5">
                        <span class="text-[10px] text-slate-500 font-mono w-4">#<?= $index + 1; ?></span>
                        <div>
                            <span class="font-bold text-white block"><?= htmlspecialchars($player['username']); ?></span>
                            <span class="text-[10px] text-slate-400"><?= htmlspecialchars($player['email']); ?></span>
                        </div>
                    </div>
                    <span class="text-[10px] text-slate-500"><?= date("d M, h:i A", strtotime($player['joined_at'])); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-xs text-slate-500 text-center py-3">No players have joined yet.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/common/bottom.php'; ?>