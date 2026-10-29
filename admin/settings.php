<?php
require_once __DIR__ . '/common/header.php';

$msg = "";
$msg_type = "";

// 1. Handle Cashfree & Admin Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_all_settings') {
    $cashfree_app_id = sanitize($_POST['cashfree_app_id']);
    $cashfree_secret = sanitize($_POST['cashfree_secret_key']);
    $cashfree_env = sanitize($_POST['cashfree_env']);
    $admin_upi = sanitize($_POST['admin_upi_id']);
    $whatsapp = sanitize($_POST['support_whatsapp']);

    // Save Cashfree Keys into DB
    $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('cashfree_app_id', '$cashfree_app_id') ON DUPLICATE KEY UPDATE setting_value = '$cashfree_app_id'");
    $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('cashfree_secret_key', '$cashfree_secret') ON DUPLICATE KEY UPDATE setting_value = '$cashfree_secret'");
    $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('cashfree_env', '$cashfree_env') ON DUPLICATE KEY UPDATE setting_value = '$cashfree_env'");
    $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_upi_id', '$admin_upi') ON DUPLICATE KEY UPDATE setting_value = '$admin_upi'");
    $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('support_whatsapp', '$whatsapp') ON DUPLICATE KEY UPDATE setting_value = '$whatsapp'");

    // Handle QR Upload
    if (isset($_FILES['qr_code_image']) && $_FILES['qr_code_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['qr_code_image']['tmp_name'];
        $file_name = $_FILES['qr_code_image']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allowed)) {
            $qr_dir = __DIR__ . '/../uploads/qr/';
            if (!file_exists($qr_dir)) {
                mkdir($qr_dir, 0777, true);
            }
            $new_name = 'admin_qr_' . time() . '.' . $ext;
            if (move_uploaded_file($file_tmp, $qr_dir . $new_name)) {
                $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_qr_image', '$new_name') ON DUPLICATE KEY UPDATE setting_value = '$new_name'");
            }
        }
    }

    $msg = "Cashfree Gateway aur UPI settings successfully update ho gayi!";
    $msg_type = "success";
}

// 2. Handle Admin Password Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_admin_auth') {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];

    $adm_id = (int)$_SESSION['admin_id'];
    $stmt = $conn->prepare("SELECT password FROM admin WHERE id = ?");
    $stmt->bind_param("i", $adm_id);
    $stmt->execute();
    $curr_hash = $stmt->get_result()->fetch_assoc()['password'];
    $stmt->close();

    if (!password_verify($current_pass, $curr_hash) && $current_pass !== $curr_hash) {
        $msg = "Current admin password galat hai.";
        $msg_type = "error";
    } elseif (strlen($new_pass) < 6) {
        $msg = "New password kam se kam 6 characters ka hona chahiye.";
        $msg_type = "error";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
        $upd->bind_param("si", $hashed, $adm_id);
        if ($upd->execute()) {
            $msg = "Admin password successfully update ho gaya!";
            $msg_type = "success";
        }
        $upd->close();
    }
}

// Fetch Latest Settings from DB
$settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM settings");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
}

$cf_app_id = $settings['cashfree_app_id'] ?? (defined('CASHFREE_APP_ID') ? CASHFREE_APP_ID : '');
$cf_secret = $settings['cashfree_secret_key'] ?? (defined('CASHFREE_SECRET_KEY') ? CASHFREE_SECRET_KEY : '');
$cf_env = $settings['cashfree_env'] ?? (defined('CASHFREE_ENVIRONMENT') ? CASHFREE_ENVIRONMENT : 'TEST');
$admin_upi = $settings['admin_upi_id'] ?? 'adeptplay@upi';
$admin_qr = $settings['admin_qr_image'] ?? '';
$support_wa = $settings['support_whatsapp'] ?? '+919878385810';
?>

<div class="mb-4">
    <h2 class="text-base font-bold text-white">System Settings</h2>
    <p class="text-[11px] text-slate-400">Cashfree Payment Gateway & Admin Configuration</p>
</div>

<!-- Alerts -->
<?php if (!empty($msg)): ?>
    <div class="mb-4 p-3 rounded-xl text-xs font-semibold flex items-center gap-2 <?= ($msg_type === 'success') ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?>"></i>
        <span><?= $msg; ?></span>
    </div>
<?php endif; ?>

