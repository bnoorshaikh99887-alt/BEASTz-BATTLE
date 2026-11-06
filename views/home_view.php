<!-- Alert Feedback -->
<?php if (!empty($alert_msg)): ?>
    <div class="mb-4 p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2 <?= $alert_type === "success" ? "bg-emerald-50 text-emerald-800 border border-emerald-200" : ($alert_type === "warning" ? "bg-amber-50 text-amber-800 border border-amber-200" : "bg-rose-50 text-rose-800 border border-rose-200"); ?>">
        <i class="fa-solid <?= $alert_type === "success" ? "fa-circle-check text-emerald-600" : "fa-circle-exclamation text-rose-600"; ?> text-sm"></i>
        <span><?= htmlspecialchars($alert_msg); ?></span>
    </div>
<?php endif; ?>

<!-- ============================================================= -->
<!-- 1. DUAL BALANCE CARDS & QUICK ACTIONS (REFERENCE IMAGE STYLE) -->
<!-- ============================================================= -->
<div class="mb-5">
    <div class="grid grid-cols-2 gap-3 mb-3">
        <!-- Current Balance Card -->
        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-3.5 shadow-2xs">
            <div class="flex items-center gap-1.5 mb-1.5">
                <div class="w-6 h-6 rounded-lg bg-blue-50 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-wallet text-[11px]"></i>
                </div>
                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Current Balance</span>
            </div>
            <div class="text-xl font-black text-slate-900 dark:text-white tabular-nums tracking-tight font-display">
                <?= format_inr($current_balance ?? ($user_wallet ?? 0.00)); ?>
            </div>
        </div>

        <!-- Bonus Balance Card -->
        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-3.5 shadow-2xs">
            <div class="flex items-center gap-1.5 mb-1.5">
                <div class="w-6 h-6 rounded-lg bg-amber-50 dark:bg-amber-950/70 text-amber-500 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-gift text-[11px]"></i>
                </div>
                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Bonus Balance</span>
            </div>
            <div class="text-xl font-black text-slate-900 dark:text-white tabular-nums tracking-tight font-display">
                <?= format_inr(50.00); ?>
            </div>
        </div>
    </div>

    <!-- Deposit & Withdraw Actions (Royal Blue Accent) -->
    <div class="grid grid-cols-2 gap-3">
        <button type="button" onclick="openDepositModal()" class="flex items-center justify-center gap-2 py-3 px-4 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-sm transition-all active:scale-97 cursor-pointer">
            <div class="w-5 h-5 rounded-md bg-white/20 flex items-center justify-center">
                <i class="fa-solid fa-arrow-down text-white text-[10px]"></i>
            </div>
            <span>Deposit</span>
        </button>

        <button type="button" onclick="openWithdrawModal()" class="flex items-center justify-center gap-2 py-3 px-4 rounded-xl bg-white dark:bg-[#111827] hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-800 dark:text-slate-200 font-bold text-xs border border-slate-200 dark:border-slate-800 shadow-2xs transition-all active:scale-97 cursor-pointer">
            <div class="w-5 h-5 rounded-md bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <i class="fa-solid fa-arrow-up text-slate-700 dark:text-slate-300 text-[10px]"></i>
            </div>
            <span>Withdraw</span>
        </button>
    </div>
</div>

