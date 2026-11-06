<?php
// Ensure transactions are fetched for Passbook History in Settings/Profile view
if (!isset($tx_res) && isset($conn) && isset($me['id'])) {
    $current_uid = (int)$me['id'];
    $tx_res = $conn->query("SELECT * FROM transactions WHERE user_id = $current_uid ORDER BY id DESC LIMIT 15");
}
?>

<!-- Alert Feedback -->
<?php if (!empty($msg)): ?>
    <div class="mb-4 p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2 <?= ($msg_type === 'success') ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600'; ?>"></i>
        <span><?= htmlspecialchars($msg); ?></span>
    </div>
<?php endif; ?>

<!-- Top Profile Header (Clean & Fast) -->
<div class="text-center pt-2 pb-5">
    <div class="relative inline-block mb-2.5 cursor-pointer group" onclick="openAvatarModal()" title="Click to Change Photo">
        <img id="profileHeaderAvatar" src="<?= htmlspecialchars($my_avatar); ?>" alt="Avatar" class="w-20 h-20 rounded-full object-cover border-4 border-blue-500 shadow-md bg-slate-100 mx-auto transition group-hover:scale-105">
        <div class="absolute bottom-0 right-0 w-7 h-7 bg-blue-600 text-white border-2 border-white rounded-full flex items-center justify-center text-xs shadow-md group-hover:bg-blue-600 transition">
            <i class="fa-solid fa-camera text-[10px]"></i>
        </div>
    </div>
    
    <div>
        <button type="button" onclick="openAvatarModal()" class="text-[11px] font-bold text-sky-300 hover:text-sky-200 underline mb-1">
            Change Photo
        </button>
    </div>
    
    <h2 class="text-lg font-black text-slate-900 tracking-tight font-display">
        <?= htmlspecialchars($me['username']); ?>
    </h2>
    <span class="text-xs text-slate-500 block">
        <?= !empty($me['email']) ? htmlspecialchars($me['email']) : (!empty($my_phone) ? '+91 ' . htmlspecialchars($my_phone) : 'Player'); ?>
    </span>

    <div class="mt-2 inline-flex items-center gap-1.5 px-3.5 py-1 bg-blue-950/40 border border-blue-600/40 rounded-full text-xs font-black text-sky-400">
        <i class="fa-solid fa-wallet text-blue-400"></i>
        <span>Balance: <?= format_compact_inr($me['wallet_balance']); ?></span>
    </div>
</div>

<!-- ============================================================= -->
<!-- SECTION 1: ACCOUNT SETTINGS & IN-GAME PROFILE (PRIMARY VIEW) -->
<!-- ============================================================= -->
<div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs mb-3.5">
    <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-blue-950/60 text-sky-200 flex items-center justify-center text-xs">
                <i class="fa-solid fa-id-badge"></i>
            </div>
            <span class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Account Settings</span>
        </div>
        <button type="button" onclick="openAccountModal()" class="px-2.5 py-1 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-lg text-[10px] shadow-xs transition active:scale-95 flex items-center gap-1">
            <i class="fa-solid fa-pen-to-square text-[9px]"></i>
            <span>Edit</span>
        </button>
    </div>

    <div class="grid grid-cols-2 gap-2.5 text-xs">
        <div class="bg-slate-50 border border-slate-100 rounded-xl p-2.5">
            <span class="text-[10px] font-bold text-slate-400 block mb-0.5">Game Name (IGN)</span>
            <span id="viewGameName" class="font-extrabold text-slate-800 break-all">
                <?= !empty($my_ign) ? htmlspecialchars($my_ign) : '<span class="text-rose-500 font-semibold text-[11px]">Not configured</span>'; ?>
            </span>
        </div>
        <div class="bg-slate-50 border border-slate-100 rounded-xl p-2.5">
            <span class="text-[10px] font-bold text-slate-400 block mb-0.5">Free Fire UID</span>
            <span id="viewGameUid" class="font-mono font-bold text-slate-800 break-all">
                <?= !empty($my_game_id) ? htmlspecialchars($my_game_id) : '<span class="text-rose-500 font-semibold text-[11px]">Not configured</span>'; ?>
            </span>
        </div>
        <div class="bg-slate-50 border border-slate-100 rounded-xl p-2.5">
            <span class="text-[10px] font-bold text-slate-400 block mb-0.5">Registered Phone</span>
            <span id="viewPhone" class="font-mono font-bold text-slate-800">
                <?= !empty($my_phone) ? '+91 ' . htmlspecialchars($my_phone) : '<span class="text-slate-400">None</span>'; ?>
            </span>
        </div>
        <div class="bg-slate-50 border border-slate-100 rounded-xl p-2.5">
            <span class="text-[10px] font-bold text-slate-400 block mb-0.5">Payout UPI ID</span>
            <span id="viewUpi" class="font-mono font-bold text-slate-800 truncate block">
                <?= !empty($my_upi) ? htmlspecialchars($my_upi) : '<span class="text-slate-400">Not set</span>'; ?>
            </span>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- JOIN PRIVATE TOURNAMENT (REQUESTED IN ME / SETTINGS) -->
