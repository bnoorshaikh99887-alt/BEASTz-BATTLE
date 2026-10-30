<?php
require_once __DIR__ . '/common/header.php';

$alert_msg = "";
$alert_type = "";

// Pre-fetch user saved IGN & UID
$curr_uid = (int)($_SESSION['user_id'] ?? 0);
$user_ign = "";
$user_gid = "";
if ($curr_uid > 0) {
    $u_chk = $conn->prepare("SELECT game_name, game_id FROM users WHERE id = ?");
    if ($u_chk) {
        $u_chk->bind_param("i", $curr_uid);
        $u_chk->execute();
        $u_res = $u_chk->get_result()->fetch_assoc();
        $u_chk->close();
        $user_ign = $u_res['game_name'] ?? '';
        $user_gid = $u_res['game_id'] ?? '';
    }
}

// -------------------------------------------------------------
// TOURNAMENT JOIN / REGISTRATION HANDLER
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_tournament') {
    check_user_auth();
    $curr_uid = (int)$_SESSION['user_id'];
    $tid = (int)($_POST['tournament_id'] ?? 0);
    $in_game_name = sanitize($_POST['in_game_name'] ?? '');
    $game_id = sanitize($_POST['game_id'] ?? '');
    $slot_no = (int)($_POST['slot_no'] ?? 1);

    if ($tid <= 0 || empty($in_game_name)) {
        $alert_msg = "Please enter your in-game character name (IGN).";
        $alert_type = "error";
    } elseif (empty($game_id)) {
        $alert_msg = "Please enter your Game UID.";
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

        $max_slots = (int)($tourney['total_slots'] ?? 48);
        if ($max_slots <= 0) $max_slots = 48;

        if (!$tourney) {
            $alert_msg = "Tournament not found or expired.";
            $alert_type = "error";
        } elseif (strtolower($tourney['status']) === 'completed') {
            $alert_msg = "This tournament has already ended.";
            $alert_type = "error";
        } elseif ((int)$tourney['enrolled_count'] >= $max_slots) {
            $alert_msg = "Match is full! All slots have been booked.";
            $alert_type = "error";
        } elseif ($slot_no < 1 || $slot_no > $max_slots) {
            $alert_msg = "Please select a valid slot number between 1 and " . $max_slots . ".";
            $alert_type = "error";
        } else {
            // 1. Check if user already enrolled
            $chk_stmt = $conn->prepare("SELECT id, slot_no FROM participants WHERE tournament_id = ? AND user_id = ?");
            $chk_stmt->bind_param("ii", $tid, $curr_uid);
            $chk_stmt->execute();
            $already_joined = $chk_stmt->get_result()->fetch_assoc();
            $chk_stmt->close();

            if ($already_joined) {
                $alert_msg = "You have already joined this match (Occupying Slot #" . ($already_joined['slot_no'] ?: 1) . ")!";
                $alert_type = "warning";
            } else {
                // 2. Check if requested slot is already taken by someone else
                $slot_chk = $conn->prepare("SELECT id FROM participants WHERE tournament_id = ? AND slot_no = ?");
                $slot_chk->bind_param("ii", $tid, $slot_no);
                $slot_chk->execute();
                $is_slot_taken = ($slot_chk->get_result()->num_rows > 0);
                $slot_chk->close();

                if ($is_slot_taken) {
                    $alert_msg = "Slot #" . $slot_no . " was just booked by another player! Please choose an available slot.";
                    $alert_type = "error";
                } else {
                    // 3. Check wallet balance
                    $u_stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                    $u_stmt->bind_param("i", $curr_uid);
                    $u_stmt->execute();
                    $user_data = $u_stmt->get_result()->fetch_assoc();
                    $u_stmt->close();

                    $user_bal = (float)($user_data['wallet_balance'] ?? 0.00);
                    $fee = (float)$tourney['entry_fee'];

                    if ($user_bal < $fee) {
                        $needed = $fee - $user_bal;
                        $alert_msg = "Insufficient wallet balance! Please add " . format_inr($needed) . " to your wallet.";
                        $alert_type = "error";
                    } else {
                        $conn->begin_transaction();
                        try {
                            if ($fee > 0) {
                                $ded_stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
                                $ded_stmt->bind_param("di", $fee, $curr_uid);
                                $ded_stmt->execute();
                                $ded_stmt->close();

                                $tx_desc = "Entry Fee: " . $tourney['title'] . " (Match #" . $tid . ", Slot #" . $slot_no . ")";
                                $tx_stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
                                $tx_stmt->bind_param("ids", $curr_uid, $fee, $tx_desc);
                                $tx_stmt->execute();
                                $tx_stmt->close();
                            }

                            $tourney_game = $tourney['game_name'] ?? 'Free Fire';

                            // Insert into participants with all default-safe fields
                            $p_stmt = $conn->prepare("INSERT INTO participants (tournament_id, user_id, in_game_name, game_name, game_id, slot_no) VALUES (?, ?, ?, ?, ?, ?)");
                            $p_stmt->bind_param("iisssi", $tid, $curr_uid, $in_game_name, $tourney_game, $game_id, $slot_no);
                            $p_stmt->execute();
                            $p_stmt->close();

                            // Maintain compatibility with user_tournaments table
                            @$conn->query("INSERT INTO user_tournaments (user_id, tournament_id, game_name, slot_no) VALUES ({$curr_uid}, {$tid}, '" . $conn->real_escape_string($in_game_name) . "', {$slot_no})");

                            // Update user profile IGN and Game ID if empty
                            $upd_u = $conn->prepare("UPDATE users SET game_name = ?, game_id = ? WHERE id = ?");
                            if ($upd_u) {
                                $upd_u->bind_param("ssi", $in_game_name, $game_id, $curr_uid);
                                $upd_u->execute();
                                $upd_u->close();
                            }

                            $conn->commit();
                            $alert_msg = "🎉 Slot #" . $slot_no . " booked successfully for " . htmlspecialchars($tourney['title']) . "! Check Room ID in My Matches before match start.";
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
}

// -------------------------------------------------------------
// FETCH TOURNAMENTS & BOOKED SLOTS MAP
// -------------------------------------------------------------
$tournaments_res = $conn->query("
    SELECT t.id, t.title, t.game, t.map, t.match_type, t.entry_fee, t.per_kill, t.prize_pool, t.match_time, 
    t.total_slots, t.joined_slots, t.status, 
    COALESCE(t.game_name, t.game, 'Free Fire') as display_game,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
    FROM tournaments t
    WHERE t.status != 'completed'
    ORDER BY t.match_time ASC, t.id DESC
");

// Pre-fetch booked slots per tournament
$booked_slots_map = [];
$b_res = $conn->query("SELECT tournament_id, slot_no FROM participants");
if ($b_res) {
    while ($brow = $b_res->fetch_assoc()) {
        $tid_b = (int)$brow['tournament_id'];
        if (!isset($booked_slots_map[$tid_b])) $booked_slots_map[$tid_b] = [];
        $booked_slots_map[$tid_b][] = (int)$brow['slot_no'];
    }
}
?>

<!-- Alert Feedback -->
<?php if (!empty($alert_msg)): ?>
    <div class="mb-4 p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2 <?= $alert_type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : ($alert_type === 'warning' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-rose-50 text-rose-800 border border-rose-200'); ?>">
        <i class="fa-solid <?= $alert_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600'; ?> text-sm"></i>
        <span><?= htmlspecialchars($alert_msg); ?></span>
    </div>
<?php endif; ?>

<!-- Horizontal Category Filter Bar -->
<div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1 mb-4 border-b border-slate-200/80 -mx-1 px-1">
    <button type="button" onclick="filterTourneys('ALL', this)" class="category-tab px-3.5 py-1.5 rounded-full text-xs font-extrabold whitespace-nowrap transition bg-yellow-400 text-slate-950 shadow-xs border border-yellow-400">
        ALL
    </button>
    <button type="button" onclick="filterTourneys('SOLO BR', this)" class="category-tab px-3.5 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition bg-white text-slate-600 border border-slate-200 hover:bg-slate-50">
        SOLO BR
    </button>
    <button type="button" onclick="filterTourneys('DUO BR', this)" class="category-tab px-3.5 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition bg-white text-slate-600 border border-slate-200 hover:bg-slate-50">
        DUO BR
    </button>
    <button type="button" onclick="filterTourneys('SQUAD', this)" class="category-tab px-3.5 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition bg-white text-slate-600 border border-slate-200 hover:bg-slate-50">
        SQUAD
    </button>
    <button type="button" onclick="filterTourneys('CS 1VS1', this)" class="category-tab px-3.5 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition bg-white text-slate-600 border border-slate-200 hover:bg-slate-50">
        CS 1VS1
    </button>
    <button type="button" onclick="filterTourneys('CS 2VS2', this)" class="category-tab px-3.5 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition bg-white text-slate-600 border border-slate-200 hover:bg-slate-50">
        CS 2VS2
    </button>
</div>

<!-- Tournaments List -->
<div class="space-y-4 mb-20" id="tournamentsContainer">
    <?php if ($tournaments_res && $tournaments_res->num_rows > 0): ?>
        <?php while ($t = $tournaments_res->fetch_assoc()): ?>
            <?php
                $enrolled = (int)$t['enrolled_count'];
                $total_slots = (int)($t['total_slots'] ?: 48);
                $spots_left = max(0, $total_slots - $enrolled);
                $is_full = ($spots_left <= 0);

                // Category tags
                $cat_tag = strtoupper(trim(($t['match_type'] ?? '') . ' ' . ($t['title'] ?? '')));
                $map_name = strtoupper(!empty($t['map']) ? $t['map'] : 'BERMUDA');
                $mode_name = strtoupper(!empty($t['match_type']) ? $t['match_type'] : 'SOLO BR');

                // Booked slots JSON for modal
                $t_booked = $booked_slots_map[$t['id']] ?? [];
                $t_booked_json = htmlspecialchars(json_encode($t_booked));
            ?>
            <div class="tournament-card bg-white border border-slate-200 rounded-2xl p-4 shadow-sm hover:shadow transition" data-category="<?= htmlspecialchars($cat_tag); ?>">
                <!-- Header Tags & Thumbnail -->
                <div class="flex items-start justify-between gap-3 mb-2">
                    <div class="flex-1">
                        <div class="flex items-center gap-1.5 flex-wrap mb-1.5">
                            <span class="text-[9px] font-bold text-slate-600 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded uppercase">
                                <?= htmlspecialchars($mode_name); ?>
                            </span>
                            <span class="text-[9px] font-bold text-slate-600 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded uppercase">
                                <?= htmlspecialchars($map_name); ?>
                            </span>
                            <span class="text-[9px] font-bold text-yellow-800 bg-yellow-100 border border-yellow-300 px-2 py-0.5 rounded uppercase">
                                <?= $total_slots; ?> SLOTS
                            </span>
                        </div>
                        <h3 class="text-sm font-extrabold text-slate-900 leading-snug">
                            <?= htmlspecialchars($t['title']); ?>
                        </h3>
                    </div>

                    <!-- Match Thumbnail -->
                    <div class="relative w-20 h-14 rounded-xl overflow-hidden shrink-0 border border-slate-200 shadow-xs bg-slate-900">
                        <img src="https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=200&q=80" alt="Thumb" class="w-full h-full object-cover">
                        <span class="absolute top-1 right-1 bg-black/80 text-yellow-400 font-black text-[8px] px-1.5 py-0.2 rounded font-mono">
                            FF
                        </span>
                    </div>
                </div>

                <!-- Prize Pool & Per Kill Bar -->
                <div class="flex items-center justify-between text-xs mb-2">
                    <div>
                        <span class="text-[10px] text-slate-400 block font-semibold">Prize Pool</span>
                        <span class="text-sm font-extrabold text-amber-500 font-mono">
                            <?= format_inr($t['prize_pool']); ?>
                        </span>
                    </div>
                    <?php if ((float)$t['per_kill'] > 0): ?>
                        <div class="text-center">
                            <span class="text-[10px] text-slate-400 block font-semibold">Per Kill Reward</span>
                            <span class="text-xs font-extrabold text-emerald-600 font-mono">
                                <?= format_inr($t['per_kill']); ?> / Kill
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="text-right">
                        <span class="text-[10px] text-slate-400 block font-semibold">Match Time</span>
                        <span class="text-xs font-bold text-slate-700">
                            <?= date("d M, h:i A", strtotime($t['match_time'])); ?>
                        </span>
                    </div>
                </div>

                <!-- Progress Bar of Slots -->
                <div class="w-full bg-slate-100 rounded-full h-1.5 mb-3 overflow-hidden">
                    <?php $fill_pct = min(100, round(($enrolled / $total_slots) * 100)); ?>
                    <div class="bg-yellow-400 h-1.5 rounded-full transition-all duration-300" style="width: <?= $fill_pct; ?>%"></div>
                </div>

                <!-- Match Footer (Spots Left + Join Button) -->
                <div class="flex items-center justify-between pt-1 border-t border-slate-100">
                    <div>
                        <span class="text-[10px] text-slate-400 uppercase tracking-tight block">Match ID #<?= $t['id']; ?></span>
                        <span class="text-xs font-bold <?= $is_full ? 'text-rose-600' : 'text-slate-700'; ?>">
                            <?= $is_full ? 'Match Full' : $spots_left . ' spots left'; ?>
                        </span>
                    </div>

                    <?php if ($is_full): ?>
                        <button disabled class="px-5 py-2 bg-slate-200 text-slate-500 font-extrabold text-xs rounded-xl cursor-not-allowed">
                            FULL
                        </button>
                    <?php else: ?>
                        <button type="button" 
                                onclick="openJoinModal(<?= $t['id']; ?>, '<?= addslashes($t['title']); ?>', <?= (float)$t['entry_fee']; ?>, <?= (float)$t['prize_pool']; ?>, <?= $total_slots; ?>, '<?= $t_booked_json; ?>')" 
                                class="px-5 py-2 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black text-xs rounded-xl shadow-xs transition active:scale-95 flex items-center gap-1.5">
                            <span><?= (float)$t['entry_fee'] > 0 ? format_inr($t['entry_fee']) : 'FREE'; ?></span>
                            <span class="font-extrabold">JOIN</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="text-center py-12 bg-white rounded-2xl border border-slate-200 p-6">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-yellow-100 text-yellow-600 flex items-center justify-center text-xl mb-2">
                <i class="fa-solid fa-gamepad"></i>
            </div>
            <h4 class="text-sm font-bold text-slate-800">No tournaments available right now</h4>
            <p class="text-xs text-slate-500 mt-1">Please check back soon! New custom matches are scheduled daily.</p>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================= -->
<!-- MODAL: JOIN TOURNAMENT (IGN + Game UID + Slot Number Selector) -->
<!-- ============================================================= -->
<div id="joinModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative max-h-[90vh] overflow-y-auto">
        <button type="button" onclick="closeJoinModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-4">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-yellow-400 flex items-center justify-center text-slate-950 text-xl font-black mb-2 shadow-sm">
                <i class="fa-solid fa-trophy"></i>
            </div>
            <h3 class="text-base font-extrabold text-slate-900" id="modalTourneyTitle">Tournament Registration</h3>
            <p class="text-xs text-slate-500 mt-0.5">Select your custom room slot and confirm</p>
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3 mb-4 space-y-1.5 text-xs">
            <div class="flex justify-between text-slate-600">
                <span>Entry Fee:</span>
                <span class="font-extrabold text-slate-900" id="modalEntryFee">₹0.00</span>
            </div>
            <div class="flex justify-between text-slate-600">
                <span>Prize Pool:</span>
                <span class="font-extrabold text-amber-500" id="modalPrizePool">₹0.00</span>
            </div>
            <div class="flex justify-between text-slate-600 border-t border-slate-200 pt-1.5">
                <span>Your Balance:</span>
                <span class="font-extrabold font-mono text-emerald-600"><?= format_inr($user_wallet); ?></span>
            </div>
        </div>

        <form method="POST" action="index.php" class="space-y-3.5" autocomplete="off">
            <input type="hidden" name="action" value="join_tournament">
            <input type="hidden" name="tournament_id" id="modalTournamentId" value="0">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Free Fire / Game Character Name (IGN) <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="in_game_name" id="modalIgnInput" required
                       value="<?= htmlspecialchars($user_ign); ?>"
                       placeholder="e.g. ꧁☆BEAST☆꧂" 
                       class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none font-bold">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Game UID (Player ID) <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="game_id" id="modalGameIdInput" required
                       value="<?= htmlspecialchars($user_gid); ?>"
                       placeholder="e.g. 192837465" 
                       class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2 text-xs text-slate-900 font-mono placeholder-slate-400 focus:outline-none font-bold">
            </div>

            <!-- SLOT SELECTION (1 to Total Slots with Occupied Check) -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-bold text-slate-700">
                        Choose Slot Number <span class="text-rose-500">*</span>
                    </label>
                    <span class="text-[10px] text-slate-400 font-semibold" id="slotStatusHint">Pick available slot</span>
                </div>
                <select name="slot_no" id="modalSlotSelect" required class="w-full bg-slate-50 border border-slate-300 focus:border-yellow-400 focus:bg-white rounded-xl px-3 py-2.5 text-xs text-slate-900 font-extrabold focus:outline-none">
                    <!-- Populated dynamically in JavaScript -->
                </select>
                <p class="text-[10px] text-slate-400 mt-1">Sit in this exact slot in the Free Fire custom room lobby.</p>
            </div>

            <button type="submit" class="w-full py-3 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs shadow-xs transition active:scale-95 flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-gamepad"></i>
                <span>Confirm Slot & Join Match</span>
            </button>
        </form>
    </div>
</div>

<script>
    // Category Filter Handler
    function filterTourneys(category, btn) {
        document.querySelectorAll('.category-tab').forEach(el => {
            el.classList.remove('bg-yellow-400', 'text-slate-950', 'border-yellow-400');
            el.classList.add('bg-white', 'text-slate-600', 'border-slate-200');
        });
        btn.classList.remove('bg-white', 'text-slate-600', 'border-slate-200');
        btn.classList.add('bg-yellow-400', 'text-slate-950', 'border-yellow-400');

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

    // Join Modal Open/Close with Slot Generation
    function openJoinModal(id, title, fee, prize, totalSlots, bookedJsonStr) {
        <?php if (!isset($_SESSION['user_id'])): ?>
            window.location.href = 'login.php';
            return;
        <?php endif; ?>

        document.getElementById('modalTournamentId').value = id;
        document.getElementById('modalTourneyTitle').innerText = title;
        document.getElementById('modalEntryFee').innerText = fee > 0 ? '₹' + fee.toFixed(2) : 'FREE';
        document.getElementById('modalPrizePool').innerText = '₹' + prize.toFixed(2);

        // Parse booked slots
        let bookedSlots = [];
        try {
            bookedSlots = JSON.parse(bookedJsonStr) || [];
        } catch(e) {
            bookedSlots = [];
        }

        // Generate Slot Options (1 to totalSlots)
        const slotSelect = document.getElementById('modalSlotSelect');
        slotSelect.innerHTML = '';

        let firstAvailable = null;
        for (let i = 1; i <= totalSlots; i++) {
            const opt = document.createElement('option');
            opt.value = i;
            const isBooked = bookedSlots.includes(i);
            if (isBooked) {
                opt.text = 'Slot #' + i + ' — [ OCCUPIED / BOOKED ]';
                opt.disabled = true;
                opt.classList.add('text-slate-400');
            } else {
                opt.text = 'Slot #' + i + ' — Available';
                if (!firstAvailable) firstAvailable = i;
            }
            slotSelect.appendChild(opt);
        }

        if (firstAvailable) {
            slotSelect.value = firstAvailable;
        }

        document.getElementById('joinModal').classList.remove('hidden');
    }

    function closeJoinModal() {
        document.getElementById('joinModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
