<?php
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
        $host_admin_id = (int)($_SESSION['admin_id'] ?? 1);
        $is_private = (!empty($_POST['is_private']) && (int)$_POST['is_private'] === 1) ? 1 : 0;
        $access_code = $is_private ? ('BTZ-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4))) : null;
        $stmt = $conn->prepare("INSERT INTO tournaments (title, game, game_name, map, match_type, entry_fee, per_kill, prize_pool, match_time, total_slots, joined_slots, status, commission_percent, hosted_by_admin_id, is_private, access_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'upcoming', ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssssdddsiiiis", $title, $game_name, $game_name, $map, $match_type, $entry_fee, $per_kill, $prize_pool, $match_time, $total_slots, $commission, $host_admin_id, $is_private, $access_code);
            if ($stmt->execute()) {
                $new_tid = (int)$stmt->insert_id;
                if (file_exists(__DIR__ . "/../../common/onesignal_helper.php")) {
                    require_once __DIR__ . "/../../common/onesignal_helper.php";
                } elseif (file_exists(__DIR__ . "/../common/onesignal_helper.php")) {
                    require_once __DIR__ . "/../common/onesignal_helper.php";
                }
                $list_title = "⚔️ New Tournament Listed: " . $title;
                $list_msg = "New " . $game_name . " (" . $match_type . ") match is live for registration! Entry: " . format_inr($entry_fee) . " | Prize: " . format_inr($prize_pool) . ". Join now!";
                if (function_exists("send_onesignal_push")) {
                    send_onesignal_push($list_title, $list_msg, "/?tab=home", [], [], ["badge_type" => "NEW_TOURNAMENT", "tournament_id" => $new_tid], true);
                }
                @$conn->query("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES ('" . $conn->real_escape_string($list_title) . "', '" . $conn->real_escape_string($list_msg) . "', '/?tab=home', 'NEW_TOURNAMENT')");
                $msg = "Tournament '" . htmlspecialchars($title) . "' created & listing push alert broadcasted to all gamers!";
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

// 2. Handle Instant Tournament Deletion with Atomic Entry Fee Refunds
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_tournament') {
    $tid = (int)($_POST['tournament_id'] ?? 0);
    if ($tid > 0) {
        $conn->begin_transaction();
        try {
            $t_stmt = $conn->prepare("SELECT id, title, entry_fee FROM tournaments WHERE id = ? FOR UPDATE");
            $t_stmt->bind_param("i", $tid);
            $t_stmt->execute();
            $tourney = $t_stmt->get_result()->fetch_assoc();
            $t_stmt->close();

            if ($tourney) {
                $fee = (float)($tourney['entry_fee'] ?? 0.00);
                if ($fee > 0) {
                    $p_stmt = $conn->prepare("SELECT user_id FROM participants WHERE tournament_id = ?");
                    $p_stmt->bind_param("i", $tid);
                    $p_stmt->execute();
                    $p_res = $p_stmt->get_result();
                    $refund_users = [];
                    while ($pr = $p_res->fetch_assoc()) {
                        $uid = (int)$pr['user_id'];
                        if ($uid > 0) $refund_users[] = $uid;
                    }
                    $p_stmt->close();
                    $refund_users = array_values(array_unique($refund_users));

                    foreach ($refund_users as $p_uid) {
                        $upd_u = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                        $upd_u->bind_param("di", $fee, $p_uid);
                        $upd_u->execute();
                        $upd_u->close();

                        $tx_desc = "Refund: Match #" . $tid . " (" . $tourney['title'] . ") Cancelled by Admin";
                        $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
                        $tx->bind_param("ids", $p_uid, $fee, $tx_desc);
                        $tx->execute();
                        $tx->close();
                    }
                }
            }

            @$conn->query("DELETE FROM anti_cheat_logs WHERE tournament_id = {$tid}");
            @$conn->query("DELETE FROM participants WHERE tournament_id = {$tid}");
            @$conn->query("DELETE FROM user_tournaments WHERE tournament_id = {$tid}");
            $conn->query("DELETE FROM tournaments WHERE id = {$tid}");
            $conn->commit();

            $msg = "Tournament #$tid deleted successfully and participant entry fees refunded to wallets!";
            $msg_type = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Delete failed: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

// Current active tab (default 'active' vs 'completed')
$current_tab = $_GET['tab'] ?? 'active';

// Fetch Active Tournaments (Upcoming & Live)
$active_tourneys = $conn->query("
    SELECT t.*, 
    COALESCE(t.game_name, t.game, 'Free Fire') as display_game,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
    FROM tournaments t 
    WHERE LOWER(t.status) != 'completed'
    ORDER BY t.match_time ASC, t.id DESC
    LIMIT 60
");

// Fetch Completed Tournaments
$completed_tourneys = $conn->query("
    SELECT t.*, 
    COALESCE(t.game_name, t.game, 'Free Fire') as display_game,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count,
    u.username as winner_name
    FROM tournaments t 
    LEFT JOIN users u ON t.winner_user_id = u.id
    WHERE LOWER(t.status) = 'completed'
    ORDER BY t.id DESC
    LIMIT 60
");

$active_cnt = $active_tourneys ? $active_tourneys->num_rows : 0;
$completed_cnt = $completed_tourneys ? $completed_tourneys->num_rows : 0;
?>

<!-- Alerts -->
<?php if (!empty($msg)): ?>
    <div class="mb-4 p-3 rounded-xl text-xs font-semibold flex items-center gap-2 <?= ($msg_type === 'success') ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-500' : 'fa-circle-exclamation text-rose-500' ?>"></i>
        <span><?= $msg; ?></span>
    </div>
<?php endif; ?>

<!-- Create Tournament Form Card -->
<div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs mb-6">
    <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
        <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
            <i class="fa-solid fa-circle-plus text-blue-400 text-sm"></i> Create New Tournament
        </h3>
        <span class="text-[10px] text-slate-400 font-bold uppercase">Free Fire & BGMI</span>
    </div>

    <form method="POST" action="index.php?tab=tournaments" class="space-y-3">
        <input type="hidden" name="action" value="create_tournament">

        <div>
            <label class="block text-[11px] font-bold text-slate-700 mb-1">Tournament Title *</label>
            <input type="text" name="title" required placeholder="e.g. FREE FIRE - SOLO HUNTER" class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:outline-none">
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Game *</label>
                <select name="game_name" required class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-medium focus:outline-none">
                    <option value="Free Fire">Free Fire</option>
                    <option value="Free Fire Max">Free Fire Max</option>
                    <option value="BGMI">BGMI</option>
                    <option value="Call of Duty">Call of Duty Mobile</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Match Category</label>
                <select name="match_type" id="adminMatchType" onchange="adminUpdateSlots(this.value)" class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-medium focus:outline-none">
                    <option value="SOLO BR">SOLO BR (48 Slots)</option>
                    <option value="DUO BR">DUO BR (24 Teams)</option>
                    <option value="SQUAD BR">SQUAD BR (12 Squads)</option>
                    <option value="CS 1VS1">CS 1VS1 (2 Slots)</option>
                    <option value="CS 2VS2">CS 2VS2 (2 Teams)</option>
                    <option value="CS 4VS4">CS 4VS4 (2 Squads)</option>
                    <option value="LONE WOLF 1V1">LONE WOLF 1V1 (2 Slots)</option>
                    <option value="LONE WOLF 2V2">LONE WOLF 2V2 (2 Teams)</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Map</label>
                <select name="map" class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-medium focus:outline-none">
                    <option value="BERMUDA">BERMUDA</option>
                    <option value="PURGATORY">PURGATORY</option>
                    <option value="KALAHARI">KALAHARI</option>
                    <option value="NEXTERRA">NEXTERRA</option>
                    <option value="ERANGEL">ERANGEL (BGMI)</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Total Slots</label>
                <input type="number" name="total_slots" id="adminTotalSlots" value="48" required class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Visibility / Access</label>
                <select name="is_private" class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-medium focus:outline-none">
                    <option value="0">🌐 Public (Visible on Feed)</option>
                    <option value="1">🔒 Private (Code Required)</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-2">
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Entry Fee (₹)</label>
                <input type="number" step="0.5" name="entry_fee" value="10" required class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Per Kill (₹)</label>
                <input type="number" step="0.5" name="per_kill" value="0" class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Prize Pool (₹)</label>
                <input type="number" step="1" name="prize_pool" value="225" required class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-mono font-black text-amber-600 focus:outline-none">
            </div>
        </div>

        <div>
            <label class="block text-[11px] font-bold text-slate-700 mb-1">Match Schedule Time *</label>
            <input type="datetime-local" name="match_time" required class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 rounded-xl px-3 py-2 text-xs text-slate-900 font-medium focus:outline-none">
        </div>

        <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-1.5 cursor-pointer">
            <i class="fa-solid fa-bolt text-xs"></i>
            <span>Publish Tournament Match</span>
        </button>
    </form>
</div>

<!-- ============================================================= -->
<!-- TOURNAMENTS SECTION WITH SEPARATE ACTIVE & COMPLETED TABS -->
<!-- ============================================================= -->
<div class="space-y-3 mb-8">
    <div class="flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Tournament Management</h3>
    </div>

    <!-- Navigation Tabs (Active vs Completed Section) -->
    <div class="grid grid-cols-2 gap-2 p-1 bg-slate-200/80 dark:bg-slate-800 rounded-2xl text-xs font-bold">
        <button type="button" onclick="switchTourneySubTab('active', this)" id="adminBtnTourneyActive" class="tourney-subtab-btn py-2 text-center rounded-xl transition bg-blue-600 text-white font-black shadow-xs cursor-pointer">
            <i class="fa-solid fa-gamepad mr-1"></i> Active & Live (<?= $active_cnt; ?>)
        </button>
        <button type="button" onclick="switchTourneySubTab('completed', this)" id="adminBtnTourneyCompleted" class="tourney-subtab-btn py-2 text-center rounded-xl transition text-slate-600 dark:text-slate-400 hover:text-slate-900 font-bold cursor-pointer">
            <i class="fa-solid fa-clock-rotate-left mr-1"></i> Completed Matches (<?= $completed_cnt; ?>)
        </button>
    </div>

    <!-- CONTAINER 1: ACTIVE & LIVE TOURNAMENTS -->
    <div id="adminTourneysActive" class="admin-tourney-pane space-y-3">
        <?php if ($active_tourneys && $active_tourneys->num_rows > 0): ?>
            <?php while ($t = $active_tourneys->fetch_assoc()): ?>
                <?php
                    $status_clean = strtolower($t['status'] ?? 'upcoming');
                    $is_live = ($status_clean === 'live' || $status_clean === 'ongoing');
                    $slots = (int)($t['total_slots'] ?: 48);
                    $enrolled = (int)$t['enrolled_count'];
                ?>
                <div class="bg-white border <?= $is_live ? 'border-emerald-400 ring-2 ring-emerald-400/20' : 'border-slate-200' ?> rounded-2xl p-4 shadow-sm hover:shadow transition space-y-2.5">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="text-[9px] font-bold px-2 py-0.5 rounded uppercase <?= $is_live ? 'bg-emerald-100 text-emerald-800 font-black' : 'bg-blue-950/60 text-sky-200 font-bold' ?>">
                                    <?= htmlspecialchars($t['status']); ?>
                                </span>
                                <span class="text-[9px] text-slate-500 font-bold uppercase bg-slate-100 px-1.5 py-0.5 rounded">
                                    <?= htmlspecialchars($t['match_type']); ?>
                                </span>
                            </div>
                            <h4 class="text-xs font-black text-slate-900 leading-tight">
                                <?= htmlspecialchars($t['title']); ?>
                            </h4>
                            <?php if (!empty($t['is_private'])): ?>
                                <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                    <span class="text-[9px] font-black px-1.5 py-0.2 rounded uppercase bg-purple-100 text-purple-700 border border-purple-200">
                                        🔒 PRIVATE
                                    </span>
                                    <span class="text-[10px] font-mono font-bold text-blue-600">
                                        Code: <?= htmlspecialchars($t['access_code'] ?? ''); ?>
                                    </span>
                                    <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($t['access_code'] ?? ''); ?>'); alert('Copied code: <?= htmlspecialchars($t['access_code'] ?? ''); ?>');" class="px-1.5 py-0.2 rounded bg-blue-600 text-white text-[9px] font-bold">
                                        Copy
                                    </button>
                                </div>
                            <?php endif; ?>
                            <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                                <?= date('d M Y, h:i A', strtotime($t['match_time'])); ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] text-slate-400 block font-bold">Prize Pool</span>
                            <span class="text-xs font-black text-blue-400 font-mono"><?= format_compact_inr($t['prize_pool']); ?></span>
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
                        <a href="manage_tournament.php?id=<?= $t['id']; ?>" class="flex-1 py-2 bg-blue-600 hover:bg-blue-500 text-white text-center font-black rounded-xl text-xs shadow-xs transition">
                            <i class="fa-solid fa-gear text-[10px] mr-1"></i> Manage & Results
                        </a>
                        <form method="POST" action="index.php?tab=tournaments" onsubmit="return confirm('Are you sure you want to delete this tournament and remove its participants?');" class="shrink-0">
                            <input type="hidden" name="action" value="delete_tournament">
                            <input type="hidden" name="tournament_id" value="<?= $t['id']; ?>">
                            <button type="submit" class="p-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs border border-rose-200 transition cursor-pointer" title="Delete Match">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="bg-white dark:bg-[#131b2e] rounded-2xl border border-slate-200 dark:border-slate-800 p-8 text-center text-slate-400 text-xs">
                <i class="fa-solid fa-gamepad text-2xl text-slate-300 dark:text-slate-600 mb-2 block"></i>
                No active or live tournaments right now. Create one above!
            </div>
        <?php endif; ?>
    </div>

    <!-- CONTAINER 2: COMPLETED TOURNAMENTS -->
    <div id="adminTourneysCompleted" class="admin-tourney-pane hidden space-y-3">
        <?php if ($completed_tourneys && $completed_tourneys->num_rows > 0): ?>
            <?php while ($ct = $completed_tourneys->fetch_assoc()): ?>
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs space-y-2.5">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="text-[9px] font-black px-2 py-0.5 rounded uppercase bg-slate-100 text-slate-700 border border-slate-200">
                                    COMPLETED
                                </span>
                                <span class="text-[9px] text-slate-500 font-bold uppercase bg-slate-100 px-1.5 py-0.5 rounded">
                                    <?= htmlspecialchars($ct['match_type']); ?>
                                </span>
                            </div>
                            <h4 class="text-xs font-black text-slate-900 leading-tight">
                                <?= htmlspecialchars($ct['title']); ?>
                            </h4>
                            <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                                <?= date('d M Y, h:i A', strtotime($ct['match_time'])); ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] text-slate-400 block font-bold">Prize Distributed</span>
                            <span class="text-xs font-black text-blue-400 font-mono"><?= format_compact_inr($ct['prize_pool']); ?></span>
                        </div>
                    </div>

                    <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200/60 text-xs flex justify-between items-center">
                        <div>
                            <span class="text-slate-400 text-[10px] block">Participants</span>
                            <span class="font-bold text-slate-800"><?= $ct['enrolled_count']; ?> Players</span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block">Top Winner</span>
                            <span class="font-bold text-slate-900">
                                <?= !empty($ct['winner_name']) ? '@' . htmlspecialchars($ct['winner_name']) : 'Results Paid'; ?>
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="text-slate-400 text-[10px] block">Status</span>
                            <span class="text-emerald-600 font-bold text-[11px]">Prize Disbursed</span>
                        </div>
                    </div>

                    <!-- Manage / Anti-Cheat Rollback Button -->
                    <div class="flex items-center gap-2 pt-1">
                        <a href="manage_tournament.php?id=<?= $ct['id']; ?>&anti_cheat=1" class="flex-1 py-2 bg-slate-100 hover:bg-blue-950/40 text-slate-800 hover:text-slate-950 text-center font-bold border border-slate-200 rounded-xl text-xs transition">
                            <i class="fa-solid fa-shield-halved text-rose-500 mr-1"></i> View Results & Anti-Cheat
                        </a>
                        <form method="POST" action="index.php?tab=tournaments" onsubmit="return confirm('Delete completed tournament archive?');" class="shrink-0">
                            <input type="hidden" name="action" value="delete_tournament">
                            <input type="hidden" name="tournament_id" value="<?= $ct['id']; ?>">
                            <button type="submit" class="p-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs border border-rose-200 transition cursor-pointer" title="Delete Archive">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="bg-white dark:bg-[#131b2e] rounded-2xl border border-slate-200 dark:border-slate-800 p-8 text-center text-slate-400 text-xs">
                <i class="fa-solid fa-trophy text-2xl text-slate-300 dark:text-slate-600 mb-2 block"></i>
                No completed matches found in archive.
            </div>
        <?php endif; ?>
    </div>

    <script>
    function switchTourneySubTab(tab, btn) {
        document.querySelectorAll(".tourney-subtab-btn").forEach(function(b) {
            b.classList.remove("bg-blue-600", "text-slate-950", "font-black", "shadow-xs");
            b.classList.add("text-slate-600", "dark:text-slate-400", "font-bold");
        });
        if (btn) {
            btn.classList.remove("text-slate-600", "dark:text-slate-400", "font-bold");
            btn.classList.add("bg-blue-600", "text-slate-950", "font-black", "shadow-xs");
        }
        document.querySelectorAll(".admin-tourney-pane").forEach(function(p) {
            p.classList.add("hidden");
        });
        if (tab === "active") {
            var el = document.getElementById("adminTourneysActive");
            if (el) el.classList.remove("hidden");
        } else {
            var el = document.getElementById("adminTourneysCompleted");
            if (el) el.classList.remove("hidden");
        }
    }
    </script>
</div>

<script>
function adminUpdateSlots(mode) {
    const input = document.getElementById("adminTotalSlots");
    if (!input) return;
    const m = (mode || "").toUpperCase();
    if (m === "SOLO BR") {
        input.value = 48;
    } else if (m === "DUO BR") {
        input.value = 24;
    } else if (m === "SQUAD BR") {
        input.value = 12;
    } else if (m === "CS 1VS1" || m === "LONE WOLF 1V1") {
        input.value = 2;
    } else if (m === "CS 2VS2" || m === "LONE WOLF 2V2") {
        input.value = 2;
    } else if (m === "CS 4VS4") {
        input.value = 2;
    } else {
        input.value = 48;
    }
}
</script>
