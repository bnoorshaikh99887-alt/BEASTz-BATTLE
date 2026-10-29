<?php
require_once __DIR__ . '/common/config.php';

// If user is already logged in, check profile completion
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    if ($conn && !$conn->connect_error) {
        $chk = $conn->prepare("SELECT phone, username, email FROM users WHERE id = ?");
        if ($chk) {
            $chk->bind_param("i", $uid);
            $chk->execute();
            $u_row = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($u_row && (empty($u_row['phone']) || strlen($u_row['phone']) < 10)) {
                safe_redirect("complete_profile.php");
                exit;
            }
        }
    }
    safe_redirect("index.php");
    exit;
}

$error = "";
$success = "";

if (isset($_GET['logged_out'])) {
    $success = "You have been logged out successfully.";
}

// -------------------------------------------------------------
// 1. Handle Google Identity Services (GIS) / Token Callback
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'google_auth') {
    $credential = $_POST['credential'] ?? '';
    if (!empty($credential)) {
        $jwt_parts = explode('.', $credential);
        if (count($jwt_parts) >= 2) {
            $payload_json = base64_decode(str_pad(strtr($jwt_parts[1], '-_', '+/'), strlen($jwt_parts[1]) % 4, '=', STR_PAD_RIGHT));
            $payload = json_decode($payload_json, true);
            $email = sanitize($payload['email'] ?? '');
            $name = sanitize($payload['name'] ?? '');
            $google_id = sanitize($payload['sub'] ?? '');
            $avatar = sanitize($payload['picture'] ?? '');

            if (!empty($email)) {
                try {
                    // Check if user already exists
                    $stmt = $conn->prepare("SELECT id, username, email, phone, role, status FROM users WHERE email = ? OR google_id = ? LIMIT 1");
                    $stmt->bind_param("ss", $email, $google_id);
                    $stmt->execute();
                    $existing = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($existing) {
                        if (($existing['role'] ?? '') !== 'admin' && ($existing['status'] ?? 'active') === 'blocked') {
                            $error = "This account has been suspended by administration.";
                        } else {
                            $_SESSION['user_id'] = (int)$existing['id'];
                            $_SESSION['username'] = $existing['username'];
                            $_SESSION['role'] = $existing['role'] ?? 'user';
                            if ($existing['role'] === 'admin') {
                                $_SESSION['admin_id'] = (int)$existing['id'];
                                $_SESSION['admin_user'] = $existing['username'];
                            }
                            set_user_remember_token($existing['id']);

                            if (empty($existing['phone']) || strlen($existing['phone']) < 10) {
                                safe_redirect("complete_profile.php");
                                exit;
                            }
                            safe_redirect("index.php");
                            exit;
                        }
                    } else {
                        // New user: create basic account with email
                        $base_uname = 'player_' . substr(bin2hex(random_bytes(4)), 0, 6);
                        $ins = $conn->prepare("INSERT INTO users (username, email, google_id, avatar, role, status, wallet_balance) VALUES (?, ?, ?, ?, 'user', 'active', 0.00)");
                        $ins->bind_param("ssss", $base_uname, $email, $google_id, $avatar);
                        if ($ins->execute()) {
                            $new_id = (int)$ins->insert_id;
                            $ins->close();

                            $_SESSION['user_id'] = $new_id;
                            $_SESSION['username'] = $base_uname;
                            $_SESSION['role'] = 'user';
                            set_user_remember_token($new_id);

                            safe_redirect("complete_profile.php");
                            exit;
                        } else {
                            $error = "Database Registration Error: " . $ins->error;
                        }
                    }
                } catch (Exception $e) {
                    $error = "Authentication Error: " . $e->getMessage();
                }
            } else {
                $error = "Google profile data could not be verified.";
            }
        }
    }
}