<!-- Form 1: Cashfree & UPI Settings -->
<div class="bg-slate-800/80 border border-slate-700 rounded-2xl p-4 shadow-lg mb-4">
    <form method="POST" action="settings.php" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="action" value="update_all_settings">

        <!-- Cashfree Payment Gateway Box -->
        <div class="p-3.5 rounded-xl bg-violet-950/40 border border-violet-500/30 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-violet-300 flex items-center gap-1.5">
                    <i class="fa-solid fa-bolt text-amber-400"></i> Cashfree Payment Gateway
                </span>
                <span class="text-[9px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-1.5 py-0.5 rounded font-mono font-bold">API v2023-08-01</span>
            </div>

            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">
                    Cashfree App ID (Client ID) <span class="text-rose-400">*</span>
                </label>
                <input type="text" name="cashfree_app_id" value="<?= htmlspecialchars($cf_app_id); ?>" placeholder="e.g. 1002345abcdef" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs font-mono text-emerald-400 focus:outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">
                    Cashfree Secret Key <span class="text-rose-400">*</span>
                </label>
                <input type="password" name="cashfree_secret_key" value="<?= htmlspecialchars($cf_secret); ?>" placeholder="Enter Cashfree Secret Key" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs font-mono text-slate-200 focus:outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Environment Mode</label>
                <select name="cashfree_env" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-2.5 py-2 text-xs text-white focus:outline-none">
                    <option value="TEST" <?= ($cf_env === 'TEST') ? 'selected' : '' ?>>TEST (Sandbox / Testing Keys)</option>
                    <option value="PROD" <?= ($cf_env === 'PROD') ? 'selected' : '' ?>>PROD (Production / Live Real Payments)</option>
                </select>
            </div>
        </div>

        <!-- Manual UPI Fallback Box -->
        <div class="space-y-3 pt-1">
            <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                <i class="fa-solid fa-qrcode text-emerald-400"></i> Manual UPI / QR Details
            </h4>

            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Admin UPI ID</label>
                <input type="text" name="admin_upi_id" value="<?= htmlspecialchars($admin_upi); ?>" required placeholder="e.g. yourname@okaxis" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Upload QR Code Image</label>
                <input type="file" name="qr_code_image" accept="image/*" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-400 file:mr-3 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[10px] file:font-semibold file:bg-violet-600 file:text-white">
            </div>

            <?php if (!empty($admin_qr) && file_exists(__DIR__ . '/../uploads/qr/' . $admin_qr)): ?>
                <div class="bg-slate-900/60 p-2 rounded-xl border border-slate-800 flex items-center gap-3">
                    <img src="../uploads/qr/<?= htmlspecialchars($admin_qr); ?>" alt="Admin QR" class="w-12 h-12 object-contain rounded-lg border border-slate-700 bg-white p-0.5">
                    <span class="text-[11px] text-slate-300 font-semibold">Active QR for Manual Payments</span>
                </div>
            <?php endif; ?>

            <div>
                <label class="block text-[11px] font-medium text-slate-300 mb-1">Support WhatsApp Number</label>
                <input type="text" name="support_whatsapp" value="<?= htmlspecialchars($support_wa); ?>" class="w-full bg-slate-900 border border-slate-700 focus:border-violet-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
            </div>
        </div>

        <button type="submit" class="w-full py-2.5 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl text-xs transition shadow-md shadow-violet-600/30">
            Save Payment & Gateway Configuration
        </button>
    </form>
</div>

<!-- Form 2: Admin Password -->
<div class="bg-slate-800/80 border border-slate-700 rounded-2xl p-4 shadow-lg mb-4">
    <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <i class="fa-solid fa-lock text-rose-400"></i> Update Admin Password
    </h3>

    <form method="POST" action="settings.php" class="space-y-3">
        <input type="hidden" name="action" value="update_admin_auth">

        <div>
            <label class="block text-[11px] font-medium text-slate-300 mb-1">Current Password</label>
            <input type="password" name="current_password" required placeholder="Enter current admin password" class="w-full bg-slate-900 border border-slate-700 focus:border-rose-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
        </div>

        <div>
            <label class="block text-[11px] font-medium text-slate-300 mb-1">New Admin Password</label>
            <input type="password" name="new_password" required minlength="6" placeholder="Choose minimum 6 chars" class="w-full bg-slate-900 border border-slate-700 focus:border-rose-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-none">
        </div>

        <button type="submit" class="w-full py-2 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-xl text-xs transition">
            Update Credentials
        </button>
    </form>
</div>

<div class="bg-slate-900 border border-slate-800 rounded-xl p-3 text-center text-xs text-slate-400">
    <span class="font-bold text-white block">Adept Code &middot; Learn, Build, Conquer</span>
    <span class="text-[10px]">Official Support: +91 9878385810 / 988385810</span>
</div>

<?php require_once __DIR__ . '/common/bottom.php'; ?>