<!-- ============================================================= -->
<!-- 2. TOP EVENTS HEADER & CAROUSEL CATEGORY FILTERS              -->
<!-- ============================================================= -->
<div class="mb-4">
    <div class="flex items-center justify-between mb-2.5">
        <h2 class="text-sm font-black text-slate-900 dark:text-white tracking-tight font-display">
            Top Events
        </h2>
        <div class="flex items-center gap-2">
            <button type="button" onclick="openPrivateMatchModal()" class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1 cursor-pointer">
                <i class="fa-solid fa-key text-[10px]"></i>
                <span>Private Code</span>
            </button>
            <span class="text-slate-300 dark:text-slate-600">·</span>
            <button type="button" onclick="filterTourneys('ALL', this)" class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-0.5 cursor-pointer">
                <span>See All</span>
                <span>&gt;</span>
            </button>
        </div>
    </div>

    <!-- Category Filters -->
    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1 -mx-1 px-1">
        <button type="button" onclick="filterTourneys('ALL', this)" class="category-tab flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer bg-blue-600 text-white shadow-xs">
            <i class="fa-solid fa-gamepad"></i>
            <span>All Modes</span>
        </button>
        <button type="button" onclick="filterTourneys('SOLO BR', this)" class="category-tab flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer bg-white dark:bg-[#111827] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:bg-slate-50">
            <i class="fa-solid fa-user"></i>
            <span>Solo BR</span>
        </button>
        <button type="button" onclick="filterTourneys('DUO BR', this)" class="category-tab flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer bg-white dark:bg-[#111827] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:bg-slate-50">
            <i class="fa-solid fa-user-group"></i>
            <span>Duo BR</span>
        </button>
        <button type="button" onclick="filterTourneys('SQUAD BR', this)" class="category-tab flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer bg-white dark:bg-[#111827] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:bg-slate-50">
            <i class="fa-solid fa-users"></i>
            <span>Squad BR</span>
        </button>
        <button type="button" onclick="filterTourneys('CS 1VS1', this)" class="category-tab flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer bg-white dark:bg-[#111827] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:bg-slate-50">
            <i class="fa-solid fa-crosshairs"></i>
            <span>CS 1v1</span>
        </button>
        <button type="button" onclick="filterTourneys('LONE WOLF 1V1', this)" class="category-tab flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer bg-white dark:bg-[#111827] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:bg-slate-50">
            <i class="fa-solid fa-fire"></i>
            <span>Lone Wolf</span>
        </button>
    </div>
</div>

