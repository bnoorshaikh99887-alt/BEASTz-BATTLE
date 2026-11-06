<!-- Alert Feedback -->
<?php if (!empty($alert_msg)): ?>
    <div class="mb-4 p-3.5 rounded-2xl text-xs font-bold flex items-center gap-2 <?= $alert_type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : ($alert_type === 'warning' ? 'bg-amber-50 text-sky-200 border border-amber-200' : 'bg-rose-50 text-rose-800 border border-rose-200'); ?>">
        <i class="fa-solid <?= $alert_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600'; ?> text-sm"></i>
        <span><?= htmlspecialchars($alert_msg); ?></span>
    </div>
<?php endif; ?>

<!-- ============================================================= -->
<!-- 1. DUAL BALANCE CARDS & ACTIONS (MATCHING REFERENCE IMAGE) -->
<!-- ============================================================= -->
<div class="mb-5">
    <div class="grid grid-cols-2 gap-3 mb-3">
        <!-- Current Balance -->
        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-3.5 shadow-2xs">
            <div class="flex items-center gap-1.5 mb-1.5">
                <div class="w-6 h-6 rounded-lg bg-blue-50 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-wallet text-[11px]"></i>
                </div>
                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Current Balance</span>
            </div>
            <div class="text-xl font-black text-slate-900 dark:text-white tabular-nums tracking-tight font-display">
                <?= format_inr($user_wallet); ?>
            </div>
        </div>

        <!-- Bonus Balance -->
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

    <!-- Deposit & Withdraw Action Buttons (Blue Accent) -->
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
<!-- 2. TOP EVENTS HEADER & CAROUSEL CATEGORY FILTERS (REFERENCE)  -->
<!-- ============================================================= -->
<div class="mb-4">
    <div class="flex items-center justify-between mb-2.5">
        <h2 class="text-sm font-black text-slate-900 dark:text-white tracking-tight font-display">
            Top Events
        </h2>
        <button type="button" onclick="filterTourneys('ALL', this)" class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-0.5 cursor-pointer">
            <span>See All</span>
            <span>&gt;</span>
        </button>
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
<!-- Tournaments List -->
<div class="space-y-4 mb-20" id="tournamentsContainer">
    <?php if ($tournaments_res && $tournaments_res->num_rows > 0): ?>
        <?php while ($t = $tournaments_res->fetch_assoc()): ?>
            <?php
                $enrolled = (int)$t['enrolled_count'];
                $cat_tag = strtoupper(trim(($t['match_type'] ?? '') . ' ' . ($t['title'] ?? '')));
                $map_name = strtoupper(!empty($t['map']) ? $t['map'] : 'BERMUDA');
                $mode_name = strtoupper(!empty($t['match_type']) ? $t['match_type'] : 'SOLO BR');

                // Dynamic Slot Sizing: Squad = 12 slots, Duo = 24 slots, Solo = standard
                $is_squad = (stripos($mode_name, 'SQUAD') !== false || stripos($t['title'] ?? '', 'SQUAD') !== false);
                $is_duo = (!$is_squad && (stripos($mode_name, 'DUO') !== false || stripos($mode_name, '2VS2') !== false || stripos($t['title'] ?? '', 'DUO') !== false));
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
                            <span class="text-[9px] font-bold text-sky-200 bg-blue-950/60 border border-blue-600/40 px-2 py-0.5 rounded uppercase">
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
                        <span class="absolute top-1 right-1 bg-black/80 text-sky-400 font-black text-[8px] px-1.5 py-0.2 rounded font-mono">
                            FF
                        </span>
                    </div>
                </div>

                <!-- Prize Pool & Per Kill Bar -->
                <div class="flex items-center justify-between text-xs mb-2">
                    <div>
                        <span class="text-[10px] text-slate-400 block font-semibold">Prize Pool</span>
                        <span class="text-sm font-extrabold text-blue-400 font-mono">
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
                    <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-300" style="width: <?= $fill_pct; ?>%"></div>
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
        <!-- Daily Scheduled Featured Tournaments (Matching Reference Design) -->
        <?php
        $demo_tourneys = [
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
        foreach ($demo_tourneys as $dt):
            $cat_tag = strtoupper($dt["match_type"]);
            $fill_pct = min(100, round(($dt["enrolled"] / $dt["total_slots"]) * 100));
            $spots_left = $dt["total_slots"] - $dt["enrolled"];
        ?>
        <div class="tournament-card bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-2xs hover:shadow-xs transition mb-3.5" data-category="<?= htmlspecialchars($cat_tag); ?>">
            <!-- Header: League/Mode + Status Badge -->
            <div class="flex items-center justify-between gap-2 mb-2.5">
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400">
                        <?= htmlspecialchars($dt["game"]); ?>
                    </span>
                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
                        <?= htmlspecialchars($dt["map"]); ?> · <?= htmlspecialchars($dt["match_type"]); ?>
                    </span>
                </div>
                <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md <?= $dt["status"] === "Ongoing" ? "bg-blue-50 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800" : "bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300" ?>">
                    <?= htmlspecialchars($dt["status"]); ?>
                </span>
            </div>

            <!-- Title & Match Info -->
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex-1">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white leading-snug tracking-tight font-display">
                        <?= htmlspecialchars($dt["title"]); ?>
                    </h3>
                    <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                        <span><i class="fa-regular fa-clock text-slate-400 mr-1"></i><?= $dt["match_time"]; ?></span>
                        <span>·</span>
                        <span>Match #<?= $dt["id"]; ?></span>
                    </div>
                </div>
                <div class="relative w-16 h-12 rounded-xl overflow-hidden shrink-0 border border-slate-200 dark:border-slate-800 bg-slate-900 shadow-2xs">
                    <img src="https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=200&q=80" alt="Thumb" class="w-full h-full object-cover">
                    <span class="absolute bottom-0 inset-x-0 bg-black/60 text-white text-[8px] font-bold text-center py-0.5">
                        <?= $dt["map"]; ?>
                    </span>
                </div>
            </div>

            <!-- Stats Bar (Prize Pool, Per Kill, Entry Fee) -->
            <div class="grid grid-cols-3 gap-2 py-2 px-3 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800/80 mb-3 text-center text-xs">
                <div>
                    <span class="text-[10px] text-slate-400 block font-semibold">Prize Pool</span>
                    <span class="text-xs font-black text-blue-600 dark:text-blue-400 tabular-nums font-mono">
                        <?= format_inr($dt["prize_pool"]); ?>
                    </span>
                </div>
                <div>
                    <span class="text-[10px] text-slate-400 block font-semibold">Per Kill</span>
                    <span class="text-xs font-black text-emerald-600 dark:text-emerald-400 tabular-nums font-mono">
                        <?= $dt["per_kill"] > 0 ? "+" . format_inr($dt["per_kill"]) : "Rank Basis"; ?>
                    </span>
                </div>
                <div>
                    <span class="text-[10px] text-slate-400 block font-semibold">Entry Fee</span>
                    <span class="text-xs font-black text-slate-900 dark:text-white tabular-nums font-mono">
                        <?= format_inr($dt["entry_fee"]); ?>
                    </span>
                </div>
            </div>

            <!-- Slots Progress Bar -->
            <div class="space-y-1 mb-3">
                <div class="flex items-center justify-between text-[10px] font-bold">
                    <span class="text-slate-500 dark:text-slate-400">
                        Slots: <strong class="text-slate-900 dark:text-white"><?= $dt["enrolled"]; ?>/<?= $dt["total_slots"]; ?></strong>
                    </span>
                    <span class="text-blue-600 dark:text-blue-400"><?= $spots_left; ?> left</span>
                </div>
                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-300" style="width: <?= $fill_pct; ?>%"></div>
                </div>
            </div>

            <!-- Card Footer -->
            <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
                <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300">
                    Win: <strong class="text-blue-600 dark:text-blue-400"><?= format_compact_inr($dt["prize_pool"] * 0.5); ?></strong> for 1st
                </span>
                <button type="button"
                        onclick="openJoinModal(<?= $dt["id"]; ?>, '<?= addslashes($dt["title"]); ?>', <?= (float)$dt["entry_fee"]; ?>, <?= (float)$dt["prize_pool"]; ?>, <?= $dt["total_slots"]; ?>, '[]', '<?= addslashes($dt["match_type"]); ?>')"
                        class="py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white font-black text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer">
                    Join · <?= format_inr($dt["entry_fee"]); ?>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
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
        <button type="button" onclick="closeJoinModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-4">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-blue-600 flex items-center justify-center text-slate-950 text-xl font-black mb-2 shadow-sm">
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
                <span class="font-extrabold text-blue-400" id="modalPrizePool">₹0.00</span>
            </div>
            <div class="flex justify-between text-slate-600 border-t border-slate-200 pt-1.5">
                <span>Your Balance:</span>
                <span class="font-extrabold font-mono text-emerald-600"><?= format_inr($user_wallet); ?></span>
            </div>
        </div>

        <form method="POST" action="index.php" class="space-y-3.5" autocomplete="off" onsubmit="return beastzLockJoin(this);">
            <input type="hidden" name="action" value="join_tournament">
            <input type="hidden" name="tournament_id" id="modalTournamentId" value="0">

            <!-- 1. CUSTOM SLOT PICKER (ONLY EMPTY SLOTS VISIBLE IN BLUE/WHITE/GRAY/BLACK) -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-800 dark:text-slate-200">
                        Select Empty Slot <span class="text-rose-500">*</span>
                    </label>
                    <span class="text-[10px] font-bold text-sky-400" id="slotStatusHint">
                        Pick available slot
                    </span>
                </div>
                
                <input type="hidden" name="slot_no" id="selectedSlotInput" value="" required>
                <input type="hidden" name="match_mode" id="modalMatchMode" value="SOLO">

                <!-- Grid showing ONLY empty slots -->
                <div class="p-2.5 rounded-2xl bg-[#0b0f19] border border-slate-800 max-h-48 overflow-y-auto">
                    <div id="customSlotGrid" class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                        <!-- Populated dynamically: Only empty slots shown -->
                    </div>
                    <div id="noSlotsAlert" class="hidden text-center py-6 text-xs text-rose-400 font-bold">
                        <i class="fa-solid fa-ban text-sm mb-1 block"></i>
                        No empty slots remaining in this match.
                    </div>
                </div>

                <div class="flex items-center justify-between mt-1.5 px-1 text-[11px]">
                    <span class="text-slate-400">Selected: <strong id="selectedSlotLabel" class="text-sky-400 font-mono">None</strong></span>
                    <span class="text-[10px] text-emerald-400 flex items-center gap-1 font-bold">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        Only open slots visible
                    </span>
                </div>
            </div>

            <!-- 2. DYNAMIC PLAYERS INPUT (SQUAD: 4 PLAYERS, DUO: 2 PLAYERS, SOLO: 1 PLAYER) -->
            <div id="playerInputsContainer" class="space-y-3">
                <!-- Dynamically populated based on match category -->
            </div>

            <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-xl text-xs shadow-xs transition active:scale-95 flex items-center justify-center gap-1.5">
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
            el.classList.remove('bg-blue-600', 'text-white', 'border-blue-500');
            el.classList.add('bg-white', 'text-slate-600', 'border-slate-200');
        });
        btn.classList.remove('bg-white', 'text-slate-600', 'border-slate-200');
        btn.classList.add('bg-blue-600', 'text-white', 'border-blue-500');

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

    // Custom Slot Selection Handler
    function selectCustomSlot(slotNum, btnEl) {
        document.querySelectorAll('.slot-btn').forEach(b => {
            b.className = 'slot-btn py-2 px-1.5 rounded-xl text-center border transition-all cursor-pointer bg-[#0f172a] border-slate-700 text-white hover:border-blue-500 hover:bg-blue-950/40';
            const st = b.querySelector('.slot-status-text');
            if (st) st.className = 'slot-status-text text-[9px] text-emerald-400 font-bold block';
        });
        if (btnEl) {
            btnEl.className = 'slot-btn py-2 px-1.5 rounded-xl text-center border-2 transition-all cursor-pointer bg-blue-600 border-sky-300 text-white ring-2 ring-blue-500/50 shadow-md scale-105';
            const st = btnEl.querySelector('.slot-status-text');
            if (st) st.className = 'slot-status-text text-[9px] text-sky-100 font-black block';
        }
        document.getElementById('selectedSlotInput').value = slotNum;
        document.getElementById('selectedSlotLabel').innerText = 'Slot #' + slotNum;
    }

    // Join Modal Open Handler with Custom Slot Picker & Dynamic Squad/Duo Inputs
    function openJoinModal(id, title, fee, prize, totalSlots, bookedJsonStr, modeName) {
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

        // Detect mode: Squad (12 slots, 4 players) vs Duo (24 slots, 2 players) vs Solo
        const normMode = (modeName || '').toUpperCase();
        const normTitle = (title || '').toUpperCase();
        const isSquad = normMode.includes('SQUAD') || normTitle.includes('SQUAD') || normMode.includes('4VS4') || normTitle.includes('4VS4');
        const isDuo = !isSquad && (normMode.includes('DUO') || normTitle.includes('DUO') || normMode.includes('2VS2') || normTitle.includes('2VS2'));
        const effectiveSlots = parseInt(totalSlots) || (isSquad ? 12 : (isDuo ? 24 : 48));

        document.getElementById('modalMatchMode').value = isSquad ? 'SQUAD' : (isDuo ? 'DUO' : 'SOLO');

        // 1. POPULATE CUSTOM SLOT GRID (ONLY EMPTY SLOTS VISIBLE IN BLUE/WHITE/GRAY/BLACK)
        const grid = document.getElementById('customSlotGrid');
        const noSlotsAlert = document.getElementById('noSlotsAlert');
        const submitBtn = document.querySelector('#joinModal button[type=submit]');
        grid.innerHTML = '';
        let availableCount = 0;
        let firstAvailable = null;

        for (let s = 1; s <= effectiveSlots; s++) {
            // Rule: ONLY empty slots are displayed
            if (bookedSlots.includes(s)) {
                continue;
            }
            availableCount++;
            if (!firstAvailable) firstAvailable = s;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'slot-btn py-2 px-1.5 rounded-xl text-center border transition-all cursor-pointer bg-[#0f172a] border-slate-700 text-white hover:border-blue-500 hover:bg-blue-950/40';
            btn.setAttribute('data-slot', s);
            btn.innerHTML = `
                <span class="block text-xs font-black font-mono">#${s}</span>
                <span class="slot-status-text text-[9px] text-emerald-400 font-bold block">OPEN</span>
            `;
            btn.onclick = function() {
                selectCustomSlot(s, btn);
            };
            grid.appendChild(btn);
        }

        if (availableCount === 0) {
            grid.classList.add('hidden');
            noSlotsAlert.classList.remove('hidden');
            document.getElementById('selectedSlotInput').value = '';
            document.getElementById('selectedSlotLabel').innerText = 'None';
            if (submitBtn) submitBtn.disabled = true;
        } else {
            grid.classList.remove('hidden');
            noSlotsAlert.classList.add('hidden');
            if (submitBtn) submitBtn.disabled = false;
            if (firstAvailable) {
                const firstBtn = grid.querySelector(`[data-slot="${firstAvailable}"]`);
                selectCustomSlot(firstAvailable, firstBtn);
            }
        }

        // 2. DYNAMIC PLAYERS INPUT (SQUAD: 4 PLAYERS, DUO: 2 PLAYERS, SOLO: 1 PLAYER)
        const container = document.getElementById('playerInputsContainer');
        container.innerHTML = '';

        let playerCount = 1;
        let modeLabel = 'Solo Match (1 Player)';
        if (isSquad) {
            playerCount = 4;
            modeLabel = 'Squad Match (4 Players Required)';
        } else if (isDuo) {
            playerCount = 2;
            modeLabel = 'Duo Match (2 Players Required)';
        }

        const headerDiv = document.createElement('div');
        headerDiv.className = 'flex items-center justify-between pb-1 border-b border-slate-200 dark:border-slate-800';
        headerDiv.innerHTML = `
            <span class="text-xs font-black uppercase text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                <i class="fa-solid fa-users text-blue-400"></i> ${modeLabel}
            </span>
            <span class="text-[10px] font-bold text-sky-400 font-mono">${playerCount} Roster</span>
        `;
        container.appendChild(headerDiv);

        for (let p = 1; p <= playerCount; p++) {
            const isLeader = (p === 1);
            const box = document.createElement('div');
            box.className = 'p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1.5';
            box.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                        <i class="fa-solid fa-gamepad text-blue-400 text-[10px]"></i>
                        Player ${p} ${isLeader ? '<span class="text-[9px] bg-blue-600 text-white font-bold px-1.5 py-0.2 rounded">Leader</span>' : ''}
                    </span>
                    <span class="text-[10px] text-slate-400">IGN & Game UID</span>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <input type="text" name="player${p}_ign" id="player${p}_ign" placeholder="Player ${p} IGN" required
                               class="w-full px-2.5 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 font-bold placeholder:text-slate-400">
                    </div>
                    <div>
                        <input type="text" name="player${p}_uid" id="player${p}_uid" placeholder="Player ${p} UID" required
                               class="w-full px-2.5 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 font-mono font-bold placeholder:text-slate-400">
                    </div>
                </div>
            `;
            container.appendChild(box);
        }

        // Hidden inputs for backwards compatibility
        const hiddenIgn = document.createElement('input');
        hiddenIgn.type = 'hidden';
        hiddenIgn.name = 'in_game_name';
        hiddenIgn.id = 'modalInGameName';
        container.appendChild(hiddenIgn);

        const hiddenUid = document.createElement('input');
        hiddenUid.type = 'hidden';
        hiddenUid.name = 'game_id';
        hiddenUid.id = 'modalGameId';
        container.appendChild(hiddenUid);

        document.getElementById('joinModal').classList.remove('hidden');
    }

    function beastzLockJoin(form) {
        if (form.dataset.locked === '1') return false;

        const slot = document.getElementById('selectedSlotInput').value;
        if (!slot) {
            alert('Please pick an empty slot first!');
            return false;
        }

        const mode = document.getElementById('modalMatchMode').value;
        const pCount = (mode === 'SQUAD') ? 4 : ((mode === 'DUO') ? 2 : 1);

        let igns = [];
        let uids = [];
        for (let p = 1; p <= pCount; p++) {
            const ignInput = form['player' + p + '_ign'];
            const uidInput = form['player' + p + '_uid'];
            const ign = ignInput ? ignInput.value.trim() : '';
            const uid = uidInput ? uidInput.value.trim() : '';
            if (!ign) {
                alert('Please enter In-Game Name (IGN) for Player ' + p);
                if (ignInput) ignInput.focus();
                return false;
            }
            if (!uid) {
                alert('Please enter Game UID for Player ' + p);
                if (uidInput) uidInput.focus();
                return false;
            }
            igns.push(p === 1 && pCount > 1 ? ign + ' [L]' : ign);
            uids.push(uid);
        }

        document.getElementById('modalInGameName').value = igns.join(', ');
        document.getElementById('modalGameId').value = uids.join(' / ');

        form.dataset.locked = '1';
        var b = form.querySelector('button[type=submit]');
        if (b) {
            b.disabled = true;
            b.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Reserving Slot #' + slot + '...</span>';
        }
        return true;
    }

    function closeJoinModal() {
        document.getElementById('joinModal').classList.add('hidden');
    }

    function openPrivateMatchModal() {
        const modal = document.getElementById("privateCodeModal");
        const err = document.getElementById("privateCodeError");
        if (err) { err.classList.add("hidden"); err.innerText = ""; }
        const inp = document.getElementById("privateMatchCodeInput");
        if (inp) { inp.value = ""; }
        if (modal) modal.classList.remove("hidden");
    }

    function closePrivateMatchModal() {
        const modal = document.getElementById("privateCodeModal");
        if (modal) modal.classList.add("hidden");
    }

    function verifyAndJoinPrivateMatch() {
        const inp = document.getElementById("privateMatchCodeInput");
        const err = document.getElementById("privateCodeError");
        const btn = document.getElementById("submitPrivateCodeBtn");
        const code = (inp ? inp.value.trim().toUpperCase() : "");

        if (!code) {
            if (err) {
                err.innerText = "Please type the 6-character match code.";
                err.classList.remove("hidden");
            }
            return;
        }

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Searching Match...';
        }
        if (err) err.classList.add("hidden");

        fetch("api/private_match.php?code=" + encodeURIComponent(code))
            .then(res => res.json())
            .then(data => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-arrow-right-to-bracket"></i> Find & Book Slot';
                }
                if (data.success && data.match) {
                    closePrivateMatchModal();
                    const m = data.match;
                    const bookedJson = JSON.stringify(m.booked_slots || []);
                    openJoinModal(m.id, m.title, m.entry_fee, m.prize_pool, m.total_slots, bookedJson, m.match_type);
                } else {
                    if (err) {
                        err.innerText = data.error || "Invalid match code. Please check and retry.";
                        err.classList.remove("hidden");
                    }
                }
            })
            .catch(e => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-arrow-right-to-bracket"></i> Find & Book Slot';
                }
                if (err) {
                    err.innerText = "Server communication error. Please try again.";
                    err.classList.remove("hidden");
                }
            });
    }

</script>