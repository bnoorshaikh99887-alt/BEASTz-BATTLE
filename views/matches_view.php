<!-- Top 3 Sub-Tabs: Upcoming / Live / Played (Full-Cover Solid Yellow Capsule Pills) -->
<div class="bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 sticky top-14 z-30 mb-4 -mx-4 px-3 py-2 shadow-xs">
    <div class="grid grid-cols-3 gap-1.5 text-center text-xs font-bold bg-slate-100 dark:bg-slate-900/90 p-1 rounded-full border border-slate-200 dark:border-slate-800">
        <button type="button" onclick="switchMatchTab('upcoming', this)" id="tabBtnUpcoming" class="match-tab py-1.5 px-3 rounded-full bg-yellow-400 text-slate-950 font-black shadow-xs transition-all">
            Upcoming
        </button>
        <button type="button" onclick="switchMatchTab('live', this)" id="tabBtnLive" class="match-tab py-1.5 px-3 rounded-full text-slate-400 hover:text-white font-bold transition-all">
            Live
        </button>
        <button type="button" onclick="switchMatchTab('played', this)" id="tabBtnPlayed" class="match-tab py-1.5 px-3 rounded-full text-slate-400 hover:text-white font-bold transition-all">
            Played
        </button>
    </div>
</div>

<!-- ============================================================= -->
<!-- TAB 1: UPCOMING MATCHES -->
<!-- ============================================================= -->
<div id="tabContentUpcoming" class="tab-pane space-y-3 mb-20">
    <?php if ($upcoming_matches && $upcoming_matches->num_rows > 0): ?>
        <?php while ($t = $upcoming_matches->fetch_assoc()): ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-1.5 flex-wrap mb-1">
                            <span class="text-[9px] font-bold text-slate-600 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded uppercase">
                                <?= htmlspecialchars($t['match_type'] ?: 'SOLO'); ?> &bull; <?= htmlspecialchars($t['map'] ?: 'BERMUDA'); ?>
                            </span>
                            <span class="text-[9px] font-black !text-black bg-yellow-400 px-2 py-0.5 rounded uppercase shadow-2xs">
                                Slot #<?= $t['slot_no'] ?: 1; ?>
                            </span>
                        </div>
                        <h3 class="text-sm font-extrabold text-slate-900">
                            <?= htmlspecialchars($t['title']); ?>
                        </h3>
                    </div>
                    <span class="text-xs font-extrabold text-amber-500">
                        Pool: <?= format_compact_inr($t['prize_pool']); ?>
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                    <div>
                        <span class="text-[10px] text-slate-400 block font-semibold">Match Time</span>
                        <span class="font-bold text-slate-800"><?= date('d M, h:i A', strtotime($t['match_time'])); ?></span>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] text-slate-400 block font-semibold">Your Registered IGN</span>
                        <span class="font-black text-amber-500 dark:text-yellow-400"><?= htmlspecialchars($t['in_game_name'] ?? 'Player'); ?></span>
                    </div>
                </div>

                <!-- High-Contrast IDP Box (Dark Mode & Light Mode Comfortable) -->
                <div class="bg-slate-900 border border-yellow-500/40 rounded-xl p-3 flex items-center justify-between text-white shadow-xs">
                    <div>
                        <div class="flex items-center gap-1.5 mb-0.5">
                            <span class="w-2 h-2 rounded-full bg-yellow-400 animate-pulse"></span>
                            <span class="text-[10px] font-black text-yellow-400 uppercase tracking-wider block">
                                CUSTOM ROOM CREDENTIALS (IDP)
                            </span>
                        </div>
                        <?php if (!empty($t['room_id'])): ?>
                            <div class="text-xs text-white font-mono mt-0.5 font-bold flex items-center gap-2">
                                <span>ID: <strong class="text-yellow-400 font-black text-sm"><?= htmlspecialchars($t['room_id']); ?></strong></span>
                                <span>PASS: <strong class="text-yellow-400 font-black text-sm"><?= htmlspecialchars($t['room_password'] ?: 'None'); ?></strong></span>
                            </div>
                        <?php else: ?>
                            <span class="text-[11px] text-slate-300 font-medium">
                                <i class="fa-solid fa-clock text-yellow-400 mr-1"></i> Room ID & Pass published 10 mins before match start.
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($t['room_id'])): ?>
                        <button type="button" onclick="copyCredentials('<?= htmlspecialchars($t['room_id']); ?>', '<?= htmlspecialchars($t['room_password']); ?>')" class="px-3 py-1.5 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black text-[10px] rounded-lg shadow-xs transition active:scale-95">
                            Copy
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="text-center py-12 bg-white rounded-2xl border border-slate-200 p-6">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-yellow-100 text-yellow-600 flex items-center justify-center text-xl mb-2">
                <i class="fa-solid fa-calendar-xmark"></i>
            </div>
            <h4 class="text-sm font-bold text-slate-800">No upcoming matches</h4>
            <p class="text-xs text-slate-500 mt-1 mb-4">You have not joined any upcoming tournaments yet.</p>
            <button type="button" onclick="switchAppTab('home')" class="inline-block px-4 py-2 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-extrabold rounded-xl text-xs shadow-xs transition cursor-pointer">
                Browse Tournaments &rarr;
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================= -->
<!-- TAB 2: LIVE MATCHES -->
<!-- ============================================================= -->
<div id="tabContentLive" class="tab-pane hidden space-y-3 mb-20">
    <?php if ($live_matches && $live_matches->num_rows > 0): ?>
        <?php while ($t = $live_matches->fetch_assoc()): ?>
            <div class="bg-white border-2 border-emerald-400 rounded-2xl p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5 text-emerald-600 text-[10px] font-black uppercase">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                        <span>MATCH CURRENTLY LIVE</span>
                    </div>
                    <span class="text-xs font-black px-2 py-0.5 rounded-lg bg-yellow-400 text-black">
                        Your Slot: #<?= $t['slot_no'] ?: 1; ?>
                    </span>
                </div>

                <h3 class="text-sm font-extrabold text-slate-900">
                    <?= htmlspecialchars($t['title']); ?>
                </h3>

                <!-- Room ID & Password Display -->
                <?php if (str_contains($t['room_id'] ?? '', 'REVIEW') || str_contains($t['room_password'] ?? '', 'Support')): ?>
                    <div class="bg-rose-50 border border-rose-300 p-3 rounded-xl flex items-center justify-between text-xs">
                        <div>
                            <span class="text-[10px] font-black text-rose-700 uppercase tracking-wider block">
                                <i class="fa-solid fa-triangle-exclamation mr-1"></i> Match Under Review
                            </span>
                            <span class="text-xs font-black text-rose-950 block mt-0.5">
                                <?= htmlspecialchars($t['room_password']); ?>
                            </span>
                        </div>
                        <a href="https://wa.me/919878385810?text=Hello%20Support%20Match%20Review%20#<?= $t['id']; ?>" target="_blank" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-black text-[10px] rounded-lg shadow-xs flex items-center gap-1">
                            <i class="fa-brands fa-whatsapp"></i> Support
                        </a>
                    </div>
                <?php else: ?>
                    <div class="bg-slate-900 text-white p-3 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-slate-400 block uppercase font-mono">Custom Room Credentials</span>
                            <div class="text-xs font-mono font-bold mt-0.5">
                                ID: <span class="text-yellow-400 font-extrabold"><?= htmlspecialchars($t['room_id'] ?: 'Pending'); ?></span> | 
                                Pass: <span class="text-yellow-400 font-extrabold"><?= htmlspecialchars($t['room_password'] ?: 'Pending'); ?></span>
                            </div>
                        </div>
                        <?php if (!empty($t['room_id'])): ?>
                            <button type="button" onclick="copyCredentials('<?= htmlspecialchars($t['room_id']); ?>', '<?= htmlspecialchars($t['room_password']); ?>')" class="px-2.5 py-1 bg-yellow-400 text-slate-950 font-black text-[10px] rounded-lg">
                                Copy
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <p class="text-[11px] text-slate-500">
                    Open Free Fire &rarr; Custom Room &rarr; Enter Room ID and sit in <strong class="text-slate-900">Slot #<?= $t['slot_no'] ?: 1; ?></strong>!
                </p>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="text-center py-12 bg-white rounded-2xl border border-slate-200 p-6">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-xl mb-2">
                <i class="fa-solid fa-gamepad"></i>
            </div>
            <h4 class="text-sm font-bold text-slate-800">No live matches right now</h4>
            <p class="text-xs text-slate-500 mt-1">Check back when your registered match start time arrives.</p>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================= -->
