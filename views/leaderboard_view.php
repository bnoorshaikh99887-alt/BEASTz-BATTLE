<div class="mb-4 text-center">
    <h2 class="text-base font-extrabold text-slate-900 font-display">Hall of Fame</h2>
    <p class="text-[11px] text-slate-500">Top earning champions on BEASTzBATTLE</p>
</div>

<!-- Top 3 Podium (White + Gray + Yellow Theme) -->
<div class="bg-white border border-slate-200 rounded-3xl p-5 mb-4 shadow-sm relative overflow-hidden">
    <div class="flex items-end justify-center gap-4 pt-2 pb-3">
        
        <!-- #2 Second Place (Left) -->
        <?php 
            $p2 = $players[1]; 
            $p2_avatar = get_avatar_url($p2['avatar'] ?? '', $p2['username']);
        ?>
        <div class="flex flex-col items-center">
            <div class="relative mb-2">
                <img src="<?= htmlspecialchars($p2_avatar); ?>" alt="P2" class="w-14 h-14 rounded-full object-cover border-3 border-slate-300 shadow bg-slate-50">
                <span class="absolute -bottom-1 -right-1 w-5 h-5 bg-slate-200 text-slate-800 text-[10px] font-black rounded-full flex items-center justify-center border-2 border-white shadow-xs">
                    2
                </span>
            </div>
            <span class="text-xs font-bold text-slate-900 truncate max-w-[85px]"><?= htmlspecialchars($p2['username']); ?></span>
            <span class="text-[11px] font-mono font-black text-emerald-600 block mt-0.5">
                <?= format_compact_inr($p2['wallet_balance']); ?>
            </span>
        </div>

        <!-- #1 First Place (Center - Elevated with Yellow Gold Ring) -->
        <?php 
            $p1 = $players[0]; 
            $p1_avatar = get_avatar_url($p1['avatar'] ?? '', $p1['username']);
        ?>
        <div class="flex flex-col items-center -translate-y-2">
            <div class="relative mb-2">
                <div class="w-6 h-6 mx-auto text-yellow-500 text-base mb-0.5">
                    <i class="fa-solid fa-crown"></i>
                </div>
                <img src="<?= htmlspecialchars($p1_avatar); ?>" alt="P1" class="w-18 h-18 rounded-full object-cover border-4 border-yellow-400 shadow-md bg-yellow-50">
                <span class="absolute -bottom-1 -right-1 w-6 h-6 bg-yellow-400 text-slate-950 text-xs font-black rounded-full flex items-center justify-center border-2 border-white shadow">
                    1
                </span>
            </div>
            <span class="text-sm font-black text-slate-900 truncate max-w-[100px]"><?= htmlspecialchars($p1['username']); ?></span>
            <span class="text-xs font-mono font-black text-yellow-600 block mt-0.5">
                <?= format_compact_inr($p1['wallet_balance']); ?>
            </span>
        </div>

        <!-- #3 Third Place (Right) -->
        <?php 
            $p3 = $players[2]; 
            $p3_avatar = get_avatar_url($p3['avatar'] ?? '', $p3['username']);
        ?>
        <div class="flex flex-col items-center">
            <div class="relative mb-2">
                <img src="<?= htmlspecialchars($p3_avatar); ?>" alt="P3" class="w-14 h-14 rounded-full object-cover border-3 border-amber-600/60 shadow bg-slate-50">
                <span class="absolute -bottom-1 -right-1 w-5 h-5 bg-amber-600 text-white text-[10px] font-black rounded-full flex items-center justify-center border-2 border-white shadow-xs">
                    3
                </span>
            </div>
            <span class="text-xs font-bold text-slate-900 truncate max-w-[85px]"><?= htmlspecialchars($p3['username']); ?></span>
            <span class="text-[11px] font-mono font-black text-emerald-600 block mt-0.5">
                <?= format_compact_inr($p3['wallet_balance']); ?>
            </span>
        </div>

    </div>
</div>

<!-- Leaderboard Ranking List -->
<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs mb-20">
    <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex justify-between text-[11px] font-bold text-slate-400 uppercase tracking-wider">
        <span>Gamer</span>
        <span>Wallet Balance</span>
    </div>
    
    <div class="divide-y divide-slate-100">
        <?php foreach ($players as $idx => $p): ?>
            <?php 
                $rank = $idx + 1;
                $p_avatar = get_avatar_url($p['avatar'] ?? '', $p['username']);
                $is_me = ((int)$p['id'] === $curr_uid);
            ?>
            <div class="px-4 py-3 flex items-center justify-between <?= $is_me ? 'bg-yellow-50/50' : 'hover:bg-slate-50/80' ?> transition">
                <div class="flex items-center gap-3">
                    <span class="w-6 text-center text-xs font-mono font-black <?= ($rank <= 3) ? 'text-yellow-600 font-extrabold' : 'text-slate-400'; ?>">
                        #<?= $rank; ?>
                    </span>
                    <img src="<?= htmlspecialchars($p_avatar); ?>" alt="Av" class="w-9 h-9 rounded-full object-cover border border-slate-200 bg-slate-50">
                    <div>
                        <span class="text-xs font-bold text-slate-900 block leading-tight flex items-center gap-1.5">
                            <?= htmlspecialchars($p['username']); ?>
                            <?php if ($is_me): ?>
                                <span class="text-[9px] bg-yellow-400 text-black px-1.5 py-0.2 rounded font-black">YOU</span>
                            <?php endif; ?>
                        </span>
                        <span class="text-[10px] text-slate-400">Gamer #<?= $p['id']; ?></span>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-xs font-mono font-black text-slate-900 block">
                        <?= format_compact_inr($p['wallet_balance']); ?>
                    </span>
                    <span class="text-[9px] font-bold text-slate-400 uppercase">Balance</span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Sticky Bottom Bar for Current User Ranking -->
<div class="fixed bottom-14 left-1/2 -translate-x-1/2 w-full max-w-md bg-white/95 backdrop-blur-md border-t border-slate-200 px-4 py-2.5 z-40 shadow-lg flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-yellow-400 text-slate-950 font-black flex items-center justify-center text-xs shadow-xs">
            <?= strtoupper(substr($my_name, 0, 1)); ?>
        </div>
        <div>
            <span class="text-xs font-extrabold text-slate-900 block leading-tight">
                <?= htmlspecialchars($my_name); ?> (You)
            </span>
            <span class="text-xs font-extrabold text-emerald-600 font-mono">
                <?= format_compact_inr($my_earnings); ?>
            </span>
        </div>
    </div>
    <div class="flex items-center gap-1.5 bg-yellow-50 border border-yellow-300 px-3 py-1 rounded-xl">
        <span class="text-[10px] text-yellow-800 font-bold uppercase">Your Rank</span>
        <span class="text-sm font-black text-slate-900 font-mono">#<?= $my_rank; ?></span>
    </div>
</div>