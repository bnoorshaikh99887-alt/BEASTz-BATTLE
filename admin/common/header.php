<?php
require_once __DIR__ . '/config.php';

// Strictly Enforce Admin Authentication
$is_admin = false;
if (isset($_SESSION['admin_id']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
    $is_admin = true;
}

if (!$is_admin) {
    safe_redirect("../login.php");
    exit;
}

$user_wallet = 0.00;
$logged_username = $_SESSION['admin_user'] ?? $_SESSION['username'] ?? 'Admin';
$uid = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
if ($uid > 0 && $conn && !$conn->connect_error) {
    $stmt = $conn->prepare("SELECT username, wallet_balance, status, role FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($u = $res->fetch_assoc()) {
            if (($u['status'] ?? 'active') === 'blocked') {
                safe_redirect("/logout.php");
                exit;
            }
            $user_wallet = (float)($u['wallet_balance'] ?? 0.00);
            $logged_username = $u['username'] ?? $logged_username;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Control Center - BEASTzBATTLE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { -webkit-touch-callout: none; user-select: none; }
        input, textarea, select { user-select: auto !important; }
        ::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col items-center justify-start overflow-x-hidden">
    <div class="w-full max-w-md min-h-screen bg-slate-900 border-x border-slate-800 flex flex-col relative pb-20 shadow-2xl">
        <!-- Admin Top Navigation Banner -->
        <div class="bg-gradient-to-r from-rose-950 via-slate-900 to-indigo-950 border-b border-rose-500/30 px-3 py-2 flex items-center justify-between text-[11px] sticky top-0 z-50">
            <div class="flex items-center gap-1.5">
                <span class="px-1.5 py-0.5 rounded bg-rose-500/20 text-rose-300 font-extrabold uppercase text-[9px] border border-rose-500/30">
                    BEASTz Admin
                </span>
                <a href="index.php" class="text-slate-300 hover:text-white ml-1 font-semibold">Dashboard</a>
                <span class="text-slate-600">&bull;</span>
                <a href="tournament.php" class="text-slate-300 hover:text-white">Tourneys</a>
                <span class="text-slate-600">&bull;</span>
                <a href="deposits.php" class="text-slate-300 hover:text-white">Deposits</a>
                <span class="text-slate-600">&bull;</span>
                <a href="withdrawals.php" class="text-slate-300 hover:text-white">Payouts</a>
            </div>

            <!-- Prominent Red Logout Button -->
            <a href="/logout.php" title="Sign Out" class="bg-rose-600 hover:bg-rose-500 text-white px-2.5 py-1 rounded-lg text-[10px] font-bold shadow transition flex items-center gap-1">
                <i class="fa-solid fa-power-off text-[9px]"></i>
                <span>Logout</span>
            </a>
        </div>

        <main class="flex-1 p-4">