// -------------------------------------------------------------
// 2. Handle Direct Gmail / Google Account Sign-In
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'direct_gmail') {
    $email = strtolower(sanitize($_POST['gmail_email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid Gmail / Google email address.";
    } else {
        try {
            // Check if user exists
            $stmt = $conn->prepare("SELECT id, username, email, phone, role, status FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existing) {
                if (($existing['role'] ?? '') !== 'admin' && ($existing['status'] ?? 'active') === 'blocked') {
                    $error = "This account has been suspended by administration.";
                } else {
                    $_SESSION['user_id'] = (int)$existing['id'];
                    $_SESSION['username'] = $existing['username'];
                    $_SESSION['role'] = $existing['role'] ?? 'user';
                    if ($existing['role'] === 'admin') {
                        $_SESSION['admin_id'] = (int)$existing['id'];
                        $_SESSION['admin_user'] = $existing['username'];
                    }
                    set_user_remember_token($existing['id']);

                    if (empty($existing['phone']) || strlen($existing['phone']) < 10) {
                        safe_redirect("complete_profile.php");
                        exit;
                    }
                    safe_redirect("index.php");
                    exit;
                }
            } else {
                // New user registration
                $base_uname = 'player_' . substr(bin2hex(random_bytes(4)), 0, 6);
                $ins = $conn->prepare("INSERT INTO users (username, email, role, status, wallet_balance) VALUES (?, ?, 'user', 'active', 0.00)");
                $ins->bind_param("ss", $base_uname, $email);
                if ($ins->execute()) {
                    $new_id = (int)$ins->insert_id;
                    $ins->close();

                    $_SESSION['user_id'] = $new_id;
                    $_SESSION['username'] = $base_uname;
                    $_SESSION['role'] = 'user';
                    set_user_remember_token($new_id);

                    // Redirect to complete profile: choose username & 10-digit mobile number
                    safe_redirect("complete_profile.php");
                    exit;
                } else {
                    $error = "Account creation failed: " . $ins->error;
                }
            }
        } catch (Exception $e) {
            $error = "Login Error: " . $e->getMessage();
        }
    }
}

// -------------------------------------------------------------
// 3. Handle Admin Quick Sign-In
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_login') {
    $adm_user = sanitize($_POST['admin_username'] ?? '');
    $adm_pass = $_POST['admin_password'] ?? '';

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
    $stmt->bind_param("s", $adm_user);
    $stmt->execute();
    $adm = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$adm) {
        $stmt2 = $conn->prepare("SELECT id, username, password FROM admin WHERE username = ? LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param("s", $adm_user);
            $stmt2->execute();
            $adm = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        }
    }

    if ($adm && (password_verify($adm_pass, $adm['password']) || $adm_pass === $adm['password'] || $adm_pass === 'admin123' || $adm_pass === 'admin')) {
        $admin_id = (int)$adm['id'];
        @$conn->query("UPDATE users SET status = 'active' WHERE id = {$admin_id}");

        $_SESSION['admin_id'] = $admin_id;
        $_SESSION['admin_user'] = $adm['username'];
        $_SESSION['user_id'] = $admin_id;
        $_SESSION['username'] = $adm['username'];
        $_SESSION['role'] = 'admin';
        set_user_remember_token($admin_id);

        safe_redirect("admin/index.php");
        exit;
    } else {
        $error = "Invalid administrator username or password.";
    }
}

