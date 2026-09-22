document.addEventListener('DOMContentLoaded', () => {

    // =============================================================
    // 1. UI & SIDEBAR LOGIC
    // =============================================================
    const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');
    allSideMenu.forEach(item => {
        const li = item.parentElement;
        item.addEventListener('click', () => {
            allSideMenu.forEach(i => i.parentElement.classList.remove('active'));
            li.classList.add('active');
        });
    });

    const menuBar = document.querySelector('#content nav .bx.bx-menu');
    const sidebar = document.getElementById('sidebar');
    if (menuBar && sidebar) menuBar.addEventListener('click', () => sidebar.classList.toggle('hide'));

    const profile = document.querySelector('.profile');
    const profileMenu = document.querySelector('.profile-menu');
    if (profile && profileMenu) {
        profile.addEventListener('click', (e) => { e.stopPropagation(); profileMenu.classList.toggle('show'); });
    }
    window.addEventListener('click', (e) => {
        if (profileMenu && !e.target.closest('.profile')) profileMenu.classList.remove('show');
    });

    // =============================================================
    // 2. HELPER FUNCTIONS
    // =============================================================
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
        inputEl.dataset.isInvalid = isError;
    };

    let typingTimer;
    const checkUsernameAvailability = (inputEl, userId = null) => {
        clearTimeout(typingTimer);
        const username = inputEl.value.trim();
        if (username === "") { setUsernameError(inputEl, false); return; }

        typingTimer = setTimeout(() => {
            let url = `actions/check_username.php?username=${encodeURIComponent(username)}`;
            if (userId) url += `&user_id=${userId}`;

            fetch(url).then(res => res.json()).then(data => {
                if (data.exists) setUsernameError(inputEl, true, "Username already exists.");
                else setUsernameError(inputEl, false);
            });
        }, 500);
    };

    // =============================================================
    // 3. USER MANAGEMENT LOGIC
    // =============================================================

    const addUserModal = document.getElementById('addUserModal');
    const editModal = document.getElementById('editUserModal');
    const deleteModal = document.getElementById('deleteModal');
    const userTableBody = document.getElementById('userTableBody');
    const addUserForm = document.getElementById('addUserForm');
    const editUserForm = document.getElementById('editUserForm');
    const confirmYesBtn = document.getElementById('confirmYes');

    const addUsernameInput = document.getElementById('addUsername');
    const editUsernameInput = document.getElementById('editUsername');

    // Username Validation
    if (addUsernameInput) addUsernameInput.addEventListener('input', () => checkUsernameAvailability(addUsernameInput));
    if (editUsernameInput) editUsernameInput.addEventListener('input', () => {
        checkUsernameAvailability(editUsernameInput, document.getElementById('editUserId').value);
    });

    // Open Add Modal
    const addBtn = document.querySelector('.btn-download');
    if (addBtn) {
        addBtn.addEventListener('click', (e) => {
            e.preventDefault();
            addUserForm.reset();
            if (addUsernameInput) setUsernameError(addUsernameInput, false);
            addUserModal.style.display = 'flex';
        });
    }

    // Open Edit Modal
    if (userTableBody) {
        userTableBody.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.btn-edit');
            if (editBtn) {
                const userId = editBtn.getAttribute('data-id');
                
                // Populate Fields
                document.getElementById('editUserId').value = userId;
                document.getElementById('editFirstName').value = editBtn.getAttribute('data-fname');
                document.getElementById('editMiddleInitial').value = editBtn.getAttribute('data-mi');
                document.getElementById('editLastName').value = editBtn.getAttribute('data-lname');
                document.getElementById('editDepartment').value = editBtn.getAttribute('data-dept');
                document.getElementById('editPosition').value = editBtn.getAttribute('data-pos');
                document.getElementById('editEmail').value = editBtn.getAttribute('data-email');
                document.getElementById('editPhone').value = editBtn.getAttribute('data-phone');
                document.getElementById('editUsername').value = editBtn.getAttribute('data-username');
                
                // Password Handling
                document.getElementById('editPassword').value = ''; 
                document.getElementById('editPassword').placeholder = 'Enter new password or leave blank';
                
                // Role
                const roleId = editBtn.getAttribute('data-role-id');
                document.getElementById('editRoleSelect').value = roleId;

                if (editUsernameInput) setUsernameError(editUsernameInput, false);
                editModal.style.display = 'flex';
                return;
            }

            const deleteBtn = e.target.closest('.btn-delete');
            if (deleteBtn) {
                confirmYesBtn.setAttribute('data-id', deleteBtn.getAttribute('data-id'));
                deleteModal.style.display = 'flex';
            }
        });
    }

    // Password Toggles
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

    // Universal Close (Buttons Only - Background click listener removed)
    const closeElements = '.close-btn, .editClose-btn, .editbtn-cancel, .delbtn-cancel, #confirmNo';
    document.querySelectorAll(closeElements).forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            addUserModal.style.display = 'none';
            editModal.style.display = 'none';
            deleteModal.style.display = 'none';
        });
    });

    // --- Form Submissions ---

    // ADD USER
    if (addUserForm) {
        addUserForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (addUsernameInput && addUsernameInput.dataset.isInvalid === "true") {
                alert("Cannot proceed. Please choose a different username."); return;
            }

            const formData = new FormData(this);

            fetch('actions/add_user.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') { alert(data.message); location.reload(); } 
                    else { alert("Error: " + data.message); }
                });
        });
    }

    // EDIT USER
    if (editUserForm) {
        editUserForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (editUsernameInput && editUsernameInput.dataset.isInvalid === "true") {
                alert("Cannot proceed. Please choose a different username."); return;
            }

            const formData = new FormData(this);

            fetch('actions/edit_user.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') { alert(data.message); location.reload(); } 
                    else { alert("Error: " + data.message); }
                });
        });
    }

    // DELETE USER
    if (confirmYesBtn) {
        confirmYesBtn.addEventListener('click', function () {
            const formData = new FormData();
            formData.append('user_id', this.getAttribute('data-id'));
            fetch('actions/delete_user.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') { alert(data.message); location.reload(); } 
                    else { alert("Error: " + data.message); }
                });
        });
    }
});