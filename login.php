<?php
// login.php - Complete Login & Registration for Adept Play
require_once __DIR__ . '/common/config.php';

// Agar user pehle se logged in hai to home redirect karo
if (isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit;
}

$error = '';
$success = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'login';

// Form Submit Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submit_type = isset($_POST['submit_type']) ? sanitize($_POST['submit_type']) : 'login';

    if ($submit_type === 'register') {
        $name     = sanitize($_POST['name'] ?? '');
        $username = strtolower(sanitize($_POST['username'] ?? ''));
        $phone    = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($name) || empty($username) || empty($phone) || empty($password)) {
            $error = "Kripya sabhi fields bharein!";
        } elseif (strlen($password) < 4) {
            $error = "Password kam se kam 4 characters ka hona chahiye!";
        } else {
            // Check if username already exists
            $check_sql = "SELECT id FROM `users` WHERE `username` = ? OR `phone` = ? LIMIT 1";
            $stmt = $conn->prepare($check_sql);
            if ($stmt) {
                $stmt->bind_param("ss", $username, $phone);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $error = "Ye Username ya Mobile Number pehle se registered hai!";
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $ins_sql = "INSERT INTO `users` (`name`, `username`, `phone`, `password`, `wallet`, `role`) VALUES (?, ?, ?, ?, 0.00, 'user')";
                    $ins_stmt = $conn->prepare($ins_sql);
                    if ($ins_stmt) {
                        $ins_stmt->bind_param("ssss", $name, $username, $phone, $hash);
                        if ($ins_stmt->execute()) {
                            $new_id = $ins_stmt->insert_id;
                            $_SESSION['user_id'] = $new_id;
                            $_SESSION['username'] = $username;
                            $_SESSION['role'] = 'user';
                            header("Location: /index.php");
                            exit;
                        } else {
                            $error = "Registration fail ho gaya: " . $conn->error;
                        }
                    } else {
                        $error = "Database Error: " . $conn->error;
                    }
                }
            } else {
                $error = "Database Query Error: " . $conn->error;
            }
        }
    } else {
        // LOGIN PROCESS
        $username = strtolower(sanitize($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = "Kripya Username aur Password daalein!";
        } else {
            $sql = "SELECT `id`, `name`, `username`, `password`, `role` FROM `users` WHERE `username` = ? OR `phone` = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ss", $username, $username);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    // Password match verify (BCRYPT hash OR plain text fallback for default admin)
                    if (password_verify($password, $row['password']) || $password === $row['password'] || ($username === 'admin' && $password === 'admin123')) {
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['name'] = $row['name'];
                        $_SESSION['role'] = $row['role'];

                        // If Admin -> Go to Admin Panel
                        if ($row['role'] === 'admin') {
                            $_SESSION['admin_logged_in'] = true;
                            $_SESSION['admin_id'] = $row['id'];
                            header("Location: /tournament.php");
                            exit;
                        }

                        // Regular User -> Go to Home
                        header("Location: /index.php");
                        exit;
                    } else {
                        $error = "Galat Password! Kripya dobara koshish karein.";
                    }
                } else {
                    $error = "Ye Username registered nahi hai! Kripya register karein.";
                }
            } else {
                $error = "Database Error: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            -webkit-touch-callout: none;
            user-select: none;
            background-color: #0b0f19;
            color: #f3f4f6;
            font-family: ui-sans-serif, system-ui, sans-serif;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4" oncontextmenu="return false;">

    <div class="w-full max-w-sm bg-[#131b2e] border border-gray-800 rounded-2xl p-6 shadow-2xl relative">
        <!-- Brand Logo -->
        <div class="text-center mb-6">
            <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-amber-500 to-red-600 items-center justify-center font-black text-white text-2xl shadow-lg shadow-red-500/20 mb-3">
                A
            </div>
            <h1 class="text-2xl font-black tracking-wider text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-red-500">
                ADEPT PLAY
            </h1>
            <p class="text-xs text-gray-400 mt-1">Esports Tournament Platform</p>
        </div>

        <!-- Tab Toggle Buttons -->
        <div class="flex bg-gray-900/80 p-1 rounded-xl mb-6 border border-gray-800">
            <button type="button" id="tab-login" onclick="switchTab('login')" class="flex-1 py-2 text-xs font-bold rounded-lg transition bg-amber-500 text-black shadow">
                Login
            </button>
            <button type="button" id="tab-register" onclick="switchTab('register')" class="flex-1 py-2 text-xs font-bold rounded-lg transition text-gray-400 hover:text-white">
                Register
            </button>
        </div>

        <!-- Alert Notification -->
        <?php if (!empty($error)): ?>
            <div class="bg-red-500/20 border border-red-500/50 text-red-400 text-xs p-3 rounded-xl mb-4 flex items-center">
                <i class="fa-solid fa-circle-exclamation mr-2 text-sm flex-shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- LOGIN FORM -->
        <form method="POST" action="/login.php" id="form-login" class="space-y-4">
            <input type="hidden" name="submit_type" value="login">
            <div>
                <label class="block text-xs font-semibold text-gray-400 mb-1">Username ya Mobile</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-500 text-xs">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <input type="text" name="username" required placeholder="Enter username or mobile" class="w-full bg-gray-900/90 border border-gray-700/80 rounded-xl pl-9 pr-3 py-2.5 text-xs text-white focus:outline-none focus:border-amber-500 transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-400 mb-1">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-500 text-xs">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" name="password" required placeholder="Enter password" class="w-full bg-gray-900/90 border border-gray-700/80 rounded-xl pl-9 pr-3 py-2.5 text-xs text-white focus:outline-none focus:border-amber-500 transition">
                </div>
            </div>
            <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-black font-extrabold py-3 rounded-xl text-xs tracking-wider uppercase shadow-lg shadow-amber-500/20 transition active:scale-[0.98]">
                Login Now
            </button>
            <div class="text-center pt-2">
                <p class="text-[11px] text-gray-400">
                    Default Admin: <span class="text-amber-400 font-mono">admin</span> / <span class="text-amber-400 font-mono">admin123</span>
                </p>
            </div>
        </form>

        <!-- REGISTER FORM -->
        <form method="POST" action="/login.php" id="form-register" class="space-y-3.5 hidden">
            <input type="hidden" name="submit_type" value="register">
            <div>
                <label class="block text-xs font-semibold text-gray-400 mb-1">Full Name</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-500 text-xs">
                        <i class="fa-solid fa-id-card"></i>
                    </span>
                    <input type="text" name="name" required placeholder="Your full name" class="w-full bg-gray-900/90 border border-gray-700/80 rounded-xl pl-9 pr-3 py-2 text-xs text-white focus:outline-none focus:border-amber-500 transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-400 mb-1">Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-500 text-xs">
                        <i class="fa-solid fa-at"></i>
                    </span>
                    <input type="text" name="username" required placeholder="Choose a username" class="w-full bg-gray-900/90 border border-gray-700/80 rounded-xl pl-9 pr-3 py-2 text-xs text-white focus:outline-none focus:border-amber-500 transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-400 mb-1">Mobile Number (WhatsApp)</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-500 text-xs">
                        <i class="fa-solid fa-phone"></i>
                    </span>
                    <input type="tel" name="phone" required placeholder="10-digit mobile number" class="w-full bg-gray-900/90 border border-gray-700/80 rounded-xl pl-9 pr-3 py-2 text-xs text-white focus:outline-none focus:border-amber-500 transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-400 mb-1">Create Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-500 text-xs">
                        <i class="fa-solid fa-key"></i>
                    </span>
                    <input type="password" name="password" required placeholder="Choose password" class="w-full bg-gray-900/90 border border-gray-700/80 rounded-xl pl-9 pr-3 py-2 text-xs text-white focus:outline-none focus:border-amber-500 transition">
                </div>
            </div>
            <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-black font-extrabold py-3 rounded-xl text-xs tracking-wider uppercase shadow-lg shadow-amber-500/20 transition active:scale-[0.98]">
                Create Free Account
            </button>
        </form>

        <!-- Official Support Info -->
        <div class="mt-6 pt-4 border-t border-gray-800/80 text-center text-xs text-gray-400">
            <span>Support: </span>
            <a href="https://wa.me/919878385810" class="text-amber-400 hover:underline font-semibold">+91 9878385810</a>
        </div>
    </div>

    <script>
        function switchTab(type) {
            const loginForm = document.getElementById('form-login');
            const registerForm = document.getElementById('form-register');
            const tabLogin = document.getElementById('tab-login');
            const tabRegister = document.getElementById('tab-register');

            if (type === 'register') {
                loginForm.classList.add('hidden');
                registerForm.classList.remove('hidden');
                tabRegister.className = 'flex-1 py-2 text-xs font-bold rounded-lg transition bg-amber-500 text-black shadow';
                tabLogin.className = 'flex-1 py-2 text-xs font-bold rounded-lg transition text-gray-400 hover:text-white';
            } else {
                registerForm.classList.add('hidden');
                loginForm.classList.remove('hidden');
                tabLogin.className = 'flex-1 py-2 text-xs font-bold rounded-lg transition bg-amber-500 text-black shadow';
                tabRegister.className = 'flex-1 py-2 text-xs font-bold rounded-lg transition text-gray-400 hover:text-white';
            }
        }
    </script>
</body>
</html>
