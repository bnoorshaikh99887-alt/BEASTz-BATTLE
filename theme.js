// ============================================================
// BEASTz BATTLE - Unified Front-End Operating Engine
// Theme Manager • High-Speed SPA Switcher • Background Live Sync
// ============================================================

(function() {
    try {
        var savedTheme = localStorage.getItem('beastz_theme');
        if (savedTheme === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    } catch(e) {}
})();

function updateThemeUI() {
    var isDark = document.documentElement.classList.contains('dark');
    
    // Update Theme Icons
    var icons = document.querySelectorAll('.beastz-theme-icon');
    icons.forEach(function(icon) {
        if (isDark) {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
            icon.style.color = '#facc15';
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
            icon.style.color = '';
        }
    });

    // Update Theme Text Labels
    var labels = document.querySelectorAll('.beastz-theme-label');
    labels.forEach(function(lbl) {
        lbl.textContent = isDark ? 'Dark Mode (Active)' : 'Light Mode (Active)';
    });

    // Update Checkboxes / Switches
    var toggles = document.querySelectorAll('.beastz-theme-toggle-input');
    toggles.forEach(function(tg) {
        tg.checked = isDark;
    });

    // Update Meta Theme Color
    var metaTheme = document.querySelector('meta[name="theme-color"]');
    if (metaTheme) {
        metaTheme.setAttribute('content', isDark ? '#0b0f19' : '#facc15');
    }
}

function toggleTheme() {
    var isDark = document.documentElement.classList.toggle('dark');
    try {
        localStorage.setItem('beastz_theme', isDark ? 'dark' : 'light');
    } catch(e) {}
    updateThemeUI();
}

function setTheme(themeName) {
    if (themeName === 'dark') {
        document.documentElement.classList.add('dark');
        try { localStorage.setItem('beastz_theme', 'dark'); } catch(e) {}
    } else {
        document.documentElement.classList.remove('dark');
        try { localStorage.setItem('beastz_theme', 'light'); } catch(e) {}
    }
    updateThemeUI();
}

// ============================================================
// USER PANEL: ULTRA-FAST ZERO-RELOAD SINGLE-PAGE APPLICATION (SPA)
// ============================================================
function switchAppTab(tabName, skipState) {
    if (!tabName) tabName = 'home';
    tabName = tabName.toLowerCase().replace('spa-tab-', '');

    var views = document.querySelectorAll('.spa-tab-view');
    if (views.length === 0) {
        var fallbackUrls = {
            'home': 'index.php?tab=home',
            'matches': 'index.php?tab=matches',
            'wallet': 'index.php?tab=wallet',
            'leaderboard': 'index.php?tab=leaderboard',
            'store': 'index.php?tab=store',
            'profile': 'index.php?tab=profile'
        };
        if (fallbackUrls[tabName]) {
            window.location.href = fallbackUrls[tabName];
        }
        return;
    }

    // 1. Hide all views
    views.forEach(function(el) {
        el.classList.add('hidden');
    });

    // 2. Show requested view
    var target = document.getElementById('spa-tab-' + tabName);
    if (!target) {
        target = document.getElementById('spa-tab-home');
        tabName = 'home';
    }
    if (target) {
        target.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'instant' });
    }

    // 3. Highlight active bottom tab
    document.querySelectorAll('.nav-app-tab').forEach(function(btn) {
        btn.classList.remove('active-tab', 'text-yellow-500');
        btn.classList.add('text-slate-400');
    });
    var activeBtn = document.getElementById('nav-tab-' + tabName);
    if (activeBtn) {
        activeBtn.classList.remove('text-slate-400');
        activeBtn.classList.add('active-tab', 'text-yellow-500');
    }

    // 4. Update browser URL without network reload
    if (!skipState && window.history && window.history.replaceState) {
        var newUrl = 'index.php?tab=' + encodeURIComponent(tabName);
        window.history.replaceState({ tab: tabName }, '', newUrl);
    }
    try {
        localStorage.setItem('beastz_active_user_tab', tabName);
    } catch(e) {}
}

