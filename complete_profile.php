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

    $clean_username = preg_replace('/[^a-zA-Z0-9_]/', '', $desired_username);
    $clean_phone = preg_replace('/[^0-9]/', '', $phone_input);

    if (empty($clean_username) || strlen($clean_username) < 3 || strlen($clean_username) > 20) {
        $error = "Username must be between 3 and 20 characters (letters, numbers, underscore only).";
    } elseif (strlen($clean_phone) !== 10) {
        $error = "Please enter a valid 10-digit mobile / WhatsApp number.";
    } else {
        try {
            $chk = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
            $chk->bind_param("si", $clean_username, $user_id);
            $chk->execute();
            $taken = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($taken) {
                $error = "Username '@{$clean_username}' is already taken. Please pick another.";
            } else {
                $upd = $conn->prepare("UPDATE users SET username = ?, phone = ? WHERE id = ?");
                $upd->bind_param("ssi", $clean_username, $clean_phone, $user_id);
                if ($upd->execute()) {
                    $upd->close();
                    $_SESSION['username'] = $clean_username;
                    set_user_remember_token($user_id);
                    safe_redirect("index.php?welcome=1");
                    exit;
                } else {
                    $error = "Failed to update profile. Please try again.";
                }
            }
        } catch (Exception $e) {
            $error = "Update Error: " . $e->getMessage();
        }
    }
}

$curr_uname = (string)($user['username'] ?? '');
$display_uname = str_starts_with($curr_uname, 'player_') ? '' : $curr_uname;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Immediate Dark Mode Detection -->
    <script src="/theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Complete Your Profile - BEASTzBATTLE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: "class" };</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/theme.css">
    <style>
        * { font-family: 'Plus Jakarta Sans', sans-serif; }
        h1, h2, h3 { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-6 shadow-xl relative overflow-hidden">
        
        <div class="text-center mb-6">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-yellow-400 text-slate-950 flex items-center justify-center text-2xl mb-3 shadow-xs font-black">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <h2 class="text-xl font-black text-slate-900 tracking-tight">Setup Your Profile</h2>
            <div class="mt-1 inline-flex items-center gap-1.5 px-3 py-1 bg-yellow-50 border border-yellow-200 rounded-full text-[11px] text-yellow-800 font-bold">
                <i class="fa-brands fa-google text-xs"></i>
                <span><?= htmlspecialchars($user['email'] ?? 'Google Account'); ?></span>
            </div>
            <p class="text-xs text-slate-500 mt-2">
                Choose your unique gamer tag and mobile number to join tournaments and receive prizes.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="p-3 mb-4 rounded-xl text-xs font-semibold bg-rose-50 text-rose-800 border border-rose-200 flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-500 text-sm"></i>
                <span><?= htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="complete_profile.php" class="space-y-4">
            <input type="hidden" name="action" value="save_profile">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1 flex items-center justify-between">
                    <span>Gamer Username / Tag</span>
                    <span class="text-[10px] text-yellow-600 font-bold">Displayed in match</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3.5 top-2.5 text-slate-400 text-xs font-bold">@</span>
                    <input type="text" name="username" required
                           value="<?= htmlspecialchars($display_uname); ?>"
                           placeholder="e.g. shadow_ninja99" 
                           minlength="3" maxlength="20"
                           class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl pl-8 pr-3 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none font-bold">
                </div>
                <p class="text-[10px] text-slate-400 mt-1">Letters, numbers, and underscores only.</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1 flex items-center justify-between">
                    <span>Mobile / WhatsApp Number</span>
                    <span class="text-[10px] text-emerald-600 font-bold">For Room ID & Password</span>
                </label>
                <div class="relative flex">
                    <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-slate-300 bg-slate-100 text-slate-600 text-xs font-bold font-mono">
                        +91
                    </span>
                    <input type="tel" name="phone" required
                           value="<?= htmlspecialchars($user['phone'] ?? ''); ?>"
                           placeholder="9876543210" 
                           maxlength="10" pattern="[0-9]{10}"
                           class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-r-xl px-3 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none font-mono font-bold">
                </div>
                <p class="text-[10px] text-slate-400 mt-1">10-digit number used for match alerts.</p>
            </div>

            <button type="submit" class="w-full py-3 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs shadow-md shadow-yellow-400/20 transition active:scale-95 flex items-center justify-center gap-2">
                <span>Save & Enter Arena</span>
                <i class="fa-solid fa-arrow-right text-xs"></i>
            </button>
        </form>

        <div class="mt-4 text-center">
            <a href="logout.php" class="text-[11px] text-slate-400 hover:text-rose-500 transition">
                <i class="fa-solid fa-power-off mr-1"></i> Sign Out
            </a>
        </div>
    </div>
</body>
</html>
