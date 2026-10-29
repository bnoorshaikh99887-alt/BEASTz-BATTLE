<?php
require_once __DIR__ . '/common/config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = isset($_GET['error']) ? sanitize($_GET['error']) : "";
$success = isset($_GET['success']) ? sanitize($_GET['success']) : "";
$active_tab = "login";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'login') {
        $active_tab = "login";
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $error = "Please fill in all fields.";
        } else {
            $stmt = $conn->prepare("SELECT id, username, password, status FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($user = $res->fetch_assoc()) {
                if ($user['status'] === 'blocked') {
                    $error = "Your account is blocked. Contact support.";
                } elseif (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Incorrect password.";
                }
            } else {
                $error = "User not found with this username.";
            }
            $stmt->close();
        }
    } elseif ($action === 'signup') {
        $active_tab = "signup";
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];

        if (empty($username) || empty($email) || empty($password)) {
            $error = "Please provide all details.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            // Check for duplicate username or email
            $chk = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $chk->bind_param("ss", $username, $email);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = "Username or Email already registered.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $init_wallet = 0.00;
                $ins = $conn->prepare("INSERT INTO users (username, email, password, wallet_balance) VALUES (?, ?, ?, ?)");
                $ins->bind_param("sssd", $username, $email, $hashed, $init_wallet);
                if ($ins->execute()) {
                    $new_id = $conn->insert_id;
                    $_SESSION['user_id'] = $new_id;
                    $_SESSION['username'] = $username;
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Registration failed. Please try again.";
                }
                $ins->close();
            }
            $chk->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sign In - Adept Play</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { -webkit-touch-callout: none; user-select: none; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-3 select-none">
    
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl">
        <!-- Logo Header -->
        <div class="text-center mb-6">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-500 flex items-center justify-center text-white text-2xl mb-2 shadow-lg shadow-violet-500/20">
                <i class="fa-solid fa-gamepad"></i>
            </div>
            <h1 class="text-2xl font-black tracking-wider text-white">ADEPT<span class="text-violet-500">PLAY</span></h1>
            <p class="text-xs text-slate-400">Play Tournaments, Win Real Cash</p>
        </div>

        <!-- Notification Alerts -->
        <?php if (!empty($error)): ?>
            <div class="p-3 mb-4 rounded-xl text-xs font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30 flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-400"></i>
                <span><?= $error; ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="p-3 mb-4 rounded-xl text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-400"></i>
                <span><?= $success; ?></span>
            </div>
        <?php endif; ?>

        <!-- Two Tabs (Login / Sign Up) -->
        <div class="grid grid-cols-2 bg-slate-800/80 p-1 rounded-xl mb-6 border border-slate-700/60">
            <button type="button" onclick="switchTab('login')" id="tabBtnLogin" class="py-2 text-xs font-bold rounded-lg transition <?= ($active_tab === 'login') ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200' ?>">
                Sign In
            </button>
            <button type="button" onclick="switchTab('signup')" id="tabBtnSignup" class="py-2 text-xs font-bold rounded-lg transition <?= ($active_tab === 'signup') ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200' ?>">
                Register
            </button>
        </div>

        <!-- Login Form -->
        <div id="loginFormSection" class="<?= ($active_tab === 'login') ? 'block' : 'hidden' ?>">
            <form method="POST" action="login.php" class="space-y-4">
                <input type="hidden" name="action" value="login">

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Username</label>
                    <div class="relative">
                        <i class="fa-solid fa-user absolute left-3.5 top-3.5 text-slate-500 text-xs"></i>
                        <input type="text" name="username" required placeholder="Enter your username" class="w-full bg-slate-800 border border-slate-700 focus:border-violet-500 rounded-xl pl-9 pr-3 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Password</label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-3.5 top-3.5 text-slate-500 text-xs"></i>
                        <input type="password" name="password" required placeholder="Enter password" class="w-full bg-slate-800 border border-slate-700 focus:border-violet-500 rounded-xl pl-9 pr-3 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none">
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-violet-600 hover:bg-violet-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-600/30 transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    <span>Log In to Account</span>
                </button>
            </form>
        </div>

        <!-- Sign Up Form -->
        <div id="signupFormSection" class="<?= ($active_tab === 'signup') ? 'block' : 'hidden' ?>">
            <form method="POST" action="login.php" class="space-y-4">
                <input type="hidden" name="action" value="signup">

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Choose Username</label>
                    <div class="relative">
                        <i class="fa-solid fa-user-tag absolute left-3.5 top-3.5 text-slate-500 text-xs"></i>
                        <input type="text" name="username" required placeholder="e.g. gamer_99" class="w-full bg-slate-800 border border-slate-700 focus:border-violet-500 rounded-xl pl-9 pr-3 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Email Address</label>
                    <div class="relative">
                        <i class="fa-solid fa-envelope absolute left-3.5 top-3.5 text-slate-500 text-xs"></i>
                        <input type="email" name="email" required placeholder="name@email.com" class="w-full bg-slate-800 border border-slate-700 focus:border-violet-500 rounded-xl pl-9 pr-3 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Password (6+ chars)</label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-3.5 top-3.5 text-slate-500 text-xs"></i>
                        <input type="password" name="password" required minlength="6" placeholder="Choose a secure password" class="w-full bg-slate-800 border border-slate-700 focus:border-violet-500 rounded-xl pl-9 pr-3 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none">
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-violet-600 hover:bg-violet-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-600/30 transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Create Free Account</span>
                </button>
            </form>
        </div>

        <!-- Admin Gateway & Support Link -->
        <div class="mt-6 pt-4 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-400">
            <a href="admin/login.php" class="hover:text-violet-400 transition flex items-center gap-1">
                <i class="fa-solid fa-shield-halved"></i> Admin Login
            </a>
            <a href="https://wa.me/919878385810" target="_blank" class="hover:text-emerald-400 transition flex items-center gap-1">
                <i class="fa-brands fa-whatsapp text-emerald-400"></i> Adept Code Support
            </a>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            const loginSection = document.getElementById('loginFormSection');
            const signupSection = document.getElementById('signupFormSection');
            const tabBtnLogin = document.getElementById('tabBtnLogin');
            const tabBtnSignup = document.getElementById('tabBtnSignup');

            if (tab === 'login') {
                loginSection.classList.remove('hidden');
                signupSection.classList.add('hidden');
                tabBtnLogin.className = "py-2 text-xs font-bold rounded-lg transition bg-violet-600 text-white shadow-md";
                tabBtnSignup.className = "py-2 text-xs font-bold rounded-lg transition text-slate-400 hover:text-slate-200";
            } else {
                signupSection.classList.remove('hidden');
                loginSection.classList.add('hidden');
                tabBtnSignup.className = "py-2 text-xs font-bold rounded-lg transition bg-violet-600 text-white shadow-md";
                tabBtnLogin.className = "py-2 text-xs font-bold rounded-lg transition text-slate-400 hover:text-slate-200";
            }
        }
    </script>
</body>
</html>