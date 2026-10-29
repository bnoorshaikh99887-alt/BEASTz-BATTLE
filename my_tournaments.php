<?php
require_once __DIR__ . '/common/header.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];

// Fetch User's Upcoming Matches
$upcoming_query = "
    SELECT t.*, p.in_game_name, p.created_at as joined_at,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
    FROM participants p
    INNER JOIN tournaments t ON p.tournament_id = t.id
    WHERE p.user_id = {$user_id} AND LOWER(t.status) IN ('upcoming', 'pending')
    ORDER BY t.match_time ASC
";
$upcoming_matches = $conn->query($upcoming_query);

// Fetch User's Live Matches
$live_query = "
    SELECT t.*, p.in_game_name, p.created_at as joined_at,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count
    FROM participants p
    INNER JOIN tournaments t ON p.tournament_id = t.id
    WHERE p.user_id = {$user_id} AND LOWER(t.status) IN ('live', 'ongoing')
    ORDER BY t.match_time ASC
";
$live_matches = $conn->query($live_query);

// Fetch User's Completed / Played Matches
$played_query = "
    SELECT t.*, p.in_game_name, p.created_at as joined_at,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as enrolled_count,
    u.username as winner_name
    FROM participants p
    INNER JOIN tournaments t ON p.tournament_id = t.id
    LEFT JOIN users u ON t.winner_user_id = u.id
    WHERE p.user_id = {$user_id} AND LOWER(t.status) = 'completed'
    ORDER BY t.id DESC
";
$played_matches = $conn->query($played_query);

