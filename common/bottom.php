</main>

        <!-- Fixed Bottom App Navigation (Exact 4 Tabs Matching Video) -->
        <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-md bg-white/95 backdrop-blur-md border-t border-slate-200 py-1.5 px-2 z-50 shadow-lg">
            <div class="grid grid-cols-4 text-center">
                <?php
                $active_page = basename($_SERVER['PHP_SELF'] ?? '');
                ?>

                <!-- 1. Home -->
                <a href="index.php" class="flex flex-col items-center py-1 transition <?= ($active_page === 'index.php') ? 'text-yellow-500' : 'text-slate-400 hover:text-slate-600' ?>">
                    <div class="relative">
                        <i class="fa-solid fa-house text-lg mb-0.5"></i>
                        <?php if ($active_page === 'index.php'): ?>
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-yellow-400 rounded-full"></span>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] font-bold tracking-tight">Home</span>
                </a>

                <!-- 2. My Matches -->
                <a href="my_tournaments.php" class="flex flex-col items-center py-1 transition <?= ($active_page === 'my_tournaments.php') ? 'text-yellow-500' : 'text-slate-400 hover:text-slate-600' ?>">
                    <div class="relative">
                        <i class="fa-solid fa-trophy text-lg mb-0.5"></i>
                        <?php if ($active_page === 'my_tournaments.php'): ?>
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-yellow-400 rounded-full"></span>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] font-bold tracking-tight">My Matches</span>
                </a>

                <!-- 3. Wallet / Store -->
                <a href="wallet.php" class="flex flex-col items-center py-1 transition <?= ($active_page === 'wallet.php') ? 'text-yellow-500' : 'text-slate-400 hover:text-slate-600' ?>">
                    <div class="relative">
                        <i class="fa-solid fa-wallet text-lg mb-0.5"></i>
                        <?php if ($active_page === 'wallet.php'): ?>
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-yellow-400 rounded-full"></span>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] font-bold tracking-tight">Wallet</span>
                </a>

                <!-- 4. Leaderboard -->
                <a href="leaderboard.php" class="flex flex-col items-center py-1 transition <?= ($active_page === 'leaderboard.php') ? 'text-yellow-500' : 'text-slate-400 hover:text-slate-600' ?>">
                    <div class="relative">
                        <i class="fa-solid fa-chart-simple text-lg mb-0.5"></i>
                        <?php if ($active_page === 'leaderboard.php'): ?>
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-yellow-400 rounded-full"></span>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] font-bold tracking-tight">Leaderboard</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Security & Native Mobile App Protection JS -->
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
    </script>
</body>
</html>