<!-- ============================================================= -->
<div class="bg-gradient-to-r from-purple-500/10 to-blue-500/10 border border-purple-200 dark:border-purple-800/60 rounded-2xl p-4 shadow-xs mb-3.5 flex items-center justify-between gap-3">
    <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-purple-600 text-white flex items-center justify-center text-sm shadow-xs">
            <i class="fa-solid fa-key"></i>
        </div>
        <div>
            <span class="text-xs font-black text-slate-900 dark:text-white block">Join Private Tournament</span>
            <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Enter 6-digit match code to unlock private tournament</span>
        </div>
    </div>
    <button type="button" onclick="openPrivateMatchModal()" class="px-3.5 py-2 bg-purple-600 hover:bg-purple-500 text-white font-black rounded-xl text-xs shadow-xs transition active:scale-95 shrink-0 cursor-pointer">
        Enter Code
    </button>
</div>
<!-- ============================================================= -->
<!-- THEME & DISPLAY PREFERENCES (DARK / LIGHT MODE) -->
<!-- ============================================================= -->
<div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs mb-3.5">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-blue-950/60 text-sky-200 flex items-center justify-center text-sm shadow-2xs">
                <i class="fa-solid fa-moon beastz-theme-icon"></i>
            </div>
            <div>
                <span class="text-xs font-extrabold text-slate-900 block beastz-theme-label">Dark Mode</span>
                <span class="text-[10px] text-slate-400 block">Switch between Cyber Dark & Clean Light theme</span>
            </div>
        </div>
        <!-- Toggle Switch -->
        <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" onchange="toggleTheme()" class="sr-only peer beastz-theme-toggle-input">
            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
        </label>
    </div>
</div>

