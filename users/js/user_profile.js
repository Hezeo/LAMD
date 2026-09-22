document.addEventListener('DOMContentLoaded', () => {

    // ===== SIDEBAR STATE INITIALIZATION =====
    const sidebar = document.getElementById('sidebar');
    const savedState = localStorage.getItem('sidebarState');

    // 1. Clean up the preload class from <html> so transitions work normally again
    document.documentElement.classList.remove('sidebar-preload-hide');

    // 2. Apply the correct class to the sidebar element now that it's loaded
    // This ensures the toggle button logic works correctly
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

    // TOGGLE SIDEBAR
    const menuBar = document.querySelector('#content nav .bx.bx-menu');

    if (menuBar && sidebar) {
        menuBar.addEventListener('click', function () {
            sidebar.classList.toggle('hide');

            // SAVE STATE TO LOCAL STORAGE
            if (sidebar.classList.contains('hide')) {
                localStorage.setItem('sidebarState', 'hidden');
            } else {
                localStorage.setItem('sidebarState', 'visible');
            }
        });
    }

    function adjustSidebar() {
        if (!sidebar) return;

        const isMobile = window.innerWidth <= 576;
        const savedState = localStorage.getItem('sidebarState');

        if (isMobile) {
            sidebar.classList.add('hide');
            sidebar.classList.remove('show');
        } else {
            // On Desktop, respect the saved state
            if (savedState === 'hidden') {
                sidebar.classList.add('hide');
                sidebar.classList.remove('show');
            } else {
                sidebar.classList.remove('hide');
                sidebar.classList.add('show');
            }
        }
    }

    // Use a small timeout on resize to prevent jitter, or just call it directly
    window.addEventListener('resize', adjustSidebar);

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

    window.addEventListener('click', (e) => {
        if (!e.target.closest('.notification') && notificationMenu) {
            notificationMenu.classList.remove('show');
        }
        if (!e.target.closest('.profile') && profileMenu) {
            profileMenu.classList.remove('show');
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

    // ===== PASSWORD REVEAL =====
    const togglePassword = document.getElementById('togglePassword');
    if (togglePassword) {
        togglePassword.addEventListener('click', function () {
            const passwordField = document.getElementById('password-field');
            if (passwordField) {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                // Toggle icon class
                this.classList.toggle('bx-low-vision');
                this.classList.toggle('bx-show');
            }
        });
    }

}); // EventListener END

//UPLOAD PROFILE PICTURE
document.addEventListener('DOMContentLoaded', () => {
    const changePhotoBtn = document.getElementById('changePhotoBtn');
    const profileImageInput = document.getElementById('profileImageInput');
    const profilePic = document.getElementById('profilePic');
    // Select the small header icon
    const headerPic = document.getElementById('headerProfilePic');

    // When clicking the camera button, trigger file input
    changePhotoBtn.addEventListener('click', () => {
        profileImageInput.click();
    });

    // When a file is selected
    profileImageInput.addEventListener('change', () => {
        const file = profileImageInput.files[0];
        if (file) {
            // Visual feedback: Start loading
            const originalBtnContent = changePhotoBtn.innerHTML;
            changePhotoBtn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i>";
            changePhotoBtn.style.pointerEvents = 'none'; // Prevent double clicks

            const formData = new FormData();
            formData.append('profile_image', file);

            // Use a relative path since user_profile.php and user_profile_image.php are in the same folder
            fetch('user_profile_image.php', {
                method: 'POST',
                body: formData
            })
                .then(async res => {
                    if (!res.ok) {
                        const text = await res.text();
                        throw new Error('Server crash: ' + text);
                    }
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        // 1. Update the big profile picture
                        profilePic.src = data.image_url;

                        // 2. Update the small header icon if it exists on the page
                        if (headerPic) {
                            headerPic.src = data.image_url;
                        }

                        alert('Profile picture updated!');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error uploading image. See console for details.');
                })
                .finally(() => {
                    // Reset button state
                    changePhotoBtn.innerHTML = originalBtnContent;
                    changePhotoBtn.style.pointerEvents = 'auto';
                });
        }
    });
});

// MODAL FUNCTION
const modal = document.getElementById("editProfileModal");
const btn = document.getElementById("openEditModal");
const span = document.getElementsByClassName("close-modal")[0];
const form = modal.querySelector("form");

// Toggle Password Visibility
const togglePassword = document.getElementById("togglePassword");
const passwordInput = document.getElementById("passwordInput");

togglePassword.onclick = function () {
    // Toggle the type attribute
    const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
    passwordInput.setAttribute("type", type);

    // Toggle the icon class (Boxicons)
    this.classList.toggle('bx-show');
    this.classList.toggle('bx-hide');
};

// Open/Close logic
btn.onclick = () => modal.style.display = "block";
span.onclick = () => modal.style.display = "none";
window.onclick = (event) => { if (event.target == modal) modal.style.display = "none"; }

// Validation Logic
form.onsubmit = function (event) {
    const password = passwordInput.value;

    if (password === "") return true;

    // Regex for: 1 Uppercase, 1 Number, 1 Special Char
    const passwordRegex = /^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/;

    if (!passwordRegex.test(password)) {
        event.preventDefault();
        alert("Password must contain at least one uppercase letter, one number, and one special character.");
        passwordInput.focus();
        return false;
    }
};