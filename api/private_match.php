<?php
/**
 * BEASTz BATTLE - Private Tournament Match Lookup API
 * Finds a tournament by its unique 6-character access code
 */
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate");

if (file_exists(__DIR__ . "/../common/config.php")) {
    require_once __DIR__ . "/../common/config.php";
}

$code = strtoupper(trim($_GET['code'] ?? $_POST['code'] ?? ''));

if (empty($code)) {
    echo json_encode([
        "success" => false,
        "error"   => "Please enter a valid match access code."
    ]);
    exit;
}

global $conn;

$stmt = $conn->prepare("
    SELECT id, title, COALESCE(game_name, game, 'Free Fire') as game_name,
           map, match_type, entry_fee, per_kill, prize_pool, match_time,
           total_slots, status, is_private, access_code
    FROM tournaments
    WHERE UPPER(access_code) = ? AND status != 'completed'
    LIMIT 1
");

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "error"   => "Database query error: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("s", $code);
$stmt->execute();
$res = $stmt->get_result();
$match = $res->fetch_assoc();
$stmt->close();

if (!$match) {
    echo json_encode([
        "success" => false,
        "error"   => "No active private match found for code: " . htmlspecialchars($code) . ". Please verify the code with your host."
    ]);
    exit;
}

$tid = (int)$match['id'];

// Fetch booked slots
$booked_slots = [];
$b_res = $conn->query("SELECT slot_no FROM participants WHERE tournament_id = {$tid}");
if ($b_res) {
    while ($row = $b_res->fetch_assoc()) {
        $booked_slots[] = (int)$row['slot_no'];
    }
}

echo json_encode([
    "success" => true,
    "match"   => [
        "id"             => (int)$match['id'],
        "title"          => $match['title'],
        "game"           => $match['game_name'],
        "map"            => $match['map'],
        "match_type"     => $match['match_type'],
        "entry_fee"      => (float)$match['entry_fee'],
        "prize_pool"     => (float)$match['prize_pool'],
        "per_kill"       => (float)$match['per_kill'],
        "total_slots"    => (int)$match['total_slots'],
        "match_time"     => $match['match_time'],
        "booked_slots"   => $booked_slots,
        "enrolled_count" => count($booked_slots)
    ]
]);