<!-- TAB 3: PLAYED MATCHES (Performance & Actual Prize Won) -->
<!-- ============================================================= -->
<div id="tabContentPlayed" class="tab-pane hidden space-y-3 mb-20">
    <?php if ($played_matches && $played_matches->num_rows > 0): ?>
        <?php while ($t = $played_matches->fetch_assoc()): ?>
            <?php
                $kills = (int)($t['kills'] ?? 0);
                $rank_no = (int)($t['rank_no'] ?? 0);
                $prize_won = (float)($t['prize_won'] ?? 0.00);
                $per_kill = (float)($t['per_kill'] ?? 0.00);
                $tid_p = (int)$t['id'];

                $tourney_winners_json = htmlspecialchars(json_encode($winners_by_tourney[$tid_p] ?? []));
            ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm hover:shadow transition space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="text-[9px] font-black text-slate-600 bg-slate-100 px-2 py-0.5 rounded">
                                Slot #<?= $t['slot_no'] ?: 1; ?>
                            </span>
                            <span class="text-[9px] font-bold text-slate-400">
                                <?= date('d M, h:i A', strtotime($t['match_time'])); ?>
                            </span>
                        </div>
                        <h3 class="text-sm font-extrabold text-slate-900 leading-tight">
                            <?= htmlspecialchars($t['title']); ?>
                        </h3>
                    </div>

                    <!-- Yellow "Winners" Button -->
                    <button type="button" 
                            onclick="openWinnersModal('<?= htmlspecialchars(addslashes($t['title'])); ?>', '<?= $tourney_winners_json; ?>')" 
                            class="px-3 py-1 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black text-[11px] rounded-lg shadow-xs transition shrink-0">
                        Winners
                    </button>
                </div>

                <div class="grid grid-cols-3 gap-2 text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-center">
                    <div>
                        <span class="text-[10px] text-slate-400 block">Entry Fee</span>
                        <span class="font-extrabold text-slate-900"><?= format_inr($t['entry_fee']); ?></span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 block">Your Kills</span>
                        <span class="font-extrabold text-slate-900"><?= $kills; ?> Kills</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 block">Per Kill Rate</span>
                        <span class="font-extrabold text-emerald-600 font-mono"><?= format_inr($per_kill); ?></span>
                    </div>
                </div>

                <!-- Performance Breakdown & Actual Winnings -->
                <?php if ($prize_won > 0): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-3.5 py-2.5 rounded-xl text-xs font-bold flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-trophy text-emerald-600 text-sm"></i>
                            <div>
                                <span class="font-black text-sm text-emerald-900 block leading-tight">
                                    You won <?= format_inr($prize_won); ?>
                                </span>
                                <span class="text-[10px] text-emerald-700">
                                    <?= $kills; ?> Kills @ <?= format_inr($per_kill); ?>
                                    <?= ($rank_no > 0) ? " + Position #{$rank_no} Bonus" : ""; ?>
                                </span>
                            </div>
                        </div>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-200 text-emerald-900 font-black">
                            CREDITED
                        </span>
                    </div>
                <?php else: ?>
                    <div class="bg-slate-50 border border-slate-200 text-slate-600 px-3 py-2 rounded-xl text-[11px] font-semibold flex items-center justify-between">
                        <span>Match Completed &bull; <?= $kills; ?> Kills</span>
                        <span class="text-slate-400 font-mono">No Prize Won</span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="text-center py-12 bg-white rounded-2xl border border-slate-200 p-6">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-xl mb-2">
                <i class="fa-solid fa-trophy"></i>
            </div>
            <h4 class="text-sm font-bold text-slate-800">No played matches yet</h4>
            <p class="text-xs text-slate-500 mt-1">Your past tournament results and earned winnings will appear here.</p>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================= -->