<!-- SECTION 2: QUICK ACTION BUTTONS -->
<!-- ============================================================= -->
<div class="space-y-2 mb-4">
    <!-- FAQ SECTION (FREQUENTLY ASKED QUESTIONS) -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs mb-3">
        <h3 class="text-xs font-black uppercase text-slate-900 dark:text-white flex items-center gap-2 mb-3">
            <i class="fa-solid fa-circle-question text-blue-400"></i>
            <span>Frequently Asked Questions (FAQ)</span>
        </h3>
        <div class="space-y-2 text-xs">
            <details class="bg-slate-50 dark:bg-slate-800/60 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700/60 cursor-pointer">
                <summary class="font-bold text-slate-800 dark:text-slate-200 flex items-center justify-between">
                    <span>How do I join a custom room?</span>
                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400"></i>
                </summary>
                <p class="text-slate-500 dark:text-slate-400 text-[11px] mt-1.5 leading-relaxed">
                    Once you join a tournament, Room ID and Password will be published 10-15 minutes prior to match time in your "My Matches" tab and via push notification.
                </p>
            </details>
            <details class="bg-slate-50 dark:bg-slate-800/60 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700/60 cursor-pointer">
                <summary class="font-bold text-slate-800 dark:text-slate-200 flex items-center justify-between">
                    <span>When do I receive my winnings?</span>
                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400"></i>
                </summary>
                <p class="text-slate-500 dark:text-slate-400 text-[11px] mt-1.5 leading-relaxed">
                    Winnings are credited to your in-app wallet within 15 minutes of match completion. You can request direct UPI withdrawal anytime (min ₹50).
                </p>
            </details>
            <details class="bg-slate-50 dark:bg-slate-800/60 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700/60 cursor-pointer">
                <summary class="font-bold text-slate-800 dark:text-slate-200 flex items-center justify-between">
                    <span>What is the Anti-Cheat policy?</span>
                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400"></i>
                </summary>
                <p class="text-slate-500 dark:text-slate-400 text-[11px] mt-1.5 leading-relaxed">
                    Strict zero-tolerance policy against hackers and mod users. Matches are monitored for 24 hours, and winnings of caught cheaters are immediately revoked.
                </p>
            </details>
        </div>
    </div>

    <!-- Change Avatar Button -->
    <button type="button" onclick="openAvatarModal()" class="w-full bg-white hover:bg-slate-50 border border-slate-200 rounded-2xl p-3.5 flex items-center justify-between shadow-xs transition group">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-blue-950/60 text-sky-200 flex items-center justify-center text-xs group-hover:bg-blue-600 group-hover:text-black transition">
                <i class="fa-solid fa-camera"></i>
            </div>
            <div class="text-left">
                <span class="text-xs font-bold text-slate-900 block">Change Profile Photo</span>
                <span class="text-[10px] text-slate-400">Upload your image or pick a gaming avatar</span>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-slate-800 transition"></i>
    </button>

    <!-- WhatsApp Support -->
    <a href="https://wa.me/91<?= preg_replace('/\D/', '', $support_phone); ?>?text=Hello%20BEASTzBATTLE%20Support" target="_blank" class="w-full bg-white hover:bg-slate-50 border border-slate-200 rounded-2xl p-3.5 flex items-center justify-between shadow-xs transition group">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs group-hover:bg-emerald-500 group-hover:text-white transition">
                <i class="fa-brands fa-whatsapp text-sm"></i>
            </div>
            <div class="text-left">
                <span class="text-xs font-bold text-slate-900 block">Customer Support (WhatsApp)</span>
                <span class="text-[10px] text-emerald-600 font-semibold">+91 <?= htmlspecialchars($support_phone); ?> &bull; 24/7 Live</span>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-slate-800 transition"></i>
    </a>

    <!-- Sign Out -->
    <a href="logout.php" class="w-full bg-white hover:bg-rose-50/50 border border-slate-200 rounded-2xl p-3.5 flex items-center justify-between shadow-xs transition group text-rose-600">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-xs group-hover:bg-rose-600 group-hover:text-white transition">
                <i class="fa-solid fa-power-off"></i>
            </div>
            <span class="text-xs font-bold block">Sign Out / Logout</span>
        </div>
        <i class="fa-solid fa-chevron-right text-xs text-rose-400 transition"></i>
    </a>
</div>

