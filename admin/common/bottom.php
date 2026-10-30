</main>
        <!-- Admin Fixed Bottom Navigation (6 Tabs - Ultra-Fast Instant SPA) -->
        <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-md bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 py-2 px-1.5 z-50 shadow-lg">
            <div class="grid grid-cols-6 text-center gap-1">
                <button type="button" onclick="switchAdminTab('overview')" id="admin-bottom-overview" class="admin-bottom-tab flex flex-col items-center py-1 rounded-xl text-yellow-500 font-black cursor-pointer">
                    <i class="fa-solid fa-gauge text-base mb-0.5"></i>
                    <span class="text-[9px] font-bold">Overview</span>
                </button>
                <button type="button" onclick="switchAdminTab('tournaments')" id="admin-bottom-tournaments" class="admin-bottom-tab flex flex-col items-center py-1 rounded-xl text-slate-400 hover:text-slate-200 cursor-pointer">
                    <i class="fa-solid fa-trophy text-base mb-0.5"></i>
                    <span class="text-[9px] font-bold">Tourneys</span>
                </button>
                <button type="button" onclick="switchAdminTab('deposits')" id="admin-bottom-deposits" class="admin-bottom-tab flex flex-col items-center py-1 rounded-xl text-slate-400 hover:text-slate-200 cursor-pointer">
                    <i class="fa-solid fa-hand-holding-dollar text-base mb-0.5"></i>
                    <span class="text-[9px] font-bold">Deposits</span>
                </button>
                <button type="button" onclick="switchAdminTab('payouts')" id="admin-bottom-payouts" class="admin-bottom-tab flex flex-col items-center py-1 rounded-xl text-slate-400 hover:text-slate-200 cursor-pointer">
                    <i class="fa-solid fa-money-bill-transfer text-base mb-0.5"></i>
                    <span class="text-[9px] font-bold">Payouts</span>
                </button>
                <button type="button" onclick="switchAdminTab('notifications')" id="admin-bottom-notifications" class="admin-bottom-tab flex flex-col items-center py-1 rounded-xl text-slate-400 hover:text-slate-200 cursor-pointer">
                    <i class="fa-solid fa-bell text-base mb-0.5"></i>
                    <span class="text-[9px] font-bold">Push</span>
                </button>
                <button type="button" onclick="switchAdminTab('settings')" id="admin-bottom-settings" class="admin-bottom-tab flex flex-col items-center py-1 rounded-xl text-slate-400 hover:text-slate-200 cursor-pointer">
                    <i class="fa-solid fa-sliders text-base mb-0.5"></i>
                    <span class="text-[9px] font-bold">Settings</span>
                </button>
            </div>
        </nav>
    </div>

    <!-- Ultra-Fast Admin SPA Engine -->
    <script>
    function switchAdminTab(tabName, skipState) {
        var views = document.querySelectorAll('.admin-spa-view');
        if (views.length === 0) return;

        views.forEach(function(el) {
            el.classList.add('hidden');
        });

        var target = document.getElementById('admin-tab-' + tabName);
        if (target) {
            target.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'instant' });
        }

        // Top capsule tabs
        document.querySelectorAll('.admin-tab-btn').forEach(function(btn) {
            btn.classList.remove('bg-yellow-400', 'text-slate-950', 'border-yellow-400', 'font-black', 'shadow-xs');
            btn.classList.add('text-slate-400', 'bg-slate-900', 'border-slate-800');
        });
        var activeTop = document.getElementById('admin-nav-' + tabName);
        if (activeTop) {
            activeTop.classList.remove('text-slate-400', 'bg-slate-900', 'border-slate-800');
            activeTop.classList.add('bg-yellow-400', 'text-slate-950', 'border-yellow-400', 'font-black', 'shadow-xs');
        }

        // Bottom icons
        document.querySelectorAll('.admin-bottom-tab').forEach(function(btn) {
            btn.classList.remove('text-yellow-500', 'font-black');
            btn.classList.add('text-slate-400');
        });
        var activeBottom = document.getElementById('admin-bottom-' + tabName);
        if (activeBottom) {
            activeBottom.classList.remove('text-slate-400');
            activeBottom.classList.add('text-yellow-500', 'font-black');
        }

        // Update URL hash/query without reload
        if (!skipState && window.history && window.history.replaceState) {
            window.history.replaceState({ adminTab: tabName }, '', 'index.php?tab=' + tabName);
        }
    }

    // Auto-activate requested tab from URL
    (function() {
        var params = new URLSearchParams(window.location.search);
        var reqTab = params.get('tab');
        if (reqTab) {
            switchAdminTab(reqTab, true);
        }
    })();
    </script>
</body>
</html>