// Fetch all participants for winners modal display
$all_winners_data = [];
$completed_tourneys = $conn->query("
    SELECT t.id, t.title, t.prize_pool, t.winner_user_id, u.username as winner_name, u.avatar as winner_avatar
    FROM tournaments t
    LEFT JOIN users u ON t.winner_user_id = u.id
    WHERE LOWER(t.status) = 'completed'
");
if ($completed_tourneys) {
    while ($w = $completed_tourneys->fetch_assoc()) {
        $all_winners_data[$w['id']] = $w;
    }
}
?>

<!-- 3 Navigation Tabs (Upcoming, Live, Played) Matching Video -->
<div class="mb-4 bg-white border-b border-slate-200 -mx-3.5 px-3.5 flex items-center justify-around">
    <button type="button" onclick="switchTab('upcoming', this)" class="match-tab active-match-tab py-2.5 px-4 text-xs font-bold text-slate-900 border-b-2 border-yellow-400 transition">
        Upcoming
    </button>
    <button type="button" onclick="switchTab('live', this)" class="match-tab py-2.5 px-4 text-xs font-bold text-slate-500 border-b-2 border-transparent hover:text-slate-900 transition">
        Live
    </button>
    <button type="button" onclick="switchTab('played', this)" class="match-tab py-2.5 px-4 text-xs font-bold text-slate-500 border-b-2 border-transparent hover:text-slate-900 transition">
        Played
    </button>
</div>

<!-- ============================================================= -->
<!-- TAB 1: UPCOMING MATCHES -->
<!-- ============================================================= -->
<div id="tabContentUpcoming" class="tab-pane space-y-3">
    <?php if ($upcoming_matches && $upcoming_matches->num_rows > 0): ?>
        <?php while ($t = $upcoming_matches->fetch_assoc()): ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm hover:shadow transition">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <span class="text-[9px] font-bold text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded uppercase">
                            <?= htmlspecialchars($t['match_type'] ?: 'SOLO'); ?> &bull; <?= htmlspecialchars($t['map'] ?: 'BERMUDA'); ?>
                        </span>
                        <h3 class="text-sm font-extrabold text-slate-900 mt-1">
                            <?= htmlspecialchars($t['title']); ?>
                        </h3>
                    </div>
                    <span class="text-xs font-extrabold text-amber-500">
                        Pool: <?= format_inr($t['prize_pool']); ?>
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs text-slate-600 bg-slate-50 p-2.5 rounded-xl border border-slate-200/60 mb-3">
                    <div>
                        <span class="text-[10px] text-slate-400 block">Match Time:</span>
                        <span class="font-bold text-slate-800"><?= date('d M, h:i A', strtotime($t['match_time'])); ?></span>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] text-slate-400 block">Registered IGN:</span>
                        <span class="font-bold text-slate-800"><?= htmlspecialchars($t['in_game_name'] ?? 'Player'); ?></span>
                    </div>
                </div>

                <!-- Room ID & Password Box -->
                <div class="bg-yellow-50/60 border border-yellow-200 rounded-xl p-3 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-yellow-800 uppercase block">Tournament Credentials</span>
                        <?php if (!empty($t['room_id'])): ?>
                            <div class="text-xs text-slate-900 font-mono mt-0.5 font-bold">
                                Room ID: <span class="text-yellow-600"><?= htmlspecialchars($t['room_id']); ?></span> &bull; 
                                Pass: <span class="text-yellow-600"><?= htmlspecialchars($t['room_password'] ?: 'N/A'); ?></span>
                            </div>
                        <?php else: ?>
                            <span class="text-[11px] text-slate-500">
                                <i class="fa-solid fa-clock text-yellow-500 mr-1"></i> Room ID & Pass shared 10 mins before match.
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($t['room_id'])): ?>
                        <button type="button" onclick="copyCredentials('<?= htmlspecialchars($t['room_id']); ?>', '<?= htmlspecialchars($t['room_password']); ?>')" class="px-2.5 py-1 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-bold text-[10px] rounded-lg shadow-xs transition">
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
            <a href="index.php" class="inline-block px-4 py-2 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-bold rounded-xl text-xs shadow-xs transition">
                Browse Tournaments &rarr;
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================= -->
<!-- TAB 2: LIVE MATCHES -->
<!-- ============================================================= -->
<div id="tabContentLive" class="tab-pane hidden space-y-3">
    <?php if ($live_matches && $live_matches->num_rows > 0): ?>
        <?php while ($t = $live_matches->fetch_assoc()): ?>
            <div class="bg-white border-2 border-emerald-400 rounded-2xl p-4 shadow-sm relative overflow-hidden">
                <div class="flex items-center gap-1.5 text-emerald-600 text-[10px] font-black uppercase mb-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                    <span>MATCH CURRENTLY LIVE</span>
                </div>

                <h3 class="text-sm font-extrabold text-slate-900 mb-2">
                    <?= htmlspecialchars($t['title']); ?>
                </h3>

                <!-- Room ID & Password Display -->
                <div class="bg-slate-900 text-white p-3 rounded-xl mb-3 flex items-center justify-between">
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

                <p class="text-[11px] text-slate-500">
                    Open Free Fire &rarr; Custom Room &rarr; Enter Room ID and Password above to play now!
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
<!-- TAB 3: PLAYED MATCHES (Exact Layout & Winners Button From Video) -->
<!-- ============================================================= -->
<div id="tabContentPlayed" class="tab-pane hidden space-y-3">
    <?php if ($played_matches && $played_matches->num_rows > 0): ?>
        <?php while ($t = $played_matches->fetch_assoc()): ?>
            <?php
                $is_i_winner = ((int)$t['winner_user_id'] === $user_id);
                $winner_label = !empty($t['winner_name']) ? htmlspecialchars($t['winner_name']) : 'noor';
            ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm hover:shadow transition">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900 leading-tight">
                            <?= htmlspecialchars($t['title']); ?>
                        </h3>
                    </div>
                    <!-- Yellow "Winners" Button Matching Video -->
                    <button type="button" onclick="openWinnersModal('<?= htmlspecialchars(addslashes($t['title'])); ?>', '<?= htmlspecialchars(addslashes($winner_label)); ?>', '<?= format_inr($t['prize_pool']); ?>', 4)" class="px-3 py-1 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black text-[11px] rounded-lg shadow-xs transition shrink-0">
                        Winners
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs mb-3">
                    <div class="space-y-1">
                        <div class="text-slate-600">
                            Entry Fee: <span class="font-extrabold text-slate-900"><?= format_inr($t['entry_fee']); ?></span>
                        </div>
                        <div class="text-slate-600">
                            Prize Pool: <span class="font-extrabold text-amber-500"><?= format_inr($t['prize_pool']); ?></span>
                        </div>
                    </div>
                    <div class="text-right space-y-1 text-slate-500 text-[11px]">
                        <div><i class="fa-regular fa-calendar text-yellow-500 mr-1"></i><?= date('d M', strtotime($t['match_time'])); ?></div>
                        <div><i class="fa-regular fa-clock text-yellow-500 mr-1"></i><?= date('h:i A', strtotime($t['match_time'])); ?></div>
                    </div>
                </div>

                <!-- Won Result Badge Matching Video -->
                <?php if ($is_i_winner): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-3 py-2 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-trophy text-emerald-500"></i>
                        <span>You won <?= format_inr($t['prize_pool']); ?> (Position #1)</span>
                    </div>
                <?php else: ?>
                    <div class="bg-slate-50 border border-slate-200 text-slate-600 px-3 py-1.5 rounded-xl text-[11px] font-semibold flex items-center justify-between">
                        <span>Match Ended</span>
                        <span class="text-slate-900 font-bold">Winner: @<?= $winner_label; ?></span>
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
            <p class="text-xs text-slate-500 mt-1">Your past tournament results and winnings will appear here.</p>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================= -->
<!-- MODAL: TOURNAMENT WINNERS (Exact Match to Video Frame 0:33) -->
<!-- ============================================================= -->
<div id="winnersModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl relative">
        <button type="button" onclick="closeWinnersModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-base">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h3 class="text-sm font-extrabold text-slate-900 text-center mb-1">Tournament Winners</h3>
        <p class="text-[11px] text-slate-400 text-center mb-4" id="winnersMatchTitle">Match Results</p>

        <!-- Winners Table (Matching Video) -->
        <div class="border border-slate-200 rounded-2xl overflow-hidden mb-4">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[10px] font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-2.5 px-3">Rank</th>
                        <th class="py-2.5 px-2">Player</th>
                        <th class="py-2.5 px-2 text-right">Prize</th>
                        <th class="py-2.5 px-3 text-right">Kills</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold" id="winnersTableBody">
                    <tr>
                        <td class="py-3 px-3 text-slate-900 font-bold">#1</td>
                        <td class="py-3 px-2 text-slate-900" id="winnerRowPlayer">noor</td>
                        <td class="py-3 px-2 text-right font-extrabold text-emerald-600 font-mono" id="winnerRowPrize">₹15</td>
                        <td class="py-3 px-3 text-right font-bold text-slate-700" id="winnerRowKills">4</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <button type="button" onclick="closeWinnersModal()" class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold rounded-xl text-xs transition">
            Close
        </button>
    </div>
</div>

<script>
    // Tab Switcher
    function switchTab(tabName, btn) {
        document.querySelectorAll('.match-tab').forEach(b => {
            b.classList.remove('border-yellow-400', 'text-slate-900');
            b.classList.add('border-transparent', 'text-slate-500');
        });
        btn.classList.remove('border-transparent', 'text-slate-500');
        btn.classList.add('border-yellow-400', 'text-slate-900');

        document.getElementById('tabContentUpcoming').classList.add('hidden');
        document.getElementById('tabContentLive').classList.add('hidden');
        document.getElementById('tabContentPlayed').classList.add('hidden');

        if (tabName === 'upcoming') document.getElementById('tabContentUpcoming').classList.remove('hidden');
        if (tabName === 'live') document.getElementById('tabContentLive').classList.remove('hidden');
        if (tabName === 'played') document.getElementById('tabContentPlayed').classList.remove('hidden');
    }

    // Winners Modal Handler
    function openWinnersModal(title, player, prize, kills) {
        document.getElementById('winnersMatchTitle').innerText = title;
        document.getElementById('winnerRowPlayer').innerText = player;
        document.getElementById('winnerRowPrize').innerText = prize;
        document.getElementById('winnerRowKills').innerText = kills || 4;
        document.getElementById('winnersModal').classList.remove('hidden');
    }

    function closeWinnersModal() {
        document.getElementById('winnersModal').classList.add('hidden');
    }

    // Copy credentials helper
    function copyCredentials(roomId, pass) {
        const text = "Room ID: " + roomId + " Password: " + pass;
        navigator.clipboard.writeText(text).then(() => {
            alert("Room Credentials Copied: " + text);
        });
    }
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