<!-- ============================================================= -->
<!-- 3. TOURNAMENTS LIST                                           -->
<!-- ============================================================= -->
<div class="space-y-4 mb-20" id="tournamentsContainer">
<?php
$has_db_tourneys = false;
if (isset($tournaments_res) && $tournaments_res && $tournaments_res->num_rows > 0) {
    $has_db_tourneys = true;
    while ($t = $tournaments_res->fetch_assoc()):
        $enrolled = (int)($t['enrolled_count'] ?? 0);
        $cat_tag = strtoupper(trim(($t['match_type'] ?? '') . ' ' . ($t['title'] ?? '')));
        $map_name = strtoupper(!empty($t['map']) ? $t['map'] : 'BERMUDA');
        $mode_name = strtoupper(!empty($t['match_type']) ? $t['match_type'] : 'SOLO BR');
        $is_squad = (stripos($mode_name, 'SQUAD') !== false || stripos($t['title'] ?? '', 'SQUAD') !== false);
        $is_duo = (!$is_squad && (stripos($mode_name, 'DUO') !== false || stripos($mode_name, '2VS2') !== false));
        if ($is_squad) {
            $total_slots = 12;
        } elseif ($is_duo) {
            $total_slots = 24;
        } else {
            $total_slots = (int)($t['total_slots'] ?: 48);
            if ($total_slots <= 0) $total_slots = 48;
        }
        $spots_left = max(0, $total_slots - $enrolled);
        $is_full = ($spots_left <= 0);
        $fill_pct = min(100, round(($enrolled / $total_slots) * 100));
        $t_booked = $booked_slots_map[$t['id']] ?? [];
        $t_booked_json = htmlspecialchars(json_encode($t_booked));
?>
    <div class="tournament-card bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-2xs hover:shadow-xs transition" data-category="<?= htmlspecialchars($cat_tag); ?>">
        <div class="flex items-center justify-between gap-2 mb-2.5">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400">
                    Free Fire MAX
                </span>
                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
                    <?= htmlspecialchars($map_name); ?> · <?= htmlspecialchars($mode_name); ?>
                </span>
            </div>
            <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800">
                <?= $is_full ? "FULL" : "OPEN"; ?>
            </span>
        </div>

        <div class="flex items-start justify-between gap-3 mb-3">
            <div class="flex-1">
                <h3 class="text-sm font-black text-slate-900 dark:text-white leading-snug tracking-tight font-display">
                    <?= htmlspecialchars($t['title']); ?>
                </h3>
                <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                    <span><i class="fa-regular fa-clock text-slate-400 mr-1"></i><?= date("d M, h:i A", strtotime($t['match_time'])); ?></span>
                    <span>·</span>
                    <span>Match #<?= $t['id']; ?></span>
                </div>
            </div>
            <div class="relative w-16 h-12 rounded-xl overflow-hidden shrink-0 border border-slate-200 dark:border-slate-800 bg-slate-900 shadow-2xs">
                <img src="https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=200&q=80" alt="Thumb" class="w-full h-full object-cover">
                <span class="absolute bottom-0 inset-x-0 bg-black/60 text-white text-[8px] font-bold text-center py-0.5">
                    <?= $map_name; ?>
                </span>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-2 py-2 px-3 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800/80 mb-3 text-center text-xs">
            <div>
                <span class="text-[10px] text-slate-400 block font-semibold">Prize Pool</span>
                <span class="text-xs font-black text-blue-600 dark:text-blue-400 tabular-nums font-mono">
                    <?= format_inr($t['prize_pool']); ?>
                </span>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 block font-semibold">Per Kill</span>
                <span class="text-xs font-black text-emerald-600 dark:text-emerald-400 tabular-nums font-mono">
                    <?= (float)$t['per_kill'] > 0 ? "+" . format_inr($t['per_kill']) : "Rank Basis"; ?>
                </span>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 block font-semibold">Entry Fee</span>
                <span class="text-xs font-black text-slate-900 dark:text-white tabular-nums font-mono">
                    <?= (float)$t['entry_fee'] > 0 ? format_inr($t['entry_fee']) : "FREE"; ?>
                </span>
            </div>
        </div>

        <div class="space-y-1 mb-3">
            <div class="flex items-center justify-between text-[10px] font-bold">
                <span class="text-slate-500 dark:text-slate-400">
                    Slots: <strong class="text-slate-900 dark:text-white"><?= $enrolled; ?>/<?= $total_slots; ?></strong>
                </span>
                <span class="text-blue-600 dark:text-blue-400"><?= $spots_left; ?> left</span>
            </div>
            <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-300" style="width: <?= $fill_pct; ?>%"></div>
            </div>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
            <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300">
                Win: <strong class="text-blue-600 dark:text-blue-400"><?= format_compact_inr($t['prize_pool'] * 0.5); ?></strong> for 1st
            </span>
            <?php if ($is_full): ?>
                <button disabled class="py-2 px-4 bg-slate-200 dark:bg-slate-800 text-slate-400 font-extrabold text-xs rounded-xl cursor-not-allowed">
                    FULL
                </button>
            <?php else: ?>
                <button type="button"
                        onclick="openJoinModal(<?= $t['id']; ?>, '<?= addslashes($t['title']); ?>', <?= (float)$t['entry_fee']; ?>, <?= (float)$t['prize_pool']; ?>, <?= $total_slots; ?>, '<?= $t_booked_json; ?>', '<?= addslashes($mode_name); ?>')"
                        class="py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white font-black text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer">
                    Join · <?= (float)$t['entry_fee'] > 0 ? format_inr($t['entry_fee']) : "FREE"; ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
<?php
    endwhile;
}
?>

    <!-- Daily Scheduled Esports Matches (Clean Blue Reference Theme) -->
    <?php
    $demo_matches = [
        [
            "id" => 101,
            "title" => "Free Fire Premier League - Bermuda Rush",
            "game" => "Free Fire MAX",
            "map" => "BERMUDA",
            "match_type" => "SOLO BR",
            "prize_pool" => 1200.00,
            "per_kill" => 25.00,
            "entry_fee" => 30.00,
            "enrolled" => 22,
            "total_slots" => 48,
            "match_time" => date("d M, h:i A", strtotime("+30 minutes")),
            "status" => "Ongoing"
        ],
        [
            "id" => 102,
            "title" => "Grand Clash Squad 1v1 - Iron Cage Deathmatch",
            "game" => "Free Fire MAX",
            "map" => "IRON CAGE",
            "match_type" => "CS 1VS1",
            "prize_pool" => 400.00,
            "per_kill" => 0.00,
            "entry_fee" => 20.00,
            "enrolled" => 1,
            "total_slots" => 2,
            "match_time" => date("d M, h:i A", strtotime("+1 hour 30 minutes")),
            "status" => "Upcoming"
        ],
        [
            "id" => 103,
            "title" => "Squad Battle Royale - Purgatory Elite Cup",
            "game" => "Free Fire MAX",
            "map" => "PURGATORY",
            "match_type" => "SQUAD BR",
            "prize_pool" => 2500.00,
            "per_kill" => 40.00,
            "entry_fee" => 80.00,
            "enrolled" => 24,
            "total_slots" => 48,
            "match_time" => date("d M, h:i A", strtotime("+3 hours")),
            "status" => "Upcoming"
        ],
        [
            "id" => 104,
            "title" => "Lone Wolf 1v1 - Fast Duel Showdown",
            "game" => "Free Fire MAX",
            "map" => "COLOSSEUM",
            "match_type" => "LONE WOLF 1V1",
            "prize_pool" => 350.00,
            "per_kill" => 0.00,
            "entry_fee" => 15.00,
            "enrolled" => 0,
            "total_slots" => 2,
            "match_time" => date("d M, h:i A", strtotime("+4 hours")),
            "status" => "Upcoming"
        ]
    ];
    foreach ($demo_matches as $dm):
        $d_cat = strtoupper($dm["match_type"]);
        $d_fill = min(100, round(($dm["enrolled"] / $dm["total_slots"]) * 100));
        $d_left = $dm["total_slots"] - $dm["enrolled"];
    ?>
    <div class="tournament-card bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-2xs hover:shadow-xs transition mb-3.5" data-category="<?= htmlspecialchars($d_cat); ?>">
        <div class="flex items-center justify-between gap-2 mb-2.5">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400">
                    <?= htmlspecialchars($dm["game"]); ?>
                </span>
                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
                    <?= htmlspecialchars($dm["map"]); ?> · <?= htmlspecialchars($dm["match_type"]); ?>
                </span>
            </div>
            <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md <?= $dm["status"] === "Ongoing" ? "bg-blue-50 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800" : "bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300" ?>">
                <?= htmlspecialchars($dm["status"]); ?>
            </span>
        </div>

        <div class="flex items-start justify-between gap-3 mb-3">
            <div class="flex-1">
                <h3 class="text-sm font-black text-slate-900 dark:text-white leading-snug tracking-tight font-display">
                    <?= htmlspecialchars($dm["title"]); ?>
                </h3>
                <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                    <span><i class="fa-regular fa-clock text-slate-400 mr-1"></i><?= $dm["match_time"]; ?></span>
                    <span>·</span>
                    <span>Match #<?= $dm["id"]; ?></span>
                </div>
            </div>
            <div class="relative w-16 h-12 rounded-xl overflow-hidden shrink-0 border border-slate-200 dark:border-slate-800 bg-slate-900 shadow-2xs">
                <img src="https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=200&q=80" alt="Thumb" class="w-full h-full object-cover">
                <span class="absolute bottom-0 inset-x-0 bg-black/60 text-white text-[8px] font-bold text-center py-0.5">
                    <?= $dm["map"]; ?>
                </span>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-2 py-2 px-3 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800/80 mb-3 text-center text-xs">
            <div>
                <span class="text-[10px] text-slate-400 block font-semibold">Prize Pool</span>
                <span class="text-xs font-black text-blue-600 dark:text-blue-400 tabular-nums font-mono">
                    <?= format_inr($dm["prize_pool"]); ?>
                </span>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 block font-semibold">Per Kill</span>
                <span class="text-xs font-black text-emerald-600 dark:text-emerald-400 tabular-nums font-mono">
                    <?= $dm["per_kill"] > 0 ? "+" . format_inr($dm["per_kill"]) : "Rank Basis"; ?>
                </span>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 block font-semibold">Entry Fee</span>
                <span class="text-xs font-black text-slate-900 dark:text-white tabular-nums font-mono">
                    <?= format_inr($dm["entry_fee"]); ?>
                </span>
            </div>
        </div>

        <div class="space-y-1 mb-3">
            <div class="flex items-center justify-between text-[10px] font-bold">
                <span class="text-slate-500 dark:text-slate-400">
                    Slots: <strong class="text-slate-900 dark:text-white"><?= $dm["enrolled"]; ?>/<?= $dm["total_slots"]; ?></strong>
                </span>
                <span class="text-blue-600 dark:text-blue-400"><?= $d_left; ?> left</span>
            </div>
            <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-300" style="width: <?= $d_fill; ?>%"></div>
            </div>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
            <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300">
                Win: <strong class="text-blue-600 dark:text-blue-400"><?= format_compact_inr($dm["prize_pool"] * 0.5); ?></strong> for 1st
            </span>
            <button type="button"
                    onclick="openJoinModal(<?= $dm["id"]; ?>, '<?= addslashes($dm["title"]); ?>', <?= (float)$dm["entry_fee"]; ?>, <?= (float)$dm["prize_pool"]; ?>, <?= $dm["total_slots"]; ?>, '[]', '<?= addslashes($dm["match_type"]); ?>')"
                    class="py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white font-black text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer">
                Join · <?= format_inr($dm["entry_fee"]); ?>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal: Join Private Match by Code -->
