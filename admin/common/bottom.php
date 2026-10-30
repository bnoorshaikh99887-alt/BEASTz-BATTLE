</main>
        <!-- Admin Fixed Bottom Navigation (White + Gray + Yellow) -->
        <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-md bg-white/95 backdrop-blur-md border-t border-slate-200 py-1.5 px-1 z-50 shadow-lg">
            <div class="grid grid-cols-5 text-center">
                <?php
                $cur = basename($_SERVER['PHP_SELF'] ?? '');
                ?>
                <a href="index.php" class="flex flex-col items-center py-1 transition <?= ($cur === 'index.php') ? 'text-yellow-600 font-black' : 'text-slate-400 hover:text-slate-600' ?>">
                    <i class="fa-solid fa-gauge text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold">Overview</span>
                </a>
                <a href="tournament.php" class="flex flex-col items-center py-1 transition <?= ($cur === 'tournament.php' || $cur === 'manage_tournament.php') ? 'text-yellow-600 font-black' : 'text-slate-400 hover:text-slate-600' ?>">
                    <i class="fa-solid fa-trophy text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold">Tourneys</span>
                </a>
                <a href="deposits.php" class="flex flex-col items-center py-1 transition <?= ($cur === 'deposits.php') ? 'text-yellow-600 font-black' : 'text-slate-400 hover:text-slate-600' ?>">
                    <i class="fa-solid fa-hand-holding-dollar text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold">Deposits</span>
                </a>
                <a href="withdrawals.php" class="flex flex-col items-center py-1 transition <?= ($cur === 'withdrawals.php') ? 'text-yellow-600 font-black' : 'text-slate-400 hover:text-slate-600' ?>">
                    <i class="fa-solid fa-money-bill-transfer text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold">Payouts</span>
                </a>
                <a href="settings.php" class="flex flex-col items-center py-1 transition <?= ($cur === 'settings.php') ? 'text-yellow-600 font-black' : 'text-slate-400 hover:text-slate-600' ?>">
                    <i class="fa-solid fa-sliders text-base mb-0.5"></i>
                    <span class="text-[10px] font-bold">Settings</span>
                </a>
            </div>
        </nav>
    </div>
</body>
</html>
