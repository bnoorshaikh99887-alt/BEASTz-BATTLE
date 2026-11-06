<?php
require_once __DIR__ . '/common/config.php';

if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$apiKey = 'FrlHzGnUeoucLdOwuAn0c5KQvhonwCALTFsK1QUeVMw';

// =========================================================================================
// 1. AJAX HANDLER: SEND OTP VIA CUSTOM API
// =========================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    header('Content-Type: application/json');
    $phone = preg_replace('/[^0-9]/', '', sanitize($_POST['phone'] ?? ''));
    
    if (strlen($phone) > 10 && substr($phone, 0, 2) === '91') {
        $phone = substr($phone, 2);
    }
    
    if (strlen($phone) !== 10) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid 10-digit mobile number.']);
        exit;
    }
    
    // Generate 6-digit OTP
    $otp = rand(100000, 999999);
    $_SESSION['auth_otp'] = $otp;
    $_SESSION['auth_phone'] = $phone;
    
    // TODO: Yahan apni API key ($apiKey) ke sath SMS gateway ka cURL request laga sakte ho.
    // Testing ke liye debug_otp bhej rahe hain taaki bina SMS ke bhi aaram se check ho sake.
    echo json_encode([
        'success' => true, 
        'message' => 'OTP sent successfully!',
        'debug_otp' => $otp // Testing ke liye screen par alert/console me dikh jayega
    ]);
    exit;
}

