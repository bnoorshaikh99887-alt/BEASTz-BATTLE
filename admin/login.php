<?php
require_once __DIR__ . '/../common/config.php';

// Handle Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_user']);
    header("Location: login.php");
    exit;
}

if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill in all admin credentials.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($adm = $res->fetch_assoc()) {
            // Verify password hash or fallback plain for bootstrap
            if (password_verify($password, $adm['password']) || $password === $adm['password']) {
                $_SESSION['admin_id'] = $adm['id'];
                $_SESSION['admin_user'] = $adm['username'];
                header("Location: index.php");
                exit;
            } else {
                $error = "Invalid admin password.";
            }
        } else {
            $error = "Admin account not found.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Sign In - Adept Play</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl">
        <div class="text-center mb-6">
            <div class="w-12 h-12 mx-auto rounded-xl bg-gradient-to-tr from-rose-600 to-violet-600 flex items-center justify-center text-white text-xl mb-2 shadow-lg shadow-rose-600/20">
                <i class="fa-solid fa-lock"></i>
            </div>
            <h2 class="text-xl font-bold text-white">Administrator Access</h2>
            <p class="text-[11px] text-slate-400">Adept Play Control Center</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="p-3 mb-4 rounded-xl text-xs font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30 flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-400"></i>
                <span><?= $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Admin Username</label>
                <input type="text" name="username" required placeholder="admin" class="w-full bg-slate-800 border border-slate-700 focus:border-rose-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Admin Password</label>
                <input type="password" name="password" required placeholder="admin123" class="w-full bg-slate-800 border border-slate-700 focus:border-rose-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>

            <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-rose-600 to-violet-600 hover:from-rose-500 hover:to-violet-500 text-white font-bold rounded-xl text-xs shadow-lg transition">
                Authenticate & Enter
            </button>
        </form>

        <div class="mt-4 text-center">
            <a href="../login.php" class="text-[11px] text-slate-400 hover:text-slate-200">
                &larr; Back to User Portal
            </a>
        </div>
    </div>
</body>
</html>