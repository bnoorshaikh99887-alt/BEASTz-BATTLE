// ============================================================
// BEASTz BATTLE - Real-Time Dark / Light Mode & SPA Tab Switcher Engine
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

// ============================================================
// ADMIN PANEL TAB SWITCHER & MODALS (Missing in your code)
// ============================================================

function switchAdminTab(tabId) {
    $('.admin-spa-view').addClass('hidden');
    $('#admin-tab-' + tabId).removeClass('hidden');
}

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

// ============================================================
// AUTO-BINDERS & FIXES (Runs on page load)
// ============================================================
if (typeof $ !== 'undefined') {
    $(document).ready(function() {

        // 1. Tournament Publish Freeze Fix
        $('#publishTournamentForm').on('submit', function(e) {
            e.preventDefault(); 
            let submitBtn = $(this).find('button[type="submit"]');
            let originalText = submitBtn.text();
            submitBtn.prop('disabled', true).text('Publishing...');

            $.ajax({
                url: $(this).attr('action') ? $(this).attr('action') : 'manage_tournament.php',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    alert('Tournament Published Successfully!');
                    window.location.reload(); 
                },
                error: function() {
                    submitBtn.prop('disabled', false).text(originalText);
                    alert('Connection glitch! Please try again.');
                }
            });
        });

        // 2. Profile Photo Upload Trigger
        $('a:contains("Change Photo"), button:contains("Change Photo")').on('click', function(e) {
            e.preventDefault();
            $('input[type="file"]').first().click();
        });

        $('input[type="file"]').on('change', function() {
            if ($(this).val()) {
                $(this).closest('form').submit();
            }
        });

        // 3. Add Money & Withdraw Modal Triggers (Wallet Page)
        $('button:contains("Add Money")').on('click', function(e) {
            e.preventDefault();
            openModal('depositModal');
        });

        $('button:contains("Withdraw")').on('click', function(e) {
            e.preventDefault();
            openModal('withdrawModal');
        });

        // 4. Logout Button Fallback
        $('a:contains("Sign Out"), a:contains("Logout")').on('click', function(e) {
            if(!$(this).attr('href') || $(this).attr('href') === '#') {
                e.preventDefault();
                window.location.href = 'logout.php';
            }
        });

        // Auto-hide alerts
        setTimeout(function() {
            $('.alert, .bg-emerald-50, .bg-rose-50').fadeOut('slow');
        }, 3000);

    });
}

// ============================================================
// INITIALIZATION
// ============================================================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateThemeUI);
} else {
    updateThemeUI();
}

window.toggleTheme = toggleTheme;
window.setTheme = setTheme;
window.updateThemeUI = updateThemeUI;
window.switchAppTab = switchAppTab;
