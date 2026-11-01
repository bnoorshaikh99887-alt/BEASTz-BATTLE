</main>

        <!-- Fixed Bottom App Navigation -->
        <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-md bg-white/95 dark:bg-[#0b0f19]/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800/80 py-2 px-3 z-50 shadow-lg">
            <div class="grid grid-cols-4 gap-1.5 text-center">
                <button type="button" onclick="switchAppTab('home')" id="nav-tab-home" class="nav-app-tab active-tab flex flex-col items-center py-1.5 px-1 rounded-2xl transition-all duration-200 cursor-pointer text-yellow-500">
                    <i class="fa-solid fa-house text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold tracking-tight">Home</span>
                </button>
                <button type="button" onclick="switchAppTab('matches')" id="nav-tab-matches" class="nav-app-tab flex flex-col items-center py-1.5 px-1 rounded-2xl transition-all duration-200 cursor-pointer text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-trophy text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold tracking-tight">My Matches</span>
                </button>
                <button type="button" onclick="switchAppTab('store')" id="nav-tab-store" class="nav-app-tab flex flex-col items-center py-1.5 px-1 rounded-2xl transition-all duration-200 cursor-pointer text-slate-400 hover:text-yellow-500 relative">
                    <i class="fa-solid fa-gift text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold tracking-tight">Beastz Store</span>
                    <span class="absolute top-1 right-2 w-1.5 h-1.5 rounded-full bg-yellow-400 animate-pulse"></span>
                </button>
                <button type="button" onclick="switchAppTab('leaderboard')" id="nav-tab-leaderboard" class="nav-app-tab flex flex-col items-center py-1.5 px-1 rounded-2xl transition-all duration-200 cursor-pointer text-slate-400 hover:text-slate-600">
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
                fetch(save_player_id.php, {
                    method: POST,
                    headers: { Content-Type: application/x-www-form-urlencoded },
                    body: player_id= + encodeURIComponent(playerId)
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
        if (window.BEASTzNative && typeof window.BEASTzNative.getPlayerId === function) {
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
            if (typeof OneSignal.getUserId === function) {
                OneSignal.getUserId(function(userId) {
                    if (userId) {
                        syncPlayerIdToServer(userId);
                    }
                });
            }
        });
    </script></body></html>