<!-- ============================================================= -->
<!-- SECTION 3: RECENT PASSBOOK TRANSACTIONS (STRICT 5-ITEM SCROLL BOX) -->
<!-- ============================================================= -->
<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs mb-8">
    <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-blue-950/60 text-sky-200 flex items-center justify-center text-xs">
                <i class="fa-solid fa-receipt"></i>
            </div>
            <span class="text-xs font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">Passbook History</span>
        </div>
        <button type="button" onclick="switchAppTab('wallet')" class="text-[10px] font-bold text-sky-300 hover:underline cursor-pointer">Deposit / Withdraw</button>
    </div>

    <!-- Strict Fixed Height & Vertical Scroll Container -->
    <div class="max-h-[260px] overflow-y-auto pr-1.5 space-y-2 custom-scrollbar">
        <?php if ($tx_res && $tx_res->num_rows > 0): ?>
            <?php while ($tx = $tx_res->fetch_assoc()): ?>
                <div class="p-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/60 flex items-center justify-between text-xs">
                    <div class="pr-2">
                        <span class="font-bold text-slate-900 dark:text-white block text-[11px] leading-tight">
                            <?= htmlspecialchars($tx['description'] ?? 'Transaction'); ?>
                        </span>
                        <span class="text-[9px] text-slate-400">
                            <?= date('d M Y, h:i A', strtotime($tx['created_at'])); ?>
                        </span>
                    </div>
                    <span class="font-black font-mono whitespace-nowrap text-xs <?= ($tx['type'] === 'credit') ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'; ?>">
                        <?= ($tx['type'] === 'credit') ? '+' : '-'; ?><?= format_inr($tx['amount']); ?>
                    </span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center py-4 text-xs text-slate-400">No passbook transactions recorded yet.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 1: ACCOUNT SETTINGS (IGN, Free Fire Game ID, Phone, UPI) -->
<!-- ============================================================= -->
<div id="accountModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeAccountModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-lg font-black mx-auto mb-2 shadow-xs">
                <i class="fa-solid fa-user-gear"></i>
            </div>
            <h3 class="text-sm font-extrabold text-slate-900">Account Settings</h3>
            <p class="text-[11px] text-slate-400">Save your in-game identity for tournaments</p>
        </div>

        <form id="fastAccountForm" onsubmit="submitFastAccount(event)" class="space-y-3">
            <input type="hidden" name="action" value="update_account_settings">
            
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Game Character Name (IGN) <span class="text-rose-500">*</span></label>
                <input type="text" name="game_name" value="<?= htmlspecialchars($my_ign); ?>" required placeholder="e.g. ShadowHunter" class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Free Fire Game UID <span class="text-rose-500">*</span></label>
                <input type="text" name="game_id" value="<?= htmlspecialchars($my_game_id); ?>" required placeholder="e.g. 2839221886" class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">WhatsApp Phone Number</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($my_phone); ?>" placeholder="10-digit mobile" class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-mono font-bold focus:outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Default Payout UPI ID</label>
                <input type="text" name="upi_id" value="<?= htmlspecialchars($my_upi); ?>" placeholder="e.g. yourname@upi" class="w-full bg-slate-50 border border-slate-300 focus:border-blue-500 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-mono focus:outline-none">
            </div>

            <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-xl text-xs transition shadow-sm active:scale-95">
                Save Account Details
            </button>
        </form>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 2: CHANGE PROFILE PHOTO (WITH FAST CANVAS RESIZER) -->