<div id="privateCodeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs hidden">
    <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-3xl p-5 max-w-sm w-full shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-xl bg-blue-600/10 text-blue-500 flex items-center justify-center">
                    <i class="fa-solid fa-key"></i>
                </span>
                <h3 class="text-sm font-black text-slate-900 dark:text-white">Join Private Match</h3>
            </div>
            <button type="button" onclick="closePrivateMatchModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white p-1 text-base cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
            Enter the 6-character match code provided by your tournament host to book your slot.
        </p>
        <div>
            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Access Code</label>
            <input type="text" id="privateMatchCodeInput" placeholder="e.g. BTZ-A89F" maxlength="16" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white font-mono font-black text-center text-sm uppercase tracking-wider focus:outline-none focus:border-blue-500">
            <p id="privateCodeError" class="hidden text-[11px] text-rose-500 font-bold mt-1.5 text-center"></p>
        </div>
        <button type="button" id="submitPrivateCodeBtn" onclick="verifyAndJoinPrivateMatch()" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-xl text-xs transition active:scale-95 flex items-center justify-center gap-2 cursor-pointer shadow-md">
            <i class="fa-solid fa-arrow-right-to-bracket"></i>
            <span>Find & Book Slot</span>
        </button>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL: JOIN TOURNAMENT (IGN + Game UID + Slot Number Selector) -->
