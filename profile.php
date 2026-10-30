<?php
require_once __DIR__ . '/common/header.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];
$msg = "";
$msg_type = "";

// 1. Handle Profile Avatar Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_avatar') {
    $chosen_avatar = sanitize($_POST['avatar'] ?? '');
    if (!empty($chosen_avatar)) {
        $upd = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        if ($upd) {
            $upd->bind_param("si", $chosen_avatar, $user_id);
            if ($upd->execute()) {
                $msg = "Profile photo updated successfully!";
                $msg_type = "success";
            } else {
                $msg = "Failed to update profile photo: " . $upd->error;
                $msg_type = "error";
            }
            $upd->close();
        }
    }
}

// 2. Handle Account Settings Update (IGN & Free Fire UID)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_game_id') {
    $game_name = sanitize($_POST['game_name'] ?? '');
    $game_id = sanitize($_POST['game_id'] ?? '');

    $upd = $conn->prepare("UPDATE users SET game_name = ?, game_id = ? WHERE id = ?");
    if ($upd) {
        $upd->bind_param("ssi", $game_name, $game_id, $user_id);
        if ($upd->execute()) {
            $msg = "Account details (IGN & Game UID) saved successfully!";
            $msg_type = "success";
        } else {
            $msg = "Update failed: " . $upd->error;
            $msg_type = "error";
        }
        $upd->close();
    }
}

