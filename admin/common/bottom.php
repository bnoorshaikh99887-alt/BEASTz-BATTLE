</main>

        <!-- Admin Mobile Bottom Bar -->
        <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-md bg-slate-900/95 backdrop-blur-md border-t border-slate-800 py-2 px-1 z-50">
            <div class="grid grid-cols-5 text-center">
                <?php
                $cur = basename($_SERVER['PHP_SELF']);
                ?>
                <a href="index.php" class="flex flex-col items-center py-1 <?= ($cur === 'index.php') ? 'text-violet-400 font-bold' : 'text-slate-400 hover:text-slate-200' ?>">
                    <i class="fa-solid fa-gauge text-sm mb-1"></i>
                    <span class="text-[10px]">Stats</span>
                </a>

                <a href="tournament.php" class="flex flex-col items-center py-1 <?= ($cur === 'tournament.php' || $cur === 'manage_tournament.php') ? 'text-violet-400 font-bold' : 'text-slate-400 hover:text-slate-200' ?>">
                    <i class="fa-solid fa-trophy text-sm mb-1"></i>
                    <span class="text-[10px]">Tourneys</span>
                </a>

                <a href="deposits.php" class="flex flex-col items-center py-1 <?= ($cur === 'deposits.php') ? 'text-violet-400 font-bold' : 'text-slate-400 hover:text-slate-200' ?>">
                    <i class="fa-solid fa-hand-holding-dollar text-sm mb-1"></i>
                    <span class="text-[10px]">Deposits</span>
                </a>

                <a href="withdrawals.php" class="flex flex-col items-center py-1 <?= ($cur === 'withdrawals.php') ? 'text-violet-400 font-bold' : 'text-slate-400 hover:text-slate-200' ?>">
                    <i class="fa-solid fa-money-bill-transfer text-sm mb-1"></i>
                    <span class="text-[10px]">Payouts</span>
                </a>

                <a href="settings.php" class="flex flex-col items-center py-1 <?= ($cur === 'settings.php' || $cur === 'setting.php') ? 'text-violet-400 font-bold' : 'text-slate-400 hover:text-slate-200' ?>">
                    <i class="fa-solid fa-sliders text-sm mb-1"></i>
                    <span class="text-[10px]">Settings</span>
                </a>
            </div>
        </nav>

    </div>
</body>
</html>
