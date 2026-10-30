<?php
require_once __DIR__ . '/config.php';

$user_wallet = 0.00;
$logged_username = "";
$user_role = "";
$user_avatar = "";
$is_admin = false;

if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, wallet_balance, status, role, phone, avatar, game_name, game_id FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($u = $res->fetch_assoc()) {
            if (($u['role'] ?? '') !== 'admin' && ($u['status'] ?? 'active') === 'blocked') {
                safe_redirect("logout.php");
                exit;
            }
            $user_wallet = (float)($u['wallet_balance'] ?? 0.00);
            $logged_username = $u['username'] ?? '';
            $user_role = $u['role'] ?? 'user';
            $user_avatar = !empty($u['avatar']) ? $u['avatar'] : 'https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($logged_username);

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
} else {
    $user_avatar = get_avatar_url('', 'Guest');
}

if (isset($_SESSION['admin_id']) || $user_role === 'admin' || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
    $is_admin = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>BEASTzBATTLE - Esports Arena</title>
    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        display: ['"Outfit"', 'sans-serif']
                    },
                    colors: {
                        theme: {
                            yellow: '#facc15',
                            'yellow-dark': '#eab308',
                            amber: '#f59e0b',
                            slate: '#0f172a',
                            light: '#f8fafc',
                            card: '#ffffff',
                            border: '#e2e8f0'
                        }
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { font-family: 'Plus Jakarta Sans', sans-serif; }
        h1, h2, h3, .font-display { font-family: 'Outfit', sans-serif; }
        body {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
            touch-action: pan-y;
            background-color: #f1f5f9;
        }
        input, textarea, select {
            -webkit-user-select: auto !important;
            user-select: auto !important;
        }
        ::-webkit-scrollbar { display: none; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex flex-col items-center justify-start overflow-x-hidden">
    <!-- Mobile App Container (Max Width 440px) -->
    <div class="w-full max-w-md min-h-screen bg-slate-50 border-x border-slate-200 flex flex-col relative pb-20 shadow-xl">
        
        <?php if ($is_admin): ?>
            <!-- Admin Top Navigation Strip -->
            <div class="bg-slate-900 border-b border-yellow-500/40 px-3 py-1.5 flex items-center justify-between text-[11px] sticky top-0 z-50 text-white">
                <div class="flex items-center gap-1.5">
                    <span class="px-1.5 py-0.5 rounded bg-yellow-400 text-slate-950 font-black uppercase text-[9px] tracking-wider">
                        ADMIN
                    </span>
                    <a href="admin/index.php" class="text-slate-200 hover:text-white font-semibold">Panel</a>
                    <span class="text-slate-500">&bull;</span>
                    <a href="admin/tournament.php" class="text-slate-300 hover:text-white">Tourneys</a>
                    <span class="text-slate-500">&bull;</span>
                    <a href="admin/deposits.php" class="text-slate-300 hover:text-white">Deposits</a>
                </div>
                <a href="logout.php" title="Sign Out" class="text-rose-400 hover:text-rose-300 text-[10px] font-bold flex items-center gap-1">
                    <i class="fa-solid fa-power-off text-[9px]"></i>
                    <span>Logout</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- App Header (Matching Video: Avatar Left, Brand Center, Wallet Right) -->
        <header class="sticky <?= $is_admin ? 'top-7' : 'top-0' ?> z-40 bg-white/95 backdrop-blur-md border-b border-slate-200 px-4 py-2.5 flex items-center justify-between shadow-xs">
            <!-- Left: Avatar leading to Profile -->
            <a href="profile.php" title="My Profile & Account Settings" class="flex items-center gap-2 group">
                <div class="relative">
                    <img src="<?= htmlspecialchars($user_avatar); ?>" alt="Avatar" class="w-9 h-9 rounded-full object-cover border-2 border-yellow-400 shadow-sm bg-slate-100">
                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 border-2 border-white rounded-full"></span>
                </div>
                <div class="hidden sm:block text-left">
                    <span class="text-xs font-bold text-slate-900 block leading-tight">
                        <?= !empty($logged_username) ? htmlspecialchars($logged_username) : 'Guest Player'; ?>
                    </span>
                    <span class="text-[10px] text-slate-400">View Profile</span>
                </div>
            </a>

            <!-- Center: App Logo / Title -->
            <a href="index.php" class="flex items-center gap-1.5">
                <div class="w-7 h-7 rounded-lg bg-yellow-400 flex items-center justify-center text-slate-950 shadow-xs font-black">
                    <i class="fa-solid fa-gamepad text-xs"></i>
                </div>
                <span class="text-base font-black tracking-tight text-slate-900 font-display">
                    BEASTz<span class="text-yellow-500">BATTLE</span>
                </span>
            </a>

            <!-- Right: Wallet Button / Balance (Matching Video) -->
            <div class="flex items-center gap-2">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="wallet.php" class="flex items-center gap-1.5 bg-slate-100 hover:bg-yellow-50 border border-slate-200 hover:border-yellow-400 px-3 py-1.5 rounded-xl shadow-xs transition group">
                        <i class="fa-solid fa-wallet text-yellow-500 text-xs group-hover:scale-110 transition"></i>
                        <span class="text-xs font-extrabold text-slate-900 font-mono"><?= format_compact_inr($user_wallet); ?></span>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="text-xs bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black px-3 py-1.5 rounded-xl shadow-xs transition flex items-center gap-1.5">
                        <i class="fa-brands fa-google text-xs"></i>
                        <span>Login</span>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 p-3.5">