<!-- MODAL: TOURNAMENT WINNERS -->
<!-- ============================================================= -->
<div id="winnersModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative max-h-[85vh] overflow-y-auto">
        <button type="button" onclick="closeWinnersModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="text-center mb-4">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-yellow-400 text-slate-950 flex items-center justify-center text-xl mb-2 shadow-xs">
                <i class="fa-solid fa-crown"></i>
            </div>
            <h3 class="text-base font-extrabold text-slate-900" id="winnersModalTitle">Match Winners</h3>
            <p class="text-xs text-slate-400 mt-0.5">Top performers and prize earners</p>
        </div>

        <div id="winnersListContainer" class="space-y-2 mb-4">
            <!-- Dynamically populated -->
        </div>

        <button type="button" onclick="closeWinnersModal()" class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold rounded-xl text-xs transition">
            Close
        </button>
    </div>
</div>

<script>
    function switchMatchTab(tab, btn) {
        document.querySelectorAll('.match-tab').forEach(b => {
            b.classList.remove('border-yellow-400', 'text-slate-950', 'font-black');
            b.classList.add('border-transparent', 'text-slate-500');
        });
        btn.classList.remove('border-transparent', 'text-slate-500');
        btn.classList.add('border-yellow-400', 'text-slate-950', 'font-black');

        document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));

        if (tab === 'upcoming') {
            document.getElementById('tabContentUpcoming').classList.remove('hidden');
        } else if (tab === 'live') {
            document.getElementById('tabContentLive').classList.remove('hidden');
        } else if (tab === 'played') {
            document.getElementById('tabContentPlayed').classList.remove('hidden');
        }
    }

    function copyCredentials(room, pass) {
        const text = "Room ID: " + room + "\nPassword: " + pass;
        navigator.clipboard.writeText(text).then(() => {
            alert("Room ID & Password copied to clipboard!");
        });
    }

    function openWinnersModal(title, winnersJsonStr) {
        document.getElementById('winnersModalTitle').innerText = title;
        const container = document.getElementById('winnersListContainer');
        container.innerHTML = '';

        let winners = [];
        try {
            winners = JSON.parse(winnersJsonStr) || [];
        } catch(e) {
            winners = [];
        }

        if (winners.length === 0) {
            container.innerHTML = '<p class="text-xs text-slate-500 text-center py-4">No winner data published yet.</p>';
        } else {
            winners.forEach((w, idx) => {
                const row = document.createElement('div');
                row.className = 'flex items-center justify-between p-2.5 bg-slate-50 rounded-xl border border-slate-200 text-xs';
                
                const rankText = w.rank_no > 0 ? '#' + w.rank_no : (idx + 1);
                row.innerHTML = `
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-yellow-400 text-slate-950 font-black flex items-center justify-center text-[10px]">
                            ${rankText}
                        </span>
                        <div>
                            <span class="font-extrabold text-slate-900 block">${w.in_game_name || w.username}</span>
                            <span class="text-[10px] text-slate-500">${w.kills} Kills</span>
                        </div>
                    </div>
                    <span class="font-mono font-black text-emerald-600">₹${parseFloat(w.prize_won).toFixed(2)}</span>
                `;
                container.appendChild(row);
            });
        }

        document.getElementById('winnersModal').classList.remove('hidden');
    }

    function closeWinnersModal() {
        document.getElementById('winnersModal').classList.add('hidden');
    }
</script>