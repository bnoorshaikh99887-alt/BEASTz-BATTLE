<?php
require_once __DIR__ . '/common/header.php';

$msg = "";
$msg_type = "";

// 1. Handle New Tournament Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_tournament') {
    $title = sanitize($_POST['title']);
    $game_name = sanitize($_POST['game_name']);
    $entry_fee = (float)$_POST['entry_fee'];
    $prize_pool = (float)$_POST['prize_pool'];
    $match_time = $_POST['match_time'];
    $commission = (int)$_POST['commission_percent'];

    if (empty($title) || empty($game_name) || empty($match_time)) {
        $msg = "Please fill in all tournament parameters.";
        $msg_type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO tournaments (title, game_name, entry_fee, prize_pool, match_time, commission_percent, status) VALUES (?, ?, ?, ?, ?, ?, 'Upcoming')");
        $stmt->bind_param("ssddsi", $title, $game_name, $entry_fee, $prize_pool, $match_time, $commission);
        if ($stmt->execute()) {
            $msg = "Tournament created successfully!";
            $msg_type = "success";
        } else {
            $msg = "Failed to create tournament.";
            $msg_type = "error";
        }
        $stmt->close();
    }
}

// 2. Handle Tournament Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_tournament') {
    $tid = (int)$_POST['tournament_id'];
    $del = $conn->prepare("DELETE FROM tournaments WHERE id = ?");
    $del->bind_param("i", $tid);
    if ($del->execute()) {
        $msg = "Tournament deleted.";
        $msg_type = "success";
    }
    $del->close();
}

// Fetch all Tournaments
$all_tourneys = $conn->query("
    SELECT t.*, 
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
    FROM tournaments t 
    ORDER BY t.id DESC");
?>

<!-- Alerts -->
<?php if (!empty($msg)): ?>
    <div class="mb-4 p-3 rounded-xl text-xs font-semibold flex items-center gap-2 <?= ($msg_type === 'success') ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?>"></i>
        <span><?= $msg; ?></span>
    </div>
<?php endif; ?>

<!-- Create Tournament Form Accordion / Card -->
<div class="bg-slate-800/80 border border-slate-700 rounded-2xl p-4 shadow-lg mb-6">
    <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <i class="fa-solid fa-circle-plus text-violet-400"></i> Create New Tournament
    </h3>

    <form method="POST" action="tournament.php" class="space-y-3">
        <input type="hidden" name="action" value="create_tournament">

        <div>
            <label class="block text-[11px] font-medium text-slate-300 mb-1">Tournament Title</label>
            <input type="text" name="title" required placeholder="e.g. Free Fire Squad Grand Finals" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Game Name</label>
                <select name="game_name" required class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-2.5 py-2 text-xs text-white focus:outline-none">
                    <option value="Battlegrounds Mobile India">BGMI</option>
                    <option value="Free Fire Max">Free Fire Max</option>
                    <option value="Call of Duty: Mobile">Call of Duty</option>
                    <option value="Valorant Mobile">Valorant</option>
                    <option value="Other Esports">Other Game</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Commission (%)</label>
                <input type="number" name="commission_percent" value="20" min="0" max="100" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Entry Fee (₹)</label>
                <input type="number" step="0.5" min="0" name="entry_fee" value="50" required class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Prize Pool (₹)</label>
                <input type="number" step="1" min="0" name="prize_pool" value="1500" required class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
        </div>

        <div>
            <label class="block text-[11px] font-medium text-slate-300 mb-1">Match Date & Time</label>
            <input type="datetime-local" name="match_time" required class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
        </div>

        <button type="submit" class="w-full py-2.5 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-violet-600/30">
            Publish Tournament
        </button>
    </form>
</div>

<!-- List of Tournaments -->
<div class="space-y-3">
    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider px-1">Created Tournaments</h3>

    <?php if ($all_tourneys && $all_tourneys->num_rows > 0): ?>
        <?php while ($t = $all_tourneys->fetch_assoc()): ?>
            <div class="bg-slate-800/80 border border-slate-700 rounded-xl p-3 shadow-md space-y-2">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-700 text-slate-300">
                            <?= htmlspecialchars($t['game_name']); ?>
                        </span>
                        <h4 class="text-xs font-bold text-white mt-1"><?= htmlspecialchars($t['title']); ?></h4>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= ($t['status'] === 'Completed') ? 'bg-slate-700 text-slate-400' : (($t['status'] === 'Live') ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400') ?>">
                        <?= $t['status']; ?>
                    </span>
                </div>

                <div class="grid grid-cols-3 gap-1 bg-slate-900/60 p-2 rounded-lg text-[10px]">
                    <div>
                        <span class="text-slate-400 block">Prize</span>
                        <span class="font-bold text-amber-400"><?= format_inr($t['prize_pool']); ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block">Entry</span>
                        <span class="font-bold text-slate-200"><?= format_inr($t['entry_fee']); ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block">Joined</span>
                        <span class="font-bold text-violet-400"><?= $t['enrolled_count']; ?> players</span>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <a href="manage_tournament.php?id=<?= $t['id']; ?>" class="flex-1 py-1.5 bg-violet-600/30 hover:bg-violet-600/40 border border-violet-500/40 text-violet-300 text-center rounded-lg text-xs font-bold transition">
                        <i class="fa-solid fa-gear mr-1"></i> Manage Room & Winner
                    </a>

                    <form method="POST" action="tournament.php" onsubmit="return confirm('Delete this tournament?');">
                        <input type="hidden" name="action" value="delete_tournament">
                        <input type="hidden" name="tournament_id" value="<?= $t['id']; ?>">
                        <button type="submit" class="py-1.5 px-3 bg-rose-500/20 hover:bg-rose-500/30 text-rose-400 rounded-lg text-xs font-bold transition border border-rose-500/30">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="text-xs text-slate-500 text-center py-4">No tournaments created yet.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/common/bottom.php'; ?>