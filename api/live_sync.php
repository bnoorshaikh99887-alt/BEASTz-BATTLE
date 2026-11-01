<?php
/**
 * BEASTz BATTLE - Real-Time Live Sync & AJAX Auto-Refresh API
 * High-speed background polling for Wallet, Leaderboard, and Admin Control Center
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../common/config.php';

global $conn;
if (!$conn || $conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection offline'
    ]);
    exit;
}

$response = [
    'success' => true,
    'timestamp' => time(),
    'role' => $_SESSION['role'] ?? 'guest',
    'active_panel' => $_SESSION['active_panel'] ?? ($_COOKIE['active_panel'] ?? 'user'),
    'user' => null,
    'leaderboard' => [],
    'admin' => null
];

// =============================================================
// 1. USER LIVE STATUS & WALLET DATA
// =============================================================
$curr_uid = (int)($_SESSION['user_id'] ?? 0);
if ($curr_uid > 0) {
    $u_stmt = $conn->prepare("SELECT id, username, wallet_balance, COALESCE(winning_balance, 0.00) as winning_balance, status, role, avatar FROM users WHERE id = ?");
    if ($u_stmt) {
        $u_stmt->bind_param("i", $curr_uid);
        $u_stmt->execute();
        $user_row = $u_stmt->get_result()->fetch_assoc();
        $u_stmt->close();

        if ($user_row) {
            $bal = (float)$user_row['wallet_balance'];
            
            // Fetch Pending Withdrawals for current user
            $pw_stmt = $conn->prepare("SELECT id, amount, upi_id, status, created_at FROM withdrawals WHERE user_id = ? AND LOWER(status) = 'pending' ORDER BY id DESC");
            $pw_list = [];
            if ($pw_stmt) {
                $pw_stmt->bind_param("i", $curr_uid);
                $pw_stmt->execute();
                $pw_res = $pw_stmt->get_result();
                while ($pwr = $pw_res->fetch_assoc()) {
                    $pw_list[] = [
                        'id' => (int)$pwr['id'],
                        'amount' => (float)$pwr['amount'],
                        'formatted_amount' => format_inr($pwr['amount']),
                        'upi_id' => $pwr['upi_id'],
                        'status' => $pwr['status'],
                        'created_at' => $pwr['created_at']
                    ];
                }
                $pw_stmt->close();
            }

            // Check if user recently had a withdrawal approved in the last 60 seconds
            $recent_completed = null;
            $rc_stmt = $conn->prepare("SELECT id, amount, upi_id, created_at FROM withdrawals WHERE user_id = ? AND LOWER(status) = 'completed' ORDER BY id DESC LIMIT 1");
            if ($rc_stmt) {
                $rc_stmt->bind_param("i", $curr_uid);
                $rc_stmt->execute();
                if ($rc_row = $rc_stmt->get_result()->fetch_assoc()) {
                    $recent_completed = [
                        'id' => (int)$rc_row['id'],
                        'amount' => (float)$rc_row['amount'],
                        'formatted_amount' => format_inr($rc_row['amount']),
                        'upi_id' => $rc_row['upi_id']
                    ];
                }
                $rc_stmt->close();
            }

            $response['user'] = [
                'logged_in' => true,
                'user_id' => $curr_uid,
                'username' => $user_row['username'],
                'wallet_balance' => $bal,
                'formatted_balance' => format_inr($bal),
                'compact_balance' => format_compact_inr($bal),
                'status' => $user_row['status'],
                'role' => $user_row['role'],
                'pending_withdrawals' => $pw_list,
                'pending_count' => count($pw_list),
                'recent_completed_withdrawal' => $recent_completed
            ];
        }
    }
}

// =============================================================
// 2. LIVE LEADERBOARD / POINT TABLE (TOP 20)
// =============================================================
$lb_res = $conn->query("
    SELECT id, username, wallet_balance, avatar 
    FROM users 
    WHERE role != 'admin' OR role IS NULL 
    ORDER BY wallet_balance DESC, id ASC 
    LIMIT 20
");
$lb_list = [];
$my_rank = 1;
$rank_counter = 1;

if ($lb_res) {
    while ($p = $lb_res->fetch_assoc()) {
        $p_uid = (int)$p['id'];
        $is_me = ($p_uid === $curr_uid);
        if ($is_me) {
            $my_rank = $rank_counter;
        }

        $lb_list[] = [
            'rank' => $rank_counter,
            'id' => $p_uid,
            'username' => $p['username'],
            'wallet_balance' => (float)$p['wallet_balance'],
            'formatted_balance' => format_compact_inr($p['wallet_balance']),
            'avatar' => get_avatar_url($p['avatar'] ?? '', $p['username']),
            'is_me' => $is_me
        ];
        $rank_counter++;
    }
}
$response['leaderboard'] = $lb_list;
if ($response['user']) {
    $response['user']['my_rank'] = $my_rank;
}

// =============================================================
// 3. ADMIN CONTROL CENTER METRICS (FOR ADMIN USERS)
// =============================================================
$is_admin = (isset($_SESSION['admin_id']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'));
if ($is_admin) {
    $stats_q = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM users) as users_cnt,
            (SELECT COUNT(*) FROM tournaments) as tourneys_cnt,
            (SELECT COALESCE(SUM(prize_pool), 0.00) FROM tournaments WHERE LOWER(status) = 'completed') as prize_dist,
            (SELECT COALESCE(SUM(amount), 0.00) FROM deposits WHERE LOWER(status) IN ('completed', 'success')) as total_collected,
            (SELECT COUNT(*) FROM deposits WHERE LOWER(status) = 'pending') as pending_deposits,
            (SELECT COUNT(*) FROM withdrawals WHERE LOWER(status) = 'pending') as pending_withdrawals
    ");

    $st = $stats_q ? $stats_q->fetch_assoc() : [];
    $p_dep = (int)($st['pending_deposits'] ?? 0);
    $p_wdr = (int)($st['pending_withdrawals'] ?? 0);
    $u_cnt = (int)($st['users_cnt'] ?? 0);
    $t_cnt = (int)($st['tourneys_cnt'] ?? 0);
    $tot_coll = (float)($st['total_collected'] ?? 0.00);
    $prz_dist = (float)($st['prize_dist'] ?? 0.00);
    $tot_rev = max(0, $tot_coll - $prz_dist);

    // Fetch latest pending payouts list for admin real-time table sync
    $pending_payouts_list = [];
    $payout_q = $conn->query("
        SELECT w.id, w.user_id, w.amount, w.upi_id, w.status, w.created_at, w.is_admin_request, u.username
        FROM withdrawals w
        LEFT JOIN users u ON w.user_id = u.id
        WHERE LOWER(w.status) = 'pending'
        ORDER BY w.is_admin_request DESC, w.id DESC
        LIMIT 10
    ");
    if ($payout_q) {
        while ($pw = $payout_q->fetch_assoc()) {
            $pending_payouts_list[] = [
                'id' => (int)$pw['id'],
                'user_id' => (int)$pw['user_id'],
                'username' => $pw['username'] ?: 'Unknown Gamer',
                'amount' => (float)$pw['amount'],
                'formatted_amount' => format_inr($pw['amount']),
                'upi_id' => $pw['upi_id'],
                'created_at' => date("d M Y, h:i A", strtotime($pw['created_at'])),
                'is_admin_request' => (int)($pw['is_admin_request'] ?? 0)
            ];
        }
    }

    $response['admin'] = [
        'is_admin' => true,
        'users_cnt' => $u_cnt,
        'tourneys_cnt' => $t_cnt,
        'total_revenue' => $tot_rev,
        'formatted_revenue' => format_compact_inr($tot_rev),
        'prize_dist' => $prz_dist,
        'formatted_prize_dist' => format_compact_inr($prz_dist),
        'pending_deposits' => $p_dep,
        'pending_withdrawals' => $p_wdr,
        'pending_payouts_list' => $pending_payouts_list
    ];
}

echo json_encode($response);
exit;
