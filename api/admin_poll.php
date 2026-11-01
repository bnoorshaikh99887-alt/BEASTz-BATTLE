<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../common/config.php';

global $conn;
if (!$conn || $conn->connect_error) {
    echo json_encode(['success' => false]);
    exit;
}

$pw = $conn->query("SELECT COUNT(*) as cnt, MAX(amount) as latest_amount FROM withdrawals WHERE LOWER(status) = 'pending'");
$pw_row = $pw ? $pw->fetch_assoc() : ['cnt' => 0, 'latest_amount' => 0];

$pd = $conn->query("SELECT COUNT(*) as cnt FROM deposits WHERE LOWER(status) = 'pending'");
$pd_row = $pd ? $pd->fetch_assoc() : ['cnt' => 0];

echo json_encode([
    'success' => true,
    'pending_withdrawals' => (int)($pw_row['cnt'] ?? 0),
    'latest_amount' => (float)($pw_row['latest_amount'] ?? 0),
    'pending_deposits' => (int)($pd_row['cnt'] ?? 0)
]);
?>