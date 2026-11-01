<?php
session_start();
require_once __DIR__ . '/common/config.php';

// Authentication Check: Worker or Master Admin can access
$is_authenticated = false;
$logged_worker_id = 0;
$logged_worker_user = '';
$is_master = false;

if (!empty($_SESSION['admin_id']) || !empty($_SESSION['worker_id'])) {
    $logged_worker_id = (int)($_SESSION['worker_id'] ?? $_SESSION['admin_id']);
    $logged_worker_user = $_SESSION['admin_user'] ?? $_SESSION['username'] ?? 'Worker';
    $is_master = (!empty($_SESSION['is_super_admin']) || $logged_worker_user === 'anonymous.349' || $logged_worker_user === 'admin');
    $is_authenticated = true;
}

if (!$is_authenticated) {
    $_SESSION['active_panel'] = 'worker';
    setcookie('beastz_active_panel', 'worker', time() + (365 * 24 * 3600), '/');
    safe_redirect("login.php");
    exit;
}

// Set active panel cookie
$_SESSION['active_panel'] = 'worker';
if (!isset($_COOKIE['beastz_active_panel']) || $_COOKIE['beastz_active_panel'] !== 'worker') {
    setcookie('beastz_active_panel', 'worker', time() + (365 * 24 * 3600), '/');
}

$feedback_msg = "";
$feedback_type = "";

