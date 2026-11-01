<?php
// admin/views/users_view.php - "Login Users" Registered Gamers Directory
$user_msg = "";
$user_msg_type = "";

// Handle Suspend / Unblock Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_user_status') {
    $uid = (int)($_POST['target_user_id'] ?? 0);
    $new_st = ($_POST['new_status'] === 'blocked') ? 'blocked' : 'active';

    if ($uid > 0) {
        $chk_adm = $conn->prepare("SELECT role, username FROM users WHERE id = ? LIMIT 1");
        $chk_adm->bind_param("i", $uid);
        $chk_adm->execute();
        $target_user = $chk_adm->get_result()->fetch_assoc();
        $chk_adm->close();

        if ($target_user && ($target_user['role'] ?? '') === 'admin') {
            $user_msg = "Admin accounts cannot be suspended.";
            $user_msg_type = "error";
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_st, $uid);
            $stmt->execute();
            $stmt->close();
            $user_msg = "User @" . htmlspecialchars($target_user['username']) . " status updated to " . ucfirst($new_st) . "!";
            $user_msg_type = "success";
        }
    }
}

// Fetch all registered gamers
$all_gamers = $conn->query("
    SELECT id, username, name, email, phone, wallet_balance, winning_balance, game_name, game_id, upi_id, role, status, created_at, referral_code
    FROM users 
    WHERE role != 'admin' OR role IS NULL
    ORDER BY id DESC
");
?>

<div class="space-y-4">
    <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h2 class="text-base font-extrabold text-slate-900 dark:text-white font-display flex items-center gap-2">
                <i class="fa-solid fa-users text-yellow-500"></i>
                <span>Registered Gamers Directory</span>
            </h2>
            <p class="text-[11px] text-slate-500">View gamer names, mobile numbers, emails, and direct WhatsApp contact</p>
        </div>
        <span class="px-2.5 py-1 bg-yellow-400 text-slate-950 font-black rounded-full text-[10px] uppercase">
            Total: <?= ($all_gamers ? $all_gamers->num_rows : 0); ?>
        </span>
    </div>

    <?php if (!empty($user_msg)): ?>
        <div class="p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2.5 <?= ($user_msg_type === 'success') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/10 text-rose-400 border border-rose-500/30' ?>">
            <i class="fa-solid <?= ($user_msg_type === 'success') ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?> text-sm"></i>
            <span><?= htmlspecialchars($user_msg); ?></span>
        </div>
    <?php endif; ?>

    <!-- Search Input -->
    <div class="relative">
        <span class="absolute left-3.5 top-2.5 text-slate-400 text-xs"><i class="fa-solid fa-magnifying-glass"></i></span>
        <input type="text" id="gamerSearchInput" onkeyup="filterGamerList()" placeholder="Search gamers by username, phone or email..." class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-yellow-400 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-900 dark:text-white font-medium focus:outline-none">
    </div>

    <!-- Gamers Cards List -->
    <div class="space-y-2.5" id="gamersCardsContainer">
        <?php if ($all_gamers && $all_gamers->num_rows > 0): ?>
            <?php while ($u = $all_gamers->fetch_assoc()): ?>
                <?php 
                    $is_blocked = (($u['status'] ?? 'active') === 'blocked');
                    $clean_phone = preg_replace('/[^0-9]/', '', $u['phone'] ?? '');
                ?>
                <div class="gamer-card bg-white dark:bg-[#131b2e] border <?= $is_blocked ? 'border-rose-400/40 bg-rose-500/5' : 'border-slate-200 dark:border-slate-800' ?> rounded-2xl p-3.5 shadow-sm space-y-2.5 text-xs transition"
                     data-search="<?= strtolower(htmlspecialchars($u['username'] . ' ' . $u['phone'] . ' ' . $u['email'] . ' ' . $u['game_name'] . ' ' . $u['game_id'])); ?>">
                    
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-extrabold text-sm text-slate-900 dark:text-white">@<?= htmlspecialchars($u['username']); ?></span>
                                <?php if ($is_blocked): ?>
                                    <span class="px-2 py-0.2 rounded-full bg-rose-500/20 text-rose-400 font-bold text-[9px] uppercase">
                                        BLOCKED
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-0.2 rounded-full bg-emerald-500/20 text-emerald-400 font-bold text-[9px] uppercase">
                                        ACTIVE
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="text-[10px] text-slate-400 block mt-0.5">Joined: <?= date('d M Y', strtotime($u['created_at'])); ?> &bull; UID: #<?= $u['id']; ?></span>
                        </div>

                        <div class="text-right">
                            <span class="text-xs font-mono font-black text-slate-900 dark:text-white block"><?= format_inr($u['wallet_balance']); ?></span>
                            <span class="text-[9px] text-slate-400">Balance</span>
                        </div>
                    </div>

                    <!-- Contact Details Box (Phone + WhatsApp + Gmail) -->
                    <div class="grid grid-cols-2 gap-2 bg-slate-50 dark:bg-slate-900/80 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 text-[11px]">
                        <div>
                            <span class="text-[9px] text-slate-400 uppercase font-bold block mb-0.5">Mobile Phone</span>
                            <?php if (!empty($clean_phone)): ?>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200">+91 <?= htmlspecialchars($clean_phone); ?></span>
                                    <a href="https://wa.me/91<?= $clean_phone; ?>?text=<?= urlencode("Hello @" . $u['username'] . ", from BEASTz BATTLE Esports Support!"); ?>" target="_blank" class="px-1.5 py-0.5 rounded bg-emerald-500 hover:bg-emerald-600 text-white text-[9px] font-bold flex items-center gap-1">
                                        <i class="fa-brands fa-whatsapp text-[10px]"></i> Chat
                                    </a>
                                </div>
                            <?php else: ?>
                                <span class="text-slate-400">No phone</span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <span class="text-[9px] text-slate-400 uppercase font-bold block mb-0.5">Gmail / Email</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 truncate block select-all">
                                <?= !empty($u['email']) ? htmlspecialchars($u['email']) : 'None'; ?>
                            </span>
                        </div>

                        <div>
                            <span class="text-[9px] text-slate-400 uppercase font-bold block mb-0.5">Game IGN</span>
                            <span class="font-bold text-yellow-600 dark:text-yellow-400">
                                <?= !empty($u['game_name']) ? htmlspecialchars($u['game_name']) : 'Not set'; ?>
                            </span>
                        </div>

                        <div>
                            <span class="text-[9px] text-slate-400 uppercase font-bold block mb-0.5">Game UID</span>
                            <span class="font-mono font-bold text-slate-800 dark:text-slate-200 select-all">
                                <?= !empty($u['game_id']) ? htmlspecialchars($u['game_id']) : 'Not set'; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Action: Suspend / Unblock -->
                    <form method="POST" action="index.php?tab=users" onsubmit="return confirm('<?= $is_blocked ? 'Unblock' : 'Suspend / Block'; ?> gamer @<?= addslashes($u['username']); ?>?');">
                        <input type="hidden" name="action" value="toggle_user_status">
                        <input type="hidden" name="target_user_id" value="<?= $u['id']; ?>">
                        <input type="hidden" name="new_status" value="<?= $is_blocked ? 'active' : 'blocked'; ?>">
                        <button type="submit" class="w-full py-1.5 rounded-xl text-[11px] font-bold transition flex items-center justify-center gap-1.5 <?= $is_blocked ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 hover:bg-emerald-500/30' : 'bg-slate-100 dark:bg-slate-800 hover:bg-rose-500/20 text-rose-500 hover:text-rose-400 border border-slate-200 dark:border-slate-700'; ?>">
                            <i class="fa-solid <?= $is_blocked ? 'fa-unlock' : 'fa-ban'; ?>"></i>
                            <span><?= $is_blocked ? "Unblock Gamer Account" : "Suspend / Block Gamer"; ?></span>
                        </button>
                    </form>

                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="p-8 text-center text-slate-400 text-xs bg-white dark:bg-[#131b2e] rounded-2xl border border-slate-200 dark:border-slate-800">
                No registered gamers found in database.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterGamerList() {
    var q = document.getElementById('gamerSearchInput').value.toLowerCase().trim();
    var cards = document.querySelectorAll('.gamer-card');
    cards.forEach(function(card) {
        var data = card.getAttribute('data-search') || '';
        if (data.indexOf(q) !== -1) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>
