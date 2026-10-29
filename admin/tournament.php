<?php
require_once __DIR__ . '/common/header.php';

$msg = "";
$msg_type = "";

// 1. Handle New Tournament Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_tournament') {
    $title = sanitize($_POST['title'] ?? '');
    $game_name = sanitize($_POST['game_name'] ?? 'BGMI');
    $map = sanitize($_POST['map'] ?? 'Erangel');
    $match_type = sanitize($_POST['match_type'] ?? 'Squad');
    $entry_fee = (float)($_POST['entry_fee'] ?? 0);
    $prize_pool = (float)($_POST['prize_pool'] ?? 0);
    $per_kill = (float)($_POST['per_kill'] ?? 0);
    $total_slots = (int)($_POST['total_slots'] ?? 100);
    $commission = (int)($_POST['commission_percent'] ?? 20);
    $match_time_raw = $_POST['match_time'] ?? '';

    if (empty($title) || empty($game_name) || empty($match_time_raw)) {
        $msg = "Please fill in tournament title, game, and match schedule time.";
        $msg_type = "error";
    } else {
        // Format ISO datetime-local to MySQL DATETIME format (YYYY-MM-DD HH:MM:SS)
        $match_time = date('Y-m-d H:i:s', strtotime($match_time_raw));

        $stmt = $conn->prepare("INSERT INTO tournaments (title, game, game_name, map, match_type, entry_fee, per_kill, prize_pool, match_time, total_slots, joined_slots, status, commission_percent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'upcoming', ?)");
        if ($stmt) {
            $stmt->bind_param("sssssdddsii", $title, $game_name, $game_name, $map, $match_type, $entry_fee, $per_kill, $prize_pool, $match_time, $total_slots, $commission);
            if ($stmt->execute()) {
                $msg = "🎉 Tournament '" . htmlspecialchars($title) . "' created successfully!";
                $msg_type = "success";
            } else {
                $msg = "Failed to create tournament: " . $stmt->error;
                $msg_type = "error";
            }
            $stmt->close();
        } else {
            $msg = "Database statement prepare error: " . $conn->error;
            $msg_type = "error";
        }
    }
}

// 2. Handle Tournament Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_tournament') {
    $tid = (int)($_POST['tournament_id'] ?? 0);
    if ($tid > 0) {
        // Remove participants first
        @$conn->query("DELETE FROM participants WHERE tournament_id = $tid");
        $del = $conn->prepare("DELETE FROM tournaments WHERE id = ?");
        if ($del) {
            $del->bind_param("i", $tid);
            if ($del->execute()) {
                $msg = "Tournament #$tid deleted successfully.";
                $msg_type = "success";
            } else {
                $msg = "Delete failed: " . $del->error;
                $msg_type = "error";
            }
            $del->close();
        }
    }
}

