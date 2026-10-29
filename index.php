<?php
require_once __DIR__ . '/common/header.php';

$alert_msg = "";
$alert_type = "";

if (isset($_GET['welcome'])) {
    $alert_msg = "Welcome to BEASTzBATTLE! Your gamer profile is ready.";
    $alert_type = "success";
}

// -------------------------------------------------------------
// Join Tournament Action
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_tournament') {
    if (!isset($_SESSION['user_id'])) {
        safe_redirect("login.php");
        exit;
    }

    $curr_uid = (int)$_SESSION['user_id'];
    $tid = (int)($_POST['tournament_id'] ?? 0);
    $in_game_name = sanitize($_POST['in_game_name'] ?? '');

    if ($tid <= 0 || empty($in_game_name)) {
        $alert_msg = "Please enter your character IGN / Game Name.";
        $alert_type = "error";
    } else {
        $t_stmt = $conn->prepare("
            SELECT t.id, t.title, COALESCE(t.game_name, t.game, 'Free Fire') as game_name, t.entry_fee, t.prize_pool, t.status, t.total_slots,
            (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
            FROM tournaments t 
            WHERE t.id = ?
        ");
        $t_stmt->bind_param("i", $tid);
        $t_stmt->execute();
        $tourney = $t_stmt->get_result()->fetch_assoc();
        $t_stmt->close();

        if (!$tourney) {
            $alert_msg = "Tournament not found or expired.";
            $alert_type = "error";
        } elseif (strtolower($tourney['status']) === 'completed') {
            $alert_msg = "This tournament has already ended.";
            $alert_type = "error";
        } elseif ((int)$tourney['enrolled_count'] >= (int)($tourney['total_slots'] ?? 100)) {
            $alert_msg = "Match is full! All slots booked.";
            $alert_type = "error";
        } else {
            // Check if already enrolled
            $chk_stmt = $conn->prepare("SELECT id FROM participants WHERE tournament_id = ? AND user_id = ?");
            $chk_stmt->bind_param("ii", $tid, $curr_uid);
            $chk_stmt->execute();
            if ($chk_stmt->get_result()->num_rows > 0) {
                $alert_msg = "You are already joined in this tournament!";
                $alert_type = "warning";
                $chk_stmt->close();
            } else {
                $chk_stmt->close();

                // Check wallet balance
                $u_stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                $u_stmt->bind_param("i", $curr_uid);
                $u_stmt->execute();
                $user_data = $u_stmt->get_result()->fetch_assoc();
                $u_stmt->close();

                $user_bal = (float)($user_data['wallet_balance'] ?? 0.00);
                $fee = (float)$tourney['entry_fee'];

                if ($user_bal < $fee) {
                    $needed = $fee - $user_bal;
                    $alert_msg = "Insufficient balance! Please add " . format_inr($needed) . " to your wallet.";
                    $alert_type = "error";
                } else {
                    $conn->begin_transaction();
                    try {
                        if ($fee > 0) {
                            $ded_stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
                            $ded_stmt->bind_param("di", $fee, $curr_uid);
                            $ded_stmt->execute();
                            $ded_stmt->close();

                            $tx_desc = "Entry Fee: " . $tourney['title'] . " (Match #" . $tid . ")";
                            $tx_stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
                            $tx_stmt->bind_param("ids", $curr_uid, $fee, $tx_desc);
                            $tx_stmt->execute();
                            $tx_stmt->close();
                        }

                        $p_stmt = $conn->prepare("INSERT INTO participants (tournament_id, user_id, in_game_name) VALUES (?, ?, ?)");
                        $p_stmt->bind_param("iis", $tid, $curr_uid, $in_game_name);
                        $p_stmt->execute();
                        $p_stmt->close();

                        // Save in_game_name to user profile if empty
                        @$conn->query("UPDATE users SET game_name = '" . $conn->real_escape_string($in_game_name) . "' WHERE id = {$curr_uid} AND (game_name IS NULL OR game_name = '')");

                        $conn->commit();
                        $alert_msg = "🎉 Slot booked successfully for " . htmlspecialchars($tourney['title']) . "! Check Room ID in My Matches before match start.";
                        $alert_type = "success";
                        $user_wallet -= $fee;
                    } catch (Exception $e) {
                        $conn->rollback();
                        $alert_msg = "Registration Error: " . $e->getMessage();
                        $alert_type = "error";
                    }
                }
            }
        }
    }
}

// -------------------------------------------------------------
// Fetch Available Tournaments for Players
// -------------------------------------------------------------
$tourneys_query = "
    SELECT t.*, 
    COALESCE(t.game_name, t.game, 'Free Fire') as display_game,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
    FROM tournaments t 
    WHERE LOWER(t.status) IN ('upcoming', 'live', 'ongoing')
    ORDER BY CASE WHEN LOWER(t.status) IN ('live','ongoing') THEN 1 ELSE 2 END, t.match_time ASC";
$active_matches = $conn->query($tourneys_query);

// Fetch user's registered tournaments for quick status
$joined_ids = [];
if (isset($_SESSION['user_id'])) {
    $my_uid = (int)$_SESSION['user_id'];
    $j_res = $conn->query("SELECT tournament_id FROM participants WHERE user_id = {$my_uid}");
    if ($j_res) {
        while ($j_row = $j_res->fetch_assoc()) {
            $joined_ids[] = (int)$j_row['tournament_id'];
        }
    }
}

// Default thumbnail bank for clean esports card rendering
$game_thumbs = [
    'https://images.unsplash.com/photo-1542751371-adc38448a05e?w=400&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?w=400&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1511512578047-dfb367046420?w=400&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=400&auto=format&fit=crop&q=80'
];
?>

<!-- Alert Feedback -->
<?php if (!empty($alert_msg)): ?>
    <div class="mb-3.5 p-3 rounded-xl text-xs font-semibold flex items-center gap-2 <?= ($alert_type === 'success') ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : (($alert_type === 'warning') ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-rose-50 text-rose-800 border border-rose-200') ?>">
        <i class="fa-solid <?= ($alert_type === 'success') ? 'fa-circle-check text-emerald-500' : 'fa-circle-exclamation text-amber-500' ?>"></i>
        <span><?= htmlspecialchars($alert_msg); ?></span>
    </div>
<?php endif; ?>

<!-- Horizontal Category Bar (Exact Match to Video: SOLO BR, DUO BR, etc.) -->
<div class="mb-3.5 -mx-3.5 px-3.5 overflow-x-auto no-scrollbar flex items-center gap-2 pb-1 border-b border-slate-200 bg-white pt-1">
    <button type="button" onclick="filterTourneys('ALL', this)" class="category-tab active-tab px-3 py-1.5 text-xs font-bold text-slate-900 border-b-2 border-yellow-400 whitespace-nowrap transition">
        ALL
    </button>
    <button type="button" onclick="filterTourneys('SOLO BR', this)" class="category-tab px-3 py-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 border-b-2 border-transparent whitespace-nowrap transition">
        SOLO BR
    </button>
    <button type="button" onclick="filterTourneys('DUO BR', this)" class="category-tab px-3 py-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 border-b-2 border-transparent whitespace-nowrap transition">
        DUO BR
    </button>
    <button type="button" onclick="filterTourneys('DUO PR KILL', this)" class="category-tab px-3 py-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 border-b-2 border-transparent whitespace-nowrap transition">
        DUO PR KILL
    </button>
    <button type="button" onclick="filterTourneys('SOLO PER KILL', this)" class="category-tab px-3 py-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 border-b-2 border-transparent whitespace-nowrap transition">
        SOLO PER KILL
    </button>
    <button type="button" onclick="filterTourneys('LONE WOLF', this)" class="category-tab px-3 py-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 border-b-2 border-transparent whitespace-nowrap transition">
        LONE WOLF
    </button>
    <button type="button" onclick="filterTourneys('CS CHALLENGERS', this)" class="category-tab px-3 py-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 border-b-2 border-transparent whitespace-nowrap transition">
        CS CHALLENGERS
    </button>
    <button type="button" onclick="filterTourneys('CLASH SQUAD', this)" class="category-tab px-3 py-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 border-b-2 border-transparent whitespace-nowrap transition">
        CLASH SQUAD
    </button>
    <button type="button" onclick="filterTourneys('CS HEADSHOT', this)" class="category-tab px-3 py-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 border-b-2 border-transparent whitespace-nowrap transition">
        CS HEADSHOT
    </button>
    <button type="button" onclick="filterTourneys('LOSS TO WIN', this)" class="category-tab px-3 py-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 border-b-2 border-transparent whitespace-nowrap transition">
        LOSS TO WIN
    </button>
</div>

<!-- Tournaments List (Matching Video Card Layout) -->
<div class="space-y-3" id="tournamentsContainer">
    <?php if ($active_matches && $active_matches->num_rows > 0): ?>
        <?php $idx = 0; ?>
        <?php while ($t = $active_matches->fetch_assoc()): ?>
            <?php
                $idx++;
                $total_slots = (int)($t['total_slots'] ?: 48);
                $enrolled = (int)$t['enrolled_count'];
                $spots_left = max(0, $total_slots - $enrolled);
                $is_full = ($spots_left === 0);
                $is_joined = in_array((int)$t['id'], $joined_ids);
                $fee = (float)$t['entry_fee'];
                $match_type_raw = strtoupper($t['match_type'] ?? 'SOLO BR');
                $map_name = strtoupper($t['map'] ?? 'BERMUDA');
                $match_time_str = date('d M h:i A', strtotime($t['match_time']));
                $thumb_url = $game_thumbs[$idx % count($game_thumbs)];
            ?>

            <!-- Tournament Card (White Card with Gray Border and Yellow Accents) -->
            <div class="tournament-card bg-white border border-slate-200 rounded-2xl p-3.5 shadow-sm hover:shadow-md transition"
                 data-category="<?= htmlspecialchars($match_type_raw); ?>">

                <div class="flex items-start justify-between gap-3">
                    <!-- Left: Details, Badges, Title, Prize & Action -->
                    <div class="flex-1 min-w-0">
                        <!-- Top Badges Row -->
                        <div class="flex items-center gap-1.5 mb-1.5 flex-wrap">
                            <span class="text-[10px] font-bold text-slate-700 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-md uppercase">
                                <?= htmlspecialchars($t['match_type'] ?: 'SOLO'); ?>
                            </span>
                            <span class="text-[10px] font-bold text-slate-700 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-md uppercase">
                                <?= htmlspecialchars($map_name); ?>
                            </span>
                            <span class="text-[10px] font-bold text-slate-700 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-md uppercase">
                                <?= $total_slots; ?> SLOTS
                            </span>
                        </div>

                        <!-- Match Title -->
                        <h3 class="text-sm font-extrabold text-slate-900 tracking-tight leading-snug mb-1 truncate">
                            <?= htmlspecialchars($t['title']); ?>
                        </h3>

                        <!-- Prize Pool (Yellow/Amber Highlight) -->
                        <div class="text-xs font-black text-amber-500 mb-2">
                            Prize Pool - <?= format_inr($t['prize_pool']); ?>
                        </div>

                        <!-- Progress Bar (Gray Track + Filled Yellow Bar) -->
                        <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden mb-2 border border-slate-200/60">
                            <?php $pct = min(100, round(($enrolled / $total_slots) * 100)); ?>
                            <div class="bg-yellow-400 h-1.5 rounded-full" style="width: <?= $pct; ?>%;"></div>
                        </div>

                        <!-- Bottom Meta: Match ID, Spots Left, and JOIN Button -->
                        <div class="flex items-center justify-between gap-2 pt-0.5">
                            <span class="text-[10px] font-mono text-slate-400">
                                MATCH ID
                            </span>

                            <span class="text-[11px] font-bold text-yellow-600">
                                <?= $spots_left; ?> spots left
                            </span>

                            <?php if ($is_joined): ?>
                                <a href="my_tournaments.php" class="px-3 py-1.5 rounded-lg bg-emerald-500 text-white font-extrabold text-xs shadow-xs flex items-center gap-1">
                                    <i class="fa-solid fa-check text-[10px]"></i> Joined
                                </a>
                            <?php elseif ($is_full): ?>
                                <button disabled class="px-3 py-1.5 rounded-lg bg-slate-200 text-slate-400 font-bold text-xs cursor-not-allowed">
                                    FULL
                                </button>
                            <?php else: ?>
                                <button type="button" 
                                        onclick="openJoinModal(<?= $t['id']; ?>, '<?= htmlspecialchars(addslashes($t['title'])); ?>', <?= $fee; ?>, <?= $t['prize_pool']; ?>)" 
                                        class="px-3.5 py-1.5 rounded-lg bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black text-xs shadow-xs hover:shadow transition active:scale-95 flex items-center gap-1">
                                    <span><?= $fee > 0 ? format_inr($fee) : 'FREE'; ?> JOIN</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right: Thumbnail and Time Schedule (Matching Video) -->
                    <div class="w-24 shrink-0 flex flex-col items-center">
                        <div class="w-24 h-20 rounded-xl overflow-hidden border border-slate-200 bg-slate-100 shadow-xs mb-1.5 relative">
                            <img src="<?= $thumb_url; ?>" alt="Match" class="w-full h-full object-cover">
                            <span class="absolute top-1 right-1 px-1.5 py-0.5 rounded bg-black/60 text-white text-[8px] font-bold">FF</span>
                        </div>
                        <span class="text-[10px] font-bold text-slate-700 text-center leading-tight">
                            <?= $match_time_str; ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="text-center py-12 bg-white rounded-2xl border border-slate-200 p-6">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-yellow-100 text-yellow-600 flex items-center justify-center text-xl mb-2">
                <i class="fa-solid fa-gamepad"></i>
            </div>
            <h4 class="text-sm font-bold text-slate-800">No matches available right now</h4>
            <p class="text-xs text-slate-500 mt-1">New Free Fire tournament matches are being prepared. Check back shortly!</p>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Join Tournament Confirmation -->
<div id="joinModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative animate-in fade-in duration-200">
        <button type="button" onclick="closeJoinModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-4">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-yellow-400 flex items-center justify-center text-slate-950 text-xl font-black mb-2 shadow-sm">
                <i class="fa-solid fa-trophy"></i>
            </div>
            <h3 class="text-base font-extrabold text-slate-900" id="modalTourneyTitle">Tournament Registration</h3>
            <p class="text-xs text-slate-500 mt-0.5">Confirm your slot in the lobby</p>
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3 mb-4 space-y-2 text-xs">
            <div class="flex justify-between text-slate-600">
                <span>Entry Fee:</span>
                <span class="font-extrabold text-slate-900" id="modalEntryFee">₹0.00</span>
            </div>
            <div class="flex justify-between text-slate-600">
                <span>Prize Pool:</span>
                <span class="font-extrabold text-amber-500" id="modalPrizePool">₹0.00</span>
            </div>
            <div class="flex justify-between text-slate-600 border-t border-slate-200 pt-2">
                <span>Your Wallet Balance:</span>
                <span class="font-extrabold font-mono text-emerald-600"><?= format_inr($user_wallet); ?></span>
            </div>
        </div>

        <form method="POST" action="index.php" class="space-y-3.5">
            <input type="hidden" name="action" value="join_tournament">
            <input type="hidden" name="tournament_id" id="modalTournamentId" value="0">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Free Fire In-Game Character Name (IGN) <span class="text-yellow-600">*</span>
                </label>
                <input type="text" name="in_game_name" id="modalIgnInput" required
                       placeholder="e.g. ꧁☆BEAST☆꧂" 
                       class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none font-bold">
                <p class="text-[10px] text-slate-400 mt-1">Must match your game character name for result verification.</p>
            </div>

            <button type="submit" class="w-full py-3 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs shadow-md transition active:scale-95 flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-gamepad"></i>
                <span>Confirm & Join Match</span>
            </button>
        </form>
    </div>
</div>

<script>
    // Category Filter Handler
    function filterTourneys(category, btn) {
        document.querySelectorAll('.category-tab').forEach(el => {
            el.classList.remove('border-yellow-400', 'text-slate-900', 'font-bold');
            el.classList.add('border-transparent', 'text-slate-500');
        });
        btn.classList.remove('border-transparent', 'text-slate-500');
        btn.classList.add('border-yellow-400', 'text-slate-900', 'font-bold');

        const cards = document.querySelectorAll('.tournament-card');
        category = category.trim().toUpperCase();

        cards.forEach(card => {
            const cardCat = (card.getAttribute('data-category') || '').trim().toUpperCase();
            if (category === 'ALL' || cardCat.includes(category) || category.includes(cardCat)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Join Modal Open/Close
    function openJoinModal(id, title, fee, prize) {
        <?php if (!isset($_SESSION['user_id'])): ?>
            window.location.href = 'login.php';
            return;
        <?php endif; ?>

        document.getElementById('modalTournamentId').value = id;
        document.getElementById('modalTourneyTitle').innerText = title;
        document.getElementById('modalEntryFee').innerText = fee > 0 ? '₹' + fee.toFixed(2) : 'FREE';
        document.getElementById('modalPrizePool').innerText = '₹' + prize.toFixed(2);
        
        // Auto-fill IGN if user has configured one
        const modalIgn = document.getElementById('modalIgnInput');
        if (modalIgn && !modalIgn.value) {
            modalIgn.value = '<?= htmlspecialchars(addslashes($_SESSION['username'] ?? '')); ?>';
        }

        document.getElementById('joinModal').classList.remove('hidden');
    }

    function closeJoinModal() {
        document.getElementById('joinModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
