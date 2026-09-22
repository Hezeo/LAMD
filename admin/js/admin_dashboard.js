document.addEventListener('DOMContentLoaded', () => {

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
    const sidebar = document.getElementById('sidebar');

    // Sidebar toggle operation
    menuBar.addEventListener('click', function () {
        sidebar.classList.toggle('hide');
    });

    function adjustSidebar() {
        if (!sidebar) return;

        if (window.innerWidth <= 576) {
            sidebar.classList.add('hide');
            sidebar.classList.remove('show');
        } else {
            sidebar.classList.remove('hide');
            sidebar.classList.add('show');
        }
    }

    window.addEventListener('load', adjustSidebar);
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
}); //EventListener END

// MODAL & USER MANAGEMENT LOGIC
document.addEventListener('DOMContentLoaded', () => {
    // --- 1. SELECTORS ---
    const addUserModal = document.getElementById('addUserModal');
    const editModal = document.getElementById('editUserModal');
    const deleteModal = document.getElementById('deleteModal');
    const userTableBody = document.getElementById('userTableBody');
    const addUserForm = document.getElementById('addUserForm');
    const editUserForm = document.getElementById('editUserForm');
    const confirmYesBtn = document.getElementById('confirmYes');

    // --- 2. USERNAME VALIDATION LOGIC ---

    /**
     * Helper to display validation messages below inputs
     */
    const setUsernameError = (inputEl, isError, message = "") => {
        let errorSpan = inputEl.parentNode.querySelector('.username-error');
        if (!errorSpan) {
            errorSpan = document.createElement('small');
            errorSpan.className = 'username-error';
            errorSpan.style.color = '#d32f2f';
            errorSpan.style.display = 'block';
            errorSpan.style.marginTop = '2px';
            errorSpan.style.fontSize = '0.75rem';
            inputEl.parentNode.appendChild(errorSpan);
        }

        inputEl.style.borderColor = isError ? '#d32f2f' : '';
        errorSpan.textContent = isError ? message : "";
        inputEl.dataset.isInvalid = isError; // Store state to block form submit
    };

    /**
     * Debounced function to check database for existing username
     */
    let typingTimer;
    const checkUsernameAvailability = (inputEl, userId = null) => {
        clearTimeout(typingTimer);
        const username = inputEl.value.trim();

        if (username === "") {
            setUsernameError(inputEl, false);
            return;
        }

        typingTimer = setTimeout(() => {
            let url = `actions/check_username.php?username=${encodeURIComponent(username)}`;
            if (userId) url += `&user_id=${userId}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.exists) {
                        setUsernameError(inputEl, true, "Username already exists.");
                    } else {
                        setUsernameError(inputEl, false);
                    }
                })
                .catch(err => console.error("Validation Error:", err));
        }, 500); // Wait 500ms after user stops typing
    };

    // Attach listeners to username inputs
    const addUsernameInput = document.getElementById('addUsername');
    const editUsernameInput = document.getElementById('editUsername');

    if (addUsernameInput) {
        addUsernameInput.addEventListener('input', () => checkUsernameAvailability(addUsernameInput));
    }
    if (editUsernameInput) {
        editUsernameInput.addEventListener('input', () => {
            const currentUserId = document.getElementById('editUserId').value;
            checkUsernameAvailability(editUsernameInput, currentUserId);
        });
    }

    // --- 3. MODAL OPENING LOGIC ---

    // Open Add User Modal
    const addBtn = document.querySelector('.btn-download');
    if (addBtn) {
        addBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (addUserForm) {
                addUserForm.reset();
                if (addUsernameInput) setUsernameError(addUsernameInput, false);
            }
            addUserModal.style.display = 'flex';
        });
    }

    // Open Edit/Delete Modals (via Event Delegation)
    if (userTableBody) {
        userTableBody.addEventListener('click', (e) => {
            // --- Handle Edit Button Click ---
            const editBtn = e.target.closest('.btn-edit');
            if (editBtn) {
                const row = editBtn.closest('tr');

                // Populate ALL Modal Fields from data-attributes
                document.getElementById('editUserId').value = editBtn.getAttribute('data-id');
                document.getElementById('editFirstName').value = editBtn.getAttribute('data-fname');
                document.getElementById('editMiddleInitial').value = editBtn.getAttribute('data-mi');
                document.getElementById('editLastName').value = editBtn.getAttribute('data-lname');
                document.getElementById('editDepartment').value = editBtn.getAttribute('data-dept');
                document.getElementById('editPosition').value = editBtn.getAttribute('data-pos');
                document.getElementById('editEmail').value = editBtn.getAttribute('data-email');
                document.getElementById('editPhone').value = editBtn.getAttribute('data-phone');
                document.getElementById('editPassword').value = editBtn.getAttribute('data-password');

                // Username from table cell
                document.getElementById('editUsername').value = row.cells[2].textContent.trim();
                if (editUsernameInput) setUsernameError(editUsernameInput, false);

                const editPassInput = document.getElementById('editPassword');
                if (editPassInput) editPassInput.setAttribute('type', 'password');

                const editIcon = document.querySelector('#toggleEditPassword i');
                if (editIcon) {
                    editIcon.classList.add('fa-eye');
                    editIcon.classList.remove('fa-eye-slash');
                }

                editModal.style.display = 'flex';
                return;
            }

            // --- Handle Delete Button Click ---
            const deleteBtn = e.target.closest('.btn-delete');
            if (deleteBtn) {
                const userId = deleteBtn.getAttribute('data-id');
                if (confirmYesBtn) confirmYesBtn.setAttribute('data-id', userId);
                deleteModal.style.display = 'flex';
            }
        });
    }

    // --- 4. PASSWORD TOGGLE LOGIC ---

    const toggleAddPassword = document.getElementById('toggleAddPassword');
    const addPasswordInput = document.getElementById('addPassword');
    if (toggleAddPassword && addPasswordInput) {
        toggleAddPassword.addEventListener('click', () => {
            const type = addPasswordInput.type === 'password' ? 'text' : 'password';
            addPasswordInput.type = type;
            toggleAddPassword.querySelector('i').classList.toggle('fa-eye');
            toggleAddPassword.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }

    const toggleEditPassword = document.getElementById('toggleEditPassword');
    const editPasswordInput = document.getElementById('editPassword');
    if (toggleEditPassword && editPasswordInput) {
        toggleEditPassword.addEventListener('click', () => {
            const type = editPasswordInput.type === 'password' ? 'text' : 'password';
            editPasswordInput.type = type;
            toggleEditPassword.querySelector('i').classList.toggle('fa-eye');
            toggleEditPassword.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }

    // --- 5. UNIVERSAL CLOSE LOGIC ---

    const closeElements = '.close-btn, .editClose-btn, .editbtn-cancel, .delbtn-cancel, #confirmNo, #cancelEdit';
    document.querySelectorAll(closeElements).forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            addUserModal.style.display = 'none';
            editModal.style.display = 'none';
            deleteModal.style.display = 'none';
        });
    });

    window.addEventListener('click', (e) => {
        if (e.target === addUserModal || e.target === editModal || e.target === deleteModal) {
            addUserModal.style.display = 'none';
            editModal.style.display = 'none';
            deleteModal.style.display = 'none';
        }
    });

    // --- 6. FORM SUBMISSIONS ---

    // ADD USER SUBMISSION
    if (addUserForm) {
        addUserForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Block if username is invalid
            if (addUsernameInput && addUsernameInput.dataset.isInvalid === "true") {
                alert("Cannot proceed. Please choose a different username.");
                return;
            }

            const formData = new FormData(this);
            fetch('actions/add_user.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert("Error: " + data.message);
                    }
                })
                .catch(error => alert("An error occurred while connecting to the server."));
        });
    }

    // EDIT USER SUBMISSION
    if (editUserForm) {
        editUserForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Block if username is invalid
            if (editUsernameInput && editUsernameInput.dataset.isInvalid === "true") {
                alert("Cannot proceed. Please choose a different username.");
                return;
            }

            const formData = new FormData(this);
            fetch('actions/edit_user.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert("Error: " + data.message);
                    }
                })
                .catch(error => alert("An error occurred while updating the user."));
        });
    }

    // DELETE USER SUBMISSION
    if (confirmYesBtn) {
        confirmYesBtn.addEventListener('click', function () {
            const userId = this.getAttribute('data-id');
            const formData = new FormData();
            formData.append('user_id', userId);

            fetch('actions/delete_user.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert("Error: " + data.message);
                    }
                })
                .catch(error => alert("An error occurred while deleting the user."));
        });
    }
});