<!-- ============================================================= -->
<div id="joinModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 rounded-3xl p-5 shadow-2xl relative max-h-[90vh] overflow-y-auto">
        <button type="button" onclick="closeJoinModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base cursor-pointer">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-4">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-blue-600 text-white flex items-center justify-center text-xl font-black mb-2 shadow-sm">
                <i class="fa-solid fa-trophy"></i>
            </div>
            <h3 class="text-base font-extrabold text-slate-900 dark:text-white" id="modalTourneyTitle">Tournament Registration</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Select your custom room slot and confirm</p>
        </div>

        <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-3 mb-4 space-y-1.5 text-xs">
            <div class="flex justify-between text-slate-600 dark:text-slate-400">
                <span>Entry Fee:</span>
                <span class="font-extrabold text-slate-900 dark:text-white" id="modalEntryFee">₹0.00</span>
            </div>
            <div class="flex justify-between text-slate-600 dark:text-slate-400">
                <span>Prize Pool:</span>
                <span class="font-extrabold text-blue-600 dark:text-blue-400" id="modalPrizePool">₹0.00</span>
            </div>
            <div class="flex justify-between text-slate-600 dark:text-slate-400 border-t border-slate-200 dark:border-slate-800 pt-1.5">
                <span>Your Balance:</span>
                <span class="font-extrabold font-mono text-emerald-600 dark:text-emerald-400"><?= format_inr($current_balance ?? ($user_wallet ?? 0.00)); ?></span>
            </div>
        </div>

        <form method="POST" action="index.php" class="space-y-3.5" autocomplete="off" onsubmit="return beastzLockJoin(this);">
            <input type="hidden" name="action" value="join_tournament">
            <input type="hidden" name="tournament_id" id="modalTournamentId" value="0">

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">In-Game Name (IGN)</label>
                <input type="text" name="ign" required placeholder="Enter exact Free Fire IGN" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Game UID</label>
                <input type="text" name="game_uid" required placeholder="Enter your numerical UID" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:outline-none focus:border-blue-500">
            </div>

            <!-- SLOT SELECTION -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Room Slot</label>
                    <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400" id="slotPickerStatus">Select 1 slot</span>
                </div>
                <input type="hidden" name="slot_number" id="selectedSlotInput" value="1">
                <div class="grid grid-cols-6 gap-1.5 max-h-36 overflow-y-auto p-1 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800" id="slotGridContainer">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>

            <button type="submit" id="joinSubmitBtn" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-black text-xs rounded-xl shadow-md transition active:scale-95 cursor-pointer mt-2">
                Confirm & Pay
            </button>
        </form>
    </div>
</div>

