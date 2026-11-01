/**
 * BEASTz BATTLE - Unified Theme, Tab Switcher & Live Polling Sync Engine
 * Production grade real-time updates for Point Table, Wallet Balance, Payouts List
 */

// ============================================================
// THEME CONTROLLER (Dark / Light)
// ============================================================
function getSavedTheme() {
    try {
        return localStorage.getItem('beastz_theme') || 'light';
    } catch(e) {
        return 'light';
    }
}

function setTheme(theme) {
    var root = document.documentElement;
    if (theme === 'dark') {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }
    try {
        localStorage.setItem('beastz_theme', theme);
    } catch(e) {}
    updateThemeUI(theme);
}

function toggleTheme() {
    var isDark = document.documentElement.classList.contains('dark');
    setTheme(isDark ? 'light' : 'dark');
}

function updateThemeUI(theme) {
    if (!theme) theme = getSavedTheme();
    var icons = document.querySelectorAll('.beastz-theme-icon');
    icons.forEach(function(icon) {
        if (theme === 'dark') {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    });
}

// Initial theme apply
(function() {
    var t = getSavedTheme();
    if (t === 'dark') {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
})();

// ============================================================
// ESCAPE HTML HELPER
// ============================================================
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ============================================================
// USER PANEL SPA TAB SWITCHER
// ============================================================
function switchAppTab(tabName, skipState) {
    if (!tabName) tabName = 'home';
    tabName = String(tabName).toLowerCase().replace('spa-tab-', '').trim();

    var aliasMap = {
        'matches': 'home',
        'tournaments': 'home',
        'tourneys': 'home',
        'leaderboards': 'leaderboard',
        'ranks': 'leaderboard',
        'points': 'leaderboard',
        'point_table': 'leaderboard',
        'wallets': 'wallet',
        'passbook': 'wallet',
        'profiles': 'profile',
        'account': 'profile',
        'shops': 'store',
        'shop': 'store'
    };
    if (aliasMap[tabName]) tabName = aliasMap[tabName];

    var views = document.querySelectorAll('.spa-tab-view');
    var target = document.getElementById('spa-tab-' + tabName);
    if (!target) {
        tabName = 'home';
        target = document.getElementById('spa-tab-home');
    }
    if (!target) return;

    views.forEach(function(el) { el.classList.add('hidden'); });
    target.classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'instant' });

    // Update bottom nav bar
    document.querySelectorAll('.bottom-nav-item').forEach(function(btn) {
        btn.classList.remove('text-yellow-500', 'font-black');
        btn.classList.add('text-slate-400');
    });
    var activeBottom = document.getElementById('nav-' + tabName);
    if (activeBottom) {
        activeBottom.classList.remove('text-slate-400');
        activeBottom.classList.add('text-yellow-500', 'font-black');
    }

    if (!skipState && window.history && window.history.replaceState) {
        window.history.replaceState({ tab: tabName }, '', 'index.php?tab=' + encodeURIComponent(tabName));
    }
    try {
        localStorage.setItem('beastz_active_user_tab', tabName);
        localStorage.setItem('beastz_active_panel', 'user');
    } catch(e) {}
}