// =========================================================================================
// 2. AJAX HANDLER: VERIFY OTP & LOGIN / AUTO-REGISTER
// =========================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    header('Content-Type: application/json');
    $entered_otp = trim($_POST['otp'] ?? '');
    $phone = $_SESSION['auth_phone'] ?? '';
    
    if (empty($entered_otp) || empty($phone) || $entered_otp != ($_SESSION['auth_otp'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP! Please try again.']);
        exit;
    }
    
    if (!$db_ok) {
        echo json_encode(['success' => false, 'message' => 'Database connection error.']);
        exit;
    }

    // 2.1 Check in Admin/Worker table
    $adm_chk = $conn->prepare("SELECT id, username, role, status FROM admin WHERE username = ? LIMIT 1");
    $adm_chk->bind_param("s", $phone);
    $adm_chk->execute();
    $adm_row = $adm_chk->get_result()->fetch_assoc();
    $adm_chk->close();

    if ($adm_row) {
        if (($adm_row['status'] ?? 'active') === 'suspended') {
            echo json_encode(['success' => false, 'message' => 'Admin account suspended.']);
            exit;
        }
        
        $is_super = ($adm_row['username'] === 'anonymous.349' || $adm_row['username'] === 'admin' || $adm_row['role'] === 'super_admin');
        if ($is_super) {
            $_SESSION['admin_id'] = (int)$adm_row['id'];
            $_SESSION['admin_user'] = $adm_row['username'];
            $_SESSION['admin_role'] = 'super_admin';
            $_SESSION['is_super_admin'] = true;
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['active_panel'] = 'admin';
            setcookie('active_panel', 'admin', time() + (365 * 24 * 3600), '/');
            echo json_encode(['success' => true, 'redirect' => 'admin/index.php']);
        } else {
            $_SESSION['worker_id'] = (int)$adm_row['id'];
            $_SESSION['is_worker'] = true;
            $_SESSION['active_panel'] = 'worker';
            setcookie('active_panel', 'worker', time() + (365 * 24 * 3600), '/');
            echo json_encode(['success' => true, 'redirect' => 'worker.php']);
        }
        exit;
    }

    // 2.2 Check in Users table
    $u_stmt = $conn->prepare("SELECT id, username, role, status FROM users WHERE phone = ? LIMIT 1");
    $u_stmt->bind_param("s", $phone);
    $u_stmt->execute();
    $u = $u_stmt->get_result()->fetch_assoc();
    $u_stmt->close();

    if ($u) {
        if (($u['status'] ?? 'active') === 'blocked') {
            echo json_encode(['success' => false, 'message' => 'Account Blocked. Contact support.']);
            exit;
        }
        
        $_SESSION['user_id'] = (int)$u['id'];
        $_SESSION['username'] = $u['username'];
        $_SESSION['role'] = 'user';
        $_SESSION['user_logged_in'] = true;
        $_SESSION['active_panel'] = 'user';
        setcookie('active_panel', 'user', time() + (365 * 24 * 3600), '/');
        echo json_encode(['success' => true, 'redirect' => 'index.php']);
        exit;
    } else {
        // 2.3 Auto-Register New User
        $uid_rand = md5($phone . time());
        $username = "Gamer_" . substr($uid_rand, 0, 6);
        $initial_wallet = 0.00;
        $default_avatar = 'https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($username);
        $my_ref_code = 'BEAST' . strtoupper(substr(md5($phone . time()), 0, 5));
        $empty_str = "";
        
        $ins = $conn->prepare("INSERT INTO users (username, name, email, phone, password, role, status, wallet_balance, avatar, referral_code) VALUES (?, ?, ?, ?, ?, 'user', 'active', ?, ?, ?)");
        $ins->bind_param("sssssdss", $username, $username, $empty_str, $phone, $uid_rand, $initial_wallet, $default_avatar, $my_ref_code);
        
        if ($ins->execute()) {
            $new_id = (int)$ins->insert_id;
            $ins->close();
            
            $_SESSION['user_id'] = $new_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'user';
            $_SESSION['user_logged_in'] = true;
            $_SESSION['active_panel'] = 'user';
            setcookie('active_panel', 'user', time() + (365 * 24 * 3600), '/');
            echo json_encode(['success' => true, 'redirect' => 'index.php']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create new account.']);
            exit;
        }
    }
}

// Session checks
$has_admin_session  = !empty($_SESSION['admin_id']);
$has_worker_session = !empty($_SESSION['worker_id']) || (!empty($_SESSION['is_worker']) && $has_admin_session);
if ($has_admin_session && !empty($_SESSION['is_super_admin'])) { header("Location: admin/index.php"); exit; }
if ($has_worker_session || $has_admin_session) { header("Location: worker.php"); exit; }
if (isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$success = isset($_GET['logged_out']) ? "You have been logged out successfully." : "";
if (!function_exists('sanitize')) { function sanitize($data) { return htmlspecialchars(strip_tags(trim($data))); } }
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <script src="/theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>BEASTzBATTLE - Esports Arena Login</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/theme.css">
</head>
<body class="bg-[#070a12] text-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm bg-[#131b2e] border border-[#1e2d42] rounded-3xl p-6 shadow-2xl relative overflow-hidden">
        
        <div class="absolute -top-16 -right-16 w-36 h-36 bg-[#3D40BD]/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="text-center mb-5">
            <div class="w-16 h-16 mx-auto mb-2.5 flex items-center justify-center">
                <img src="/logo.png" alt="BEASTz BATTLE" class="w-full h-full object-contain drop-shadow-[0_0_20px_rgba(61,64,189,0.7)]">
            </div>
            <h1 class="text-xl font-black font-display tracking-tight text-white">
                BEASTz <span class="text-[#3D40BD]">BATTLE</span>
            </h1>
            <p class="text-[11px] text-slate-400 mt-0.5">Instant OTP Login & Signup</p>
        </div>

        <?php if (!empty($success)): ?>
            <div class="p-3 mb-4 rounded-xl text-xs font-bold bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-400 shrink-0"></i>
                <span><?= htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <!-- PHONE INPUT SECTION -->
        <div id="phone-section" class="space-y-3.5">
            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">MOBILE NUMBER</label>
                <div class="flex gap-2">
                    <input type="text" value="+91" disabled class="w-16 bg-[#0b0f19] border border-[#1e293b] rounded-xl px-2 text-center text-white font-bold">
                    <input type="number" id="phoneNumber" placeholder="10-digit number" class="w-full bg-[#0b0f19] border border-[#1e293b] rounded-xl px-3 py-2 text-white font-bold tracking-wide focus:border-[#3D40BD] focus:outline-none">
                </div>
            </div>
            
            <button id="sendOtpBtn" class="w-full py-3 bg-[#3D40BD] hover:bg-[#6C6FF5] text-white font-black rounded-xl text-xs transition shadow-[0_4px_15px_-2px_rgba(61,64,189,0.4)] active:scale-95 flex items-center justify-center gap-2 cursor-pointer mt-2">
                <i class="fa-solid fa-paper-plane"></i>
                <span>Send OTP</span>
            </button>
        </div>

        <!-- OTP VERIFY SECTION -->
        <div id="otp-section" class="hidden space-y-3.5">
            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1 text-center">ENTER OTP RECEIVED</label>
                <input type="number" id="otpCode" placeholder="••••••" class="w-full bg-[#0b0f19] border border-[#1e293b] rounded-xl px-3 py-3 text-white text-center tracking-[0.5em] text-xl font-bold focus:border-[#3D40BD] focus:outline-none">
            </div>
            
            <button id="verifyOtpBtn" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-black rounded-xl text-xs transition shadow-[0_4px_15px_-2px_rgba(5,150,105,0.4)] active:scale-95 flex items-center justify-center gap-2 cursor-pointer mt-2">
                <i class="fa-solid fa-check-circle"></i>
                <span>Verify & Enter Arena</span>
            </button>
            <button id="backBtn" class="w-full text-slate-400 text-[11px] font-semibold mt-4 hover:text-white transition">
                <i class="fa-solid fa-arrow-left mr-1"></i> Change Number
            </button>
        </div>

    </div>

    <!-- AJAX Frontend Script (No Firebase / No reCAPTCHA) -->
    <script>
        const phoneSection = document.getElementById('phone-section');
        const otpSection = document.getElementById('otp-section');
        const phoneInput = document.getElementById('phoneNumber');
        const otpInput = document.getElementById('otpCode');
        const sendOtpBtn = document.getElementById('sendOtpBtn');
        const verifyOtpBtn = document.getElementById('verifyOtpBtn');
        const backBtn = document.getElementById('backBtn');

        // SEND OTP
        sendOtpBtn.addEventListener('click', () => {
            const numberVal = phoneInput.value.trim();
            if(numberVal.length !== 10) {
                alert("Please enter a valid 10-digit mobile number.");
                return;
            }

            sendOtpBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Sending...</span>';
            sendOtpBtn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'send_otp');
            formData.append('phone', numberVal);

            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    phoneSection.classList.add('hidden');
                    otpSection.classList.remove('hidden');
                    if(data.debug_otp) {
                        alert("OTP (Test): " + data.debug_otp); // Testing ke liye turant OTP alert dikha dega
                    } else {
                        alert(data.message);
                    }
                } else {
                    alert(data.message || "Error sending OTP.");
                }
                sendOtpBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> <span>Send OTP</span>';
                sendOtpBtn.disabled = false;
            })
            .catch(err => {
                alert("Network error.");
                sendOtpBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> <span>Send OTP</span>';
                sendOtpBtn.disabled = false;
            });
        });

        // VERIFY OTP
        verifyOtpBtn.addEventListener('click', () => {
            const code = otpInput.value.trim();
            if(code.length !== 6) {
                alert("Please enter the 6-digit OTP.");
                return;
            }

            verifyOtpBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Verifying...</span>';
            verifyOtpBtn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'verify_otp');
            formData.append('otp', code);

            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    window.location.href = data.redirect;
                } else {
                    alert(data.message || "Invalid OTP.");
                    verifyOtpBtn.innerHTML = '<i class="fa-solid fa-check-circle"></i> <span>Verify & Enter Arena</span>';
                    verifyOtpBtn.disabled = false;
                }
            })
            .catch(err => {
                alert("Server connection error.");
                verifyOtpBtn.innerHTML = '<i class="fa-solid fa-check-circle"></i> <span>Verify & Enter Arena</span>';
                verifyOtpBtn.disabled = false;
            });
        });

        backBtn.addEventListener('click', () => {
            otpSection.classList.add('hidden');
            phoneSection.classList.remove('hidden');
            otpInput.value = '';
        });
    </script>
</body>
</html>
