<?php
require_once __DIR__ . '/common/config.php';

// =============================================================
// ✅ SESSION PERSISTENCE & REFRESH ROUTER
// Retains exact logged-in role & panel across reloads / APK restarts
// =============================================================
$is_admin_session = (!empty($_SESSION['admin_id']) || (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') || !empty($_SESSION['admin_logged_in']));
$active_panel = $_SESSION['active_panel'] ?? $_COOKIE['beastz_active_panel'] ?? $_COOKIE['active_panel'] ?? '';

if ($is_admin_session) {
    if (isset($_GET['view']) && $_GET['view'] === 'player') {
        $_SESSION['active_panel'] = 'user';
        setcookie('active_panel', 'user', time() + (365 * 24 * 3600), '/');
        setcookie('beastz_active_panel', 'user', time() + (365 * 24 * 3600), '/');
    } elseif ($active_panel === 'admin' || !isset($_GET['view'])) {
        $_SESSION['active_panel'] = 'admin';
        setcookie('active_panel', 'admin', time() + (365 * 24 * 3600), '/');
        setcookie('beastz_active_panel', 'admin', time() + (365 * 24 * 3600), '/');
        safe_redirect("admin/index.php");
        exit;
    }
}

$curr_uid = (int)($_SESSION['user_id'] ?? 0);
$alert_msg = "";
$alert_type = "";
$msg = "";
$msg_type = "";
if (!empty($_SESSION['flash_msg'])) {
    $alert_msg = (string)$_SESSION['flash_msg'];
    $alert_type = (string)($_SESSION['flash_type'] ?? 'success');
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// -------------------------------------------------------------
// 1. ACTION HANDLER: TOURNAMENT JOIN / REGISTRATION
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_tournament') {
    check_user_auth();
    $curr_uid = (int)$_SESSION['user_id'];
    $tid = (int)($_POST['tournament_id'] ?? 0);
    $slot_no = (int)($_POST['slot_no'] ?? 0);
    $match_mode = strtoupper(trim($_POST['match_mode'] ?? ''));

    if ($tid <= 0 || $slot_no <= 0) {
        $alert_msg = "Please select a valid tournament and empty slot number.";
        $alert_type = "error";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Lock tournament row for update to ensure consistent state
            $t_stmt = $conn->prepare("
                SELECT t.id, t.title, COALESCE(t.game_name, t.game, 'Free Fire') as game_name, 
                       t.entry_fee, t.prize_pool, t.status, t.total_slots, t.match_type,
                       (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
                FROM tournaments t 
                WHERE t.id = ? FOR UPDATE
            ");
            $t_stmt->bind_param("i", $tid);
            $t_stmt->execute();
            $tourney = $t_stmt->get_result()->fetch_assoc();
            $t_stmt->close();

            if (!$tourney) {
                throw new Exception("Tournament not found or expired.");
            }
            if (strtolower($tourney['status']) === 'completed') {
                throw new Exception("This tournament has already ended.");
            }

            // 2. Dynamic Slot Rules: Squad = 12 slots, Duo = 24 slots, Solo = standard slots
            $t_title_upper = strtoupper($tourney['title'] ?? '');
            $t_type_upper = strtoupper($tourney['match_type'] ?? '');
            $is_squad = ($match_mode === 'SQUAD' || str_contains($t_type_upper, 'SQUAD') || str_contains($t_title_upper, 'SQUAD'));
            $is_duo = (!$is_squad && ($match_mode === 'DUO' || str_contains($t_type_upper, 'DUO') || str_contains($t_type_upper, '2VS2') || str_contains($t_title_upper, 'DUO')));

            $max_slots = $is_squad ? 12 : ($is_duo ? 24 : (int)($tourney['total_slots'] ?: 48));
            if ($max_slots <= 0) $max_slots = 48;

            if ($slot_no < 1 || $slot_no > $max_slots) {
                throw new Exception("Please select a valid slot number between 1 and " . $max_slots . ".");
            }

            // 3. Dynamic Roster Parsing & Validation (Squad: 4 players, Duo: 2 players, Solo: 1 player)
            $p_count = $is_squad ? 4 : ($is_duo ? 2 : 1);
            $roster_igns = [];
            $roster_uids = [];

            for ($p = 1; $p <= $p_count; $p++) {
                $p_ign = sanitize($_POST["player{$p}_ign"] ?? '');
                $p_uid = sanitize($_POST["player{$p}_uid"] ?? '');

                // Fallback for single player / legacy forms
                if (empty($p_ign) || empty($p_uid)) {
                    if ($p === 1) {
                        $p_ign = sanitize($_POST['in_game_name'] ?? '');
                        $p_uid = sanitize($_POST['game_id'] ?? '');
                    }
                }

                if (empty($p_ign)) {
                    throw new Exception("Please enter In-Game Character Name (IGN) for Player #" . $p . ".");
                }
                if (empty($p_uid)) {
                    throw new Exception("Please enter Game UID for Player #" . $p . ".");
                }

                $roster_igns[] = ($p === 1 && $p_count > 1) ? $p_ign . " [Leader]" : $p_ign;
                $roster_uids[] = $p_uid;
            }

            $in_game_name = implode(", ", $roster_igns);
            $game_id = implode(" / ", $roster_uids);

            // 4. Lock user balance row
            $u_stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
            $u_stmt->bind_param("i", $curr_uid);
            $u_stmt->execute();
            $user_data = $u_stmt->get_result()->fetch_assoc();
            $u_stmt->close();
            $user_bal = (float)($user_data['wallet_balance'] ?? 0.00);

            // 5. Anti-Race Double Booking Checks inside Transaction
            $dup = $conn->prepare("SELECT id, slot_no FROM participants WHERE tournament_id = ? AND user_id = ? FOR UPDATE");
            $dup->bind_param("ii", $tid, $curr_uid);
            $dup->execute();
            $dup_row = $dup->get_result()->fetch_assoc();
            $dup->close();
            if ($dup_row) {
                throw new Exception("You have already joined this match (Occupying Slot #" . ($dup_row['slot_no'] ?: 1) . ")!");
            }

            // Check if slot_no is already taken
            $slot_chk = $conn->prepare("SELECT id FROM participants WHERE tournament_id = ? AND slot_no = ? FOR UPDATE");
            $slot_chk->bind_param("ii", $tid, $slot_no);
            $slot_chk->execute();
            $is_taken = ($slot_chk->get_result()->num_rows > 0);
            $slot_chk->close();
            if ($is_taken) {
                throw new Exception("Slot #" . $slot_no . " was just booked by another player! Please choose an available slot.");
            }

            // Check match capacity
            $cnt_chk = $conn->prepare("SELECT COUNT(*) AS c FROM participants WHERE tournament_id = ? FOR UPDATE");
            $cnt_chk->bind_param("i", $tid);
            $cnt_chk->execute();
            $enrolled_now = (int)($cnt_chk->get_result()->fetch_assoc()['c'] ?? 0);
            $cnt_chk->close();
            if ($enrolled_now >= $max_slots) {
                throw new Exception("Match is full! All " . $max_slots . " slots have been booked.");
            }

            // 6. Balance Verification & Atomic Barrier Deduction
            $fee = (float)$tourney['entry_fee'];
            if ($user_bal < $fee) {
                throw new Exception("Insufficient wallet balance! Please add " . format_inr($fee - $user_bal) . " to your wallet.");
            }

            if ($fee > 0) {
                $ded_stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?");
                $ded_stmt->bind_param("did", $fee, $curr_uid, $fee);
                $ded_stmt->execute();
                $deducted = ($ded_stmt->affected_rows === 1);
                $ded_stmt->close();
                if (!$deducted) {
                    throw new Exception("Insufficient wallet balance or concurrent payment modification.");
                }

                $mode_desc = $is_squad ? 'Squad' : ($is_duo ? 'Duo' : 'Solo');
                $tx_desc = "Entry Fee: " . $tourney['title'] . " (Match #" . $tid . ", Slot #" . $slot_no . " [" . $mode_desc . "])";
                $tx_stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
                $tx_stmt->bind_param("ids", $curr_uid, $fee, $tx_desc);
                $tx_stmt->execute();
                $tx_stmt->close();
            }

            // 7. Insert Participant Record
            $tourney_game = $tourney['game_name'] ?? 'Free Fire';
            $p_stmt = $conn->prepare("INSERT INTO participants (tournament_id, user_id, in_game_name, game_name, game_id, slot_no) VALUES (?, ?, ?, ?, ?, ?)");
            $p_stmt->bind_param("iisssi", $tid, $curr_uid, $in_game_name, $tourney_game, $game_id, $slot_no);
            $p_stmt->execute();
            $p_stmt->close();

            @$conn->query("INSERT INTO user_tournaments (user_id, tournament_id, game_name, slot_no) VALUES ({$curr_uid}, {$tid}, '" . $conn->real_escape_string($in_game_name) . "', {$slot_no})");

            $upd_u = $conn->prepare("UPDATE users SET game_name = ?, game_id = ? WHERE id = ?");
            if ($upd_u) {
                $leader_ign = $roster_igns[0] ?? $in_game_name;
                $leader_uid = $roster_uids[0] ?? $game_id;
                $upd_u->bind_param("ssi", $leader_ign, $leader_uid, $curr_uid);
                $upd_u->execute();
                $upd_u->close();
            }

            $conn->commit();
            $_SESSION['flash_msg'] = "Slot #" . $slot_no . " booked successfully for " . $tourney['title'] . "! Room ID will be displayed in My Matches.";
            $_SESSION['flash_type'] = "success";
            safe_redirect("index.php?tab=matches");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $alert_msg = "Booking Error: " . $e->getMessage();
            $alert_type = "error";
        }
    }
}

// 2. ACTION HANDLER: PROFILE & ACCOUNT SETTINGS
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_account_settings') {
    check_user_auth();
    $curr_uid = (int)$_SESSION['user_id'];
    $game_name = sanitize($_POST['game_name'] ?? '');
    $game_id = sanitize($_POST['game_id'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $upi_id = sanitize($_POST['upi_id'] ?? '');

    $upd = $conn->prepare("UPDATE users SET game_name = ?, game_id = ?, phone = IF(? != '', ?, phone), upi_id = IF(? != '', ?, upi_id) WHERE id = ?");
    if ($upd) {
        $upd->bind_param("ssssssi", $game_name, $game_id, $phone, $phone, $upi_id, $upi_id, $curr_uid);
        if ($upd->execute()) {
            $msg = "Account settings saved successfully!";
            $msg_type = "success";
        } else {
            $msg = "Update failed: " . $upd->error;
            $msg_type = "error";
        }
        $upd->close();
    }
}

// -------------------------------------------------------------
// 3. ACTION HANDLER: AVATAR UPDATE
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_avatar') {
    check_user_auth();
    $curr_uid = (int)$_SESSION['user_id'];
    $chosen_avatar = trim($_POST['avatar'] ?? '');
    if (!empty($chosen_avatar) && (str_starts_with($chosen_avatar, 'http') || str_starts_with($chosen_avatar, 'data:image'))) {
        $upd = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        if ($upd) {
            $upd->bind_param("si", $chosen_avatar, $curr_uid);
            if ($upd->execute()) {
                $msg = "Profile photo updated successfully!";
                $msg_type = "success";
            } else {
                $msg = "Failed to update photo: " . $upd->error;
                $msg_type = "error";
            }
            $upd->close();
        }
    }
}

// -------------------------------------------------------------
// 4. ACTION HANDLER: WITHDRAWAL REQUEST
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_withdrawal') {
    check_user_auth();
    $curr_uid = (int)$_SESSION['user_id'];
    $amount = (float)($_POST['amount'] ?? 0.00);
    $upi_id = sanitize($_POST['upi_id'] ?? '');
    $min_wdr = (float)get_setting('min_withdrawal', '50.00');

    $chk_stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $chk_stmt->bind_param("i", $curr_uid);
    $chk_stmt->execute();
    $fresh_u = $chk_stmt->get_result()->fetch_assoc();
    $chk_stmt->close();
    $avail_bal = (float)($fresh_u['wallet_balance'] ?? 0.00);

    if ($amount < $min_wdr) {
        $alert_msg = "Minimum withdrawal amount is " . format_inr($min_wdr) . ".";
        $alert_type = "error";
    } elseif (empty($upi_id) || !str_contains($upi_id, '@')) {
        $alert_msg = "Please enter a valid UPI ID (e.g. yourname@upi).";
        $alert_type = "error";
    } elseif ($avail_bal < $amount) {
        $alert_msg = "Insufficient balance. You have " . format_inr($avail_bal) . " available.";
        $alert_type = "error";
    } else {
        $conn->begin_transaction();
        try {
            $ded = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?");
            $ded->bind_param("did", $amount, $curr_uid, $amount);
            $ded->execute();
            $ded_ok = ($ded->affected_rows > 0);
            $ded->close();
            if (!$ded_ok) { throw new Exception("Insufficient balance."); }

            $w_stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, upi_id, status) VALUES (?, ?, ?, 'Pending')");
            $w_stmt->bind_param("ids", $curr_uid, $amount, $upi_id);
            $w_stmt->execute();
            $new_wdr_id = (int)$w_stmt->insert_id; // FIX: read insert_id BEFORE close() (was fatal Error -> frozen/500 page)
            $w_stmt->close();

            $tx_desc = "Withdrawal Request (Pending to " . $upi_id . ")";
            $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
            $tx->bind_param("ids", $curr_uid, $amount, $tx_desc);
            $tx->execute();
            $tx->close();

            $conn->query("UPDATE users SET upi_id = '" . $conn->real_escape_string($upi_id) . "' WHERE id = {$curr_uid}");
            $c_pid = trim($_POST["player_id"] ?? $_COOKIE["beastz_player_id"] ?? "");
            if (!empty($c_pid) && strlen($c_pid) > 15) {
                $conn->query("UPDATE withdrawals SET player_id = '" . $conn->real_escape_string($c_pid) . "' WHERE id = " . $new_wdr_id);
                $conn->query("INSERT IGNORE INTO user_devices (user_id, player_id) VALUES ({$curr_uid}, '" . $conn->real_escape_string($c_pid) . "')");
                $conn->query("UPDATE users SET player_id = '" . $conn->real_escape_string($c_pid) . "' WHERE id = {$curr_uid}");
            }
            $conn->commit();
            $alert_msg = "Withdrawal request of " . format_inr($amount) . " submitted! Payout will be sent to " . $upi_id . " within 15-30 minutes.";
            $alert_type = "success";
        } catch (Throwable $e) {
            @$conn->rollback();
            $alert_msg = "Withdrawal error: " . $e->getMessage();
            $alert_type = "error";
        }
    }
}

// -------------------------------------------------------------
// 5. FETCH USER PROFILE & WALLET DATA
// -------------------------------------------------------------
$user_ign = "";
$user_gid = "";
$my_phone = "";
$my_upi = "";
$current_balance = 0.00;
$me = [];
$my_avatar = get_avatar_url('', 'Player');

if ($curr_uid > 0) {
    $u_chk = $conn->prepare("SELECT * FROM users WHERE id = ?");
    if ($u_chk) {
        $u_chk->bind_param("i", $curr_uid);
        $u_chk->execute();
        $me = $u_chk->get_result()->fetch_assoc() ?: [];
        $u_chk->close();

        $user_ign = $me['game_name'] ?? '';
        $user_gid = $me['game_id'] ?? '';
        $my_phone = $me['phone'] ?? '';
        $my_upi = $me['upi_id'] ?? '';
        $current_balance = (float)($me['wallet_balance'] ?? 0.00);
        $my_avatar = !empty($me['avatar']) ? $me['avatar'] : get_avatar_url('', $me['username'] ?? 'Player');
    }
}

$my_ign = $user_ign;
$my_game_id = $user_gid;
$saved_upi_id = $my_upi;
$admin_upi = get_setting('upi_id', '9878385810@upi');
if (empty($admin_upi)) $admin_upi = '9878385810@upi';
$admin_name = get_setting('upi_name', 'BEASTz BATTLE');
$support_phone = get_setting('support_phone', '9878385810');

// -------------------------------------------------------------
// 6. FETCH TOURNAMENTS & BOOKED SLOTS MAP
// -------------------------------------------------------------
$joined_filter = ($curr_uid > 0) ? " AND t.id NOT IN (SELECT tournament_id FROM participants WHERE user_id = {$curr_uid}) " : "";
$tournaments_res = $conn->query("
    SELECT t.id, t.title, t.game, t.map, t.match_type, t.entry_fee, t.per_kill, t.prize_pool, t.match_time, 
    t.total_slots, t.joined_slots, t.status, 
    COALESCE(t.game_name, t.game, 'Free Fire') as display_game,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
    FROM tournaments t
    WHERE t.status != 'completed' {$joined_filter}
    ORDER BY t.match_time ASC, t.id DESC
");

$booked_slots_map = [];
$b_res = $conn->query("SELECT p.tournament_id, p.slot_no FROM participants p INNER JOIN tournaments tt ON tt.id = p.tournament_id WHERE tt.status != 'completed'"); // FIX (LAG): was loading EVERY participant ever
if ($b_res) {
    while ($brow = $b_res->fetch_assoc()) {
        $tid_b = (int)$brow['tournament_id'];
        if (!isset($booked_slots_map[$tid_b])) $booked_slots_map[$tid_b] = [];
        $booked_slots_map[$tid_b][] = (int)$brow['slot_no'];
    }
}

// -------------------------------------------------------------
// 7. FETCH USER'S REGISTERED MATCHES (Upcoming, Live, Played)
// -------------------------------------------------------------
$upcoming_matches = false;
$live_matches = false;
$played_matches = false;
$winners_by_tourney = [];

if ($curr_uid > 0) {
    $upcoming_matches = $conn->query("
        SELECT t.*, p.slot_no, p.in_game_name, p.game_id as player_game_id, p.created_at as joined_at,
        (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
        FROM participants p
        INNER JOIN tournaments t ON p.tournament_id = t.id
        WHERE p.user_id = {$curr_uid} AND LOWER(t.status) IN ('upcoming', 'pending')
        ORDER BY t.match_time ASC
    ");

    $live_matches = $conn->query("
        SELECT t.*, p.slot_no, p.in_game_name, p.game_id as player_game_id, p.created_at as joined_at,
        (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
        FROM participants p
        INNER JOIN tournaments t ON p.tournament_id = t.id
        WHERE p.user_id = {$curr_uid} AND LOWER(t.status) IN ('live', 'ongoing')
        ORDER BY t.match_time ASC
    ");

    $played_matches = $conn->query("
        SELECT t.*, p.slot_no, p.in_game_name, p.kills, p.rank_no, p.prize_won, p.game_id as player_game_id, p.created_at as joined_at,
        (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count,
        u.username as winner_name
        FROM participants p
        INNER JOIN tournaments t ON p.tournament_id = t.id
        LEFT JOIN users u ON t.winner_user_id = u.id
        WHERE p.user_id = {$curr_uid} AND LOWER(t.status) = 'completed' AND (t.match_time >= NOW() - INTERVAL 24 HOUR OR t.id >= (SELECT COALESCE(MAX(id)-15, 0) FROM tournaments))
        ORDER BY t.id DESC
    ");
}

$winners_res = $conn->query("
    SELECT p.tournament_id, p.in_game_name, p.kills, p.rank_no, p.prize_won, u.username
    FROM participants p
    INNER JOIN users u ON p.user_id = u.id
    INNER JOIN (
        SELECT id FROM tournaments WHERE LOWER(status) = 'completed' ORDER BY id DESC LIMIT 40
    ) recent ON recent.id = p.tournament_id
    WHERE p.prize_won > 0 OR (p.rank_no IS NOT NULL AND p.rank_no > 0)
    ORDER BY p.tournament_id DESC, p.rank_no ASC, p.prize_won DESC
");
if ($winners_res) {
    while ($wrow = $winners_res->fetch_assoc()) {
        $tid_w = (int)$wrow['tournament_id'];
        if (!isset($winners_by_tourney[$tid_w])) $winners_by_tourney[$tid_w] = [];
        $winners_by_tourney[$tid_w][] = $wrow;
    }
}

// -------------------------------------------------------------
// 8. FETCH TRANSACTIONS & PENDING REQUESTS
// -------------------------------------------------------------
$pending_deposits = false;
$pending_withdrawals = false;
$history_res = false;

if ($curr_uid > 0) {
    $pending_deposits = $conn->query("SELECT id, amount, transaction_id, status, created_at FROM deposits WHERE user_id = {$curr_uid} AND LOWER(status) = 'pending' ORDER BY id DESC LIMIT 5");
    $pending_withdrawals = $conn->query("SELECT id, amount, upi_id, status, created_at FROM withdrawals WHERE user_id = {$curr_uid} AND LOWER(status) = 'pending' ORDER BY id DESC LIMIT 5");
    $history_res = $conn->query("SELECT * FROM transactions WHERE user_id = {$curr_uid} ORDER BY id DESC LIMIT 15");
}

// -------------------------------------------------------------
// 9. FETCH LEADERBOARD CHAMPIONS
// -------------------------------------------------------------
$leaderboard_res = $conn->query("
    SELECT id, username, avatar, wallet_balance, winning_balance
    FROM users
    WHERE (role IS NULL OR role != 'admin')
    ORDER BY wallet_balance DESC, id ASC
    LIMIT 25
");
$players = [];
if ($leaderboard_res) {
    while ($row = $leaderboard_res->fetch_assoc()) {
        $players[] = $row;
    }
}

$my_rank = 1;
$my_earnings = $current_balance;
$my_name = !empty($me['username']) ? $me['username'] : 'Player';
foreach ($players as $idx => $p) {
    if ((int)$p['id'] === $curr_uid) {
        $my_rank = $idx + 1;
        break;
    }
}

// Active Tab determination
$current_active_tab = $requested_tab ?? ($_GET['tab'] ?? 'home');
if ($current_active_tab === 'my_tournaments' || $current_active_tab === 'my-matches' || $current_active_tab === 'tourneys') {
    $current_active_tab = 'matches';
}
require_once __DIR__ . '/common/header.php';
?>

<!-- ============================================================= -->
<!-- UNIFIED SINGLE-PAGE APPLICATION (SPA) TABS CONTAINER (0ms SWITCH) -->
<!-- ============================================================= -->

<!-- TAB 1: HOME (TOURNAMENTS LIST & SLOTS) -->
<div id="spa-tab-home" class="spa-tab-view <?= ($current_active_tab === 'home' ? '' : 'hidden') ?>">
    <?php require __DIR__ . '/views/home_view.php'; ?>
</div>

<!-- TAB 2: MY MATCHES (UPCOMING / LIVE / PLAYED) -->
<div id="spa-tab-matches" class="spa-tab-view <?= ($current_active_tab === 'matches' ? '' : 'hidden') ?>">
    <?php require __DIR__ . '/views/matches_view.php'; ?>
</div>

<!-- TAB 3: WALLET (BALANCE, ADD MONEY, WITHDRAW, PASSBOOK) -->
<div id="spa-tab-wallet" class="spa-tab-view <?= ($current_active_tab === 'wallet' ? '' : 'hidden') ?>">
    <?php require __DIR__ . '/views/wallet_view.php'; ?>
</div>

<!-- TAB 4: LEADERBOARD (HALL OF FAME & RANKING) -->
<div id="spa-tab-leaderboard" class="spa-tab-view <?= ($current_active_tab === 'leaderboard' ? '' : 'hidden') ?>">
    <?php require __DIR__ . '/views/leaderboard_view.php'; ?>
</div>

<!-- TAB 6: BEASTZ STORE (COMING SOON / REWARDS) -->
<div id="spa-tab-store" class="spa-tab-view <?= ($current_active_tab === 'store' ? '' : 'hidden') ?>">
    <?php require __DIR__ . '/views/store_view.php'; ?>
</div>

<!-- TAB 5: PROFILE & ACCOUNT SETTINGS -->
<div id="spa-tab-profile" class="spa-tab-view <?= ($current_active_tab === 'profile' ? '' : 'hidden') ?>">
    <?php require __DIR__ . '/views/profile_view.php'; ?>
</div>

<?php
require_once __DIR__ . '/common/bottom.php';
?>
