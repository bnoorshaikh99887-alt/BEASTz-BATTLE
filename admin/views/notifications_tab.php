<!-- ============================================================= -->
<!-- PUSH NOTIFICATIONS BROADCAST CENTER (ZOMATO STYLE) -->
<!-- ============================================================= -->
<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-base font-extrabold text-slate-900 dark:text-white font-display flex items-center gap-2">
                <i class="fa-solid fa-bell text-yellow-500"></i>
                <span>Push Notifications Studio</span>
            </h2>
            <p class="text-[11px] text-slate-500">Broadcast instant pop-up notifications to all active APK & Web gamers</p>
        </div>
        <span class="px-2.5 py-1 bg-yellow-400 text-slate-950 font-black rounded-full text-[10px] uppercase shadow-xs">
            Zomato Style
        </span>
    </div>

    <!-- Quick Preset Chips -->
    <div class="bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-slate-800 rounded-2xl p-3.5 shadow-sm space-y-2">
        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">
            Quick 1-Tap Presets
        </span>
        <div class="grid grid-cols-2 gap-2 text-xs">
            <button type="button" onclick="applyNotifPreset(1)" class="p-2 rounded-xl bg-slate-50 dark:bg-slate-800 hover:bg-yellow-50 border border-slate-200 dark:border-slate-700 hover:border-yellow-400 text-left transition">
                <span class="font-black text-slate-900 dark:text-white block text-[11px]">🔥 Match Starting Soon</span>
                <span class="text-[9px] text-slate-400 block truncate">Room ID & Pass published</span>
            </button>
            <button type="button" onclick="applyNotifPreset(2)" class="p-2 rounded-xl bg-slate-50 dark:bg-slate-800 hover:bg-yellow-50 border border-slate-200 dark:border-slate-700 hover:border-yellow-400 text-left transition">
                <span class="font-black text-slate-900 dark:text-white block text-[11px]">💰 ₹500 Mega Match</span>
                <span class="text-[9px] text-slate-400 block truncate">Slots filling fast! Join now</span>
            </button>
            <button type="button" onclick="applyNotifPreset(3)" class="p-2 rounded-xl bg-slate-50 dark:bg-slate-800 hover:bg-yellow-50 border border-slate-200 dark:border-slate-700 hover:border-yellow-400 text-left transition">
                <span class="font-black text-slate-900 dark:text-white block text-[11px]">⚡ Instant UPI Deposit</span>
                <span class="text-[9px] text-slate-400 block truncate">0% fee & instant wallet credit</span>
            </button>
            <button type="button" onclick="applyNotifPreset(4)" class="p-2 rounded-xl bg-slate-50 dark:bg-slate-800 hover:bg-yellow-50 border border-slate-200 dark:border-slate-700 hover:border-yellow-400 text-left transition">
                <span class="font-black text-slate-900 dark:text-white block text-[11px]">🏆 Winners Announced</span>
                <span class="text-[9px] text-slate-400 block truncate">Prize distributed to wallets</span>
            </button>
        </div>
    </div>

    <!-- Phone Status Bar Mockup Preview -->
    <div class="bg-slate-900 border border-yellow-500/40 rounded-2xl p-3.5 shadow-sm space-y-2">
        <span class="text-[10px] font-black uppercase tracking-wider text-yellow-400 block flex items-center justify-between">
            <span>Live Android Status Bar Preview</span>
            <span class="text-[9px] text-slate-400 font-mono">Now &bull; Push</span>
        </span>
        <div class="bg-[#070a12] border border-slate-800 rounded-xl p-3 flex items-start gap-2.5 shadow-inner">
            <div class="w-9 h-9 rounded-xl bg-yellow-400 text-slate-950 flex items-center justify-center text-sm shrink-0 font-black shadow-xs">
                <i class="fa-solid fa-gamepad"></i>
            </div>
            <div class="flex-1 min-w-0 text-left">
                <div class="flex items-center justify-between text-[10px] text-slate-400 mb-0.5">
                    <span class="font-black uppercase text-yellow-400 tracking-wider">BEASTz BATTLE</span>
                    <span class="text-[9px]">Just now</span>
                </div>
                <h4 id="previewNotifTitle" class="text-xs font-black text-white truncate">
                    🔥 CS 1VS1 Headshot Match Starting in 10 mins!
                </h4>
                <p id="previewNotifMsg" class="text-[11px] text-slate-300 line-clamp-2 mt-0.5 leading-tight">
                    Room ID & Pass published in My Matches! Enter custom room and take your slot now!
                </p>
            </div>
        </div>
    </div>

    <!-- Broadcast Form -->
    <div class="bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm space-y-3">
        <form id="pushBroadcastForm" onsubmit="submitPushBroadcast(event)" class="space-y-3">
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                    Notification Title *
                </label>
                <input type="text" id="notifTitleInput" required placeholder="e.g. 🔥 Match Starting in 10 mins!" 
                       oninput="updateNotifPreview()"
                       class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white font-bold focus:outline-none">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                    Notification Message Body *
                </label>
                <textarea id="notifMsgInput" required rows="3" placeholder="Write your broadcast announcement..."
                          oninput="updateNotifPreview()"
                          class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white font-medium focus:outline-none"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                        Action On Tap
                    </label>
                    <select id="notifTargetInput" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white font-bold focus:outline-none">
                        <option value="/?tab=home">Home (Tournaments)</option>
                        <option value="/?tab=matches">My Matches (IDP)</option>
                        <option value="/?tab=wallet">Wallet (Add Money)</option>
                        <option value="/?tab=leaderboard">Leaderboard</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                        Notification Type
                    </label>
                    <select id="notifBadgeInput" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-yellow-400 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white font-bold focus:outline-none">
                        <option value="ANNOUNCEMENT">Announcement</option>
                        <option value="MATCH_ALERT">Match Alert</option>
                        <option value="BONUS">Bonus / Offer</option>
                        <option value="UPDATE">App Update</option>
                    </select>
                </div>
            </div>

            <div id="pushStatusAlert" class="hidden p-3 rounded-xl text-xs font-bold"></div>

            <button type="submit" id="pushSendBtn" class="w-full py-3.5 bg-yellow-400 hover:bg-yellow-500 text-slate-950 font-black rounded-xl text-xs transition shadow-md active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                <i class="fa-solid fa-paper-plane"></i>
                <span>Broadcast Push Notification to All Gamers</span>
            </button>
        </form>
    </div>

    <!-- Recent Sent History -->
    <div class="bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-400">
                Recent Sent Broadcasts
            </h3>
            <span class="text-[10px] text-slate-500">Auto-stored in TiDB</span>
        </div>
        <div class="space-y-2" id="recentBroadcastsList">
            <?php
            $notifs_q = $conn->query("SELECT * FROM app_notifications ORDER BY id DESC LIMIT 5");
            if ($notifs_q && $notifs_q->num_rows > 0):
                while ($n = $notifs_q->fetch_assoc()):
            ?>
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 flex items-start justify-between gap-2 text-xs">
                    <div>
                        <div class="flex items-center gap-1.5 mb-0.5">
                            <span class="px-1.5 py-0.2 rounded bg-yellow-400 text-slate-950 font-black text-[8px] uppercase">
                                <?= htmlspecialchars($n['badge_type'] ?: 'ALERT'); ?>
                            </span>
                            <span class="font-black text-slate-900 dark:text-white"><?= htmlspecialchars($n['title']); ?></span>
                        </div>
                        <p class="text-[11px] text-slate-400 line-clamp-1"><?= htmlspecialchars($n['message']); ?></p>
                        <span class="text-[9px] text-slate-500 mt-1 block"><?= date('d M, h:i A', strtotime($n['created_at'])); ?></span>
                    </div>
                </div>
            <?php 
                endwhile;
            else:
            ?>
                <p class="text-xs text-slate-400 text-center py-4">No push broadcasts sent yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function updateNotifPreview() {
        var t = document.getElementById('notifTitleInput').value.trim();
        var m = document.getElementById('notifMsgInput').value.trim();
        document.getElementById('previewNotifTitle').innerText = t || 'Title Preview';
        document.getElementById('previewNotifMsg').innerText = m || 'Message Body Preview...';
    }

    function applyNotifPreset(num) {
        var t = '', m = '', target = '/?tab=home';
        if (num === 1) {
            t = '🔥 Free Fire CS 1VS1 Headshot Match Starting in 10 mins!';
            m = 'Room ID & Pass published in My Matches! Enter custom room and take your slot now!';
            target = '/?tab=matches';
        } else if (num === 2) {
            t = '💰 ₹500 Mega Tournament Open!';
            m = 'New high-prize tournament live on arena! Limited slots left, join your slot now!';
            target = '/?tab=home';
        } else if (num === 3) {
            t = '⚡ Instant 10-Second UPI Deposit Live!';
            m = 'Add money instantly via QR or UPI with zero fees! Fast approval!';
            target = '/?tab=wallet';
        } else if (num === 4) {
            t = '🏆 Tournament Winners Announced!';
            m = 'Prize money credited to winner wallets. Check your rank on Leaderboard!';
            target = '/?tab=leaderboard';
        }
        document.getElementById('notifTitleInput').value = t;
        document.getElementById('notifMsgInput').value = m;
        document.getElementById('notifTargetInput').value = target;
        updateNotifPreview();
    }

    function submitPushBroadcast(e) {
        e.preventDefault();
        var btn = document.getElementById('pushSendBtn');
        var alertBox = document.getElementById('pushStatusAlert');
        var title = document.getElementById('notifTitleInput').value.trim();
        var msg = document.getElementById('notifMsgInput').value.trim();
        var target = document.getElementById('notifTargetInput').value;
        var badge = document.getElementById('notifBadgeInput').value;

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Broadcasting...</span>';

        var formData = new FormData();
        formData.append('title', title);
        formData.append('message', msg);
        formData.append('target_url', target);
        formData.append('badge_type', badge);

        fetch('/api/send_notification.php', {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> <span>Broadcast Push Notification to All Gamers</span>';
            alertBox.classList.remove('hidden', 'bg-rose-50', 'text-rose-800', 'bg-emerald-50', 'text-emerald-800');
            if (data.success) {
                alertBox.classList.add('bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-200');
                alertBox.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> ' + data.message;
                document.getElementById('pushBroadcastForm').reset();
                updateNotifPreview();
            } else {
                alertBox.classList.add('bg-rose-50', 'text-rose-800', 'border', 'border-rose-200');
                alertBox.innerHTML = '<i class="fa-solid fa-circle-exclamation text-rose-600 mr-1"></i> ' + (data.error || 'Failed to send notification');
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> <span>Broadcast Push Notification to All Gamers</span>';
            alertBox.classList.remove('hidden');
            alertBox.classList.add('bg-rose-50', 'text-rose-800', 'border', 'border-rose-200');
            alertBox.innerHTML = 'Network error broadcasting notification.';
        });
    }
</script>
