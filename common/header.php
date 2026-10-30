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

$is_native_apk = (str_contains($_SERVER['HTTP_USER_AGENT'] ?? '', 'BEASTzNativeApp'));

if (isset($_SESSION['admin_id']) || $user_role === 'admin' || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
    $is_admin = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>BEASTzBATTLE - Esports Arena</title>

    <!-- Immediate Zero-Flash Dark Mode Switcher -->
    <script src="/theme.js"></script>

    <!-- PWA & Mobile Web App Meta -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#facc15">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="BEASTz BATTLE">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png">

    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
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
    
    <!-- Unified Dark/Light Mode Theme Stylesheet -->
    <link rel="stylesheet" href="/theme.css">

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
    <!-- BEASTz BATTLE Ultra-Stylish Glowing Esports Intro / Splash Screen -->
    <div id="beastz-splash-screen" class="fixed inset-0 z-[9999] bg-[#05070e] flex flex-col items-center justify-center transition-opacity duration-700 overflow-hidden" style="background: radial-gradient(circle at 50% 45%, rgba(250, 204, 21, 0.25) 0%, rgba(7, 10, 18, 0.95) 60%, #05070e 100%);">
        <!-- Ambient Glowing Aura Rings -->
        <div class="absolute w-72 h-72 rounded-full bg-yellow-400/10 blur-3xl animate-pulse pointer-events-none"></div>
        
        <div class="relative flex flex-col items-center z-10 px-6 text-center">
            <!-- Glowing Rotating Cyber Shield Badge -->
            <div class="relative mb-5">
                <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-yellow-400 via-amber-400 to-yellow-500 text-slate-950 flex items-center justify-center text-4xl shadow-[0_0_50px_rgba(250,204,21,0.85)] border-2 border-white/40 transform transition hover:scale-105">
                    <i class="fa-solid fa-gamepad text-slate-950 drop-shadow-md"></i>
                </div>
                <!-- Dual Outer Neon Rings -->
                <div class="absolute -inset-2 rounded-[28px] border-2 border-yellow-400/60 animate-ping" style="animation-duration: 2.5s;"></div>
                <div class="absolute -inset-4 rounded-[34px] border border-yellow-400/30"></div>
            </div>

            <!-- Glowing Logo Typography -->
            <h1 class="text-3xl sm:text-4xl font-black tracking-wider font-display text-transparent bg-clip-text bg-gradient-to-r from-yellow-300 via-amber-400 to-yellow-500 drop-shadow-[0_0_30px_rgba(250,204,21,0.7)]">
                BEASTz<span class="text-white drop-shadow-[0_0_20px_rgba(255,255,255,0.8)]">BATTLE</span>
            </h1>
            
            <div class="flex items-center gap-2 mt-2">
                <span class="h-[1px] w-6 bg-gradient-to-r from-transparent to-yellow-400"></span>
                <span class="text-[10px] font-black uppercase tracking-[0.25em] text-yellow-400 font-mono">
                    ESPORTS ARENA &bull; BATTLE GROUND
                </span>
                <span class="h-[1px] w-6 bg-gradient-to-l from-transparent to-yellow-400"></span>
            </div>

            <!-- Laser Power Energy Bar -->
            <div class="w-48 h-1.5 bg-slate-900 rounded-full mt-7 overflow-hidden p-0.5 border border-yellow-500/40 shadow-[0_0_15px_rgba(250,204,21,0.3)]">
                <div class="h-full bg-gradient-to-r from-yellow-400 to-amber-300 rounded-full animate-pulse" style="width: 85%; box-shadow: 0 0 10px #facc15;"></div>
            </div>

            <div class="mt-3 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-yellow-400 animate-ping"></span>
                <span class="text-[9px] font-extrabold text-slate-300 uppercase tracking-widest font-mono">CONNECTING TO ARENA...</span>
            </div>
        </div>
    </div>
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                var splash = document.getElementById('beastz-splash-screen');
                if (splash) {
                    splash.style.opacity = '0';
                    splash.style.pointerEvents = 'none';
                    setTimeout(function() { splash.remove(); }, 600);
                }
            }, 750);
        });
    </script>
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
                <div class="flex items-center gap-2">
                    <button type="button" onclick="toggleTheme()" title="Toggle Dark/Light Mode" class="text-yellow-400 hover:text-yellow-300 text-[10px] flex items-center gap-1">
                        <i class="fa-solid fa-moon text-[10px] beastz-theme-icon"></i>
                    </button>
                    <a href="logout.php" title="Sign Out" class="text-rose-400 hover:text-rose-300 text-[10px] font-bold flex items-center gap-1">
                        <i class="fa-solid fa-power-off text-[9px]"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$is_native_apk): ?>
        <!-- Web APK Download Banner (Web Only, Never inside APK) -->
        <div id="apk-download-banner" class="bg-slate-900 border-b border-yellow-500/50 px-3.5 py-2 text-white flex items-center justify-between text-xs sticky top-0 z-50 shadow-md">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-yellow-400 text-slate-950 flex items-center justify-center text-sm shadow-xs shrink-0">
                    <i class="fa-brands fa-android"></i>
                </div>
                <div class="font-black text-xs sm:text-sm text-yellow-400 font-display uppercase tracking-wide">
                    BEASTzBATTLE APK DOWNLOAD
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="/BEASTz-BATTLE.apk" download="BEASTz-BATTLE.apk" class="bg-yellow-400 hover:bg-yellow-500 active:scale-95 text-slate-950 font-black text-[11px] px-3 py-1.5 rounded-lg shadow-sm transition flex items-center gap-1.5">
                    <i class="fa-solid fa-download text-[10px]"></i>
                    <span>DOWNLOAD</span>
                </a>
                <button type="button" onclick="document.getElementById('apk-download-banner').style.display='none'" class="text-slate-400 hover:text-white p-1 text-xs" title="Dismiss">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <script>
            if (navigator.userAgent.indexOf('BEASTzNativeApp') !== -1) {
                var b = document.getElementById('apk-download-banner');
                if (b) b.style.display = 'none';
            }
        </script>
        <?php endif; ?>

        <!-- App Header (Matching Video: Avatar Left, Brand Center, Wallet Right) -->
        <header class="sticky <?= $is_admin ? 'top-7' : 'top-0' ?> z-40 bg-white/95 backdrop-blur-md border-b border-slate-200 px-4 py-2.5 flex items-center justify-between shadow-xs">
            <!-- Left: Avatar leading to Profile -->
            <button type="button" onclick="switchAppTab('profile')" title="My Profile & Account Settings" class="flex items-center gap-2 group cursor-pointer text-left">
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
            </button>

            <!-- Center: App Logo / Title -->
            <button type="button" onclick="switchAppTab('home')" class="flex items-center gap-1.5 cursor-pointer">
                <div class="w-7 h-7 rounded-lg bg-yellow-400 flex items-center justify-center text-slate-950 shadow-xs font-black">
                    <i class="fa-solid fa-gamepad text-xs"></i>
                </div>
                <span class="text-base font-black tracking-tight text-slate-900 font-display">
                    BEASTz<span class="text-yellow-500">BATTLE</span>
                </span>
            </button>

            <!-- Right: Dark Mode Toggle & Wallet Button -->
            <div class="flex items-center gap-1.5 sm:gap-2">
                <!-- Dark Mode Quick Toggle Button -->
                <button type="button" onclick="toggleTheme()" id="theme-toggle-btn" title="Toggle Dark / Light Theme" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-yellow-50 border border-slate-200 hover:border-yellow-400 flex items-center justify-center text-slate-700 hover:text-yellow-500 transition active:scale-90 shadow-2xs">
                    <i class="fa-solid fa-moon text-xs beastz-theme-icon"></i>
                </button>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <button type="button" onclick="switchAppTab('wallet')" class="flex items-center gap-1.5 bg-slate-100 hover:bg-yellow-50 border border-slate-200 hover:border-yellow-400 px-2.5 sm:px-3 py-1.5 rounded-xl shadow-xs transition group cursor-pointer">
                        <i class="fa-solid fa-wallet text-yellow-500 text-xs group-hover:scale-110 transition"></i>
                        <span id="header-wallet-balance" class="text-xs font-extrabold text-slate-900 font-mono"><?= format_compact_inr($user_wallet); ?></span>
                    </button>
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
