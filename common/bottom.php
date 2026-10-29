</main>

        <!-- Fixed Bottom App Navigation -->
        <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-md bg-slate-900/95 backdrop-blur-md border-t border-slate-800 py-2 px-3 z-50">
            <div class="grid grid-cols-4 text-center">
                <?php
                $active_page = basename($_SERVER['PHP_SELF']);
                ?>
                <!-- Home -->
                <a href="index.php" class="flex flex-col items-center py-1 <?= ($active_page === 'index.php') ? 'text-violet-400' : 'text-slate-400 hover:text-slate-200' ?>">
                    <i class="fa-solid fa-trophy text-lg mb-1"></i>
                    <span class="text-[11px] font-semibold tracking-wide">Tourneys</span>
                </a>

                <!-- My Tournaments -->
                <a href="my_tournaments.php" class="flex flex-col items-center py-1 <?= ($active_page === 'my_tournaments.php') ? 'text-violet-400' : 'text-slate-400 hover:text-slate-200' ?>">
                    <i class="fa-solid fa-gamepad text-lg mb-1"></i>
                    <span class="text-[11px] font-semibold tracking-wide">My Games</span>
                </a>

                <!-- Wallet -->
                <a href="wallet.php" class="flex flex-col items-center py-1 <?= ($active_page === 'wallet.php') ? 'text-violet-400' : 'text-slate-400 hover:text-slate-200' ?>">
                    <i class="fa-solid fa-wallet text-lg mb-1"></i>
                    <span class="text-[11px] font-semibold tracking-wide">Wallet</span>
                </a>

                <!-- Profile -->
                <a href="profile.php" class="flex flex-col items-center py-1 <?= ($active_page === 'profile.php') ? 'text-violet-400' : 'text-slate-400 hover:text-slate-200' ?>">
                    <i class="fa-solid fa-user-gear text-lg mb-1"></i>
                    <span class="text-[11px] font-semibold tracking-wide">Profile</span>
                </a>
            </div>
        </nav>

    </div>

    <!-- Security & Native Mobile App Protection JS -->
    <script>
        // 1. Disable Right Click Context Menu
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });

        // 2. Disable Key Shortcuts (Inspect Element, Save, Select All)
        document.addEventListener('keydown', function(e) {
            if (
                e.key === 'F12' ||
                (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'C' || e.key === 'J')) ||
                (e.ctrlKey && (e.key === 'U' || e.key === 'S' || e.key === 'A'))
            ) {
                if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    return false;
                }
            }
        });

        // 3. Disable Double-Tap Zoom & Pinch Zoom
        document.addEventListener('dblclick', function(e) {
            e.preventDefault();
        }, { passive: false });

        document.addEventListener('touchstart', function(event) {
            if (event.touches.length > 1) {
                event.preventDefault();
            }
        }, { passive: false });

        document.addEventListener('gesturestart', function(e) {
            e.preventDefault();
        });
    </script>
</body>
</html>