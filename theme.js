// BEASTz BATTLE - Real-Time Dark / Light Mode & SPA Tab Switcher Engine

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
// ULTRA-FAST ZERO-RELOAD SINGLE-PAGE APPLICATION (SPA) ENGINE
// ============================================================
function switchAppTab(tabName, skipState) {
    var views = document.querySelectorAll('.spa-tab-view');
    if (views.length === 0) {
        // Fallback if not inside unified SPA container
        var fallbackUrls = {
            'home': 'index.php',
            'matches': 'my_tournaments.php',
            'wallet': 'wallet.php',
            'leaderboard': 'leaderboard.php',
            'profile': 'profile.php'
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
    if (target) {
        target.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'instant' });
    }

    // 3. Highlight active bottom tab with transparent dark fog / shadow glow
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
        var urlMap = {
            'home': 'index.php',
            'matches': 'my_tournaments.php',
            'wallet': 'wallet.php',
            'leaderboard': 'leaderboard.php',
            'profile': 'profile.php'
        };
        if (urlMap[tabName]) {
            window.history.replaceState({ tab: tabName }, '', urlMap[tabName]);
        }
    }
}

window.addEventListener('popstate', function(e) {
    if (e.state && e.state.tab) {
        switchAppTab(e.state.tab, true);
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateThemeUI);
} else {
    updateThemeUI();
}

window.toggleTheme = toggleTheme;
window.setTheme = setTheme;
window.updateThemeUI = updateThemeUI;
window.switchAppTab = switchAppTab;


// ============================================================
// REAL-TIME PUSH NOTIFICATION RECEIVER (WEB + NATIVE APK)
// ============================================================
(function() {
    var lastNotifId = 0;
    try {
        lastNotifId = parseInt(localStorage.getItem('beastz_last_notif_id') || '0', 10);
    } catch(e) {}

    // Request Web Notification Permission gracefully on interaction
    function reqWebPerm() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }
    document.addEventListener('click', reqWebPerm, { once: true });
    document.addEventListener('touchstart', reqWebPerm, { once: true });

    function showInAppAlert(title, message, url) {
        var existing = document.getElementById('beastz-live-toast');
        if (existing) existing.remove();

        var toast = document.createElement('div');
        toast.id = 'beastz-live-toast';
        toast.className = 'fixed top-4 left-1/2 -translate-x-1/2 w-[92%] max-w-sm z-[9999] bg-slate-900/95 border-2 border-yellow-400 text-white rounded-2xl p-3.5 shadow-[0_10px_30px_rgba(0,0,0,0.8)] backdrop-blur-md flex items-start gap-3 transition-all duration-300 transform -translate-y-10 opacity-0 cursor-pointer';
        toast.innerHTML = 
            '<div class="w-9 h-9 rounded-xl bg-yellow-400 text-slate-950 flex items-center justify-center text-base shrink-0 shadow-xs">' +
                '<i class="fa-solid fa-bell animate-bounce"></i>' +
            '</div>' +
            '<div class="flex-1 text-left min-w-0">' +
                '<div class="flex items-center justify-between mb-0.5">' +
                    '<span class="text-[9px] font-black uppercase tracking-wider text-yellow-400">BEASTz ALERT &bull; NOW</span>' +
                    '<span class="text-[10px] text-slate-400"><i class="fa-solid fa-xmark"></i></span>' +
                '</div>' +
                '<h4 class="text-xs font-black text-white truncate">' + title + '</h4>' +
                '<p class="text-[11px] text-slate-300 line-clamp-2 mt-0.5">' + message + '</p>' +
            '</div>';

        toast.onclick = function() {
            if (url) {
                if (url.indexOf('?tab=') !== -1) {
                    var tab = url.split('?tab=')[1];
                    if (window.switchAppTab) window.switchAppTab(tab);
                } else {
                    window.location.href = url;
                }
            }
            toast.remove();
        };

        document.body.appendChild(toast);
        setTimeout(function() {
            toast.classList.remove('-translate-y-10', 'opacity-0');
        }, 50);

        setTimeout(function() {
            if (toast.parentNode) {
                toast.classList.add('-translate-y-10', 'opacity-0');
                setTimeout(function() { if (toast.parentNode) toast.remove(); }, 300);
            }
        }, 8000);
    }

    function checkPushAlerts() {
        fetch('/api/get_notifications.php?since_id=' + lastNotifId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.success && data.notifications && data.notifications.length > 0) {
                    data.notifications.forEach(function(n) {
                        if (n.id > lastNotifId) {
                            lastNotifId = n.id;
                            try { localStorage.setItem('beastz_last_notif_id', lastNotifId); } catch(e) {}
                            
                            // 1. Android Native Notification
                            if (window.BEASTzNative && window.BEASTzNative.postNativeNotification) {
                                window.BEASTzNative.postNativeNotification(n.title, n.message, n.target_url);
                            } 
                            // 2. Web Browser Notification
                            else if ('Notification' in window && Notification.permission === 'granted') {
                                try {
                                    new Notification(n.title, {
                                        body: n.message,
                                        icon: '/icon-192.png'
                                    });
                                } catch(e) {}
                            }

                            // 3. In-App Floating Toast
                            showInAppAlert(n.title, n.message, n.target_url);
                        }
                    });
                }
            })
            .catch(function() {});
    }

    // Check after 3 seconds, then every 20 seconds
    setTimeout(checkPushAlerts, 3000);
    setInterval(checkPushAlerts, 20000);
})();