// ============================================================
// ADMIN CONTROL CENTER: 100% RELIABLE SPA TAB SWITCHER
// ============================================================
function switchAdminTab(tabName, skipState) {
    if (!tabName) tabName = 'overview';
    tabName = tabName.toLowerCase().replace('admin-tab-', '');

    // Canonical Tab Alias Map
    var aliasMap = {
        'dashboard': 'overview',
        'home': 'overview',
        'tourney': 'tournaments',
        'tourneys': 'tournaments',
        'tournament': 'tournaments',
        'deposit': 'deposits',
        'payout': 'payouts',
        'withdrawals': 'payouts',
        'withdrawal': 'payouts',
        'broadcast': 'notifications',
        'notification': 'notifications',
        'notif': 'notifications',
        'user': 'users',
        'gamer': 'users',
        'gamers': 'users',
        'admin': 'admins',
        'setting': 'settings'
    };
    if (aliasMap[tabName]) {
        tabName = aliasMap[tabName];
    }

    var views = document.querySelectorAll('.admin-spa-view');
    var target = document.getElementById('admin-tab-' + tabName);

    // If SPA views do not exist on current page (e.g., from user page or standalone script)
    if (views.length === 0 || !target) {
        var adminPath = window.location.pathname.indexOf('/admin/') !== -1 ? 'index.php' : 'admin/index.php';
        window.location.href = adminPath + '?tab=' + encodeURIComponent(tabName);
        return;
    }

    // Hide all views
    views.forEach(function(el) {
        el.classList.add('hidden');
    });

    // Unhide requested tab
    target.classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'instant' });

    // Update Top Navigation Pill Capsules
    var actPillClasses = ['bg-yellow-400', 'text-slate-950', 'border-yellow-400', 'font-black', 'shadow-xs'];
    var inactPillClasses = ['text-slate-400', 'hover:text-white', 'font-bold', 'bg-slate-900', 'border-slate-800'];

    document.querySelectorAll('.admin-tab-btn').forEach(function(btn) {
        actPillClasses.forEach(function(c) { btn.classList.remove(c); });
        inactPillClasses.forEach(function(c) { btn.classList.add(c); });
    });

    var activeTopBtn = document.getElementById('admin-nav-' + tabName);
    if (activeTopBtn) {
        inactPillClasses.forEach(function(c) { activeTopBtn.classList.remove(c); });
        actPillClasses.forEach(function(c) { activeTopBtn.classList.add(c); });
        try {
            activeTopBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        } catch(e) {}
    }

    // Update Bottom Navigation Tabs
    document.querySelectorAll('.admin-bottom-tab').forEach(function(btn) {
        btn.classList.remove('text-yellow-500', 'font-black');
        btn.classList.add('text-slate-400');
    });

    var activeBottomBtn = document.getElementById('admin-bottom-' + tabName);
    if (activeBottomBtn) {
        activeBottomBtn.classList.remove('text-slate-400');
        activeBottomBtn.classList.add('text-yellow-500', 'font-black');
    }

    // Update URL history state without reload
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

    // Check if in Admin Panel
    var adminViews = document.querySelectorAll('.admin-spa-view');
    if (adminViews.length > 0) {
        try { localStorage.setItem('beastz_active_panel', 'admin'); } catch(e) {}
        if (reqTab) {
            switchAdminTab(reqTab, true);
        } else {
            var lastAdminTab = localStorage.getItem('beastz_active_admin_tab') || 'overview';
            switchAdminTab(lastAdminTab, true);
        }
        return;
    }

    // Check if in User App
    var userViews = document.querySelectorAll('.spa-tab-view');
    if (userViews.length > 0) {
        try { localStorage.setItem('beastz_active_panel', 'user'); } catch(e) {}
        if (reqTab) {
            switchAppTab(reqTab, true);
        } else {
            var lastUserTab = localStorage.getItem('beastz_active_user_tab') || 'home';
            switchAppTab(lastUserTab, true);
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPageTabs);
} else {
    initPageTabs();
}

