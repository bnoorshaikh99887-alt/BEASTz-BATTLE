<?php
session_start();
require_once 'db.php'; // Aapki database connection file

if (isset($_SESSION['user_id']) && isset($_POST['player_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $player_id = trim($_POST['player_id']);

    if (!empty($player_id)) {
        $stmt = $conn->prepare("UPDATE users SET player_id = ? WHERE id = ?");
        $stmt->bind_param("si", $player_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}
?>