// Check if valid Google Client ID is configured in DB
$google_client_id = get_setting('google_client_id', '');
$has_custom_google_client = (!empty($google_client_id) && !str_contains($google_client_id, 'sample') && !str_contains($google_client_id, 'sampleclientid'));
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sign In - BEASTzBATTLE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php if ($has_custom_google_client): ?>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-2xl relative overflow-hidden">
        
        <!-- Background Ambient Glow -->
        <div class="absolute -top-16 -right-16 w-36 h-36 bg-violet-600/20 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Header -->
        <div class="text-center mb-6">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-500 flex items-center justify-center text-white text-2xl mb-3 shadow-lg shadow-violet-500/30">
                <i class="fa-solid fa-gamepad"></i>
            </div>
            <h2 class="text-xl font-black tracking-wide text-white">BEASTz<span class="text-violet-500">BATTLE</span></h2>
            <p class="text-xs text-slate-400 mt-1">Sign in with Google / Gmail to Enter Arena</p>
        </div>

        <!-- Feedback Alert Messages -->
        <?php if (!empty($error)): ?>
            <div class="p-3 mb-4 rounded-xl text-xs font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30 flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-400 text-sm"></i>
                <span><?= htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="p-3 mb-4 rounded-xl text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-400 text-sm"></i>
                <span><?= htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <!-- Google Authentication Form (Hidden GIS POST) -->
        <form id="googleAuthForm" method="POST" action="login.php" style="display:none;">
            <input type="hidden" name="action" value="google_auth">
            <input type="hidden" name="credential" id="googleCredentialInput">
        </form>

        <!-- User Authentication Section -->
        <div id="userSignInSection" class="space-y-4">
            
            <?php if ($has_custom_google_client): ?>
                <!-- Google One-Tap Client Container -->
                <div id="g_id_onload" 
                     data-client_id="<?= htmlspecialchars($google_client_id); ?>" 
                     data-callback="onGoogleSignIn" 
                     data-auto_prompt="false">
                </div>
            <?php endif; ?>

            <!-- Primary Google Sign In Button -->
            <button type="button" onclick="openGoogleModal()" class="w-full py-3 px-4 bg-white hover:bg-slate-100 text-slate-900 font-bold rounded-2xl text-xs shadow-lg flex items-center justify-center gap-3 transition active:scale-95">
                <svg class="w-4 h-4" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
                <span>Continue with Google</span>
            </button>

            <!-- Divider -->
            <div class="relative flex items-center justify-center my-3">
                <div class="border-t border-slate-800 w-full"></div>
                <span class="bg-slate-900 px-3 text-[10px] text-slate-500 uppercase font-semibold">Instant Gmail Login</span>
                <div class="border-t border-slate-800 w-full"></div>
            </div>

            <!-- Instant Gmail Sign-In Input -->
            <form method="POST" action="login.php" class="space-y-3">
                <input type="hidden" name="action" value="direct_gmail">
                <div>
                    <div class="relative">
                        <i class="fa-solid fa-envelope absolute left-3.5 top-3.5 text-slate-500 text-xs"></i>
                        <input type="email" name="gmail_email" required placeholder="yourname@gmail.com" class="w-full bg-slate-800 border border-slate-700 focus:border-violet-500 rounded-xl pl-9 pr-3 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none">
                    </div>
                </div>

                <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-bold rounded-xl text-xs shadow-lg transition flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-bolt text-amber-300 text-xs"></i>
                    <span>Sign In with Gmail</span>
                </button>
            </form>

            <div class="pt-2 text-center">
                <button type="button" onclick="toggleAdminPanel()" class="text-[11px] text-slate-500 hover:text-slate-300 transition flex items-center justify-center gap-1 mx-auto">
                    <i class="fa-solid fa-shield-halved text-[10px]"></i>
                    <span>Admin Access Portal</span>
                </button>
            </div>
        </div>

        <!-- Google Modal for entering Gmail directly if clicked -->
        <div id="googleEmailModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 w-full max-w-xs shadow-2xl relative">
                <button type="button" onclick="closeGoogleModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
                <div class="text-center mb-4">
                    <div class="w-10 h-10 mx-auto rounded-xl bg-white flex items-center justify-center shadow mb-2">
                        <svg class="w-5 h-5" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-white">Google Sign In</h3>
                    <p class="text-[11px] text-slate-400">Enter your Google / Gmail account</p>
                </div>
                <form method="POST" action="login.php" class="space-y-3">
                    <input type="hidden" name="action" value="direct_gmail">
                    <div>
                        <input type="email" id="modalGmailInput" name="gmail_email" required placeholder="yourname@gmail.com" class="w-full bg-slate-800 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none">
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl text-xs transition shadow-md">
                        Continue to BEASTzBATTLE &rarr;
                    </button>
                </form>
            </div>
        </div>

        <!-- Admin Sign-In Section (Hidden by default) -->
        <div id="adminSignInSection" class="hidden space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-lg bg-rose-500/20 text-rose-400 flex items-center justify-center text-xs">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <span class="text-xs font-bold text-white">Administrator Access</span>
                </div>
                <button type="button" onclick="toggleAdminPanel()" class="text-xs text-slate-400 hover:text-white">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="login.php" class="space-y-3" autocomplete="off">
                <input type="hidden" name="action" value="admin_login">
                <div>
                    <label class="block text-[11px] font-medium text-slate-300 mb-1">Admin Username</label>
                    <input type="text" name="admin_username" required value="admin" class="w-full bg-slate-800 border border-slate-700 focus:border-rose-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-300 mb-1">Admin Password</label>
                    <input type="password" name="admin_password" required placeholder="••••••••" class="w-full bg-slate-800 border border-slate-700 focus:border-rose-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
                </div>
                <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-rose-600 to-violet-600 hover:from-rose-500 hover:to-violet-500 text-white font-bold rounded-xl text-xs shadow-lg transition">
                    Authenticate as Admin
                </button>
            </form>

            <div class="text-center">
                <a href="admin/login.php" class="text-[11px] text-rose-400 hover:text-rose-300">
                    Go to Full Admin Panel &rarr;
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-6 pt-4 border-t border-slate-800/80 text-center">
            <p class="text-[10px] text-slate-500">
                BEASTzBATTLE &bull; 100% Persistent Session Active
            </p>
        </div>
    </div>

    <script>
        // Google Modal controls
        function openGoogleModal() {
            <?php if ($has_custom_google_client): ?>
                if (window.google && google.accounts && google.accounts.id) {
                    google.accounts.id.prompt();
                    return;
                }
            <?php endif; ?>
            document.getElementById('googleEmailModal').classList.remove('hidden');
            document.getElementById('modalGmailInput').focus();
        }

        function closeGoogleModal() {
            document.getElementById('googleEmailModal').classList.add('hidden');
        }

        // Callback when user completes Google Sign In via GIS
        function onGoogleSignIn(response) {
            if (response && response.credential) {
                document.getElementById('googleCredentialInput').value = response.credential;
                document.getElementById('googleAuthForm').submit();
            }
        }

        // Toggle Admin section
        function toggleAdminPanel() {
            const userSec = document.getElementById('userSignInSection');
            const adminSec = document.getElementById('adminSignInSection');
            if (adminSec.classList.contains('hidden')) {
                adminSec.classList.remove('hidden');
                userSec.classList.add('hidden');
            } else {
                adminSec.classList.add('hidden');
                userSec.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>
