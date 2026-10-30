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

    // 3. Highlight active bottom tab
    document.querySelectorAll('.nav-app-tab').forEach(function(btn) {
        btn.classList.remove('text-yellow-500');
        btn.classList.add('text-slate-400');
        var dot = btn.querySelector('.nav-indicator');
        if (dot) dot.classList.add('hidden');
    });

    var activeBtn = document.getElementById('nav-tab-' + tabName);
    if (activeBtn) {
        activeBtn.classList.remove('text-slate-400');
        activeBtn.classList.add('text-yellow-500');
        var dot = activeBtn.querySelector('.nav-indicator');
        if (dot) dot.classList.remove('hidden');
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
