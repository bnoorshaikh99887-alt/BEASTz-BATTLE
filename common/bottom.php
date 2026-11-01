</main>
        <!-- Fixed Bottom App Navigation with Dynamic Yellow Glow -->
        <?php $cur_app_tab = $current_active_tab ?? ($_GET["tab"] ?? "home"); ?>
        <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-md bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800/80 py-2 px-3 z-50 shadow-lg">
            <div class="grid grid-cols-4 gap-1.5 text-center">
                <button type="button" onclick="switchAppTab('home')" id="nav-tab-home" class="nav-app-tab <?= ($cur_app_tab === 'home' ? 'active-tab text-yellow-500' : 'text-slate-400 hover:text-slate-600') ?> flex flex-col items-center py-1.5 px-1 rounded-2xl transition-all duration-200 cursor-pointer">
                    <i class="fa-solid fa-house text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold tracking-tight">Home</span>
                </button>
                <button type="button" onclick="switchAppTab('matches')" id="nav-tab-matches" class="nav-app-tab <?= ($cur_app_tab === 'matches' ? 'active-tab text-yellow-500' : 'text-slate-400 hover:text-slate-600') ?> flex flex-col items-center py-1.5 px-1 rounded-2xl transition-all duration-200 cursor-pointer">
                    <i class="fa-solid fa-trophy text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold tracking-tight">My Matches</span>
                </button>
                <button type="button" onclick="switchAppTab('store')" id="nav-tab-store" class="nav-app-tab <?= ($cur_app_tab === 'store' ? 'active-tab text-yellow-500' : 'text-slate-400 hover:text-yellow-500') ?> flex flex-col items-center py-1.5 px-1 rounded-2xl transition-all duration-200 cursor-pointer relative">
                    <i class="fa-solid fa-gift text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold tracking-tight">Beastz Store</span>
                    <span class="absolute top-1 right-2 w-1.5 h-1.5 rounded-full bg-yellow-400 animate-pulse"></span>
                </button>
                <button type="button" onclick="switchAppTab('leaderboard')" id="nav-tab-leaderboard" class="nav-app-tab <?= ($cur_app_tab === 'leaderboard' ? 'active-tab text-yellow-500' : 'text-slate-400 hover:text-slate-600') ?> flex flex-col items-center py-1.5 px-1 rounded-2xl transition-all duration-200 cursor-pointer">
                    <i class="fa-solid fa-chart-simple text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold tracking-tight">Leaderboard</span>
                </button>
            </div>
        </nav>
    </div>

    <!-- ONESIGNAL DEVICE ID COLLECTOR FOR NATIVE/WEB -->
    <script>
        function syncPlayerIdToServer(playerId) {
            if (!playerId || playerId === "null" || playerId === "undefined") return;
            try {
                localStorage.setItem("beastz_onesignal_player_id", playerId);
                document.cookie = "beastz_player_id=" + encodeURIComponent(playerId) + "; path=/; max-age=31536000";
                fetch("save_player_id.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: "player_id=" + encodeURIComponent(playerId)
                }).then(function(res) { return res.json(); })
                  .then(function(data) {
                      console.log("OneSignal player_id synced:", data);
                  }).catch(function(err) {
                      console.error("Token sync error:", err);
                  });
            } catch(e) {
                console.error(e);
            }
        }

        // 1. Native Android JavascriptInterface Bridge
        if (window.BEASTzNative && typeof window.BEASTzNative.getPlayerId === "function") {
            var nativeId = window.BEASTzNative.getPlayerId();
            if (nativeId) {
                window.BEASTzPlayerId = nativeId;
                syncPlayerIdToServer(nativeId);
            }
        }

        // 2. Global callback for Native Android WebView evaluation
        window.onNativePlayerIdReady = function(id) {
            window.BEASTzPlayerId = id;
            syncPlayerIdToServer(id);
        };

        // 3. OneSignal Web SDK Fallback
        window.OneSignal = window.OneSignal || [];
        OneSignal.push(function() {
            if (typeof OneSignal.getUserId === "function") {
                OneSignal.getUserId(function(userId) {
                    if (userId) {
                        syncPlayerIdToServer(userId);
                    }
                });
            }
        });

        // 4. Stored token sync retry
        try {
            var cachedId = localStorage.getItem("beastz_onesignal_player_id");
            if (cachedId) {
                syncPlayerIdToServer(cachedId);
            }
        } catch(e) {}
    </script>
</body>
</html>
