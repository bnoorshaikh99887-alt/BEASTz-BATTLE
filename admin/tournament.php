<?php
require_once __DIR__ . '/common/header.php';

$msg = "";
$msg_type = "";

// 1. Handle New Tournament Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_tournament') {
    $title = sanitize($_POST['title'] ?? '');
    $game_name = sanitize($_POST['game_name'] ?? 'Free Fire');
    $map = sanitize($_POST['map'] ?? 'BERMUDA');
    $match_type = sanitize($_POST['match_type'] ?? 'SOLO BR');
    $entry_fee = (float)($_POST['entry_fee'] ?? 0);
    $prize_pool = (float)($_POST['prize_pool'] ?? 0);
    $per_kill = (float)($_POST['per_kill'] ?? 0);
    $total_slots = (int)($_POST['total_slots'] ?? 32);
    $commission = (int)($_POST['commission_percent'] ?? 15);
    $match_time_raw = $_POST['match_time'] ?? '';

    if (empty($title) || empty($game_name) || empty($match_time_raw)) {
        $msg = "Please fill in tournament title, game, and match schedule time.";
        $msg_type = "error";
    } else {
        $match_time = date('Y-m-d H:i:s', strtotime($match_time_raw));
        $stmt = $conn->prepare("INSERT INTO tournaments (title, game, game_name, map, match_type, entry_fee, per_kill, prize_pool, match_time, total_slots, joined_slots, status, commission_percent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'upcoming', ?)");
        if ($stmt) {
            $stmt->bind_param("sssssdddsii", $title, $game_name, $game_name, $map, $match_type, $entry_fee, $per_kill, $prize_pool, $match_time, $total_slots, $commission);
            if ($stmt->execute()) {
                $msg = "Tournament '" . htmlspecialchars($title) . "' created successfully!";
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

// Fetch all Tournaments
$all_tourneys = $conn->query("
    SELECT t.*, 
    COALESCE(t.game_name, t.game, 'Free Fire') as display_game,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
    FROM tournaments t 
    ORDER BY t.id DESC
    LIMIT 100");
?>

<!-- Alerts -->
<?php if (!empty($msg)): ?>
    <div class="mb-4 p-3 rounded-xl text-xs font-semibold flex items-center gap-2 <?= ($msg_type === 'success') ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-500' : 'fa-circle-exclamation text-rose-500' ?>"></i>
        <span><?= $msg; ?></span>
    </div>
<?php endif; ?>

<!-- Create Tournament Form Card (White + Gray + Yellow Theme) -->
<div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm mb-6">
    <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
        <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
            <i class="fa-solid fa-circle-plus text-yellow-500 text-sm"></i> Create New Tournament
        </h3>
        <span class="text-[10px] text-slate-400 font-bold uppercase">Free Fire & BGMI</span>
    </div>

    <form method="POST" action="tournament.php" class="space-y-3">
        <input type="hidden" name="action" value="create_tournament">

        <div>
            <label class="block text-[11px] font-bold text-slate-700 mb-1">Tournament Title *</label>
            <input type="text" name="title" required placeholder="e.g. FREE FIRE - SOLO HUNTER" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:outline-none">
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Game *</label>
                <select name="game_name" required class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-medium focus:outline-none">
                    <option value="Free Fire">Free Fire</option>
                    <option value="Free Fire Max">Free Fire Max</option>
                    <option value="BGMI">BGMI</option>
                    <option value="Call of Duty">Call of Duty Mobile</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Match Category</label>
                <select name="match_type" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-medium focus:outline-none">
                    <option value="SOLO BR">SOLO BR</option>
                    <option value="DUO BR">DUO BR</option>
                    <option value="DUO PR KILL">DUO PR KILL</option>
                    <option value="SOLO PER KILL">SOLO PER KILL</option>
                    <option value="LONE WOLF">LONE WOLF</option>
                    <option value="CS CHALLENGERS">CS CHALLENGERS</option>
                    <option value="CLASH SQUAD">CLASH SQUAD</option>
                    <option value="CS HEADSHOT">CS HEADSHOT</option>
                    <option value="LOSS TO WIN">LOSS TO WIN</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Map</label>
                <input type="text" name="map" value="BERMUDA" required placeholder="BERMUDA / PURGATORY" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-slate-900 font-medium focus:outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Total Slots</label>
                <input type="number" name="total_slots" value="32" min="2" max="100" required class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>
        </div>

        <div class="grid grid-cols-3 gap-2">
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Entry Fee (₹)</label>
                <input type="number" step="0.5" name="entry_fee" value="8" required class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Per Kill (₹)</label>
                <input type="number" step="0.5" name="per_kill" value="0" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Prize Pool (₹)</label>
                <input type="number" step="1" name="prize_pool" value="225" required class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-mono font-black text-amber-600 focus:outline-none">
            </div>
        </div>

        <div>
            <label class="block text-[11px] font-bold text-slate-700 mb-1">Match Schedule Time *</label>
            <input type="datetime-local" name="match_time" required class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-slate-900 font-medium focus:outline-none">
        </div>

        <button type="submit" class="w-full py-3 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-bolt text-xs"></i>
            <span>Publish Tournament Match</span>
        </button>
    </form>
</div>

<!-- Tournaments List Section -->
<div class="space-y-3 mb-6">
    <div class="flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">All Matches (<?= $all_tourneys ? $all_tourneys->num_rows : 0; ?>)</h3>
    </div>

    <?php if ($all_tourneys && $all_tourneys->num_rows > 0): ?>
        <?php while ($t = $all_tourneys->fetch_assoc()): ?>
            <?php
                $status_clean = strtolower($t['status'] ?? 'upcoming');
                $is_completed = ($status_clean === 'completed');
                $is_live = ($status_clean === 'live' || $status_clean === 'ongoing');
                $slots = (int)($t['total_slots'] ?: 48);
                $enrolled = (int)$t['enrolled_count'];
            ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm hover:shadow transition space-y-2.5">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded uppercase <?= $is_live ? 'bg-emerald-100 text-emerald-800' : ($is_completed ? 'bg-slate-100 text-slate-600' : 'bg-yellow-100 text-yellow-800') ?>">
                                <?= htmlspecialchars($t['status']); ?>
                            </span>
                            <span class="text-[9px] text-slate-500 font-bold uppercase bg-slate-100 px-1.5 py-0.5 rounded">
                                <?= htmlspecialchars($t['match_type']); ?>
                            </span>
                        </div>
                        <h4 class="text-xs font-black text-slate-900 leading-tight">
                            <?= htmlspecialchars($t['title']); ?>
                        </h4>
                        <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                            <?= date('d M Y, h:i A', strtotime($t['match_time'])); ?>
                        </p>
                    </div>

                    <div class="text-right">
                        <span class="text-[10px] text-slate-400 block font-bold">Prize Pool</span>
                        <span class="text-xs font-black text-amber-500 font-mono"><?= format_inr($t['prize_pool']); ?></span>
                    </div>
                </div>

                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200/60 text-xs flex justify-between">
                    <div>
                        <span class="text-slate-400 text-[10px] block">Entry Fee</span>
                        <span class="font-extrabold text-slate-900"><?= format_inr($t['entry_fee']); ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Slots Joined</span>
                        <span class="font-bold text-slate-800"><?= $enrolled; ?> / <?= $slots; ?></span>
                    </div>
                    <div class="text-right">
                        <span class="text-slate-400 text-[10px] block">Credentials</span>
                        <span class="font-mono text-[11px] <?= !empty($t['room_id']) ? 'text-emerald-600 font-bold' : 'text-slate-400' ?>">
                            <?= !empty($t['room_id']) ? 'ID: ' . htmlspecialchars($t['room_id']) : 'Not Added'; ?>
                        </span>
                    </div>
                </div>

                <!-- Admin Action Buttons -->
                <div class="flex items-center gap-2 pt-1">
                    <a href="manage_tournament.php?id=<?= $t['id']; ?>" class="flex-1 py-1.5 bg-yellow-400 hover:bg-yellow-500 text-slate-950 text-center font-black rounded-lg text-xs shadow-xs transition">
                        <i class="fa-solid fa-gear text-[10px] mr-1"></i> Manage & Room ID
                    </a>

                    <form method="POST" action="tournament.php" onsubmit="return confirm('Are you sure you want to delete this tournament and remove its participants?');" class="shrink-0">
                        <input type="hidden" name="action" value="delete_tournament">
                        <input type="hidden" name="tournament_id" value="<?= $t['id']; ?>">
                        <button type="submit" class="p-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-xs border border-rose-200 transition" title="Delete Match">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="text-xs text-slate-400 text-center py-6">No tournaments found.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