// ============================================================
// REAL-TIME AUTO-REFRESH & BACKGROUND LIVE SYNC ENGINE (AJAX)
// Seamless background sync for Web & Android APK (WebView)
// ============================================================
var LiveSyncEngine = (function() {
    var pollInterval = 4000; // 4 seconds
    var timerId = null;
    var lastWalletBal = null;
    var lastPendingWithdrawalsCount = null;
    var lastCompletedWithdrawalId = null;
    var lastAdminPendingDep = null;
    var lastAdminPendingWdr = null;
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
        alertBox.innerHTML = '<i class="fa-solid fa-circle-check text-sm"></i><span>' + message + '</span>';
        document.body.appendChild(alertBox);
        setTimeout(function() {
            alertBox.style.opacity = '0';
            setTimeout(function() { alertBox.remove(); }, 300);
        }, 4000);
    }

    function syncUI(data) {
        if (!data || !data.success) return;

        // 1. Sync User Wallet Balance
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

            // Update Wallet View Compact & Full
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

            // Update all .user-wallet-balance-val elements
            document.querySelectorAll('.user-wallet-balance-val').forEach(function(el) {
                el.textContent = data.user.compact_balance;
            });

            // 2. Sync Pending Withdrawals
            var pendingContainer = document.getElementById('user-pending-withdrawals-container');
            if (pendingContainer) {
                if (data.user.pending_count === 0) {
                    pendingContainer.classList.add('hidden');
                } else {
                    pendingContainer.classList.remove('hidden');
                }
            }

            // Detect if a pending withdrawal was just completed by Admin
            if (data.user.recent_completed_withdrawal) {
                var rc = data.user.recent_completed_withdrawal;
                if (lastCompletedWithdrawalId !== null && lastCompletedWithdrawalId !== rc.id) {
                    showInAppAlert('✅ Payout of ' + rc.formatted_amount + ' has been completed! Check your UPI.', 'success');
                }
                lastCompletedWithdrawalId = rc.id;
            }

            lastWalletBal = newBal;
            lastPendingWithdrawalsCount = data.user.pending_count;
        }

        // 3. Sync Leaderboard / Point Table
        if (data.leaderboard && data.leaderboard.length > 0) {
            var lbListEl = document.getElementById('live-leaderboard-list');
            if (lbListEl && !document.getElementById('spa-tab-leaderboard')?.classList.contains('hidden')) {
                // Update rank values dynamically if needed
                data.leaderboard.forEach(function(player, idx) {
                    var rankNum = idx + 1;
                    var item = lbListEl.children[idx];
                    if (item) {
                        var balEl = item.querySelector('.font-mono.font-black');
                        if (balEl && balEl.textContent.trim() !== player.formatted_balance) {
                            balEl.textContent = player.formatted_balance;
                            flashElement(balEl, 'text-emerald-600');
                        }
                    }
                });
            }
        }

        // 4. Sync Admin Control Center
        if (data.admin) {
            var adm = data.admin;

            // Overview stats numbers
            var uCntEl = document.getElementById('admin-stat-users-cnt');
            if (uCntEl && uCntEl.textContent.trim() != adm.users_cnt) {
                uCntEl.textContent = adm.users_cnt;
            }

            var tCntEl = document.getElementById('admin-stat-tourneys-cnt');
            if (tCntEl && tCntEl.textContent.trim() != adm.tourneys_cnt) {
                tCntEl.textContent = adm.tourneys_cnt;
            }

            var revEl = document.getElementById('admin-stat-revenue');
            if (revEl && revEl.textContent.trim() != adm.formatted_revenue) {
                revEl.textContent = adm.formatted_revenue;
            }

            var przEl = document.getElementById('admin-stat-prize-dist');
            if (przEl && przEl.textContent.trim() != adm.formatted_prize_dist) {
                przEl.textContent = adm.formatted_prize_dist;
            }

            // Pending request badge notification in overview
            var pDepEl = document.getElementById('admin-pending-deposits-cnt');
            if (pDepEl) pDepEl.textContent = adm.pending_deposits;
            var pWdrEl = document.getElementById('admin-pending-payouts-cnt');
            if (pWdrEl) pWdrEl.textContent = adm.pending_withdrawals;

            // If pending payouts count changed on admin payouts tab
            if (lastAdminPendingWdr !== null && lastAdminPendingWdr !== adm.pending_withdrawals) {
                var pCardCont = document.getElementById('admin-payouts-cards-container');
                if (pCardCont && !document.getElementById('admin-tab-payouts')?.classList.contains('hidden')) {
                    // Subtle refresh highlight on payouts tab
                    flashElement(pCardCont, 'border-yellow-400');
                }
            }

            lastAdminPendingDep = adm.pending_deposits;
            lastAdminPendingWdr = adm.pending_withdrawals;
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
        .catch(function(err) {
            // Quiet fallback on network drops
        })
        .finally(function() {
            isPolling = false;
        });
    }

    function start() {
        if (timerId) clearInterval(timerId);
        // Initial quick poll after 1s
        setTimeout(doPoll, 1000);
        // Recurring background poll
        timerId = setInterval(doPoll, pollInterval);
    }

    function stop() {
        if (timerId) {
            clearInterval(timerId);
            timerId = null;
        }
    }

    // Page Visibility Handler: pause on background, resume immediately on focus
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

// Start Live Sync Engine automatically
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        LiveSyncEngine.start();
    });
} else {
    LiveSyncEngine.start();
}

// Global Export
window.toggleTheme = toggleTheme;
window.setTheme = setTheme;
window.updateThemeUI = updateThemeUI;
window.switchAppTab = switchAppTab;
window.switchAdminTab = switchAdminTab;
window.openModal = openModal;
window.closeModal = closeModal;
window.LiveSyncEngine = LiveSyncEngine;
