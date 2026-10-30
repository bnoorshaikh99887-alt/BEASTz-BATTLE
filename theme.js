// BEASTz BATTLE - Real-Time Dark / Light Mode Switcher Engine

(function() {
    try {
        var savedTheme = localStorage.getItem('beastz_theme');
        // Default to dark for elite esports experience if not explicitly light
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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateThemeUI);
} else {
    updateThemeUI();
}

window.toggleTheme = toggleTheme;
window.setTheme = setTheme;
window.updateThemeUI = updateThemeUI;