// ============================================================
// ADMIN CONTROL CENTER: 100% RELIABLE SPA TAB SWITCHER
// ============================================================
function switchAdminTab(tabName, skipState) {
    if (!tabName) tabName = 'overview';
    tabName = String(tabName).toLowerCase().replace('admin-tab-', '').trim();

    // Canonical Tab Alias Map
    var aliasMap = {
        'dashboard': 'overview',
        'home': 'overview',
        'overview': 'overview',
        'tourney': 'tournaments',
        'tourneys': 'tournaments',
        'tournament': 'tournaments',
        'tournaments': 'tournaments',
        'deposit': 'deposits',
        'deposits': 'deposits',
        'payout': 'payouts',
        'payouts': 'payouts',
        'withdrawals': 'payouts',
        'withdrawal': 'payouts',
        'broadcast': 'notifications',
        'notification': 'notifications',
        'notifications': 'notifications',
        'notif': 'notifications',
        'user': 'users',
        'users': 'users',
        'gamer': 'users',
        'gamers': 'users',
        'admin': 'admins',
        'admins': 'admins',
        'manage_admins': 'admins',
        'setting': 'settings',
        'settings': 'settings'
    };

    if (aliasMap[tabName]) {
        tabName = aliasMap[tabName];
    }

    var views = document.querySelectorAll('.admin-spa-view');
    var target = document.getElementById('admin-tab-' + tabName);

    // If SPA views do not exist on current page (e.g. from user page)
    if (views.length === 0) {
        var adminPath = window.location.pathname.indexOf('/admin/') !== -1 ? 'index.php' : 'admin/index.php';
        window.location.href = adminPath + '?tab=' + encodeURIComponent(tabName);
        return;
    }

    // Fallback if requested tab does not exist
    if (!target) {
        tabName = 'overview';
        target = document.getElementById('admin-tab-overview');
    }
    if (!target) return;

    // Hide all views & show requested tab
    views.forEach(function(el) { el.classList.add('hidden'); });
    target.classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'instant' });

    // Top Navigation Pill Highlight
    var actPills = ['bg-yellow-400', 'text-slate-950', 'border-yellow-400', 'font-black', 'shadow-xs'];
    var inactPills = ['text-slate-400', 'hover:text-white', 'font-bold', 'bg-slate-900', 'border-slate-800'];

    document.querySelectorAll('.admin-tab-btn').forEach(function(btn) {
        actPills.forEach(function(c) { btn.classList.remove(c); });
        inactPills.forEach(function(c) { btn.classList.add(c); });
    });

    var activeTopBtn = document.getElementById('admin-nav-' + tabName);
    if (activeTopBtn) {
        inactPills.forEach(function(c) { activeTopBtn.classList.remove(c); });
        actPills.forEach(function(c) { activeTopBtn.classList.add(c); });
        try { activeTopBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' }); } catch(e) {}
    }

    // Bottom Navigation Icon Highlight
    document.querySelectorAll('.admin-bottom-tab').forEach(function(btn) {
        btn.classList.remove('text-yellow-500', 'font-black');
        btn.classList.add('text-slate-400');
    });

    var activeBottomBtn = document.getElementById('admin-bottom-' + tabName);
    if (activeBottomBtn) {
        activeBottomBtn.classList.remove('text-slate-400');
        activeBottomBtn.classList.add('text-yellow-500', 'font-black');
    }

    // Update URL query state without reload
    if (!skipState && window.history && window.history.replaceState) {
        var newUrl = 'index.php?tab=' + encodeURIComponent(tabName);
        window.history.replaceState({ adminTab: tabName }, '', newUrl);
    }
    try {
        localStorage.setItem('beastz_active_admin_tab', tabName);
        localStorage.setItem('beastz_active_panel', 'admin');
    } catch(e) {}
}

