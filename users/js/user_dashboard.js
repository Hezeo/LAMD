document.addEventListener('DOMContentLoaded', () => {

    // ===== SIDEBAR STATE INITIALIZATION =====
    const sidebar = document.getElementById('sidebar');
    const savedState = localStorage.getItem('sidebarState');

    // 1. Clean up the preload class from <html> so transitions work normally again
    document.documentElement.classList.remove('sidebar-preload-hide');

    // 2. Apply the correct class to the sidebar element now that it's loaded
    if (sidebar) {
        if (savedState === 'hidden') {
            sidebar.classList.add('hide');
            sidebar.classList.remove('show');
        } else {
            sidebar.classList.remove('hide');
            sidebar.classList.add('show');
        }
    }

    // ===== SIDEBAR ACTIVE =====
    const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');

    allSideMenu.forEach(item => {
        const li = item.parentElement;
        item.addEventListener('click', () => {
            allSideMenu.forEach(i => i.parentElement.classList.remove('active'));
            li.classList.add('active');
        });
    });

        // ===== TOGGLE SIDEBAR (FIXED FOR MOBILE) =====
    const menuBar = document.querySelector('#content nav .bx.bx-menu');

    if (menuBar && sidebar) {
        menuBar.addEventListener('click', function (e) {
            if (window.innerWidth <= 576) {
                // --- MOBILE MODE: Slide In/Out + Toggle Overlay ---
                sidebar.classList.toggle('show');
                document.body.classList.toggle('nav-open');
            } else {
                // --- DESKTOP MODE: Collapse/Expand ---
                sidebar.classList.toggle('hide');
                if (sidebar.classList.contains('hide')) {
                    localStorage.setItem('sidebarState', 'hidden');
                } else {
                    localStorage.setItem('sidebarState', 'visible');
                }
            }
        });
    }

    // ===== RESPONSIVE ADJUSTMENT =====
    function adjustSidebar() {
        if (!sidebar) return;

        const isMobile = window.innerWidth <= 576;
        const currentSavedState = localStorage.getItem('sidebarState');

        if (isMobile) {
            sidebar.classList.add('hide');
            sidebar.classList.remove('show');
        } else {
            // On Desktop, respect the saved state
            if (currentSavedState === 'hidden') {
                sidebar.classList.add('hide');
                sidebar.classList.remove('show');
            } else {
                sidebar.classList.remove('hide');
                sidebar.classList.add('show');
            }
        }
    }

    window.addEventListener('resize', adjustSidebar);
    adjustSidebar(); // Run once on load to ensure correct state immediately

    // ===== SEARCH =====
    const searchButton = document.querySelector('#content nav form .form-input button');
    const searchButtonIcon = document.querySelector('#content nav form .form-input button .bx');
    const searchForm = document.querySelector('#content nav form');

    if (searchButton && searchButtonIcon && searchForm) {
        searchButton.addEventListener('click', (e) => {
            if (window.innerWidth < 768) {
                e.preventDefault();
                searchForm.classList.toggle('show');
                searchButtonIcon.classList.toggle('bx-x');
                searchButtonIcon.classList.toggle('bx-search');
            }
        });
    }

    // ===== DARK MODE =====
    const switchMode = document.getElementById('switch-mode');

    if (switchMode) {
        switchMode.addEventListener('change', function () {
            document.body.classList.toggle('dark', this.checked);
        });
    }

    // ===== NOTIFICATION =====
    const notification = document.querySelector('.notification');
    const notificationMenu = document.querySelector('.notification-menu');

    if (notification && notificationMenu) {
        notification.addEventListener('click', () => {
            notificationMenu.classList.toggle('show');
        });
    }

    // ===== PROFILE =====
    const profile = document.querySelector('.profile');
    const profileMenu = document.querySelector('.profile-menu');

    if (profile && profileMenu) {
        profile.addEventListener('click', () => {
            profileMenu.classList.toggle('show');
        });
    }

        // ===== CLOSE DROPDOWNS / MOBILE MENU ON OUTSIDE CLICK =====
    window.addEventListener('click', (e) => {
        // --- Existing Notification Logic ---
        if (!e.target.closest('.notification') && notificationMenu) {
            notificationMenu.classList.remove('show');
        }
        // --- Existing Profile Logic ---
        if (!e.target.closest('.profile') && profileMenu) {
            profileMenu.classList.remove('show');
        }

        // --- MOBILE MENU: Close if clicking Overlay or outside sidebar ---
        if (window.innerWidth <= 576 && sidebar.classList.contains('show')) {
            // If click is NOT on sidebar AND NOT on burger button
            if (!e.target.closest('#sidebar') && !e.target.closest('.bx-menu')) {
                sidebar.classList.remove('show');
                document.body.classList.remove('nav-open');
            }
        }
    });

    // ===== MENU TOGGLE =====
    window.toggleMenu = function (menuId) {
        const menu = document.getElementById(menuId);
        if (!menu) return;

        document.querySelectorAll('.menu').forEach(m => {
            if (m !== menu) m.style.display = 'none';
        });

        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    };

    document.querySelectorAll('.menu').forEach(menu => {
        menu.style.display = 'none';
    });

}); // EventListener END