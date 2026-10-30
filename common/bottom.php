</main>

        <!-- Fixed Bottom App Navigation (Exact 4 Tabs - Ultra Fast 0ms Client-Side Switching) -->
        <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-md bg-white/95 backdrop-blur-md border-t border-slate-200 py-1.5 px-2 z-50 shadow-lg">
            <div class="grid grid-cols-4 text-center">
                <!-- 1. Home -->
                <button type="button" onclick="switchAppTab('home')" id="nav-tab-home" class="nav-app-tab flex flex-col items-center py-1 transition text-yellow-500 cursor-pointer">
                    <div class="relative">
                        <i class="fa-solid fa-house text-lg mb-0.5"></i>
                        <span class="nav-indicator absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-yellow-400 rounded-full"></span>
                    </div>
                    <span class="text-[10px] font-bold tracking-tight">Home</span>
                </button>

                <!-- 2. My Matches -->
                <button type="button" onclick="switchAppTab('matches')" id="nav-tab-matches" class="nav-app-tab flex flex-col items-center py-1 transition text-slate-400 hover:text-slate-600 cursor-pointer">
                    <div class="relative">
                        <i class="fa-solid fa-trophy text-lg mb-0.5"></i>
                        <span class="nav-indicator hidden absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-yellow-400 rounded-full"></span>
                    </div>
                    <span class="text-[10px] font-bold tracking-tight">My Matches</span>
                </button>

                <!-- 3. Wallet / Store -->
                <button type="button" onclick="switchAppTab('wallet')" id="nav-tab-wallet" class="nav-app-tab flex flex-col items-center py-1 transition text-slate-400 hover:text-slate-600 cursor-pointer">
                    <div class="relative">
                        <i class="fa-solid fa-wallet text-lg mb-0.5"></i>
                        <span class="nav-indicator hidden absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-yellow-400 rounded-full"></span>
                    </div>
                    <span class="text-[10px] font-bold tracking-tight">Wallet</span>
                </button>

                <!-- 4. Leaderboard -->
                <button type="button" onclick="switchAppTab('leaderboard')" id="nav-tab-leaderboard" class="nav-app-tab flex flex-col items-center py-1 transition text-slate-400 hover:text-slate-600 cursor-pointer">
                    <div class="relative">
                        <i class="fa-solid fa-chart-simple text-lg mb-0.5"></i>
                        <span class="nav-indicator hidden absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-yellow-400 rounded-full"></span>
                    </div>
                    <span class="text-[10px] font-bold tracking-tight">Leaderboard</span>
                </button>
            </div>
        </nav>
    </div>

    <!-- Security, Native Protection & PWA App Engine -->
    <script>
        // Disable Right Click Context Menu
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });

        // Disable Dev Tools Key Shortcuts
        document.addEventListener('keydown', function(e) {
            if (
                e.key === 'F12' ||
                (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'C' || e.key === 'J')) ||
                (e.ctrlKey && (e.key === 'U' || e.key === 'S'))
            ) {
                if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    return false;
                }
            }
        });

        // Initialize active tab from query string if given
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
