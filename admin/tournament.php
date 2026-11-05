<?php
/**
 * BEASTz BATTLE - Tournament Management & Deletion Engine
 * Enforces Strong Admin Auth + Atomic Entry Fee Refund for All Enrolled Participants
 */
require_once __DIR__ . "/common/config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_admin = !empty($_SESSION['admin_id']) || 
            ((isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) || 
            !empty($_SESSION['is_super_admin']) || 
            !empty($_SESSION['admin_role']);

if (!$is_admin) {
    safe_redirect("../login.php");
    exit;
}

$method = $_SERVER["REQUEST_METHOD"] ?? 'GET';

if ($method === "POST" && ($_POST["action"] ?? '') === "delete_tournament") {
    // CSRF / Same-origin check
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        $o_host = parse_url($origin, PHP_URL_HOST);
        $h_host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
        if (!$o_host || strcasecmp($o_host, $h_host) !== 0) {
            http_response_code(403);
            exit("Blocked");
        }
    }

    $tid = (int)($_POST["tournament_id"] ?? 0);
    if ($tid > 0) {
        $conn->begin_transaction();
        try {
            // 1. Lock tournament and get details
            $t_stmt = $conn->prepare("SELECT id, title, entry_fee, status FROM tournaments WHERE id = ? FOR UPDATE");
            $t_stmt->bind_param("i", $tid);
            $t_stmt->execute();
            $tourney = $t_stmt->get_result()->fetch_assoc();
            $t_stmt->close();

            if (!$tourney) {
                throw new Exception("Tournament not found.");
            }

            $fee = (float)($tourney['entry_fee'] ?? 0.00);

            // 2. ATOMIC REFUND: If match had entry fee, refund every enrolled participant
            if ($fee > 0) {
                $p_stmt = $conn->prepare("SELECT user_id FROM participants WHERE tournament_id = ?");
                $p_stmt->bind_param("i", $tid);
                $p_stmt->execute();
                $p_res = $p_stmt->get_result();

                $refund_users = [];
                while ($pr = $p_res->fetch_assoc()) {
                    $uid = (int)$pr['user_id'];
                    if ($uid > 0) {
                        $refund_users[] = $uid;
                    }
                }
                $p_stmt->close();

                $refund_users = array_values(array_unique($refund_users));

                foreach ($refund_users as $p_uid) {
                    // Atomic wallet credit
                    $upd_u = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $upd_u->bind_param("di", $fee, $p_uid);
                    $upd_u->execute();
                    $upd_u->close();

                    // Passbook transaction entry
                    $tx_desc = "Refund: Match #" . $tid . " (" . $tourney['title'] . ") Cancelled by Admin";
                    $tx = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
                    $tx->bind_param("ids", $p_uid, $fee, $tx_desc);
                    $tx->execute();
                    $tx->close();
                }
            }

            // 3. Delete dependent rows and tournament
            @$conn->query("DELETE FROM anti_cheat_logs WHERE tournament_id = {$tid}");
            @$conn->query("DELETE FROM participants WHERE tournament_id = {$tid}");
            @$conn->query("DELETE FROM user_tournaments WHERE tournament_id = {$tid}");
            $conn->query("DELETE FROM tournaments WHERE id = {$tid}");

            $conn->commit();
            $_SESSION['flash_msg'] = "Tournament #" . $tid . " deleted successfully. All participant entry fees refunded!";
            $_SESSION['flash_type'] = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_msg'] = "Delete Error: " . $e->getMessage();
            $_SESSION['flash_type'] = "error";
        }
    }
    safe_redirect("index.php?tab=tournaments");
    exit;
}

safe_redirect("index.php?tab=tournaments");
exit;
