<?php
require_once __DIR__ . "/common/config.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "delete_tournament") {
    $tid = (int)($_POST["tournament_id"] ?? 0);
    if ($tid > 0) {
        @$conn->query("DELETE FROM anti_cheat_logs WHERE tournament_id = $tid");
        @$conn->query("DELETE FROM participants WHERE tournament_id = $tid");
        @$conn->query("DELETE FROM user_tournaments WHERE tournament_id = $tid");
        @$conn->query("DELETE FROM tournaments WHERE id = $tid");
    }
    safe_redirect("index.php?tab=tournaments");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    safe_redirect("index.php?tab=tournaments");
    exit;
}

require_once __DIR__ . "/common/header.php";
require_once __DIR__ . "/views/tournaments_view.php";
safe_redirect("index.php?tab=tournaments");
exit;
?>