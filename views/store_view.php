<!-- BEASTz STORE COMING SOON VIEW (MATCHING ATTACHED DESIGN) -->
<div class="space-y-6 pt-4 pb-8 max-w-sm mx-auto text-center">
    <!-- Gift Icon Emblem -->
    <div class="relative inline-block mx-auto mt-4">
        <div class="w-24 h-24 rounded-full bg-rose-50 dark:bg-rose-500/10 border-2 border-rose-200 dark:border-rose-500/20 flex items-center justify-center shadow-lg shadow-rose-500/10">
            <i class="fa-solid fa-gift text-4xl text-rose-500"></i>
        </div>
    </div>

    <!-- Title & Subtitle -->
    <div class="space-y-2 px-2">
        <h2 class="text-2xl font-black text-slate-900 dark:text-white font-display tracking-tight">
            Beastz Store <span class="text-blue-400">Coming Soon!</span>
        </h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed font-medium">
            We're building something special for you. Soon you'll be able to redeem points for exciting rewards!
        </p>
    </div>

    <!-- What to Expect Card -->
    <div class="bg-white dark:bg-[#121826] border border-slate-200 dark:border-slate-800 rounded-3xl p-5 text-left shadow-sm space-y-3">
        <h3 class="text-xs font-black uppercase text-blue-400 flex items-center gap-2">
            <i class="fa-regular fa-star text-sm"></i>
            <span>What to Expect</span>
        </h3>
        <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300 font-medium">
            <li class="flex items-start gap-2.5">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mt-1.5 flex-shrink-0"></span>
                <span>Exclusive gaming merchandise and collectibles</span>
            </li>
            <li class="flex items-start gap-2.5">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mt-1.5 flex-shrink-0"></span>
                <span>Gift cards and vouchers for popular gaming platforms (Play Store, Steam, UC & Diamonds)</span>
            </li>
            <li class="flex items-start gap-2.5">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mt-1.5 flex-shrink-0"></span>
                <span>Tournament tickets and bonuses for premium events</span>
            </li>
            <li class="flex items-start gap-2.5">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mt-1.5 flex-shrink-0"></span>
                <span>Special limited-time offers for in-game skins & weapon crates</span>
            </li>
        </ul>
    </div>

    <!-- Notify Button -->
    <div class="pt-2 px-2">
        <button type="button" onclick="notifyMeStore()" id="notifyStoreBtn" class="w-full py-3.5 px-6 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-blue-600 hover:text-slate-950 text-slate-700 dark:text-slate-200 font-extrabold text-xs transition-all duration-200 border border-slate-300 dark:border-slate-700 flex items-center justify-center gap-2 shadow-sm cursor-pointer active:scale-95">
            <i class="fa-regular fa-bell text-sm"></i>
            <span>Notify Me When Available</span>
        </button>
    </div>
</div>

<script>
function notifyMeStore() {
    var btn = document.getElementById('notifyStoreBtn');
    btn.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-500"></i> <span class="text-emerald-500 font-black">You are on the Priority List!</span>';
    btn.classList.add('border-emerald-500/40', 'bg-emerald-500/10');
    if (typeof showFastToast === 'function') {
        showFastToast('🔔 You will receive a push alert as soon as Beastz Store opens!', 'success');
    }
}
</script>
