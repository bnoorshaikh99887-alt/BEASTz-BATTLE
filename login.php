<?php
session_start();
require_once __DIR__ . '/common/config.php';

// Agar Worker login hai, toh Worker panel bhejo
if (isset($_SESSION['is_worker']) && $_SESSION['is_worker']) {
    header("Location: worker.php");
    exit;
}
if (isset($_SESSION['active_panel']) && $_SESSION['active_panel'] === 'worker') {
    header("Location: worker.php");
    exit;
}
// Agar Admin login hai, toh Admin panel bhejo
if (isset($_SESSION['admin_id'])) {
    if (!empty($_SESSION['is_super_admin'])) {
        header("Location: admin/index.php");
    } else {
        header("Location: worker.php");
    }
    exit;
}
// Agar User login hai, toh User panel (Arena) bhejo
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = "";
$success = "";
$active_tab = $_POST['tab'] ?? ($_GET['tab'] ?? 'login');

if (isset($_GET['logged_out'])) {
    $success = "You have been logged out successfully.";
}

// Custom sanitize function fallback in case it's missing in config
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}

// =============================================================
// 1. SIGN IN HANDLER (UNIFIED: MASTER ADMIN, HOSTS & GAMERS)
// =============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'do_login') {
    $identity = trim(sanitize($_POST['identity'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    if (empty($identity) || empty($password)) {
        $error = "Please enter your username/phone and password.";
    } else {
        // 1. Check Master Admin Credentials
        if (($identity === 'anonymous.349' || $identity === 'noorshaikh99887@gmail.com') && $password === 'noor009988@') {
            unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['user_logged_in']);
            
            $_SESSION['admin_id'] = 1;
            $_SESSION['admin_user'] = 'anonymous.349';
            $_SESSION['admin_username'] = 'anonymous.349';
            $_SESSION['admin_role'] = 'super_admin';
            $_SESSION['is_super_admin'] = true;
            $_SESSION['admin_logged_in'] = true;
            
            if (function_exists('set_user_remember_token')) { set_user_remember_token(1, 'admin'); }
            $_SESSION['active_panel'] = 'admin';
            setcookie('active_panel', 'admin', time() + (365 * 24 * 3600), '/');
            setcookie('beastz_active_panel', 'admin', time() + (365 * 24 * 3600), '/');
            header("Location: admin/index.php");
            exit;
        }

        // 2. Check admin table for Sub-Admins / Hosts
        $adm_chk = $conn->prepare("SELECT id, username, password, role, status FROM admin WHERE username = ? LIMIT 1");
        if ($adm_chk) {
            $adm_chk->bind_param("s", $identity);
            $adm_chk->execute();
            $adm_row = $adm_chk->get_result()->fetch_assoc();
            $adm_chk->close();

            if ($adm_row) {
                if (($adm_row['status'] ?? 'active') === 'suspended') {
                    $error = "This administrator account has been suspended.";
                } else {
                    $match = false;
                    if (password_verify($password, $adm_row['password']) || $password === $adm_row['password']) {
                        $match = true;
                    }
                    if ($match) {
                        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['user_logged_in']);
                        
                        $is_super = ($adm_row['username'] === 'anonymous.349' || $adm_row['username'] === 'admin' || $adm_row['role'] === 'super_admin');
                        $_SESSION['admin_id'] = (int)$adm_row['id'];
                        $_SESSION['admin_user'] = $adm_row['username'];
                        $_SESSION['admin_username'] = $adm_row['username'];
                        $_SESSION['admin_role'] = $adm_row['role'] ?: ($is_super ? 'super_admin' : 'worker');
                        $_SESSION['is_super_admin'] = $is_super;
                        $_SESSION['admin_logged_in'] = true;
                        
                        if ($is_super) {
                            $_SESSION['active_panel'] = 'admin';
                            setcookie('active_panel', 'admin', time() + (365 * 24 * 3600), '/');
                            setcookie('beastz_active_panel', 'admin', time() + (365 * 24 * 3600), '/');
                            if (function_exists('set_user_remember_token')) { set_user_remember_token((int)$adm_row['id'], 'admin'); }
                            header("Location: admin/index.php");
                        } else {
                            $_SESSION['worker_id'] = (int)$adm_row['id'];
                            $_SESSION['is_worker'] = true;
                            $_SESSION['active_panel'] = 'worker';
                            setcookie('active_panel', 'worker', time() + (365 * 24 * 3600), '/');
                            setcookie('beastz_active_panel', 'worker', time() + (365 * 24 * 3600), '/');
                            if (function_exists('set_user_remember_token')) { set_user_remember_token((int)$adm_row['id'], 'admin'); }
                            header("Location: worker.php");
                        }
                        exit;
                    }
                }
            }
        }

        // 3. Check users table for Gamer / Player Accounts
        $u_stmt = $conn->prepare("SELECT id, username, email, phone, password, role, status FROM users WHERE username = ? OR phone = ? OR email = ? LIMIT 1");
        if ($u_stmt) {
            $u_stmt->bind_param("sss", $identity, $identity, $identity);
            $u_stmt->execute();
            $u = $u_stmt->get_result()->fetch_assoc();
            $u_stmt->close();

            if ($u) {
                if (($u['status'] ?? 'active') === 'blocked') {
                    $error = "Your account is temporarily suspended. Contact support.";
                } else {
                    $pass_valid = false;
                    if (password_verify($password, $u['password']) || $password === $u['password'] || ($u['username'] === 'anonymous.349' && $password === 'noor009988@')) {
                        $pass_valid = true;
                    }

                    if ($pass_valid) {
                        $uid = (int)$u['id'];
                        
                        if (($u['role'] ?? '') === 'admin') {
                            unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['user_logged_in']);
                            
                            $is_super = ($u['username'] === 'anonymous.349' || $u['username'] === 'admin');
                            $_SESSION['admin_id'] = $uid;
                            $_SESSION['admin_user'] = $u['username'];
                            $_SESSION['admin_username'] = $u['username'];
                            $_SESSION['admin_role'] = $is_super ? 'super_admin' : 'worker';
                            $_SESSION['is_super_admin'] = $is_super;
                            $_SESSION['admin_logged_in'] = true;
                            
                            if (function_exists('set_user_remember_token')) { set_user_remember_token($uid, 'admin'); }
                            if ($is_super) {
                                $_SESSION['active_panel'] = 'admin';
                                setcookie('active_panel', 'admin', time() + (365 * 24 * 3600), '/');
                                setcookie('beastz_active_panel', 'admin', time() + (365 * 24 * 3600), '/');
                                header("Location: admin/index.php");
                            } else {
                                $_SESSION['worker_id'] = $uid;
                                $_SESSION['is_worker'] = true;
                                $_SESSION['active_panel'] = 'worker';
                                setcookie('active_panel', 'worker', time() + (365 * 24 * 3600), '/');
                                setcookie('beastz_active_panel', 'worker', time() + (365 * 24 * 3600), '/');
                                header("Location: worker.php");
                            }
                            exit;
                        } else {
                            unset($_SESSION['admin_id'], $_SESSION['admin_user'], $_SESSION['admin_username'], $_SESSION['admin_role'], $_SESSION['is_super_admin'], $_SESSION['admin_logged_in']);
                            
                            $_SESSION['user_id'] = $uid;
                            $_SESSION['username'] = $u['username'];
                            $_SESSION['email'] = $u['email'];
                            $_SESSION['role'] = 'user';
                            $_SESSION['user_logged_in'] = true;
                            
                            if (function_exists('set_user_remember_token')) { set_user_remember_token($uid, 'user'); }
                            $_SESSION['active_panel'] = 'user';
                            setcookie('active_panel', 'user', time() + (365 * 24 * 3600), '/');
                            setcookie('beastz_active_panel', 'user', time() + (365 * 24 * 3600), '/');
                            header("Location: index.php");
                            exit;
                        }
                    } else {
                        $error = "Incorrect password. Please try again.";
                    }
                }
            } else {
                $error = "Account not found with this username or mobile number.";
            }
        }
    }
}

