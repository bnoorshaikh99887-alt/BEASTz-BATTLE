<?php
/**
 * tournament.php (FIXED)
 *
 * Fixes:
 *  - Admin login check add (pehle bina login koi bhi tournament delete kar sakta tha)
 *  - Delete sirf POST + same-origin
 *  - Delete ab transaction mein: beech mein fail hua to kuch half-delete nahi hoga
 *  - Errors silent (@) nahi rahe
 */
require_once __DIR__ . "/common/config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$__admin_ok = !empty($_SESSION['admin_id']) || (($_SESSION['role'] ?? '') === 'admin') || !empty($_SESSION['is_super_admin']);
if (!$__admin_ok) {
    safe_redirect("login.php");
    exit;
}

$method = $_SERVER["REQUEST_METHOD"] ?? 'GET';

if ($method === "POST" && ($_POST["action"] ?? '') === "delete_tournament") {
    // same-origin check
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
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn->begin_transaction();
        $ok = true;
        foreach ([
            "DELETE FROM anti_cheat_logs WHERE tournament_id = ?",
            "DELETE FROM participants WHERE tournament_id = ?",
            "DELETE FROM user_tournaments WHERE tournament_id = ?",
            "DELETE FROM tournaments WHERE id = ?",
        ] as $sql) {
            $st = $conn->prepare($sql);
            if (!$st) {
                if ($conn->errno === 1146) continue; // table hi nahi hai -> skip
                $ok = false; error_log("delete_tournament: " . $conn->error); break;
            }
            $st->bind_param("i", $tid);
            if (!$st->execute()) { $ok = false; error_log("delete_tournament: " . $st->error); $st->close(); break; }
            $st->close();
        }
        if ($ok) { $conn->commit(); } else { $conn->rollback(); }
    }
    safe_redirect("index.php?tab=tournaments");
    exit;
}

if ($method === "GET") {
    safe_redirect("index.php?tab=tournaments");
    exit;
}

require_once __DIR__ . "/common/header.php";
require_once __DIR__ . "/views/tournaments_view.php";
safe_redirect("index.php?tab=tournaments");
exit;
