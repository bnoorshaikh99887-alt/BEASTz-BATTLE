<?php
require_once __DIR__ . '/config.php';

$user_wallet = 0.00;
$logged_username = "";
$user_role = "";
$is_admin = false;

if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, wallet_balance, status, role, phone FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($u = $res->fetch_assoc()) {
            if (($u['status'] ?? 'active') === 'blocked') {
                safe_redirect("logout.php");
                exit;
            }
            $user_wallet = (float)($u['wallet_balance'] ?? 0.00);
            $logged_username = $u['username'] ?? '';
            $user_role = $u['role'] ?? 'user';
            
            // Check if profile setup is pending (except on complete_profile.php itself)
            $curr_script = basename($_SERVER['PHP_SELF'] ?? '');
            if ($curr_script !== 'complete_profile.php' && $curr_script !== 'logout.php') {
                if (empty($u['phone']) || strlen($u['phone']) < 10) {
                    safe_redirect("complete_profile.php");
                    exit;
                }
            }
        }
        $stmt->close();
    }
}

if (isset($_SESSION['admin_id']) || $user_role === 'admin' || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
    $is_admin = true;
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>BEASTzBATTLE - Mobile Esports Tournaments</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f5f3ff',
                            500: '#8b5cf6',
                            600: '#7c3aed',
                            700: '#6d28d9',
                            gold: '#f59e0b',
                            dark: '#0f172a',
                            card: '#1e293b'
                        }
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            touch-action: pan-y;
        }
        input, textarea, select {
            -webkit-user-select: auto !important;
            user-select: auto !important;
        }
        ::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col items-center justify-start overflow-x-hidden select-none">
    <!-- Mobile App Container (Max Width 480px) -->
    <div class="w-full max-w-md min-h-screen bg-slate-900 border-x border-slate-800 flex flex-col relative pb-20 shadow-2xl">
        <?php if ($is_admin): ?>
            <!-- Admin Top Navigation Banner -->
            <div class="bg-gradient-to-r from-rose-950 via-slate-900 to-indigo-950 border-b border-rose-500/30 px-3 py-1.5 flex items-center justify-between text-[11px] sticky top-0 z-50">
                <div class="flex items-center gap-1.5">
                    <span class="px-1.5 py-0.5 rounded bg-rose-500/20 text-rose-300 font-extrabold uppercase text-[9px] border border-rose-500/30 tracking-wider">
                        BEASTz Admin
                    </span>
                    <a href="admin/index.php" class="text-slate-300 hover:text-white ml-1 font-semibold">Panel</a>
                    <span class="text-slate-600">&bull;</span>
                    <a href="admin/tournament.php" class="text-slate-300 hover:text-white">Tourneys</a>
                    <span class="text-slate-600">&bull;</span>
                    <a href="admin/deposits.php" class="text-slate-300 hover:text-white">Deposits</a>
                    <span class="text-slate-600">&bull;</span>
                    <a href="admin/withdrawals.php" class="text-slate-300 hover:text-white">Payouts</a>
                </div>
                <!-- Admin Explicit Logout Button -->
                <a href="logout.php" title="Admin Logout" class="bg-rose-600/30 hover:bg-rose-600 text-rose-200 hover:text-white px-2.5 py-1 rounded-lg text-[10px] font-bold border border-rose-500/40 transition flex items-center gap-1">
                    <i class="fa-solid fa-power-off text-[9px]"></i>
                    <span>Logout</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- Top App Bar -->
        <header class="sticky <?= $is_admin ? 'top-8' : 'top-0' ?> z-40 bg-slate-900/95 backdrop-blur-md border-b border-slate-800/80 px-4 py-3 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-500 flex items-center justify-center text-white shadow-lg shadow-violet-500/20">
                    <i class="fa-solid fa-gamepad text-lg"></i>
                </div>
                <div>
                    <h1 class="text-lg font-black tracking-wider text-white leading-tight">BEASTz<span class="text-violet-500">BATTLE</span></h1>
                    <p class="text-[10px] text-slate-400 font-medium tracking-widest uppercase">Esports Arena</p>
                </div>
            </a>

            <div class="flex items-center gap-2">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="wallet.php" class="flex items-center gap-1.5 bg-slate-800 hover:bg-slate-700/80 border border-slate-700 px-3 py-1.5 rounded-full shadow-inner transition">
                        <i class="fa-solid fa-wallet text-amber-400 text-xs"></i>
                        <span class="text-xs font-bold text-emerald-400 font-mono"><?= format_inr($user_wallet); ?></span>
                        <i class="fa-solid fa-circle-plus text-violet-400 text-xs"></i>
                    </a>
                    <a href="logout.php" title="Sign Out" class="w-8 h-8 rounded-xl bg-slate-800/80 hover:bg-rose-500/20 border border-slate-700 hover:border-rose-500/40 text-slate-400 hover:text-rose-300 flex items-center justify-center transition">
                        <i class="fa-solid fa-power-off text-xs"></i>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="text-xs bg-violet-600 hover:bg-violet-700 text-white font-semibold px-3 py-1.5 rounded-lg shadow-md transition flex items-center gap-1.5">
                        <i class="fa-brands fa-google text-xs"></i>
                        <span>Sign In</span>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Main Body Wrapper -->
        <main class="flex-1 p-4">