// Modal Dialog Helpers
function openModal(modalId) {
    var modal = document.getElementById(modalId);
    if(modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}
function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if(modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Browser Navigation State Sync (Back/Forward Buttons)
window.addEventListener('popstate', function(e) {
    if (e.state && e.state.adminTab) {
        switchAdminTab(e.state.adminTab, true);
    } else if (e.state && e.state.tab) {
        switchAppTab(e.state.tab, true);
    }
});

// Auto-activate tabs on document load
function initPageTabs() {
    updateThemeUI();
    var params = new URLSearchParams(window.location.search);
    var hashTab = window.location.hash ? window.location.hash.replace('#', '') : null;
    var reqTab = params.get('tab') || hashTab;

    // 1. Admin Panel Check
    var adminViews = document.querySelectorAll('.admin-spa-view');
    if (adminViews.length > 0) {
        try { localStorage.setItem('beastz_active_panel', 'admin'); } catch(e) {}
        var actTab = reqTab || localStorage.getItem('beastz_active_admin_tab') || 'overview';
        switchAdminTab(actTab, true);
        return;
    }

    // 2. User App Check
    var userViews = document.querySelectorAll('.spa-tab-view');
    if (userViews.length > 0) {
        try { localStorage.setItem('beastz_active_panel', 'user'); } catch(e) {}
        var userTab = reqTab || localStorage.getItem('beastz_active_user_tab') || 'home';
        switchAppTab(userTab, true);
    }
}

// ============================================================
// REAL-TIME AUTO-REFRESH & LIVE SYNC ENGINE (Web & APK)
// High-frequency AJAX polling for Point Table, Wallet, Payouts
// ============================================================
var LiveSyncEngine = (function() {
    var timerId = null;
    var pollInterval = 3500; // 3.5 seconds
    var lastWalletBal = null;
    var lastCompletedWithdrawalId = null;
    var isPolling = false;

    function flashElement(el, colorClass) {
        if (!el) return;
        el.classList.add(colorClass || 'text-emerald-500', 'scale-105', 'transition-all');
        setTimeout(function() {
            el.classList.remove(colorClass || 'text-emerald-500', 'scale-105');
        }, 1200);
    }

    function showInAppAlert(message, type) {
        var alertBox = document.createElement('div');
        alertBox.className = 'fixed top-14 left-1/2 -translate-x-1/2 z-50 w-11/12 max-w-sm px-4 py-3 rounded-2xl shadow-xl flex items-center gap-2.5 text-xs font-bold transition-all transform translate-y-0 ' +
            (type === 'success' ? 'bg-emerald-600 text-white' : 'bg-yellow-400 text-slate-950');
        alertBox.innerHTML = '<i class="fa-solid fa-circle-check text-sm"></i><span>' + escapeHtml(message) + '</span>';
        document.body.appendChild(alertBox);
        setTimeout(function() {
            alertBox.style.opacity = '0';
            setTimeout(function() { alertBox.remove(); }, 300);
        }, 4000);
    }

    function syncUI(data) {
        if (!data || !data.success) return;

        // --------------------------------------------------------
        // 1. SYNC USER WALLET BALANCE & PENDING WITHDRAWALS
        // --------------------------------------------------------
        if (data.user) {
            var newBal = data.user.wallet_balance;
            var balChanged = (lastWalletBal !== null && lastWalletBal !== newBal);

            // Update Header Balance
            var headerBal = document.getElementById('header-wallet-balance');
            if (headerBal) {
                if (headerBal.textContent !== data.user.compact_balance) {
                    headerBal.textContent = data.user.compact_balance;
                    if (balChanged) flashElement(headerBal, 'text-yellow-400');
                }
            }

            // Update Wallet View Balances
            var walletComp = document.getElementById('user-wallet-balance-compact');
            if (walletComp) {
                if (walletComp.textContent.trim() !== data.user.compact_balance) {
                    walletComp.textContent = data.user.compact_balance;
                    if (balChanged) flashElement(walletComp, 'text-emerald-600');
                }
            }
            var walletFull = document.getElementById('user-wallet-balance-full');
            if (walletFull) {
                walletFull.textContent = 'Full: ' + data.user.formatted_balance;
            }
            document.querySelectorAll('.user-wallet-balance-val').forEach(function(el) {
                el.textContent = data.user.compact_balance;
            });

            // Update User Pending Withdrawals
            var pendingContainer = document.getElementById('user-pending-withdrawals-container');
            var pendingList = document.getElementById('user-pending-withdrawals-list');
            if (pendingContainer) {
                if (data.user.pending_count === 0) {
                    pendingContainer.classList.add('hidden');
                } else {
                    pendingContainer.classList.remove('hidden');
                    if (pendingList && data.user.pending_withdrawals) {
                        var phtml = '';
                        data.user.pending_withdrawals.forEach(function(pw) {
                            phtml += '<div class="bg-white p-2.5 rounded-xl border border-blue-100 flex items-center justify-between text-xs">' +
                                '<div><span class="font-black text-slate-900 block">' + pw.formatted_amount + '</span>' +
                                '<span class="text-[10px] font-mono text-slate-500">UPI: ' + escapeHtml(pw.upi_id) + '</span></div>' +
                                '<span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Pending Admin Transfer</span>' +
                                '</div>';
                        });
                        pendingList.innerHTML = phtml;
                    }
                }
            }

            // In-App Alert if Admin Just Completed User's Payout
            if (data.user.recent_completed_withdrawal) {
                var rc = data.user.recent_completed_withdrawal;
                if (lastCompletedWithdrawalId !== null && lastCompletedWithdrawalId !== rc.id) {
                    showInAppAlert('Payout of ' + rc.formatted_amount + ' completed! Transferred to ' + rc.upi_id, 'success');
                }
                lastCompletedWithdrawalId = rc.id;
            }

            lastWalletBal = newBal;
        }

        // --------------------------------------------------------
        // 2. SYNC POINT TABLE / LEADERBOARD (LIVE HALL OF FAME)
        // --------------------------------------------------------
        if (data.leaderboard && data.leaderboard.length > 0) {
            var lbListEl = document.getElementById('live-leaderboard-list');
            if (lbListEl) {
                var lbHtml = '';
                data.leaderboard.forEach(function(p) {
                    var isMe = p.is_me;
                    var rankColor = p.rank <= 3 ? 'text-yellow-600 font-extrabold' : 'text-slate-400';
                    lbHtml += '<div class="px-4 py-3 flex items-center justify-between ' + (isMe ? 'bg-yellow-50/50' : 'hover:bg-slate-50/80') + ' transition">' +
                        '<div class="flex items-center gap-3">' +
                            '<span class="w-6 text-center text-xs font-mono font-black ' + rankColor + '">#' + p.rank + '</span>' +
                            '<img src="' + escapeHtml(p.avatar) + '" alt="Av" class="w-9 h-9 rounded-full object-cover border border-slate-200 bg-slate-50">' +
                            '<div><span class="text-xs font-bold text-slate-900 block leading-tight flex items-center gap-1.5">' +
                                escapeHtml(p.username) +
                                (isMe ? ' <span class="text-[9px] bg-yellow-400 text-black px-1.5 py-0.2 rounded font-black">YOU</span>' : '') +
                            '</span><span class="text-[10px] text-slate-400">Gamer #' + p.id + '</span></div>' +
                        '</div>' +
                        '<div class="text-right">' +
                            '<span class="text-xs font-mono font-black text-slate-900 block">' + p.formatted_balance + '</span>' +
                            '<span class="text-[9px] font-bold text-slate-400 uppercase">Balance</span>' +
                        '</div>' +
                    '</div>';
                });
                lbListEl.innerHTML = lbHtml;
            }
        }

        // --------------------------------------------------------
        // 3. SYNC ADMIN CONTROL CENTER (METRICS & LIVE PAYOUTS LIST)
        // --------------------------------------------------------
        if (data.admin) {
            var adm = data.admin;

            var uCntEl = document.getElementById('admin-stat-users-cnt');
            if (uCntEl && uCntEl.textContent.trim() != adm.users_cnt) uCntEl.textContent = adm.users_cnt;

            var tCntEl = document.getElementById('admin-stat-tourneys-cnt');
            if (tCntEl && tCntEl.textContent.trim() != adm.tourneys_cnt) tCntEl.textContent = adm.tourneys_cnt;

            var revEl = document.getElementById('admin-stat-revenue');
            if (revEl && revEl.textContent.trim() != adm.formatted_revenue) revEl.textContent = adm.formatted_revenue;

            var przEl = document.getElementById('admin-stat-prize-dist');
            if (przEl && przEl.textContent.trim() != adm.formatted_prize_dist) przEl.textContent = adm.formatted_prize_dist;

            var pDepEl = document.getElementById('admin-pending-deposits-cnt');
            if (pDepEl) pDepEl.textContent = adm.pending_deposits;

            var pWdrEl = document.getElementById('admin-pending-payouts-cnt');
            if (pWdrEl) pWdrEl.textContent = adm.pending_withdrawals;

            // Live Update Admin Payouts List if on Payouts Tab
            var pCardCont = document.getElementById('admin-payouts-cards-container');
            if (pCardCont && adm.pending_payouts_list) {
                if (adm.pending_payouts_list.length === 0) {
                    pCardCont.innerHTML = '<div class="bg-white border border-slate-200 rounded-2xl p-8 text-center text-slate-400 text-xs font-bold">' +
                        '<i class="fa-solid fa-circle-check text-2xl text-emerald-500 mb-2 block"></i>' +
                        'All payouts have been cleared and approved! No pending requests.' +
                        '</div>';
                } else {
                    var payHtml = '';
                    adm.pending_payouts_list.forEach(function(w) {
                        var isHost = w.is_admin_request === 1;
                        var cardBorder = isHost ? 'border-yellow-400 ring-2 ring-yellow-400/30' : 'border-amber-400/60 ring-1 ring-amber-400/20';
                        payHtml += '<div class="bg-white border ' + cardBorder + ' rounded-2xl p-4 shadow-sm space-y-3 transition">' +
                            '<div class="flex items-start justify-between">' +
                                '<div><div class="flex items-center gap-2 flex-wrap">' +
                                    '<span class="text-sm font-extrabold text-slate-900">@' + escapeHtml(w.username) + '</span>' +
                                    (isHost ? '<span class="px-2 py-0.5 rounded-full bg-yellow-400 text-slate-950 font-black text-[10px] uppercase">HOST ADMIN PAYOUT</span>' : '') +
                                    '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-300">Pending Payment</span>' +
                                '</div>' +
                                '<span class="text-[11px] text-slate-500 block mt-0.5">' + w.created_at + ' &bull; Ref #' + w.id + '</span></div>' +
                                '<div class="text-right"><span class="text-lg font-black font-mono text-emerald-600 block">' + w.formatted_amount + '</span>' +
                                '<span class="text-[10px] text-slate-400">Withdrawal Amount</span></div>' +
                            '</div>' +
                            '<div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs flex justify-between items-center">' +
                                '<div><span class="text-[10px] text-slate-400 block">Payout Destination UPI:</span>' +
                                '<span class="font-mono font-bold text-slate-900 select-all text-xs">' + escapeHtml(w.upi_id) + '</span></div>' +
                                '<div class="flex items-center gap-1.5">' +
                                    '<form method="POST" class="inline" onsubmit="return confirm('Approve and send targeted Push Notification?')">' +
                                        '<input type="hidden" name="withdrawal_id" value="' + w.id + '">' +
                                        '<input type="hidden" name="action" value="complete">' +
                                        '<button type="submit" class="px-3 py-1.5 bg-yellow-400 hover:bg-yellow-500 text-slate-950 rounded-xl text-xs font-black shadow-xs transition active:scale-95 flex items-center gap-1">' +
                                            '<i class="fa-solid fa-check text-[10px]"></i> Complete' +
                                        '</button>' +
                                    '</form>' +
                                '</div>' +
                            '</div>' +
                        '</div>';
                    });
                    pCardCont.innerHTML = payHtml;
                }
            }
        }
    }

    function doPoll() {
        if (document.hidden) return; // Pause while app/tab is backgrounded to save mobile battery
        if (isPolling) return;
        isPolling = true;

        var apiPath = window.location.pathname.indexOf('/admin/') !== -1 ? '../api/live_sync.php' : 'api/live_sync.php';

        fetch(apiPath, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        })
        .then(function(res) {
            if (!res.ok) throw new Error('Network status ' + res.status);
            return res.json();
        })
        .then(function(data) {
            syncUI(data);
        })
        .catch(function(err) {})
        .finally(function() {
            isPolling = false;
        });
    }

    function start() {
        if (timerId) clearInterval(timerId);
        setTimeout(doPoll, 500);
        timerId = setInterval(doPoll, pollInterval);
    }

    function stop() {
        if (timerId) {
            clearInterval(timerId);
            timerId = null;
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stop();
        } else {
            doPoll();
            start();
        }
    });

    return {
        start: start,
        stop: stop,
        pollNow: doPoll
    };
})();

// Start Engines on Page Load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        initPageTabs();
        LiveSyncEngine.start();
    });
} else {
    initPageTabs();
    LiveSyncEngine.start();
}

// Global Exports
window.toggleTheme = toggleTheme;
window.setTheme = setTheme;
window.updateThemeUI = updateThemeUI;
window.switchAppTab = switchAppTab;
window.switchAdminTab = switchAdminTab;
window.openModal = openModal;
window.closeModal = closeModal;
window.LiveSyncEngine = LiveSyncEngine;
