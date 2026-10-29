<?php
// complete_profile.php - Step 2: Set Username & Mobile Number after Google Sign In
require_once __DIR__ . '/common/config.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];
$error = '';

// Fetch current user info from Google
$stmt = $conn->prepare("SELECT name, username, email, phone, avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// If already has custom username and phone, redirect to home
if (!empty($user['phone']) && !empty($user['username']) && strpos($user['username'], 'user_') !== 0) {
    header("Location: /index.php");
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desired_username = strtolower(sanitize($_POST['username'] ?? ''));
    $phone = sanitize($_POST['phone'] ?? '');

    // Validate 10-digit phone
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);

    if (empty($desired_username) || empty($clean_phone)) {
        $error = "Kripya Username aur Mobile Number dono bharein!";
    } elseif (strlen($desired_username) < 3) {
        $error = "Username kam se kam 3 characters ka hona chahiye!";
    } elseif (strlen($clean_phone) !== 10) {
        $error = "Kripya 10-digit ka valid mobile number daalein!";
    } else {
        // Check if username is already taken by someone else
        $chk = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
        $chk->bind_param("si", $desired_username, $user_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = "Yeh Username pehle se kisi ne le rakha hai! Doosra chunein.";
        } else {
            // Update User Profile
            $upd = $conn->prepare("UPDATE users SET username = ?, phone = ? WHERE id = ?");
            $upd->bind_param("ssi", $desired_username, $clean_phone, $user_id);
            if ($upd->execute()) {
                $_SESSION['username'] = $desired_username;
                
                // Refresh 1-year remember token
                $token = bin2hex(random_bytes(32));
                $conn->query("UPDATE users SET remember_token = '$token' WHERE id = $user_id");
                setcookie('adept_remember', $token, time() + (365 * 24 * 60 * 60), '/', '', true, true);

                header("Location: /index.php");
                exit;
            } else {
                $error = "Update error: " . $conn->error;
            }
        }
    }
}

// Suggested username from email
$suggested_username = !empty($user['email']) ? explode('@', $user['email'])[0] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Setup Profile - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #0b0f19;
            color: #f3f4f6;
            font-family: ui-sans-serif, system-ui, sans-serif;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-[#131b2e] border border-gray-800 rounded-3xl p-6 shadow-2xl">
        
        <!-- Welcome Header -->
        <div class="text-center mb-6">
            <div class="w-14 h-14 rounded-full bg-gradient-to-tr from-amber-500 to-red-600 flex items-center justify-center text-white font-bold text-xl mx-auto mb-3 shadow-lg shadow-amber-500/20">
                <i class="fa-solid fa-gamepad"></i>
            </div>
            <h2 class="text-xl font-black text-white">Setup Gaming Profile</h2>
            <p class="text-xs text-gray-400 mt-1">
                Connected: <span class="text-amber-400 font-semibold"><?= htmlspecialchars($user['email']) ?></span>
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500/20 border border-red-500/50 text-red-400 text-xs p-3 rounded-xl mb-4 flex items-center">
                <i class="fa-solid fa-circle-exclamation mr-2 text-sm flex-shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="/complete_profile.php" class="space-y-4">
            <!-- Desired Username -->
            <div>
                <label class="block text-xs font-bold text-gray-300 mb-1">
                    Choose Username <span class="text-amber-400">*</span>
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-500 text-xs font-bold">@</span>
                    <input type="text" name="username" required value="<?= htmlspecialchars($suggested_username) ?>" placeholder="e.g. beast_gamer" class="w-full bg-gray-900 border border-gray-700 rounded-xl pl-8 pr-3 py-2.5 text-xs text-white focus:outline-none focus:border-amber-500 font-mono">
                </div>
                <p class="text-[10px] text-gray-500 mt-1">Yeh aapka in-game leaderboard name hoga</p>
            </div>

            <!-- WhatsApp Mobile Number -->
            <div>
                <label class="block text-xs font-bold text-gray-300 mb-1">
                    Mobile Number (WhatsApp) <span class="text-amber-400">*</span>
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-500 text-xs font-bold">+91</span>
                    <input type="tel" name="phone" required maxlength="10" placeholder="10-digit phone number" class="w-full bg-gray-900 border border-gray-700 rounded-xl pl-12 pr-3 py-2.5 text-xs text-white focus:outline-none focus:border-amber-500 font-mono">
                </div>
                <p class="text-[10px] text-gray-500 mt-1">Match Room ID aur Password iss number par aayega</p>
            </div>

            <button type="submit" class="w-full py-3 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-black font-black uppercase tracking-wider text-xs rounded-xl shadow-lg shadow-amber-500/20 transition active:scale-95 mt-2">
                Save Profile & Start Playing <i class="fa-solid fa-arrow-right ml-1"></i>
            </button>
        </form>

        <div class="mt-4 text-center">
            <span class="text-[11px] text-gray-500">🔒 Login 1 saal tak save rahega jab tak aap logout nahi karte</span>
        </div>
    </div>
</body>
</html>