// Helper for OneSignal push notification
function send_worker_onesignal_notification($title, $body, $target_url = '/?tab=matches', $player_ids = []) {
    $onesignal_app_id = '561bd59e-8355-47c8-88cc-0766688a105a';
    $onesignal_rest_key = getenv("ONESIGNAL_API_KEY") ?: base64_decode("b3NfdjJfYXBwX2t5bjVsaHVka3ZkNHJjZ21hNXRncmNxcWxrM3A0bTR0NXliZTQ1ZnpmbWJjZ3VlYWtmb2xwdHFycWlld25ybXA2N21pNHB4azV3aHllMzZiNTVzbTQ3bDdxbXF0ZGJ2MnVmZmpzYXk=");

    $fields = [
        'app_id' => $onesignal_app_id,
        'headings' => ['en' => $title],
        'contents' => ['en' => $body],
        'data' => [
            'target_url' => $target_url,
            'badge_type' => 'TOURNAMENT_ALERT'
        ],
        'priority' => 10,
        'android_visibility' => 1,
        'content_available' => true,
        'android_sound' => 'notification',
        'android_channel_id' => 'beastz_push_alerts'
    ];

    $valid_player_ids = array_filter(array_unique($player_ids));
    if (!empty($valid_player_ids)) {
        $fields['include_player_ids'] = array_values($valid_player_ids);
    } else {
        $fields['included_segments'] = ['Subscribed Users'];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Key ' . $onesignal_rest_key
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// ==============================================================
// POST ACTION HANDLERS
// ==============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];

    // 1. ADD NEW TOURNAMENT
    if ($act === 'create_tourney') {
        $title = sanitize($_POST['title'] ?? '');
        $game = sanitize($_POST['game'] ?? 'BGMI');
        $map = sanitize($_POST['map'] ?? 'Erangel');
        $match_type = sanitize($_POST['match_type'] ?? 'Solo');
        $entry_fee = (float)($_POST['entry_fee'] ?? 0.00);
        $per_kill = (float)($_POST['per_kill'] ?? 0.00);
        $prize_pool = (float)($_POST['prize_pool'] ?? 0.00);
        $match_time = sanitize($_POST['match_time'] ?? '');
        $total_slots = (int)($_POST['total_slots'] ?? 100);

        if (empty($title) || empty($match_time)) {
            $feedback_msg = "Please provide match title and scheduled date & time.";
            $feedback_type = "error";
        } else {
            $stmt = $conn->prepare("INSERT INTO tournaments (title, game, map, match_type, entry_fee, per_kill, prize_pool, match_time, total_slots, joined_slots, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'upcoming')");
            if ($stmt) {
                $stmt->bind_param("ssssdddsi", $title, $game, $map, $match_type, $entry_fee, $per_kill, $prize_pool, $match_time, $total_slots);
                if ($stmt->execute()) {
                    $new_id = $stmt->insert_id;
                    $stmt->close();
                    $feedback_msg = "✅ Tournament #" . $new_id . " ('" . htmlspecialchars($title) . "') published successfully!";
                    $feedback_type = "success";
                } else {
                    $feedback_msg = "Failed to add tournament: " . $conn->error;
                    $feedback_type = "error";
                }
            }
        }
    }

    // 2. UPDATE / BROADCAST ROOM IDP
    if ($act === 'save_and_broadcast_idp') {
        $tid = (int)($_POST['tournament_id'] ?? 0);
        $room_id = sanitize($_POST['room_id'] ?? '');
        $room_pass = sanitize($_POST['room_password'] ?? '');
        $do_broadcast = isset($_POST['send_push']) && $_POST['send_push'] == '1';

        if ($tid <= 0) {
            $feedback_msg = "Invalid tournament selected.";
            $feedback_type = "error";
        } else {
            $upd = $conn->prepare("UPDATE tournaments SET room_id = ?, room_password = ?, status = IF(status = 'upcoming', 'ongoing', status) WHERE id = ?");
            if ($upd) {
                $upd->bind_param("ssi", $room_id, $room_pass, $tid);
                $upd->execute();
                $upd->close();

                $t_info = $conn->query("SELECT title FROM tournaments WHERE id = {$tid}")->fetch_assoc();
                $t_title = $t_info['title'] ?? 'Custom Match';

                $p_cnt = 0;
                if ($do_broadcast) {
                    // Fetch player IDs of joined gamers
                    $j_q = $conn->query("SELECT u.player_id, u.username FROM user_tournaments ut JOIN users u ON ut.user_id = u.id WHERE ut.tournament_id = {$tid}");
                    $player_ids = [];
                    if ($j_q) {
                        while ($p = $j_q->fetch_assoc()) {
                            if (!empty($p['player_id'])) {
                                $player_ids[] = $p['player_id'];
                            }
                            $p_cnt++;
                        }
                    }

                    $n_title = "🎮 Room Details: " . $t_title;
                    $n_body = "Room ID: " . $room_id . " | Password: " . $room_pass . ". Join the room right now!";
                    send_worker_onesignal_notification($n_title, $n_body, '/?tab=matches', $player_ids);

                    // Insert in-app notification
                    $conn->query("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES ('" . $conn->real_escape_string($n_title) . "', '" . $conn->real_escape_string($n_body) . "', '/?tab=matches', 'ROOM_IDP')");

                    $feedback_msg = "🚀 Room IDP saved and push notification dispatched to " . $p_cnt . " joined players!";
                    $feedback_type = "success";
                } else {
                    $feedback_msg = "Room ID & Password saved successfully for Match #" . $tid . "!";
                    $feedback_type = "success";
                }
            }
        }
    }

    // 3. BROADCAST GENERAL WORKER PUSH ANNOUNCEMENT
    if ($act === 'send_worker_broadcast') {
        $b_title = sanitize($_POST['broadcast_title'] ?? '');
        $b_message = sanitize($_POST['broadcast_message'] ?? '');
        $target_url = sanitize($_POST['target_url'] ?? '/?tab=home');

        if (empty($b_title) || empty($b_message)) {
            $feedback_msg = "Title and message are required for push notification.";
            $feedback_type = "error";
        } else {
            send_worker_onesignal_notification($b_title, $b_message, $target_url);
            $conn->query("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES ('" . $conn->real_escape_string($b_title) . "', '" . $conn->real_escape_string($b_message) . "', '" . $conn->real_escape_string($target_url) . "', 'HOST_ANNOUNCEMENT')");
            $feedback_msg = "✅ Push notification broadcast sent to all active gamers!";
            $feedback_type = "success";
        }
    }

    // 4. ANTI-CHEAT: REVOKE FUNDS / DISQUALIFY CHEATER
    if ($act === 'anti_cheat_revoke') {
        $suspect_user = trim(sanitize($_POST['suspect_user'] ?? ''));
        $match_id = (int)($_POST['match_id'] ?? 0);
        $revoke_amount = (float)($_POST['revoke_amount'] ?? 0.00);
        $reason = sanitize($_POST['reason'] ?? 'Anti-Cheat Infraction / Illegal Gameplay');

        if (empty($suspect_user) || $revoke_amount <= 0) {
            $feedback_msg = "Please provide valid username/ID and revoke amount.";
            $feedback_type = "error";
        } else {
            // Find user
            $u_q = $conn->prepare("SELECT id, username, wallet_balance FROM users WHERE username = ? OR id = ? LIMIT 1");
            $u_q->bind_param("ss", $suspect_user, $suspect_user);
            $u_q->execute();
            $target_u = $u_q->get_result()->fetch_assoc();
            $u_q->close();

            if (!$target_u) {
                $feedback_msg = "User @" . htmlspecialchars($suspect_user) . " not found in database.";
                $feedback_type = "error";
            } else {
                $t_uid = (int)$target_u['id'];
                $conn->begin_transaction();
                try {
                    // Deduct wallet balance
                    $ded = $conn->prepare("UPDATE users SET wallet_balance = GREATEST(0, wallet_balance - ?) WHERE id = ?");
                    $ded->bind_param("di", $revoke_amount, $t_uid);
                    $ded->execute();
                    $ded->close();

                    // Insert passbook transaction
                    $t_desc = "Prize Revoked (Anti-Cheat Match #" . $match_id . "): " . $reason;
                    $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
                    $tx->bind_param("ids", $t_uid, $revoke_amount, $t_desc);
                    $tx->execute();
                    $tx->close();

                    // Log anti cheat event
                    $log = $conn->prepare("INSERT INTO anti_cheat_logs (tournament_id, user_id, amount, reason, admin_username) VALUES (?, ?, ?, ?, ?)");
                    if ($log) {
                        $log->bind_param("iidss", $match_id, $t_uid, $revoke_amount, $reason, $logged_worker_user);
                        $log->execute();
                        $log->close();
                    }

                    // Send user alert notification
                    $n_title = "⚠️ Fund Revocation Warning";
                    $n_msg = "₹" . number_format($revoke_amount, 2) . " has been revoked from your wallet due to anti-cheat policy violation in Match #" . $match_id . " (" . $reason . ").";
                    $conn->query("INSERT INTO app_notifications (title, message, target_url, badge_type) VALUES ('" . $conn->real_escape_string($n_title) . "', '" . $conn->real_escape_string($n_msg) . "', '/?tab=wallet', 'ANTI_CHEAT')");

                    $conn->commit();
                    $feedback_msg = "🛡️ Anti-Cheat action executed: ₹" . number_format($revoke_amount, 2) . " revoked from @" . htmlspecialchars($target_u['username']) . "!";
                    $feedback_type = "success";
                } catch (Exception $e) {
                    $conn->rollback();
                    $feedback_msg = "Anti-cheat action failed: " . $e->getMessage();
                    $feedback_type = "error";
                }
            }
        }
    }

    // 5. DELETE TOURNAMENT
    if ($act === 'delete_tournament') {
        $del_id = (int)($_POST['tournament_id'] ?? 0);
        if ($del_id > 0) {
            $conn->query("DELETE FROM tournaments WHERE id = {$del_id}");
            $conn->query("DELETE FROM user_tournaments WHERE tournament_id = {$del_id}");
            $feedback_msg = "Tournament #" . $del_id . " deleted successfully.";
            $feedback_type = "success";
        }
    }

    // 6. TOGGLE STATUS
    if ($act === 'update_status') {
        $st_id = (int)($_POST['tournament_id'] ?? 0);
        $new_st = sanitize($_POST['new_status'] ?? 'upcoming');
        if ($st_id > 0 && in_array($new_st, ['upcoming', 'ongoing', 'completed', 'cancelled'])) {
            $conn->query("UPDATE tournaments SET status = '{$new_st}' WHERE id = {$st_id}");
            $feedback_msg = "Match #" . $st_id . " status changed to " . strtoupper($new_st) . "!";
            $feedback_type = "success";
        }
    }
}

// Fetch Tournaments
$tourneys_res = $conn->query("SELECT t.*, (SELECT COUNT(*) FROM user_tournaments ut WHERE ut.tournament_id = t.id) as joined_count FROM tournaments t ORDER BY t.id DESC");
$all_tourneys = [];
if ($tourneys_res) {
    while ($r = $tourneys_res->fetch_assoc()) {
        $all_tourneys[] = $r;
    }
}

// Fetch 24-Hour History
$history_res = $conn->query("SELECT t.*, (SELECT COUNT(*) FROM user_tournaments ut WHERE ut.tournament_id = t.id) as joined_count FROM tournaments t WHERE t.status = 'completed' OR t.match_time >= NOW() - INTERVAL 24 HOUR ORDER BY t.match_time DESC LIMIT 50");
$history_tourneys = [];
if ($history_res) {
    while ($hr = $history_res->fetch_assoc()) {
        $history_tourneys[] = $hr;
    }
}

// Fetch Recent Anti-Cheat Logs
$cheat_logs = [];
$cl_q = $conn->query("SELECT l.*, u.username FROM anti_cheat_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.id DESC LIMIT 20");
if ($cl_q) {
    while ($cl = $cl_q->fetch_assoc()) {
        $cheat_logs[] = $cl;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="/theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Worker Panel - Tournament Manager | BEASTzBATTLE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        display: ['"Outfit"', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/theme.css">
</head>
<body class="bg-slate-50 dark:bg-[#080a0f] text-slate-800 dark:text-slate-100 min-h-screen flex flex-col font-sans antialiased pb-24">
    <!-- Top Bar -->
    <header class="bg-white/95 dark:bg-[#0f141f]/95 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 px-4 py-3 sticky top-0 z-50 shadow-xs">
        <div class="max-w-5xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-amber-400 text-slate-950 flex items-center justify-center font-black shadow-sm">
                    <i class="fa-solid fa-gamepad"></i>
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <h1 class="text-sm font-black font-display text-slate-900 dark:text-white leading-tight">BEASTz WORKER</h1>
                        <span class="px-1.5 py-0.5 rounded-full text-[9px] font-black uppercase bg-amber-400/20 text-amber-500 border border-amber-400/30">Manager</span>
                    </div>
                    <p class="text-[10px] text-slate-400 font-medium">Logged in as @<?= htmlspecialchars($logged_worker_user); ?></p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="toggleTheme()" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300">
                    <i class="fa-solid fa-moon text-xs beastz-theme-icon"></i>
                </button>

                <?php if ($is_master): ?>
                <a href="admin/index.php" class="px-2.5 py-1.5 rounded-xl bg-yellow-400 text-slate-950 text-xs font-bold flex items-center gap-1 shadow-xs">
                    <i class="fa-solid fa-shield"></i> <span class="hidden sm:inline">Admin Panel</span>
                </a>
                <?php endif; ?>

                <a href="logout.php" class="px-2.5 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 border border-rose-500/30 text-xs font-bold flex items-center gap-1">
                    <i class="fa-solid fa-right-from-bracket"></i> <span class="hidden sm:inline">Logout</span>
                </a>
            </div>
        </div>

        <!-- Horizontal Tabs Strip -->
        <div class="max-w-5xl mx-auto flex items-center gap-2 overflow-x-auto no-scrollbar pt-3 text-xs">
            <button type="button" onclick="switchWorkerTab('tourneys')" id="wtab-btn-tourneys" class="w-tab-pill px-3 py-1.5 rounded-xl font-bold whitespace-nowrap bg-amber-400 text-slate-950 shadow-xs">
                <i class="fa-solid fa-trophy mr-1"></i> Tourneys (<?= count($all_tourneys); ?>)
            </button>
            <button type="button" onclick="switchWorkerTab('idp')" id="wtab-btn-idp" class="w-tab-pill px-3 py-1.5 rounded-xl font-bold whitespace-nowrap bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800">
                <i class="fa-solid fa-key mr-1"></i> Room IDP & Push
            </button>
            <button type="button" onclick="switchWorkerTab('broadcast')" id="wtab-btn-broadcast" class="w-tab-pill px-3 py-1.5 rounded-xl font-bold whitespace-nowrap bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800">
                <i class="fa-solid fa-paper-plane mr-1"></i> Push Broadcast
            </button>
            <button type="button" onclick="switchWorkerTab('history')" id="wtab-btn-history" class="w-tab-pill px-3 py-1.5 rounded-xl font-bold whitespace-nowrap bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800">
                <i class="fa-solid fa-clock-rotate-left mr-1"></i> 24h Match History
            </button>
            <button type="button" onclick="switchWorkerTab('anticheat')" id="wtab-btn-anticheat" class="w-tab-pill px-3 py-1.5 rounded-xl font-bold whitespace-nowrap bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800">
                <i class="fa-solid fa-user-slash mr-1"></i> Anti-Cheat & Revoke
            </button>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-5xl mx-auto w-full p-4 space-y-4">
        <!-- Toast Feedback -->
        <?php if (!empty($feedback_msg)): ?>
            <div class="p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2.5 shadow-sm <?= $feedback_type === 'success' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400' ?>">
                <i class="fa-solid <?= $feedback_type === 'success' ? 'fa-circle-check text-emerald-500' : 'fa-circle-exclamation text-rose-500' ?> text-sm shrink-0"></i>
                <span><?= htmlspecialchars($feedback_msg); ?></span>
            </div>
        <?php endif; ?>

        <!-- ======================================================== -->
        <!-- TAB 1: TOURNAMENTS LIST & CREATION -->
        <!-- ======================================================== -->
        <section id="worker-section-tourneys" class="worker-tab-view space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-extrabold font-display">Manage Tournaments</h2>
                    <p class="text-[11px] text-slate-400">Create, edit, list or delete BGMI & Free Fire matches</p>
                </div>
                <button type="button" onclick="openCreateTourneyModal()" class="px-3.5 py-2 bg-amber-400 hover:bg-amber-500 text-slate-950 font-black rounded-xl text-xs flex items-center gap-1.5 shadow-md active:scale-95 transition">
                    <i class="fa-solid fa-plus"></i>
                    <span>Create Match</span>
                </button>
            </div>

            <!-- Tournaments List -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                <?php if (empty($all_tourneys)): ?>
                    <div class="col-span-2 p-8 text-center bg-white dark:bg-[#0f141f] border border-slate-200 dark:border-slate-800 rounded-3xl">
                        <i class="fa-solid fa-trophy text-3xl text-slate-300 dark:text-slate-700 mb-2"></i>
                        <p class="text-xs text-slate-400 font-bold">No tournaments scheduled yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($all_tourneys as $t): ?>
                        <div class="bg-white dark:bg-[#0f141f] border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs relative overflow-hidden space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[9px] font-black uppercase mb-1 <?= strtolower($t['status']) === 'completed' ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/30' : (strtolower($t['status']) === 'ongoing' ? 'bg-amber-500/10 text-amber-500 border border-amber-500/30 animate-pulse' : 'bg-blue-500/10 text-blue-500 border border-blue-500/30') ?>">
                                        <?= htmlspecialchars(strtoupper($t['status'])); ?>
                                    </span>
                                    <h3 class="text-sm font-extrabold text-slate-900 dark:text-white leading-tight">
                                        <?= htmlspecialchars($t['title']); ?>
                                    </h3>
                                    <p class="text-[10px] text-slate-400 mt-0.5">
                                        <?= htmlspecialchars($t['game'] . ' • ' . $t['match_type'] . ' • ' . $t['map']); ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-black text-amber-500 font-mono">₹<?= number_format($t['prize_pool'], 2); ?></span>
                                    <span class="text-[10px] text-slate-400 block font-mono">Fee: ₹<?= number_format($t['entry_fee'], 0); ?></span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-[10px] bg-slate-50 dark:bg-slate-900/60 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                                <div>
                                    <span class="text-slate-400 block">Match Time:</span>
                                    <span class="font-bold font-mono"><?= date('d M, h:i A', strtotime($t['match_time'])); ?></span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block">Slots Joined:</span>
                                    <span class="font-bold font-mono text-amber-500"><?= (int)$t['joined_count']; ?> / <?= (int)$t['total_slots']; ?> Players</span>
                                </div>
                                <div class="col-span-2 pt-1 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                                    <span class="text-slate-400">Room IDP:</span>
                                    <span class="font-mono font-bold text-xs <?= !empty($t['room_id']) ? 'text-emerald-500' : 'text-slate-500' ?>">
                                        <?= !empty($t['room_id']) ? 'ID: ' . htmlspecialchars($t['room_id']) . ' | Pass: ' . htmlspecialchars($t['room_password']) : 'Not Entered' ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Fast Action Buttons -->
                            <div class="flex items-center gap-1.5 pt-1">
                                <button type="button" onclick="openIDPModal(<?= $t['id']; ?>, '<?= htmlspecialchars(addslashes($t['title'])); ?>', '<?= htmlspecialchars(addslashes($t['room_id'] ?? '')); ?>', '<?= htmlspecialchars(addslashes($t['room_password'] ?? '')); ?>')" class="flex-1 py-1.5 bg-amber-400 hover:bg-amber-500 text-slate-950 font-bold rounded-xl text-[11px] flex items-center justify-center gap-1">
                                    <i class="fa-solid fa-key text-[10px]"></i> IDP & Push
                                </button>

                                <!-- Status Switcher Form -->
                                <form method="POST" action="worker.php" class="inline">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="tournament_id" value="<?= $t['id']; ?>">
                                    <select name="new_status" onchange="this.form.submit()" class="bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-[10px] font-bold rounded-xl px-2 py-1.5 focus:outline-none">
                                        <option value="upcoming" <?= $t['status'] === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                        <option value="ongoing" <?= $t['status'] === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                                        <option value="completed" <?= $t['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="cancelled" <?= $t['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </form>

                                <!-- Delete Match -->
                                <form method="POST" action="worker.php" onsubmit="return confirm('Delete this tournament completely?');" class="inline">
                                    <input type="hidden" name="action" value="delete_tournament">
                                    <input type="hidden" name="tournament_id" value="<?= $t['id']; ?>">
                                    <button type="submit" class="w-8 h-8 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 border border-rose-500/30 flex items-center justify-center text-xs">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- TAB 2: ROOM IDP & INSTANT PUSH NOTIFICATION -->
        <!-- ======================================================== -->
        <section id="worker-section-idp" class="worker-tab-view hidden space-y-4">
            <div class="bg-white dark:bg-[#0f141f] border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-xs space-y-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-amber-400 text-slate-950 flex items-center justify-center font-black">
                        <i class="fa-solid fa-paper-plane text-base"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-black font-display text-slate-900 dark:text-white">Broadcast Room IDP to Joined Gamers</h2>
                        <p class="text-[11px] text-slate-400">Sends OneSignal push notifications directly to players registered in the match</p>
                    </div>
                </div>

                <form method="POST" action="worker.php" class="space-y-3.5">
                    <input type="hidden" name="action" value="save_and_broadcast_idp">
                    <input type="hidden" name="send_push" value="1">

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Select Tournament</label>
                        <select name="tournament_id" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none">
                            <option value="">-- Choose Match --</option>
                            <?php foreach ($all_tourneys as $t): ?>
                                <option value="<?= $t['id']; ?>">
                                    #<?= $t['id']; ?>: <?= htmlspecialchars($t['title']); ?> (<?= $t['game']; ?> - <?= date('h:i A', strtotime($t['match_time'])); ?>) - <?= (int)$t['joined_count']; ?> Joined
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">Room ID</label>
                            <input type="text" name="room_id" required placeholder="e.g. 8492048" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-mono font-bold focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">Room Password</label>
                            <input type="text" name="room_password" required placeholder="e.g. 1234" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-mono font-bold focus:outline-none">
                        </div>
                    </div>

                    <div class="p-3 bg-amber-500/10 border border-amber-500/30 rounded-2xl flex items-center gap-2 text-[11px] text-amber-500 font-bold">
                        <i class="fa-solid fa-bullhorn text-sm"></i>
                        <span>Clicking Broadcast will trigger targeted OneSignal push notification to all joined player devices!</span>
                    </div>

                    <button type="submit" class="w-full py-3 bg-amber-400 hover:bg-amber-500 text-slate-950 font-black rounded-xl text-xs transition shadow-md active:scale-95 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Save & Broadcast Room Details</span>
                    </button>
                </form>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- TAB 3: PUSH BROADCAST STUDIO (GENERAL ANNOUNCEMENTS) -->
        <!-- ======================================================== -->
        <section id="worker-section-broadcast" class="worker-tab-view hidden space-y-4">
            <div class="bg-white dark:bg-[#0f141f] border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-xs space-y-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-blue-500/20 text-blue-500 flex items-center justify-center font-black">
                        <i class="fa-solid fa-bullhorn text-base"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-black font-display text-slate-900 dark:text-white">Push Alerts Studio (All Gamers)</h2>
                        <p class="text-[11px] text-slate-400">Broadcast match alerts, schedule notices, and announcements</p>
                    </div>
                </div>

                <form method="POST" action="worker.php" class="space-y-3.5">
                    <input type="hidden" name="action" value="send_worker_broadcast">

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Notification Title</label>
                        <input type="text" name="broadcast_title" required placeholder="e.g. 🚨 Squad Erangel Room Open!" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Notification Message</label>
                        <textarea name="broadcast_message" rows="3" required placeholder="Enter message text delivered to gamer notification bar..." class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs focus:outline-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Target Tab / Redirect</label>
                        <select name="target_url" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none">
                            <option value="/?tab=home">Tournaments List (/tab=home)</option>
                            <option value="/?tab=matches">My Matches (/tab=matches)</option>
                            <option value="/?tab=wallet">Wallet & Deposits (/tab=wallet)</option>
                        </select>
                    </div>

                    <button type="submit" class="w-full py-3 bg-blue-500 hover:bg-blue-600 text-white font-black rounded-xl text-xs transition shadow-md active:scale-95 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Broadcast Notification Now</span>
                    </button>
                </form>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- TAB 4: 24-HOUR MATCH HISTORY -->
        <!-- ======================================================== -->
        <section id="worker-section-history" class="worker-tab-view hidden space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-extrabold font-display">24-Hour Match History</h2>
                    <p class="text-[11px] text-slate-400">All tournaments completed or played in the last 24 hours</p>
                </div>
            </div>

            <div class="bg-white dark:bg-[#0f141f] border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-xs">
                <?php if (empty($history_tourneys)): ?>
                    <div class="p-8 text-center">
                        <p class="text-xs text-slate-400">No match records in the last 24 hours.</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($history_tourneys as $ht): ?>
                            <div class="p-3.5 flex items-center justify-between gap-3 hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                                <div>
                                    <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full <?= $ht['status'] === 'completed' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-slate-500/10 text-slate-400' ?>">
                                        <?= strtoupper($ht['status']); ?>
                                    </span>
                                    <h4 class="text-xs font-bold text-slate-900 dark:text-white mt-1"><?= htmlspecialchars($ht['title']); ?></h4>
                                    <p class="text-[10px] text-slate-400"><?= $ht['game']; ?> • <?= date('d M, h:i A', strtotime($ht['match_time'])); ?></p>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-mono font-bold text-amber-500">₹<?= number_format($ht['prize_pool'], 2); ?></span>
                                    <span class="block text-[10px] text-slate-400 font-mono"><?= (int)$ht['joined_count']; ?> Players</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- TAB 5: ANTI-CHEAT & FUND REVOKE -->
        <!-- ======================================================== -->
        <section id="worker-section-anticheat" class="worker-tab-view hidden space-y-4">
            <div class="bg-white dark:bg-[#0f141f] border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-xs space-y-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-rose-500/20 text-rose-500 flex items-center justify-center font-black">
                        <i class="fa-solid fa-shield-halved text-base"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-black font-display text-slate-900 dark:text-white">Anti-Cheat Enforcement & Fund Revoke</h2>
                        <p class="text-[11px] text-slate-400">Revoke illegitimate tournament winnings and penalize suspected cheaters/hackers</p>
                    </div>
                </div>

                <form method="POST" action="worker.php" onsubmit="return confirm('Confirm revoke funds from this player for anti-cheat infraction?');" class="space-y-3.5">
                    <input type="hidden" name="action" value="anti_cheat_revoke">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">Suspect Username or User ID</label>
                            <input type="text" name="suspect_user" required placeholder="e.g. cheater_pro or 124" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">Tournament ID</label>
                            <input type="number" name="match_id" required placeholder="e.g. 5" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">Revoke Amount (₹)</label>
                            <input type="number" step="0.01" name="revoke_amount" required placeholder="e.g. 150.00" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-mono font-bold focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">Reason / Infraction</label>
                            <input type="text" name="reason" required placeholder="e.g. ESP / Speed Hack / Teaming" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs focus:outline-none">
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3 bg-rose-500 hover:bg-rose-600 text-white font-black rounded-xl text-xs transition shadow-md active:scale-95 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-gavel"></i>
                        <span>Execute Fund Revoke & Penalize Player</span>
                    </button>
                </form>
            </div>

            <!-- Recent Anti-Cheat Logs Table -->
            <?php if (!empty($cheat_logs)): ?>
                <div class="bg-white dark:bg-[#0f141f] border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-xs space-y-2.5">
                    <h3 class="text-xs font-extrabold text-slate-900 dark:text-white">Recent Enforcement History</h3>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800 text-[11px]">
                        <?php foreach ($cheat_logs as $cl): ?>
                            <div class="py-2 flex items-center justify-between">
                                <div>
                                    <span class="font-bold text-rose-500">@<?= htmlspecialchars($cl['username'] ?? 'User #' . $cl['user_id']); ?></span>
                                    <span class="text-slate-400 block text-[10px]"><?= htmlspecialchars($cl['reason']); ?> • Match #<?= $cl['tournament_id']; ?></span>
                                </div>
                                <div class="text-right">
                                    <span class="font-mono font-bold text-rose-500">-₹<?= number_format($cl['amount'], 2); ?></span>
                                    <span class="block text-[9px] text-slate-400"><?= date('d M, h:i A', strtotime($cl['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Create Tourney Modal -->
    <div id="createTourneyModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white dark:bg-[#0f141f] border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-black font-display text-slate-900 dark:text-white">Schedule New Match</h3>
                <button type="button" onclick="closeCreateTourneyModal()" class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="worker.php" class="space-y-3">
                <input type="hidden" name="action" value="create_tourney">

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Title</label>
                    <input type="text" name="title" required placeholder="e.g. BGMI Squad War - Erangel Daily" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Game</label>
                        <select name="game" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none">
                            <option value="BGMI">BGMI</option>
                            <option value="Free Fire">Free Fire</option>
                            <option value="Call of Duty">Call of Duty</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Match Type</label>
                        <select name="match_type" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none">
                            <option value="Solo">Solo BR</option>
                            <option value="Duo">Duo BR</option>
                            <option value="Squad" selected>Squad BR</option>
                            <option value="CS 1v1">CS 1v1</option>
                            <option value="CS 2v2">CS 2v2</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Map</label>
                        <input type="text" name="map" value="Erangel" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Total Slots</label>
                        <input type="number" name="total_slots" value="100" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 mb-1">Fee (₹)</label>
                        <input type="number" step="1" name="entry_fee" value="20" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-2 py-2 text-xs font-bold focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 mb-1">Per Kill (₹)</label>
                        <input type="number" step="1" name="per_kill" value="10" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-2 py-2 text-xs font-bold focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 mb-1">Prize (₹)</label>
                        <input type="number" step="1" name="prize_pool" value="500" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-2 py-2 text-xs font-bold focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Match Date & Time</label>
                    <input type="datetime-local" name="match_time" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none">
                </div>

                <button type="submit" class="w-full py-3 bg-amber-400 hover:bg-amber-500 text-slate-950 font-black rounded-xl text-xs transition shadow-md active:scale-95 flex items-center justify-center gap-1.5 mt-2">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Publish Tournament</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Quick IDP Modal -->
    <div id="quickIDPModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="w-full max-w-sm bg-white dark:bg-[#0f141f] border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-2xl relative">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-black font-display text-slate-900 dark:text-white" id="modalTourneyTitle">Enter Room IDP</h3>
                <button type="button" onclick="closeIDPModal()" class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="worker.php" class="space-y-3">
                <input type="hidden" name="action" value="save_and_broadcast_idp">
                <input type="hidden" name="tournament_id" id="modalTourneyId" value="">

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Room ID</label>
                    <input type="text" name="room_id" id="modalRoomId" required placeholder="e.g. 5839201" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-mono font-bold focus:outline-none">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Room Password</label>
                    <input type="text" name="room_password" id="modalRoomPass" required placeholder="e.g. 1234" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-mono font-bold focus:outline-none">
                </div>

                <div class="flex items-center gap-2 p-2 bg-slate-100 dark:bg-slate-900 rounded-xl">
                    <input type="checkbox" name="send_push" value="1" id="chkPush" checked class="w-4 h-4 rounded text-amber-400">
                    <label for="chkPush" class="text-[11px] font-bold text-slate-600 dark:text-slate-300 cursor-pointer">
                        Broadcast push notification to joined players
                    </label>
                </div>

                <button type="submit" class="w-full py-2.5 bg-amber-400 hover:bg-amber-500 text-slate-950 font-black rounded-xl text-xs transition shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Save & Send Notification</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Tab Switcher Script -->
    <script>
        function switchWorkerTab(tab) {
            document.querySelectorAll('.worker-tab-view').forEach(function(el) {
                el.classList.add('hidden');
            });
            var target = document.getElementById('worker-section-' + tab);
            if (target) target.classList.remove('hidden');

            document.querySelectorAll('.w-tab-pill').forEach(function(pill) {
                pill.className = 'w-tab-pill px-3 py-1.5 rounded-xl font-bold whitespace-nowrap bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800';
            });
            var activePill = document.getElementById('wtab-btn-' + tab);
            if (activePill) {
                activePill.className = 'w-tab-pill px-3 py-1.5 rounded-xl font-bold whitespace-nowrap bg-amber-400 text-slate-950 shadow-xs';
            }
        }

        function openCreateTourneyModal() {
            var m = document.getElementById('createTourneyModal');
            if (m) m.classList.remove('hidden');
        }

        function closeCreateTourneyModal() {
            var m = document.getElementById('createTourneyModal');
            if (m) m.classList.add('hidden');
        }

        function openIDPModal(id, title, rid, rpass) {
            document.getElementById('modalTourneyId').value = id;
            document.getElementById('modalTourneyTitle').innerText = 'IDP: ' + title;
            document.getElementById('modalRoomId').value = rid || '';
            document.getElementById('modalRoomPass').value = rpass || '';
            var m = document.getElementById('quickIDPModal');
            if (m) m.classList.remove('hidden');
        }

        function closeIDPModal() {
            var m = document.getElementById('quickIDPModal');
            if (m) m.classList.add('hidden');
        }
    </script>
</body>
</html>
