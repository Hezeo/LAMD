<?php
require_once '../config.php';
require_once '../config_session.php';
checkLogin();

 $user_id = getCurrentUserId();
 $message = '';
 $error = '';

// --- HANDLE PROFILE UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username)) {
        $error = "Username is required.";
    } else {
        try {
            $checkStmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE username = ? AND user_id != ?");
            $checkStmt->execute([$username, $user_id]);

            if ($checkStmt->rowCount() > 0) {
                $error = "The username '$username' is already taken.";
            } else {
                if (!empty($password)) {
                    $password = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "UPDATE tbl_users SET email=?, phone_number=?, username=?, password=? WHERE user_id=?";
                    $params = [$email, $phone_number, $username, $password, $user_id];
                } else {
                    $sql = "UPDATE tbl_users SET email=?, phone_number=?, username=? WHERE user_id=?";
                    $params = [$email, $phone_number, $username, $user_id];
                }

                $updateStmt = $pdo->prepare($sql);
                $updateStmt->execute($params);
                $message = "Profile updated successfully!";

                // Re-fetch user data after update
                $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM tbl_users u LEFT JOIN tbl_roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            }
        } catch (PDOException $e) {
            $error = "Update failed: " . $e->getMessage();
        }
    }
}

// --- FETCH USER DATA (WITH ROLE NAME) ---
try {
    $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM tbl_users u LEFT JOIN tbl_roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) die("User not found");

    $full_name = trim($user['first_name'] . ' ' . ($user['middle_initial'] ? $user['middle_initial'] . '.' : '') . ' ' . $user['last_name']);
    $full_name = preg_replace('/\s+/', ' ', $full_name);
    
    // Calculate account age
    $now = new DateTime();
    $created = new DateTime($user['date_added']);
    $interval = $now->diff($created);
    $account_age_days = $interval->format('%a');
    $account_age_text = '';
    if ($interval->y > 0) $account_age_text .= $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ', ';
    if ($interval->m > 0) $account_age_text .= $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ', ';
    $account_age_text .= $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// --- SMART IMAGE LOGIC ---
 $default_cloud_url = "https://i.ibb.co/kVPtjmbK/default-profile-pic.png";
 $profile_image_src = $default_cloud_url; 

if (!empty($user['profile_image'])) {
    if (filter_var($user['profile_image'], FILTER_VALIDATE_URL)) {
        $profile_image_src = htmlspecialchars($user['profile_image']);
    } else {
        $profile_image_src = '../users/images/' . htmlspecialchars($user['profile_image']);
    }
}

 $header_profile_image_src = $profile_image_src;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon"
        href="https://upload.wikimedia.org/wikipedia/commons/thumb/3/35/Logo_Del_Monte.svg/2560px-Logo_Del_Monte.svg.png"
        type="image-x-icon" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/user_profile.css">
    <title><?php echo htmlspecialchars($full_name); ?> | Land Asset Management Department</title>
</head>