// 3. Handle UPI Withdrawal Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_withdrawal') {
    $amount = (float)($_POST['amount'] ?? 0.00);
    $upi_id = sanitize($_POST['upi_id'] ?? '');
    $min_wdr = (float)get_setting('min_withdrawal', '50.00');

    // Fetch user current balances
    $u_stmt = $conn->prepare("SELECT wallet_balance, winning_balance FROM users WHERE id = ?");
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    $u_bal = $u_stmt->get_result()->fetch_assoc();
    $u_stmt->close();

    $curr_wallet = (float)($u_bal['wallet_balance'] ?? 0.00);

    if ($amount < $min_wdr) {
        $msg = "Minimum withdrawal amount is " . format_inr($min_wdr) . ".";
        $msg_type = "error";
    } elseif (empty($upi_id) || !str_contains($upi_id, '@')) {
        $msg = "Please enter a valid UPI ID (e.g. yourname@upi).";
        $msg_type = "error";
    } elseif ($curr_wallet < $amount) {
        $msg = "Insufficient wallet balance for this payout.";
        $msg_type = "error";
    } else {
        $conn->begin_transaction();
        try {
            // Record Withdrawal Request
            $w_stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, upi_id, status) VALUES (?, ?, ?, 'Pending')");
            $w_stmt->bind_param("ids", $user_id, $amount, $upi_id);
            $w_stmt->execute();
            $w_stmt->close();

            // Save UPI ID to user profile
            $conn->query("UPDATE users SET upi_id = '" . $conn->real_escape_string($upi_id) . "' WHERE id = {$user_id}");

            $conn->commit();
            $msg = "Withdrawal request of " . format_inr($amount) . " submitted! Payout will be verified within 15-30 mins.";
            $msg_type = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Withdrawal request failed: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

// Fetch Full Profile Info
$stmt = $conn->prepare("SELECT username, email, phone, wallet_balance, upi_id, avatar, game_name, game_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

$my_avatar = get_avatar_url($me['avatar'] ?? '', $me['username']);
$my_ign = !empty($me['game_name']) ? $me['game_name'] : '';
$my_game_id = !empty($me['game_id']) ? $me['game_id'] : '';
$my_phone = !empty($me['phone']) ? $me['phone'] : '';

// Fetch Recent Transactions
$tx_res = $conn->query("SELECT * FROM transactions WHERE user_id = {$user_id} ORDER BY id DESC LIMIT 10");

// Official Support Number
$support_phone = get_setting('whatsapp_support', '9878385810');
if (empty($support_phone)) $support_phone = '9878385810';
?>

<!-- Alert Feedback -->
<?php if (!empty($msg)): ?>
    <div class="mb-4 p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2 <?= ($msg_type === 'success') ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600'; ?>"></i>
        <span><?= htmlspecialchars($msg); ?></span>
    </div>
<?php endif; ?>

<!-- Top Profile Header -->
<div class="text-center pt-2 pb-6">
    <div class="relative inline-block mb-3 cursor-pointer group" onclick="openAvatarModal()">
        <img id="profileHeaderAvatar" src="<?= htmlspecialchars($my_avatar); ?>" alt="Avatar" class="w-20 h-20 rounded-full object-cover border-4 border-yellow-400 shadow-md bg-slate-100 mx-auto transition group-hover:scale-105">
        <button type="button" class="absolute bottom-0 right-0 w-7 h-7 bg-yellow-400 text-slate-950 border-2 border-white rounded-full flex items-center justify-center text-xs shadow-md group-hover:bg-yellow-500 transition">
            <i class="fa-solid fa-camera text-[10px]"></i>
        </button>
    </div>
    <div class="block">
        <button type="button" onclick="openAvatarModal()" class="text-[11px] font-bold text-yellow-600 hover:text-yellow-700 underline mb-2">
            Change Profile Photo
        </button>
    </div>
    
    <h2 class="text-lg font-black text-slate-900 tracking-tight font-display">
        <?= htmlspecialchars($me['username']); ?>
    </h2>
    <span class="text-xs text-slate-500 block">
        <?= !empty($me['email']) ? htmlspecialchars($me['email']) : '+91 ' . htmlspecialchars($my_phone); ?>
    </span>

    <div class="mt-2.5 inline-flex items-center gap-1.5 px-3.5 py-1 bg-yellow-50 border border-yellow-300 rounded-full text-xs font-black text-yellow-900">
        <i class="fa-solid fa-wallet text-yellow-500"></i>
        <span>Balance: <?= format_inr($me['wallet_balance']); ?></span>
    </div>
</div>

<!-- Profile Navigation Menu Cards -->
<div class="space-y-2.5 mb-6">
    
    <!-- 0. Change Avatar Photo -->
    <button type="button" onclick="openAvatarModal()" class="w-full bg-white hover:bg-slate-50 border border-slate-200 rounded-2xl p-4 flex items-center justify-between shadow-xs transition group">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-yellow-100 text-yellow-700 flex items-center justify-center text-sm group-hover:bg-yellow-400 group-hover:text-black transition">
                <i class="fa-solid fa-image"></i>
            </div>
            <div class="text-left">
                <span class="text-xs font-bold text-slate-900 block">Change Profile Photo</span>
                <span class="text-[10px] text-slate-400">Choose from 12 gamer avatars or upload custom image</span>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-slate-800 transition"></i>
    </button>

    <!-- 1. Account Settings -->
    <button type="button" onclick="openAccountModal()" class="w-full bg-white hover:bg-slate-50 border border-slate-200 rounded-2xl p-4 flex items-center justify-between shadow-xs transition group">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-sm group-hover:bg-yellow-100 group-hover:text-yellow-700 transition">
                <i class="fa-regular fa-user"></i>
            </div>
            <div class="text-left">
                <span class="text-xs font-bold text-slate-900 block">Account Settings</span>
                <span class="text-[10px] text-slate-400">
                    IGN: <strong class="text-slate-700"><?= !empty($my_ign) ? htmlspecialchars($my_ign) : 'Not set'; ?></strong> &bull; 
                    UID: <strong class="text-slate-700"><?= !empty($my_game_id) ? htmlspecialchars($my_game_id) : 'Not set'; ?></strong>
                </span>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-slate-800 transition"></i>
    </button>

    <!-- 2. Wallet & Cashout -->
    <a href="wallet.php" class="w-full bg-white hover:bg-slate-50 border border-slate-200 rounded-2xl p-4 flex items-center justify-between shadow-xs transition group">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-sm group-hover:bg-yellow-100 group-hover:text-yellow-700 transition">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div class="text-left">
                <span class="text-xs font-bold text-slate-900 block">Deposit & Withdraw</span>
                <span class="text-[10px] text-slate-400">Add cash or withdraw to your UPI ID</span>
            </div>
        </div>
        <span class="text-xs font-extrabold text-slate-900 font-mono"><?= format_inr($me['wallet_balance']); ?></span>
    </a>

    <!-- 3. WhatsApp Support -->
    <a href="https://wa.me/91<?= $support_phone; ?>?text=<?= urlencode('Hello BEASTzBATTLE Support, I need help with my account: @' . $me['username']); ?>" target="_blank" class="w-full bg-white hover:bg-slate-50 border border-slate-200 rounded-2xl p-4 flex items-center justify-between shadow-xs transition group">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-base group-hover:bg-emerald-500 group-hover:text-white transition">
                <i class="fa-brands fa-whatsapp"></i>
            </div>
            <div class="text-left">
                <span class="text-xs font-bold text-slate-900 block">WhatsApp Support</span>
                <span class="text-[10px] text-slate-400">+91 <?= htmlspecialchars($support_phone); ?> (Instant Reply)</span>
            </div>
        </div>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">Online</span>
    </a>

    <!-- 4. Direct Call Support -->
    <a href="tel:<?= $support_phone; ?>" class="w-full bg-white hover:bg-slate-50 border border-slate-200 rounded-2xl p-4 flex items-center justify-between shadow-xs transition group">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-yellow-50 text-yellow-600 flex items-center justify-center text-sm group-hover:bg-yellow-400 group-hover:text-slate-950 transition">
                <i class="fa-solid fa-phone"></i>
            </div>
            <div class="text-left">
                <span class="text-xs font-bold text-slate-900 block">Phone Customer Helpline</span>
                <span class="text-[10px] text-slate-400"><?= htmlspecialchars($support_phone); ?></span>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-slate-800 transition"></i>
    </a>
</div>

<!-- Logout -->
<div class="text-center pb-8">
    <a href="logout.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-rose-600 hover:text-rose-700 transition px-4 py-2 rounded-xl bg-rose-50 border border-rose-200">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        <span>Logout Account</span>
    </a>
</div>

<!-- ============================================================= -->
<!-- MODAL: CHANGE PROFILE PHOTO (12 Gamer Avatars + Custom Upload) -->
<!-- ============================================================= -->
<div id="avatarModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative max-h-[90vh] overflow-y-auto">
        <button type="button" onclick="closeAvatarModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-4">
            <div class="w-16 h-16 rounded-full overflow-hidden border-3 border-yellow-400 mx-auto mb-2 shadow-sm bg-slate-100">
                <img id="avatarPreviewImg" src="<?= htmlspecialchars($my_avatar); ?>" alt="Preview" class="w-full h-full object-cover">
            </div>
            <h3 class="text-sm font-extrabold text-slate-900">Choose Profile Photo</h3>
            <p class="text-[11px] text-slate-400">Pick a gamer avatar or upload your photo</p>
        </div>

        <form method="POST" action="profile.php" class="space-y-4">
            <input type="hidden" name="action" value="update_avatar">
            <input type="hidden" name="avatar" id="selectedAvatarInput" value="<?= htmlspecialchars($me['avatar'] ?? ''); ?>">

            <!-- 12 Gamer Presets Grid -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Popular Gamer Avatars</label>
                <div class="grid grid-cols-4 gap-2.5">
                    <?php
                    $presets = [
                        ['label' => 'Dragon', 'color' => '%23f59e0b', 'icon' => '🐉'],
                        ['label' => 'Ninja', 'color' => '%2306b6d4', 'icon' => '🥷'],
                        ['label' => 'Skull', 'color' => '%238b5cf6', 'icon' => '💀'],
                        ['label' => 'Lion', 'color' => '%23facc15', 'icon' => '🦁'],
                        ['label' => 'Fire', 'color' => '%23ef4444', 'icon' => '🔥'],
                        ['label' => 'Wolf', 'color' => '%2364748b', 'icon' => '🐺'],
                        ['label' => 'Crown', 'color' => '%23eab308', 'icon' => '👑'],
                        ['label' => 'Ghost', 'color' => '%2310b981', 'icon' => '👻'],
                        ['label' => 'Robot', 'color' => '%233b82f6', 'icon' => '🤖'],
                        ['label' => 'Tiger', 'color' => '%23f97316', 'icon' => '🐯'],
                        ['label' => 'Samurai', 'color' => '%23ec4899', 'icon' => '⚔️'],
                        ['label' => 'Viper', 'color' => '%2314b8a6', 'icon' => '🐍'],
                    ];
                    foreach ($presets as $idx => $pr):
                        $svg_uri = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'><rect width='100' height='100' rx='50' fill='{$pr['color']}'/><text x='50%' y='58%' font-size='44' text-anchor='middle' dominant-baseline='middle'>{$pr['icon']}</text></svg>";
                    ?>
                        <button type="button" onclick="selectPreset('<?= $svg_uri; ?>')" class="preset-btn p-1 rounded-2xl border-2 border-slate-200 hover:border-yellow-400 bg-slate-50 transition flex flex-col items-center">
                            <img src="<?= $svg_uri; ?>" alt="<?= $pr['label']; ?>" class="w-12 h-12 rounded-full pointer-events-none">
                            <span class="text-[9px] font-bold text-slate-600 mt-1"><?= $pr['label']; ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Custom Photo Upload / URL -->
            <div class="border-t border-slate-200 pt-3">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Or Upload from Phone</label>
                <input type="file" id="avatarFileInput" accept="image/*" class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-yellow-400 file:text-black hover:file:bg-yellow-500 cursor-pointer">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Or Paste Image URL</label>
                <input type="url" id="avatarUrlInput" placeholder="https://example.com/my-photo.jpg" oninput="handleUrlInput(this.value)" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none">
            </div>

            <button type="submit" class="w-full py-3 bg-yellow-400 hover:bg-yellow-500 text-black font-extrabold rounded-xl text-xs transition shadow-xs active:scale-95 flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-floppy-disk"></i> Save Profile Photo
            </button>
        </form>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL: ACCOUNT SETTINGS (IGN & Free Fire Game ID) -->
<!-- ============================================================= -->
<div id="accountModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeAccountModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-4">
            <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-yellow-400 mx-auto mb-2 shadow-xs bg-slate-100">
                <img src="<?= htmlspecialchars($my_avatar); ?>" alt="Avatar" class="w-full h-full object-cover">
            </div>
            <h3 class="text-sm font-extrabold text-slate-900">Account Settings</h3>
            <p class="text-[11px] text-slate-400">Save your in-game identity for tournaments</p>
        </div>

        <form method="POST" action="profile.php" class="space-y-3.5">
            <input type="hidden" name="action" value="update_game_id">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Game Character Name (IGN)</label>
                <input type="text" name="game_name" value="<?= htmlspecialchars($my_ign); ?>" required placeholder="e.g. ꧁☆BEAST☆꧂" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Game UID (Free Fire / BGMI ID)</label>
                <input type="text" name="game_id" value="<?= htmlspecialchars($my_game_id); ?>" required placeholder="e.g. 2839221886" class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>

            <button type="submit" class="w-full py-2.5 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-sm active:scale-95">
                Save Account Details
            </button>
        </form>
    </div>
</div>

<script>
    function openAvatarModal() {
        document.getElementById('avatarModal').classList.remove('hidden');
    }
    function closeAvatarModal() {
        document.getElementById('avatarModal').classList.add('hidden');
    }

    function openAccountModal() {
        document.getElementById('accountModal').classList.remove('hidden');
    }
    function closeAccountModal() {
        document.getElementById('accountModal').classList.add('hidden');
    }

    function selectPreset(svgData) {
        document.getElementById('selectedAvatarInput').value = svgData;
        document.getElementById('avatarPreviewImg').src = svgData;
        const headerImg = document.getElementById('profileHeaderAvatar');
        if (headerImg) headerImg.src = svgData;
    }

    function handleUrlInput(url) {
        if (url && url.startsWith('http')) {
            document.getElementById('selectedAvatarInput').value = url;
            document.getElementById('avatarPreviewImg').src = url;
        }
    }

    // Handle Local File Upload to base64
    document.getElementById('avatarFileInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.size > 2 * 1024 * 1024) {
                alert('Please choose an image under 2MB.');
                return;
            }
            const reader = new FileReader();
            reader.onload = function(evt) {
                const base64 = evt.target.result;
                document.getElementById('selectedAvatarInput').value = base64;
                document.getElementById('avatarPreviewImg').src = base64;
            };
            reader.readAsDataURL(file);
        }
    });

    // Automatically open avatar modal if change_photo is in URL
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('change_photo') === '1') {
            openAvatarModal();
        }
    });
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
