<?php
$current_admin_user = strtolower(trim($_SESSION['admin_user'] ?? $_SESSION['username'] ?? ''));
$current_admin_id = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
$is_super = false;
if (
    !empty($_SESSION['is_super_admin']) ||
    $current_admin_user === 'anonymous.349' ||
    $current_admin_user === 'admin' ||
    $current_admin_user === 'noorshaikh99887@gmail.com' ||
    (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin') ||
    ($current_admin_id === 1 || $current_admin_id === 210001)
) {
    $is_super = true;
}
?>
</main>

                <!-- Admin Fixed Bottom Navigation -->
        <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-md bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 py-2 px-1 z-50 shadow-lg">
            <div class="grid grid-cols-6 text-center gap-0.5">
                <button type="button" onclick="switchAdminTab('overview')" id="admin-bottom-overview" class="admin-bottom-tab flex flex-col items-center py-1 rounded-xl cursor-pointer <?= ($cur_admin_tab === 'overview' ? $act_bot : $inact_bot); ?>">
                    <i class="fa-solid fa-gauge text-base mb-0.5"></i>
                    <span class="text-[8px] font-bold">Home</span>
                </button>
                <button type="button" onclick="switchAdminTab('tournaments')" id="admin-bottom-tournaments" class="admin-bottom-tab flex flex-col items-center py-1 rounded-xl cursor-pointer <?= ($cur_admin_tab === 'tournaments' ? $act_bot : $inact_bot); ?>">
                    <i class="fa-solid fa-trophy text-base mb-0.5"></i>
                    <span class="text-[8px] font-bold">Tourneys</span>
                </button>
                <button type="button" onclick="switchAdminTab('deposits')" id="admin-bottom-deposits" class="admin-bottom-tab flex flex-col items-center py-1 rounded-xl cursor-pointer <?= ($cur_admin_tab === 'deposits' ? $act_bot : $inact_bot); ?>">
                    <i class="fa-solid fa-hand-holding-dollar text-base mb-0.5"></i>
                    <span class="text-[8px] font-bold">Deposits</span>
                </button>
                <button type="button" onclick="switchAdminTab('payouts')" id="admin-bottom-payouts" class="admin-bottom-tab flex flex-col items-center py-1 rounded-xl cursor-pointer <?= ($cur_admin_tab === 'payouts' ? $act_bot : $inact_bot); ?>">
                    <i class="fa-solid fa-money-bill-transfer text-base mb-0.5"></i>
                    <span class="text-[8px] font-bold">Payouts</span>
                </button>
                <button type="button" onclick="switchAdminTab('notifications')" id="admin-bottom-notifications" class="admin-bottom-tab flex flex-col items-center py-1 rounded-xl cursor-pointer <?= ($cur_admin_tab === 'notifications' ? $act_bot : $inact_bot); ?>">
                    <i class="fa-solid fa-paper-plane text-base mb-0.5"></i>
                    <span class="text-[8px] font-bold text-yellow-500 font-black">Push Alerts</span>
                </button>
                <button type="button" onclick="switchAdminTab('admins')" id="admin-bottom-admins" class="admin-bottom-tab flex flex-col items-center py-1 rounded-xl cursor-pointer <?= ($cur_admin_tab === 'admins' ? $act_bot : $inact_bot); ?>">
                    <i class="fa-solid fa-user-shield text-base mb-0.5"></i>
                    <span class="text-[8px] font-bold">Workers</span>
                </button>
            </div>
        </nav>
    </div>

    <!-- Ultra-Fast Admin SPA Engine -->
    <script>
    function switchAdminTab(tabName, skipState) {
        if (!tabName) tabName = 'overview';
        tabName = String(tabName).toLowerCase().replace('admin-tab-', '').trim();

        // Canonical Tab Alias Map (Prevents blank screen bugs on any tab variation)
        var aliasMap = {
            'dashboard': 'overview',
            'home': 'overview',
            'overview': 'overview',
            'tourney': 'tournaments',
            'tourneys': 'tournaments',
            'tournament': 'tournaments',
            'tournaments': 'tournaments',
            'deposit': 'deposits',
            'deposits': 'deposits',
            'payout': 'payouts',
            'payouts': 'payouts',
            'withdrawals': 'payouts',
            'withdrawal': 'payouts',
            'broadcast': 'notifications',
            'notification': 'notifications',
            'notifications': 'notifications',
            'notif': 'notifications',
            'user': 'users',
            'users': 'users',
            'gamer': 'users',
            'gamers': 'users',
            'admin': 'admins',
            'admins': 'admins',
            'manage_admins': 'admins',
            'setting': 'settings',
            'settings': 'settings'
        };

        if (aliasMap[tabName]) {
            tabName = aliasMap[tabName];
        }

        var views = document.querySelectorAll('.admin-spa-view');
        if (views.length === 0) return;

        var target = document.getElementById('admin-tab-' + tabName);
        if (!target) {
            tabName = 'overview';
            target = document.getElementById('admin-tab-overview');
        }

        if (!target) return;

        // 1. Hide all views
        views.forEach(function(el) {
            el.classList.add('hidden');
        });

        // 2. Unhide target
        target.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'instant' });

        // 3. Highlight Top Capsule Nav
        var actPillClasses = ['bg-yellow-400', 'text-slate-950', 'border-yellow-400', 'font-black', 'shadow-xs'];
        var inactPillClasses = ['text-slate-400', 'hover:text-white', 'font-bold', 'bg-slate-900', 'border-slate-800'];
        
        document.querySelectorAll('.admin-tab-btn').forEach(function(btn) {
            actPillClasses.forEach(function(c) { btn.classList.remove(c); });
            inactPillClasses.forEach(function(c) { btn.classList.add(c); });
        });

        var activeTop = document.getElementById('admin-nav-' + tabName);
        if (activeTop) {
            inactPillClasses.forEach(function(c) { activeTop.classList.remove(c); });
            actPillClasses.forEach(function(c) { activeTop.classList.add(c); });
            try {
                activeTop.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            } catch(e) {}
        }

        // 4. Highlight Bottom Navigation Icons
        document.querySelectorAll('.admin-bottom-tab').forEach(function(btn) {
            btn.classList.remove('text-yellow-500', 'font-black');
            btn.classList.add('text-slate-400');
        });

        var activeBottom = document.getElementById('admin-bottom-' + tabName);
        if (activeBottom) {
            activeBottom.classList.remove('text-slate-400');
            activeBottom.classList.add('text-yellow-500', 'font-black');
        }

        // 5. Update URL query without page reload
        if (!skipState && window.history && window.history.replaceState) {
            window.history.replaceState({ adminTab: tabName }, '', 'index.php?tab=' + encodeURIComponent(tabName));
        }

        // 6. Persist active tab and role across reloads / APK restarts
        try {
            localStorage.setItem('beastz_active_admin_tab', tabName);
            localStorage.setItem('beastz_active_panel', 'admin');
        } catch(e) {}
    }

    // Browser back/forward button support
    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.adminTab) {
            switchAdminTab(e.state.adminTab, true);
        }
    });

    // Auto-restore requested or last active tab on page load/refresh
    (function() {
        var params = new URLSearchParams(window.location.search);
        var reqTab = params.get('tab');
        if (!reqTab) {
            try {
                reqTab = localStorage.getItem('beastz_active_admin_tab');
            } catch(e) {}
        }
        if (reqTab) {
            switchAdminTab(reqTab, true);
        } else {
            switchAdminTab('overview', true);
        }
    })();
    </script>

    <!-- ONESIGNAL TARGETED PUSH LINKING -->
    <?php 
    // Player ID link for Push Notifications
    $os_user_id = $_SESSION['user_id'] ?? ''; 
    if (!empty($os_user_id)): 
    ?>
    <script>
        window.OneSignal = window.OneSignal || [];
        OneSignal.push(function() {
            OneSignal.setExternalUserId("<?= $os_user_id ?>");
        });
    </script>
    <?php endif; ?>
</body>
</html>
