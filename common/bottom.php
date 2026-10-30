</main>

        <!-- Fixed Bottom App Navigation (Exact 4 Tabs - Transparent Shadow Fog Box Glow) -->
        <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-md bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800/80 py-2 px-3 z-50 shadow-lg">
            <div class="grid grid-cols-4 gap-1.5 text-center">
                <!-- 1. Home -->
                <button type="button" onclick="switchAppTab('home')" id="nav-tab-home" class="nav-app-tab active-tab flex flex-col items-center py-1.5 px-1 rounded-2xl transition-all duration-200 cursor-pointer text-yellow-500">
                    <i class="fa-solid fa-house text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold tracking-tight">Home</span>
                </button>

                <!-- 2. My Matches -->
                <button type="button" onclick="switchAppTab('matches')" id="nav-tab-matches" class="nav-app-tab flex flex-col items-center py-1.5 px-1 rounded-2xl transition-all duration-200 cursor-pointer text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-trophy text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold tracking-tight">My Matches</span>
                </button>

                <!-- 3. Wallet / Store -->
                <button type="button" onclick="switchAppTab('wallet')" id="nav-tab-wallet" class="nav-app-tab flex flex-col items-center py-1.5 px-1 rounded-2xl transition-all duration-200 cursor-pointer text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-wallet text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold tracking-tight">Wallet</span>
                </button>

                <!-- 4. Leaderboard -->
                <button type="button" onclick="switchAppTab('leaderboard')" id="nav-tab-leaderboard" class="nav-app-tab flex flex-col items-center py-1.5 px-1 rounded-2xl transition-all duration-200 cursor-pointer text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-chart-simple text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold tracking-tight">Leaderboard</span>
                </button>
            </div>
        </nav>
    </div>

    <!-- Security, Native Protection & PWA App Engine -->
    <script>
        document.addEventListener('contextmenu', function(e) { e.preventDefault(); return false; });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'C' || e.key === 'J')) || (e.ctrlKey && (e.key === 'U' || e.key === 'S'))) {
                if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    return false;
                }
            }
        });

        (function() {
            var params = new URLSearchParams(window.location.search);
            var reqTab = params.get('tab');
            if (reqTab) {
                switchAppTab(reqTab, true);
            }
        })();
    </script>
</body>
</html>