<script>
function filterTourneys(category, btn) {
    document.querySelectorAll(".category-tab").forEach(tab => {
        tab.className = "category-tab flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer bg-white dark:bg-[#111827] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:bg-slate-50";
    });
    btn.className = "category-tab flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer bg-blue-600 text-white shadow-xs";

    const cards = document.querySelectorAll(".tournament-card");
    const upperCat = category.toUpperCase();
    cards.forEach(card => {
        const cardCat = (card.getAttribute("data-category") || "").toUpperCase();
        if (upperCat === "ALL" || cardCat.includes(upperCat)) {
            card.style.display = "";
        } else {
            card.style.display = "none";
        }
    });
}

function openPrivateMatchModal() {
    const m = document.getElementById("privateCodeModal");
    if (m) m.classList.remove("hidden");
}

function closePrivateMatchModal() {
    const m = document.getElementById("privateCodeModal");
    if (m) m.classList.add("hidden");
}

function verifyAndJoinPrivateMatch() {
    const code = document.getElementById("privateMatchCodeInput").value.trim().toUpperCase();
    const err = document.getElementById("privateCodeError");
    if (!code) {
        err.textContent = "Please enter match code";
        err.classList.remove("hidden");
        return;
    }
    err.classList.add("hidden");
    openJoinModal(999, "Private Tournament (" + code + ")", 50, 1000, 48, "[]", "SOLO BR");
    closePrivateMatchModal();
}

function openJoinModal(id, title, entryFee, prizePool, totalSlots, bookedJson, matchType) {
    document.getElementById("modalTournamentId").value = id;
    document.getElementById("modalTourneyTitle").textContent = title;
    document.getElementById("modalEntryFee").textContent = "₹" + parseFloat(entryFee).toFixed(2);
    document.getElementById("modalPrizePool").textContent = "₹" + parseFloat(prizePool).toFixed(2);

    let booked = [];
    try {
        booked = JSON.parse(bookedJson || "[]");
    } catch(e) {
        booked = [];
    }

    const grid = document.getElementById("slotGridContainer");
    grid.innerHTML = "";
    let firstOpen = null;

    for (let s = 1; s <= totalSlots; s++) {
        const isBooked = booked.includes(s);
        const slotBtn = document.createElement("button");
        slotBtn.type = "button";
        slotBtn.className = "py-1.5 text-xs font-extrabold rounded-lg border transition text-center cursor-pointer " + 
            (isBooked ? "bg-slate-200 dark:bg-slate-800 text-slate-400 border-transparent cursor-not-allowed" : "bg-white dark:bg-[#111827] text-slate-800 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:border-blue-500");
        slotBtn.textContent = s;
        if (!isBooked) {
            if (!firstOpen) firstOpen = s;
            slotBtn.onclick = function() {
                document.querySelectorAll("#slotGridContainer button").forEach(b => {
                    if (!b.disabled) b.className = "py-1.5 text-xs font-extrabold rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#111827] text-slate-800 dark:text-slate-200 hover:border-blue-500 cursor-pointer text-center";
                });
                slotBtn.className = "py-1.5 text-xs font-extrabold rounded-lg bg-blue-600 text-white border-blue-600 text-center cursor-pointer shadow-xs";
                document.getElementById("selectedSlotInput").value = s;
                document.getElementById("slotPickerStatus").textContent = "Selected: Slot " + s;
            };
        } else {
            slotBtn.disabled = true;
        }
        grid.appendChild(slotBtn);
    }

    if (firstOpen) {
        document.getElementById("selectedSlotInput").value = firstOpen;
        document.getElementById("slotPickerStatus").textContent = "Selected: Slot " + firstOpen;
        const firstBtn = grid.children[firstOpen - 1];
        if (firstBtn && !firstBtn.disabled) {
            firstBtn.className = "py-1.5 text-xs font-extrabold rounded-lg bg-blue-600 text-white border-blue-600 text-center cursor-pointer shadow-xs";
        }
    }

    const m = document.getElementById("joinModal");
    if (m) m.classList.remove("hidden");
}

function closeJoinModal() {
    const m = document.getElementById("joinModal");
    if (m) m.classList.add("hidden");
}

function beastzLockJoin(form) {
    const btn = document.getElementById("joinSubmitBtn");
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = "<i class="fa-solid fa-spinner fa-spin"></i> Joining...";
    }
    return true;
}
</script>