// Fetch all Tournaments with quick enrolled count
$all_tourneys = $conn->query("
    SELECT t.*, 
    COALESCE(t.game_name, t.game, 'BGMI') as display_game,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
    FROM tournaments t 
    ORDER BY t.id DESC
    LIMIT 100
");
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
            <label class="block text-[11px] font-medium text-slate-300 mb-1">Tournament Title *</label>
            <input type="text" name="title" required placeholder="e.g. BGMI Daily Grand Final Squad" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Game *</label>
                <select name="game_name" required class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-2.5 py-2 text-xs text-white focus:outline-none">
                    <option value="BGMI">BGMI (Battlegrounds Mobile)</option>
                    <option value="Free Fire Max">Free Fire Max</option>
                    <option value="Call of Duty: Mobile">Call of Duty Mobile</option>
                    <option value="Valorant Mobile">Valorant Mobile</option>
                    <option value="Other Esports">Other Esports</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Match Type</label>
                <select name="match_type" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-2.5 py-2 text-xs text-white focus:outline-none">
                    <option value="Squad">Squad (4P)</option>
                    <option value="Solo">Solo (1P)</option>
                    <option value="Duo">Duo (2P)</option>
                    <option value="TDM">TDM 4v4</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Map</label>
                <input type="text" name="map" value="Erangel" placeholder="e.g. Erangel, Bermuda" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Total Player Slots</label>
                <input type="number" name="total_slots" value="100" min="2" max="100" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
        </div>

        <div class="grid grid-cols-3 gap-2">
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Entry Fee (₹)</label>
                <input type="number" step="0.5" min="0" name="entry_fee" value="20" required class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Prize Pool (₹)</label>
                <input type="number" step="1" min="0" name="prize_pool" value="500" required class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Per Kill (₹)</label>
                <input type="number" step="1" min="0" name="per_kill" value="15" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Match Schedule Time *</label>
                <input type="datetime-local" name="match_time" required class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Commission (%)</label>
                <input type="number" name="commission_percent" value="20" min="0" max="100" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
        </div>

        <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-violet-600/30">
            Publish Tournament Now
        </button>
    </form>
</div>

<!-- List of Tournaments -->
<div class="space-y-3">
    <div class="flex items-center justify-between px-1">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tournament Roster</h3>
        <span class="text-[10px] text-slate-500 font-mono"><?= ($all_tourneys) ? $all_tourneys->num_rows : 0; ?> Tourneys</span>
    </div>

    <?php if ($all_tourneys && $all_tourneys->num_rows > 0): ?>
        <?php while ($t = $all_tourneys->fetch_assoc()): ?>
            <?php 
                $status_clean = strtolower($t['status'] ?? 'upcoming');
                $is_completed = ($status_clean === 'completed');
                $is_live = ($status_clean === 'live' || $status_clean === 'ongoing');
            ?>
            <div class="bg-slate-800/80 border border-slate-700 rounded-xl p-3.5 shadow-md space-y-2">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-violet-600/20 text-violet-300 border border-violet-500/30">
                                <?= htmlspecialchars($t['display_game']); ?>
                            </span>
                            <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-slate-700 text-slate-300">
                                <?= htmlspecialchars($t['match_type'] ?? 'Squad'); ?> &bull; <?= htmlspecialchars($t['map'] ?? 'Map'); ?>
                            </span>
                        </div>
                        <h4 class="text-xs font-bold text-white mt-1"><?= htmlspecialchars($t['title']); ?></h4>
                        <span class="text-[10px] text-slate-400 block mt-0.5">
                            <i class="fa-regular fa-clock text-[9px] mr-1"></i><?= date("d M, h:i A", strtotime($t['match_time'])); ?>
                        </span>
                    </div>

                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full capitalize <?= $is_completed ? 'bg-slate-700 text-slate-400' : ($is_live ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30') ?>">
                        <?= htmlspecialchars($t['status']); ?>
                    </span>
                </div>

                <div class="grid grid-cols-4 gap-1 bg-slate-900/60 p-2 rounded-lg text-[10px] text-center">
                    <div>
                        <span class="text-slate-400 block text-[9px]">Prize</span>
                        <span class="font-bold text-amber-400"><?= format_inr($t['prize_pool']); ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[9px]">Entry</span>
                        <span class="font-bold text-slate-200"><?= format_inr($t['entry_fee']); ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[9px]">Per Kill</span>
                        <span class="font-bold text-emerald-400"><?= format_inr($t['per_kill'] ?? 0); ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[9px]">Joined</span>
                        <span class="font-bold text-violet-400"><?= $t['enrolled_count']; ?>/<?= $t['total_slots'] ?? 100; ?></span>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <a href="manage_tournament.php?id=<?= $t['id']; ?>" class="flex-1 py-1.5 bg-violet-600/30 hover:bg-violet-600/40 border border-violet-500/40 text-violet-300 text-center rounded-lg text-xs font-bold transition">
                        <i class="fa-solid fa-gear mr-1"></i> Room ID & Winner
                    </a>
                    <form method="POST" action="tournament.php" onsubmit="return confirm('Delete this tournament permanently?');">
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
        <p class="text-xs text-slate-500 text-center py-6 bg-slate-800/40 rounded-xl">No tournaments created yet. Use the form above to publish your first match!</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