// =============================================================
// 2. SIGN UP / REGISTRATION HANDLER (PHONE NUMBER MANDATORY)
// =============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'do_register') {
    $active_tab = 'register';
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', sanitize($_POST['username'] ?? ''));
    $phone = preg_replace('/[^0-9]/', '', sanitize($_POST['phone'] ?? ''));
    $email = sanitize($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $referral = sanitize($_POST['referral_code'] ?? '');

    if (empty($username) || strlen($username) < 3) {
        $error = "Username must be at least 3 characters long.";
    } elseif (empty($phone) || strlen($phone) < 10) {
        $error = "Valid 10-digit mobile number is mandatory to register.";
    } elseif (empty($email) || strpos($email, '@') === false) { 
        $error = "Please enter a valid email address.";
    } elseif (empty($password) || strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check uniqueness
        $dup_chk = $conn->prepare("SELECT id, username, phone, email FROM users WHERE username = ? OR phone = ? OR email = ? LIMIT 1");
        $dup_chk->bind_param("sss", $username, $phone, $email);
        $dup_chk->execute();
        $existing = $dup_chk->get_result()->fetch_assoc();
        $dup_chk->close();

        if ($existing) {
            if (strtolower($existing['username']) === strtolower($username)) {
                $error = "Username already taken! Please pick another.";
            } elseif ($existing['phone'] === $phone) {
                $error = "Mobile number already registered! Please sign in.";
            } else {
                $error = "Email address already registered! Please sign in.";
            }
        } else {
            // Hash password
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $initial_wallet = 0.00;
            $default_avatar = 'https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($username);

            $ins = $conn->prepare("INSERT INTO users (username, name, email, phone, password, role, status, wallet_balance, avatar, referral_code, referred_by) VALUES (?, ?, ?, ?, ?, 'user', 'active', ?, ?, ?, ?)");
            $my_ref_code = 'BEAST' . strtoupper(substr(md5($username . time()), 0, 5));
            
            // YAHAN PAR FIX KIYA HAI: "sssssdssss" ki jagah "sssssdsss" (Total 9 parameters)
            $ins->bind_param("sssssdsss", $username, $username, $email, $phone, $hashed, $initial_wallet, $default_avatar, $my_ref_code, $referral);

            if ($ins->execute()) {
                $new_id = (int)$ins->insert_id;
                $ins->close();
                
                unset($_SESSION['admin_id'], $_SESSION['admin_user'], $_SESSION['admin_username'], $_SESSION['is_super_admin'], $_SESSION['admin_logged_in']);
                
                $_SESSION['user_id'] = $new_id;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = 'user';
                $_SESSION['user_logged_in'] = true;
                
                if (function_exists('set_user_remember_token')) { set_user_remember_token($new_id); }

                header("Location: index.php"); 
                exit;
            } else {
                $error = "Registration error: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="/theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>BEASTzBATTLE - Esports Arena Login</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        display: ['"Outfit"', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/theme.css">
</head>
<body class="bg-[#080a0f] text-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm bg-[#0f141f] border border-slate-800 rounded-3xl p-6 shadow-2xl relative overflow-hidden">
        
        <!-- Subtle Glow -->
        <div class="absolute -top-16 -right-16 w-36 h-36 bg-yellow-400/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Brand Header -->
        <div class="text-center mb-5">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-yellow-400 text-slate-950 flex items-center justify-center text-2xl font-black mb-2.5 shadow-lg shadow-yellow-400/20">
                <i class="fa-solid fa-gamepad"></i>
            </div>
            <h1 class="text-xl font-black font-display tracking-tight text-white">
                BEASTz <span class="text-yellow-400">BATTLE</span>
            </h1>
            <p class="text-[11px] text-slate-400 mt-0.5">India's Ultimate BGMI & Free Fire Arena</p>
        </div>

        <!-- Alert Feedbacks -->
        <?php if (!empty($error)): ?>
            <div class="p-3 mb-4 rounded-xl text-xs font-bold bg-rose-500/10 border border-rose-500/30 text-rose-300 flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-400 shrink-0"></i>
                <span><?= htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="p-3 mb-4 rounded-xl text-xs font-bold bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-400 shrink-0"></i>
                <span><?= htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <!-- Segmented Tab Switcher (Sign In / Register) -->
        <div class="grid grid-cols-2 p-1 bg-slate-900 border border-slate-800 rounded-xl mb-4 text-xs font-bold">
            <button type="button" onclick="setAuthTab('login')" id="btnTabLogin" class="py-2 rounded-lg transition <?= ($active_tab === 'login') ? 'bg-yellow-400 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-white'; ?>">
                Sign In
            </button>
            <button type="button" onclick="setAuthTab('register')" id="btnTabRegister" class="py-2 rounded-lg transition <?= ($active_tab === 'register') ? 'bg-yellow-400 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-white'; ?>">
                Register
            </button>
        </div>

        <!-- ========================================== -->
        <!-- FORM 1: SIGN IN (UNIFIED) -->
        <!-- ========================================== -->
        <form method="POST" action="login.php" id="formLogin" class="space-y-3.5 <?= ($active_tab === 'login') ? '' : 'hidden'; ?>">
            <input type="hidden" name="action" value="do_login">

            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">Username, Mobile or Email</label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-slate-500 text-xs"><i class="fa-solid fa-user"></i></span>
                    <input type="text" name="identity" required placeholder="e.g. gamer_pro or 9878385810" class="w-full bg-slate-900 border border-slate-800 focus:border-yellow-400 rounded-xl pl-9 pr-3 py-2 text-xs text-white font-bold focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">Password</label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-slate-500 text-xs"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full bg-slate-900 border border-slate-800 focus:border-yellow-400 rounded-xl pl-9 pr-3 py-2 text-xs text-white focus:outline-none">
                </div>
            </div>

            <button type="submit" class="w-full py-3 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-md active:scale-95 flex items-center justify-center gap-2 cursor-pointer mt-1">
                <i class="fa-solid fa-right-to-bracket"></i>
                <span>Sign In to Arena</span>
            </button>
        </form>

        <!-- ========================================== -->
        <!-- FORM 2: REGISTER (PHONE NUMBER MANDATORY) -->
        <!-- ========================================== -->
        <form method="POST" action="login.php" id="formRegister" class="space-y-3 <?= ($active_tab === 'register') ? '' : 'hidden'; ?>">
            <input type="hidden" name="action" value="do_register">

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                    Username <span class="text-rose-400">*</span>
                </label>
                <input type="text" name="username" required placeholder="e.g. Beast_Striker" class="w-full bg-slate-900 border border-slate-800 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-white font-bold focus:outline-none">
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                    Mobile Number (Mandatory) <span class="text-rose-400">*</span>
                </label>
                <div class="flex items-center gap-1.5">
                    <span class="px-2.5 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs font-mono font-bold text-slate-400">+91</span>
                    <input type="tel" name="phone" required minlength="10" maxlength="10" placeholder="10-digit number" class="w-full bg-slate-900 border border-slate-800 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-white font-mono font-bold focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                    Email Address <span class="text-rose-400">*</span>
                </label>
                <input type="email" name="email" required placeholder="gamer@gmail.com" class="w-full bg-slate-900 border border-slate-800 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                    Password <span class="text-rose-400">*</span>
                </label>
                <input type="password" name="password" required minlength="6" placeholder="At least 6 characters" class="w-full bg-slate-900 border border-slate-800 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                    Referral Code (Optional)
                </label>
                <input type="text" name="referral_code" placeholder="e.g. BEAST99" class="w-full bg-slate-900 border border-slate-800 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-yellow-400 font-mono focus:outline-none uppercase">
            </div>

            <button type="submit" class="w-full py-3 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-md active:scale-95 flex items-center justify-center gap-2 cursor-pointer mt-1">
                <i class="fa-solid fa-user-plus"></i>
                <span>Create Gamer Account</span>
            </button>
        </form>

    </div>

    <script>
        function setAuthTab(tab) {
            var fLog = document.getElementById('formLogin');
            var fReg = document.getElementById('formRegister');
            var bLog = document.getElementById('btnTabLogin');
            var bReg = document.getElementById('btnTabRegister');

            if (tab === 'login') {
                fLog.classList.remove('hidden');
                fReg.classList.add('hidden');
                bLog.className = 'py-2 rounded-lg transition bg-yellow-400 text-slate-950 shadow-sm';
                bReg.className = 'py-2 rounded-lg transition text-slate-400 hover:text-white';
            } else {
                fLog.classList.add('hidden');
                fReg.classList.remove('hidden');
                bLog.className = 'py-2 rounded-lg transition text-slate-400 hover:text-white';
                bReg.className = 'py-2 rounded-lg transition bg-yellow-400 text-slate-950 shadow-sm';
            }
        }
    </script>
</body>
</html>