<body>
    <!-- FIX: PREVENT FLASH OF WRONG THEME -->
    <script>
        (function() {
            try {
                const theme = localStorage.getItem('theme');
                if (theme === 'dark') {
                    document.body.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    <!-- END FIX -->

	<!-- SIDEBAR -->
	<section id="sidebar">
		<?php include 'components/user_sidebar.php'; ?>
	</section>
	<!-- SIDEBAR -->

    <!-- <section id="sidebar">
        <a href="#" class="brand">
            <img src="../images/web_icon.png" alt="LAND ASSET MANAGEMENT" style="height: 40px; width: auto; object-fit: contain; margin-right: 10px;">
            <span class="text">LAND ASSET MANAGEMENT DEPARTMENT</span>
        </a>
        <ul class="side-menu top">
            <?php if (hasPermission('Dashboard')): ?>
                <li><a href="user_dashboard.php"><i class='bx bxs-dashboard bx-sm'></i><span class="text">Dashboard</span></a></li>
            <?php endif; ?>
            <?php if (hasPermission('Land Owners')): ?>
                <li><a href="land_owners.php"><i class='bx bxs-user bx-sm'></i><span class="text">Land Owners</span></a></li>
            <?php endif; ?>
            <?php if (hasPermission('Expiry Tracker')): ?>
                <li><a href="user_exp.php"><i class='bx bx-calendar-exclamation bx-sm'></i><span class="text">Expiry Tracker</span></a></li>
            <?php endif; ?>
            <?php if (hasPermission('Reports Generator')): ?>
                <li><a href="user_report.php"><i class='bx bx-line-chart bx-sm'></i><span class="text">Reports Generator</span></a></li>
            <?php endif; ?>
        </ul>
    </section> -->
    
    <section id="content">
        <?php include 'components/user_navbar.php'; ?>

        <main>
            <div class="page-header">
                <h1>My Profile</h1>
                <p>View and manage your account information</p>
            </div>

            <!-- <?php if ($message): ?>
                <div class="profile-alert success">
                    <i class='bx bx-check-circle'></i> 
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error && !isset($_POST['update_profile'])): ?>
                <div class="profile-alert error">
                    <i class='bx bx-error-circle'></i> 
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?> -->

            <div class="profile-container">
                <!-- MAIN COLUMN -->
                <div class="profile-main">
                    <!-- Hero Profile Card -->
                    <div class="hero-profile-card">
                        <div class="hero-banner"></div>
                        <div class="hero-content">
                            <div class="hero-avatar-section">
                                <div class="hero-avatar-wrapper">
                                    <img id="profilePic" class="hero-avatar" src="<?php echo $profile_image_src; ?>" alt="Profile Picture">
                                    <button id="changePhotoBtn" class="avatar-edit-btn" title="Change Photo">
                                        <i class='bx bxs-camera'></i>
                                    </button>
                                    <input type="file" id="profileImageInput" name="profile_image" accept="image/*" style="display: none;">
                                </div>
                                <div class="hero-name-section">
                                    <h2 class="hero-name"><?php echo htmlspecialchars($full_name); ?></h2>
                                    <div class="hero-meta">
                                        <span class="hero-badge badge-role">
                                            <i class='bx bxs-shield-alt'></i>
                                            <?php echo htmlspecialchars($user['role_name'] ?? 'No Role'); ?>
                                        </span>
                                        <span class="hero-badge badge-position">
                                            <i class='bx bxs-briefcase'></i>
                                            <?php echo htmlspecialchars($user['position'] ?? 'Not Set'); ?>
                                        </span>
                                        <span class="hero-badge badge-department">
                                            <i class='bx bxs-buildings'></i>
                                            <?php echo htmlspecialchars($user['department'] ?? 'Not Set'); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="hero-actions">
                                    <button type="button" id="openEditModal" class="btn-edit-profile">
                                        <i class='bx bxs-edit'></i> Edit Profile
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Card -->
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <div class="icon-box"><i class='bx bx-address-book'></i></div>
                            <div class="header-content">
                                <h3>Contact Information</h3>
                                <p>Your email and phone details</p>
                            </div>
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-icon"><i class='bx bxs-envelope'></i></div>
                                <div class="info-content">
                                    <div class="info-label">Email Address</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon"><i class='bx bxs-phone'></i></div>
                                <div class="info-content">
                                    <div class="info-label">Phone Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['phone_number'] ?? 'Not Set'); ?></div>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon"><i class='bx bxs-user-badge'></i></div>
                                <div class="info-content">
                                    <div class="info-label">Username</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['username']); ?></div>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon"><i class='bx bx-id-card'></i></div>
                                <div class="info-content">
                                    <div class="info-label">User ID</div>
                                    <div class="info-value">#<?php echo htmlspecialchars($user['user_id']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information Card -->
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <div class="icon-box"><i class='bx bxs-user-detail'></i></div>
                            <div class="header-content">
                                <h3>Personal Information</h3>
                                <p>Your basic profile details</p>
                            </div>
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-icon"><i class='bx bxs-user'></i></div>
                                <div class="info-content">
                                    <div class="info-label">First Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['first_name']); ?></div>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon"><i class='bx bxs-user'></i></div>
                                <div class="info-content">
                                    <div class="info-label">Last Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['last_name']); ?></div>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon"><i class='bx bxs-text'></i></div>
                                <div class="info-content">
                                    <div class="info-label">Middle Initial</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['middle_initial'] ? $user['middle_initial'] . '.' : 'Not Set'); ?></div>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon"><i class='bx bxs-buildings'></i></div>
                                <div class="info-content">
                                    <div class="info-label">Department</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['department'] ?? 'Not Set'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SIDEBAR COLUMN -->
                <div class="profile-sidebar">
                    <!-- Account Activity Card -->
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <div class="icon-box"><i class='bx bx-time-five'></i></div>
                            <div class="header-content">
                                <h3>Account Activity</h3>
                                <p>Your account timeline</p>
                            </div>
                        </div>
                        <div class="meta-list">
                            <div class="meta-item">
                                <i class='bx bx-calendar-check'></i>
                                <div class="meta-content">
                                    <div class="meta-label">Member Since</div>
                                    <div class="meta-value">
                                        <?php 
                                        if(isset($user['date_added']) && $user['date_added'] != '0000-00-00 00:00:00') {
                                            $date = new DateTime($user['date_added']);
                                            echo $date->format('M d, Y');
                                        } else {
                                            echo "N/A";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-edit'></i>
                                <div class="meta-content">
                                    <div class="meta-label">Last Updated</div>
                                    <div class="meta-value">
                                        <?php 
                                        if(isset($user['updated_at']) && $user['updated_at'] != '0000-00-00 00:00:00') {
                                            $date = new DateTime($user['updated_at']);
                                            echo $date->format('M d, Y h:i A');
                                        } elseif(isset($user['date_added'])) {
                                            $date = new DateTime($user['date_added']);
                                            echo "Initial Setup (" . $date->format('M d, Y') . ")";
                                        } else {
                                            echo "N/A";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-timer'></i>
                                <div class="meta-content">
                                    <div class="meta-label">Account Age</div>
                                    <div class="meta-value"><?= $account_age_text ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Info Card -->
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <div class="icon-box"><i class='bx bx-info-circle'></i></div>
                            <div class="header-content">
                                <h3>System Info</h3>
                                <p>Technical account details</p>
                            </div>
                        </div>
                        <div class="meta-list">
                            <div class="meta-item">
                                <i class='bx bx-hash'></i>
                                <div class="meta-content">
                                    <div class="meta-label">User ID</div>
                                    <div class="meta-value">#<?= htmlspecialchars($user['user_id']) ?></div>
                                </div>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-shield-alt'></i>
                                <div class="meta-content">
                                    <div class="meta-label">Role ID</div>
                                    <div class="meta-value"><?= htmlspecialchars($user['role_id'] ?? 'N/A') ?></div>
                                </div>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-calendar'></i>
                                <div class="meta-label">Total Days Active</div>
                                <div class="meta-value"><?= $account_age_days ?> days</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Modal -->
            <div id="editProfileModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="modal-header-left">
                            <div class="modal-icon-box">
                                <i class='bx bxs-user-circle'></i>
                            </div>
                            <div>
                                <h2>Edit Profile</h2>
                                <p>Update your account information</p>
                            </div>
                        </div>
                        <button class="close-modal" id="closeModalBtn">&times;</button>
                    </div>

                    <?php if ($error): ?>
                        <div class="modal-error-box">
                            <i class='bx bx-error-circle'></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="modal-form-row">
                            <div class="modal-form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name"
                                    value="<?= htmlspecialchars($user['first_name']) ?>" readonly
                                    class="readonly-input">
                            </div>
                            <div class="modal-form-group">
                                <label>Middle Initial</label>
                                <input type="text" name="middle_initial"
                                    value="<?= htmlspecialchars($user['middle_initial']) ?>" readonly
                                    class="readonly-input">
                            </div>
                        </div>

                        <div class="modal-form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>"
                                readonly class="readonly-input">
                        </div>

                        <div class="modal-form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" placeholder="your.email@example.com">
                        </div>

                        <div class="modal-form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone_number"
                                value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>"
                                placeholder="e.g. 09123456789">
                        </div>

                        <div class="modal-form-row">
                            <div class="modal-form-group">
                                <label>Username <span style="color: var(--red);">*</span></label>
                                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>"
                                    required placeholder="Enter username">
                            </div>
                            <div class="modal-form-group">
                                <label>New Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="password" id="passwordInput"
                                        placeholder="Leave blank to keep current">
                                    <button type="button" class="toggle-password" id="togglePassword">
                                        <i class='bx bx-hide'></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn-cancel-modal" id="cancelModalBtn">Cancel</button>
                            <button type="submit" name="update_profile" class="btn-save-profile">
                                <i class='bx bx-check'></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </main>
    </section>
    
    <script src="js/user_profile.js"></script>

    <script>
        // Theme Sync
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark');
        } else {
            document.body.classList.remove('dark');
        }

        // Modal Controls
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById("editProfileModal");
            const openBtn = document.getElementById("openEditModal");
            const closeBtn = document.getElementById("closeModalBtn");
            const cancelBtn = document.getElementById("cancelModalBtn");

            if (openBtn) {
                openBtn.addEventListener('click', () => {
                    modal.style.display = "block";
                    document.body.style.overflow = "hidden";
                });
            }

            function closeModal() {
                modal.style.display = "none";
                document.body.style.overflow = "";
            }

            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

            // Close on backdrop click
            window.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal();
                }
            });

            // Close on ESC key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal.style.display === "block") {
                    closeModal();
                }
            });

            // Open modal if there's an error from form submission
            <?php if ($error && isset($_POST['update_profile'])): ?>
                modal.style.display = "block";
                document.body.style.overflow = "hidden";
            <?php endif; ?>

            // Password Toggle
            const toggleBtn = document.getElementById("togglePassword");
            const passwordInput = document.getElementById("passwordInput");
            
            if (toggleBtn && passwordInput) {
                toggleBtn.addEventListener('click', () => {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    const icon = toggleBtn.querySelector('i');
                    icon.className = type === 'password' ? 'bx bx-hide' : 'bx bx-show';
                });
            }
        });
    </script>
</body>
</html>