<!-- ============================================================= -->
<div id="avatarModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative max-h-[90vh] overflow-y-auto">
        <button type="button" onclick="closeAvatarModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-3">
            <h3 class="text-sm font-extrabold text-slate-900">Change Profile Photo</h3>
            <p class="text-[11px] text-slate-400">Upload a picture from phone or select an avatar</p>
        </div>

        <form id="avatarForm" onsubmit="submitFastAvatar(event)" class="space-y-4">
            <input type="hidden" name="action" value="update_avatar">
            <input type="hidden" name="avatar" id="selectedAvatarInput" value="<?= htmlspecialchars($my_avatar); ?>">

            <!-- Live Preview -->
            <div class="text-center">
                <div class="relative inline-block">
                    <img id="avatarPreviewImg" src="<?= htmlspecialchars($my_avatar); ?>" alt="Preview" class="w-20 h-20 rounded-full object-cover border-4 border-blue-500 shadow-sm mx-auto bg-slate-100">
                    <div id="uploadSpinner" class="hidden absolute inset-0 bg-black/40 rounded-full flex items-center justify-center text-white text-xs">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                    </div>
                </div>
                <span class="block text-[10px] text-slate-400 mt-1">Photo Preview</span>
            </div>

            <!-- Upload Custom Image (With Client-Side HTML5 Canvas Downscaler) -->
            <div class="bg-blue-950/40/70 border border-blue-500/40 rounded-2xl p-3 text-center">
                <label for="avatarFileInput" class="block cursor-pointer">
                    <div class="w-10 h-10 mx-auto rounded-full bg-blue-600 text-white flex items-center justify-center text-base mb-1.5 shadow-xs">
                        <i class="fa-solid fa-arrow-up-from-bracket"></i>
                    </div>
                    <span class="text-xs font-black text-slate-900 block">Upload from Phone Gallery / Camera</span>
                    <span class="text-[10px] text-slate-500 block mt-0.5">Auto-optimized for ultra fast loading</span>
                </label>
                <input type="file" id="avatarFileInput" accept="image/*" onclick="this.value = null;" class="hidden">
            </div>

            <!-- Preset Gaming Avatars Grid (Pure Character Vectors, Zero Letters/Text) -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-2">Or Choose a Gaming Character Avatar</label>
                <div class="grid grid-cols-4 gap-2">
                    <?php
                    global $BEASTZ_GAMING_AVATARS;
                    if (!empty($BEASTZ_GAMING_AVATARS)):
                        foreach ($BEASTZ_GAMING_AVATARS as $pr):
                    ?>
                        <button type="button" onclick="selectPreset('<?= htmlspecialchars($pr['svg']); ?>', this)" class="preset-btn p-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-blue-500 bg-slate-50 dark:bg-slate-800 hover:bg-blue-950/40 transition flex flex-col items-center">
                            <img src="<?= htmlspecialchars($pr['svg']); ?>" alt="<?= $pr['label']; ?>" class="w-10 h-10 rounded-full pointer-events-none shadow-xs">
                            <span class="text-[9px] font-black text-slate-700 dark:text-slate-300 mt-1"><?= $pr['label']; ?></span>
                        </button>
                    <?php 
                        endforeach;
                    endif;
                    ?>
                </div>
            </div>

            <button type="submit" id="saveAvatarBtn" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-xl text-xs transition shadow-xs active:scale-95 flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Save Profile Photo</span>
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

    // High performance client-side canvas compressor (downscales to 256x256 max)
    const avatarInput = document.getElementById('avatarFileInput');
    if (avatarInput) {
        avatarInput.onclick = function() { this.value = null; };
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files && e.target.files[0];
            if (!file) return;
            document.querySelectorAll('.preset-btn').forEach(function(b) {
                b.classList.remove('border-blue-500', 'ring-2', 'ring-blue-500', 'bg-blue-950/60', 'dark:bg-blue-950/40');
                b.classList.add('border-slate-200', 'dark:border-slate-700');
            });

            const spinner = document.getElementById('uploadSpinner');
            if (spinner) spinner.classList.remove('hidden');

            const reader = new FileReader();
            reader.onload = function(evt) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    const MAX_SIZE = 256;
                    let width = img.width;
                    let height = img.height;

                    if (width > height) {
                        if (width > MAX_SIZE) {
                            height *= MAX_SIZE / width;
                            width = MAX_SIZE;
                        }
                    } else {
                        if (height > MAX_SIZE) {
                            width *= MAX_SIZE / height;
                            height = MAX_SIZE;
                        }
                    }

                    canvas.width = Math.round(width);
                    canvas.height = Math.round(height);
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                    // Compress to lightweight JPEG quality 0.8 (~20KB)
                    const compressedDataUri = canvas.toDataURL('image/jpeg', 0.8);
                    
                    document.getElementById('selectedAvatarInput').value = compressedDataUri;
                    document.getElementById('avatarPreviewImg').src = compressedDataUri;
                    const headerImg = document.getElementById('profileHeaderAvatar');
                    if (headerImg) headerImg.src = compressedDataUri;

                    if (spinner) spinner.classList.add('hidden');
                };
                img.src = evt.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    function showFastToast(msg, type) {
        var el = document.getElementById('fastToastAlert');
        if (!el) {
            el = document.createElement('div');
            el.id = 'fastToastAlert';
            el.className = 'fixed top-5 left-1/2 -translate-x-1/2 z-[9999] px-4 py-2.5 rounded-2xl shadow-xl text-xs font-bold transition-all duration-300 transform -translate-y-5 opacity-0 flex items-center gap-2';
            document.body.appendChild(el);
        }
        el.className = 'fixed top-5 left-1/2 -translate-x-1/2 z-[9999] px-4 py-2.5 rounded-2xl shadow-xl text-xs font-bold transition-all duration-300 flex items-center gap-2 ' + 
            (type === 'success' ? 'bg-emerald-500 text-white shadow-emerald-500/20' : 'bg-rose-500 text-white shadow-rose-500/20');
        el.innerHTML = (type === 'success' ? '<i class="fa-solid fa-circle-check"></i> ' : '<i class="fa-solid fa-circle-exclamation"></i> ') + msg;
        setTimeout(function() { el.style.opacity = '1'; el.style.transform = 'translate(-50%, 0)'; }, 20);
        setTimeout(function() { el.style.opacity = '0'; el.style.transform = 'translate(-50%, -10px)'; }, 2800);
    }

    function submitFastAccount(e) {
        e.preventDefault();
        var form = e.target;
        var btn = form.querySelector('button[type="submit"]');
        var fd = new FormData(form);

        btn.disabled = true;
        var origText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> <span>Saving...</span>';

        var sender = window.beastzFetch || fetch;
        sender('api/update_profile.php', {
            method: 'POST',
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = origText;
            if (data.success) {
                closeAccountModal();
                if (document.getElementById('viewGameName')) document.getElementById('viewGameName').innerText = data.game_name || 'Not configured';
                if (document.getElementById('viewGameUid')) document.getElementById('viewGameUid').innerText = data.game_id || 'Not configured';
                if (document.getElementById('viewPhone')) document.getElementById('viewPhone').innerText = data.phone ? ('+91 ' + data.phone) : 'None';
                if (document.getElementById('viewUpi')) document.getElementById('viewUpi').innerText = data.upi_id || 'Not set';
                showFastToast(data.message || 'Account details saved!', 'success');
            } else {
                showFastToast(data.error || 'Failed to save settings', 'error');
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.innerHTML = origText;
            showFastToast('Network error saving settings', 'error');
        });
    }

    function submitFastAvatar(e) {
        e.preventDefault();
        var btn = document.getElementById('saveAvatarBtn');
        var avatar = document.getElementById('selectedAvatarInput').value;

        if (!avatar) {
            showFastToast('Please choose or upload a photo first', 'error');
            return;
        }

        btn.disabled = true;
        var origText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> <span>Saving Photo...</span>';

        var fd = new FormData();
        fd.append('action', 'update_avatar');
        fd.append('avatar', avatar);
        var fileInput = document.getElementById('avatarFileInput');
        if (fileInput && fileInput.files && fileInput.files[0] && !avatar.startsWith('data:image/svg+xml')) {
            fd.append('avatar_file', fileInput.files[0]);
        }

        var sender = window.beastzFetch || fetch;
        sender('api/update_profile.php', {
            method: 'POST',
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = origText;
            if (data.success) {
                closeAvatarModal();
                var headerImg = document.getElementById('profileHeaderAvatar');
                if (headerImg) headerImg.src = data.avatar;
                var previewImg = document.getElementById('avatarPreviewImg');
                if (previewImg) previewImg.src = data.avatar;
                showFastToast(data.message || 'Photo updated!', 'success');
            } else {
                showFastToast(data.error || 'Failed to update photo', 'error');
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.innerHTML = origText;
            showFastToast('Network error updating photo', 'error');
        });
    }
</script>
