<?php
require_once __DIR__ . '/common/config.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];
$error = "";
$success = "";

// Fetch current user details
$stmt = $conn->prepare("SELECT name, username, email, phone, avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    safe_redirect("login.php");
    exit;
}

// If form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_profile') {
    $desired_username = trim($_POST['username'] ?? '');
    $phone_input = trim($_POST['phone'] ?? '');

    // 1. Sanitize username
    $clean_username = preg_replace('/[^a-zA-Z0-9_]/', '', $desired_username);
    
    // 2. Sanitize phone (keep only digits)
    $clean_phone = preg_replace('/[^0-9]/', '', $phone_input);

    if (empty($clean_username) || strlen($clean_username) < 3 || strlen($clean_username) > 20) {
        $error = "Username must be between 3 and 20 characters (letters, numbers, underscore only).";
    } elseif (strlen($clean_phone) !== 10) {
        $error = "Please enter a valid 10-digit mobile / WhatsApp number.";
    } else {
        // Check if username is already taken by another player
        $chk = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
        $chk->bind_param("si", $clean_username, $user_id);
        $chk->execute();
        $taken = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($taken) {
            $error = "Username '@{$clean_username}' is already taken. Please pick another.";
        } else {
            // Update profile
            $upd = $conn->prepare("UPDATE users SET username = ?, phone = ? WHERE id = ?");
            $upd->bind_param("ssi", $clean_username, $clean_phone, $user_id);
            if ($upd->execute()) {
                $upd->close();
                $_SESSION['username'] = $clean_username;
                
                // Refresh persistent remember token
                set_user_remember_token($user_id);

                safe_redirect("index.php?welcome=1");
                exit;
            } else {
                $error = "Failed to update profile. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Complete Your Profile - Adept Play</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-2xl relative overflow-hidden">
        
        <!-- Top Glow -->
        <div class="absolute -top-16 -left-16 w-36 h-36 bg-violet-600/20 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Header -->
        <div class="text-center mb-6">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?= htmlspecialchars($user['avatar']); ?>" alt="Avatar" class="w-16 h-16 rounded-full mx-auto mb-3 border-2 border-violet-500 shadow-lg object-cover">
            <?php else: ?>
                <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-500 flex items-center justify-center text-white text-2xl mb-3 shadow-lg shadow-violet-500/30">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            <?php endif; ?>

            <h2 class="text-xl font-black text-white">Setup Your Profile</h2>
            <div class="mt-1 inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-[11px] text-emerald-400 font-medium">
                <i class="fa-brands fa-google text-xs"></i>
                <span><?= htmlspecialchars($user['email'] ?? 'Google Account'); ?></span>
            </div>
            <p class="text-xs text-slate-400 mt-2">
                Choose your unique gamer tag and mobile number to join tournaments and receive prizes.
            </p>
        </div>

        <!-- Alert -->
        <?php if (!empty($error)): ?>
            <div class="p-3 mb-4 rounded-xl text-xs font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30 flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-400 text-sm"></i>
                <span><?= htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Setup Form -->
        <form method="POST" action="complete_profile.php" class="space-y-4">
            <input type="hidden" name="action" value="save_profile">

            <!-- Gamer Username -->
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1 flex items-center justify-between">
                    <span>Gamer Username / Tag</span>
                    <span class="text-[10px] text-violet-400 font-normal">Displayed in match</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3.5 top-2.5 text-slate-500 text-xs font-bold">@</span>
                    <input type="text" name="username" required
                           value="<?= htmlspecialchars(str_starts_with($user['username'], 'player_') ? '' : $user['username']); ?>"
                           placeholder="e.g. shadow_ninja99"
                           minlength="3" maxlength="20"
                           class="w-full bg-slate-800 border border-slate-700 focus:border-violet-500 rounded-xl pl-8 pr-3 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none">
                </div>
                <p class="text-[10px] text-slate-500 mt-1">Letters, numbers, and underscores only.</p>
            </div>

            <!-- WhatsApp / Mobile Number -->
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1 flex items-center justify-between">
                    <span>Mobile / WhatsApp Number</span>
                    <span class="text-[10px] text-amber-400 font-normal">For Room ID & Password</span>
                </label>
                <div class="relative flex">
                    <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-slate-700 bg-slate-800/80 text-slate-400 text-xs font-bold font-mono">
                        +91
                    </span>
                    <input type="tel" name="phone" required
                           value="<?= htmlspecialchars($user['phone'] ?? ''); ?>"
                           placeholder="9876543210"
                           maxlength="10" pattern="[0-9]{10}"
                           class="w-full bg-slate-800 border border-slate-700 focus:border-violet-500 rounded-r-xl px-3 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none font-mono">
                </div>
                <p class="text-[10px] text-slate-500 mt-1">10-digit number used for match alerts.</p>
            </div>

            <button type="submit" class="w-full py-3 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-bold rounded-xl text-xs shadow-lg shadow-violet-600/30 transition active:scale-95 flex items-center justify-center gap-2">
                <span>Save & Enter Arena</span>
                <i class="fa-solid fa-arrow-right text-xs"></i>
            </button>
        </form>

        <div class="mt-4 text-center">
            <a href="logout.php" class="text-[11px] text-slate-500 hover:text-rose-400 transition">
                <i class="fa-solid fa-power-off mr-1"></i> Sign Out
            </a>
        </div>

    </div>

</body>
</html>
