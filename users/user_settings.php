<?php
require_once '../config.php';
require_once '../config_session.php';
checkLogin();

 $user_id = getCurrentUserId();
 $message = '';
 $error = '';
 $success = '';

// --- HANDLE SETTINGS UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Handle Password Change
    if (isset($_POST['change_password'])) {
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        try {
            $stmt = $pdo->prepare("SELECT password FROM tbl_users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch();

            // CHANGE: Removed password_verify() for direct string comparison
            if (!$user_data || $current_pass !== $user_data['password']) {
                $error = "Incorrect current password.";
            } elseif (strlen($new_pass) < 6) {
                $error = "New password must be at least 6 characters long.";
            } elseif ($new_pass !== $confirm_pass) {
                $error = "New passwords do not match.";
            } else {
                // CHANGE: Removed password_hash() to save the password as plain text
                $update = $pdo->prepare("UPDATE tbl_users SET password = ? WHERE user_id = ?");
                $update->execute([$new_pass, $user_id]);
                $success = "Password updated successfully!";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }

    // 2. Handle Profile Info Update (Email/Phone)
    if (isset($_POST['update_contact_info'])) {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone_number'] ?? '');

        try {
            $update = $pdo->prepare("UPDATE tbl_users SET email = ?, phone_number = ? WHERE user_id = ?");
            $update->execute([$email, $phone, $user_id]);
            $success = "Contact information updated successfully!";
        } catch (PDOException $e) {
            $error = "Update failed: " . $e->getMessage();
        }
    }
}

// --- FETCH USER DATA ---
try {
    $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM tbl_users u LEFT JOIN tbl_roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) die("User not found");
    $full_name = trim($user['first_name'] . ' ' . ($user['middle_initial'] ? $user['middle_initial'] . '.' : '') . ' ' . $user['last_name']);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// --- SMART IMAGE LOGIC ---
 $default_cloud_url = "https://i.ibb.co/kVPtjmbK/default-profile-pic.png";
 $profile_image_src = $default_cloud_url; 
if (!empty($user['profile_image'])) {
    $profile_image_src = filter_var($user['profile_image'], FILTER_VALIDATE_URL) 
        ? htmlspecialchars($user['profile_image']) 
        : 'images/' . htmlspecialchars($user['profile_image']);
}
 $header_profile_image_src = $profile_image_src;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="https://upload.wikimedia.org/wikipedia/commons/thumb/3/35/Logo_Del_Monte.svg/2560px-Logo_Del_Monte.svg.png" type="image/x-icon" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/user_profile.css">
    <link rel="stylesheet" href="css/user_settings.css">
    <title>Settings | <?php echo htmlspecialchars($full_name); ?></title>
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
                <h1>Settings</h1>
                <p>Manage your account security and preferences</p>
            </div>

            <?php if ($success): ?>
                <div class="settings-alert success">
                    <i class='bx bx-check-circle'></i> 
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="settings-alert error">
                    <i class='bx bx-error-circle'></i> 
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="settings-container">
                <!-- LEFT COLUMN -->
                <div class="settings-main">
                    <!-- Password Section -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <div class="icon-box"><i class='bx bx-lock-alt'></i></div>
                            <div class="header-content">
                                <h3>Change Password</h3>
                                <p>Ensure your account is using a strong password</p>
                            </div>
                        </div>
                        <form class="settings-form" method="POST">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" required placeholder="Enter current password">
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" required placeholder="At least 6 characters">
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" required placeholder="Re-type new password">
                            </div>
                            <button type="submit" name="change_password" class="btn-save">
                                <i class='bx bx-check-shield'></i> Update Password
                            </button>
                        </form>
                    </div>

                    <!-- Contact Info Section -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <div class="icon-box"><i class='bx bx-envelope'></i></div>
                            <div class="header-content">
                                <h3>Contact Information</h3>
                                <p>Update your email and phone number</p>
                            </div>
                        </div>
                        <form class="settings-form" method="POST">
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" placeholder="your.email@example.com">
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone_number" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>" placeholder="e.g. 09123456789">
                            </div>
                            <button type="submit" name="update_contact_info" class="btn-save">
                                <i class='bx bx-save'></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="settings-sidebar">
                    <!-- Theme Settings -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <div class="icon-box"><i class='bx bx-palette'></i></div>
                            <div class="header-content">
                                <h3>Appearance</h3>
                                <p>Customize your view</p>
                            </div>
                        </div>
                        <div class="theme-options">
                            <div class="theme-option active" id="theme-light" onclick="setTheme('light')">
                                <i class='bx bx-sun'></i>
                                <span>Light</span>
                            </div>
                            <div class="theme-option" id="theme-dark" onclick="setTheme('dark')">
                                <i class='bx bx-moon'></i>
                                <span>Dark</span>
                            </div>
                        </div>
                    </div>

                    <!-- Account Info -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <div class="icon-box"><i class='bx bx-user'></i></div>
                            <div class="header-content">
                                <h3>Account Details</h3>
                                <p>Your system information</p>
                            </div>
                        </div>
                        <div class="account-info-list">
                            <div class="account-info-item">
                                <label>Username</label>
                                <span><?= htmlspecialchars($user['username']) ?></span>
                            </div>
                            <div class="account-info-item">
                                <label>Role</label>
                                <span><?= htmlspecialchars($user['role_name']) ?></span>
                            </div>
                            <div class="account-info-item">
                                <label>Department</label>
                                <span><?= htmlspecialchars($user['department'] ?? 'N/A') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Login Activity -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <div class="icon-box"><i class='bx bx-time'></i></div>
                            <div class="header-content">
                                <h3>Login Activity</h3>
                                <p>Your recent account history</p>
                            </div>
                        </div>

                        <div class="activity-item">
                            <i class='bx bx-calendar-check'></i>
                            <div class="detail">
                                <strong>Member Since</strong>
                                <span>
                                    <?php 
                                    if(isset($user['date_added']) && $user['date_added'] != '0000-00-00 00:00:00') {
                                        $date = new DateTime($user['date_added']);
                                        echo $date->format('M d, Y');
                                    } else {
                                        echo "N/A";
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>

                        <div class="activity-item">
                            <i class='bx bx-edit'></i>
                            <div class="detail">
                                <strong>Last Updated</strong>
                                <span>
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
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </section>
    
    <script src="js/user_profile.js"></script>
    <script>
        function setTheme(theme) {
            document.querySelectorAll('.theme-option').forEach(el => el.classList.remove('active'));
            const themeBtn = document.getElementById('theme-' + theme);
            if(themeBtn) themeBtn.classList.add('active');

            if (theme === 'dark') {
                document.body.classList.add('dark');
            } else {
                document.body.classList.remove('dark');
            }
            localStorage.setItem('theme', theme);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'light';
            setTheme(savedTheme);
        });
    </script>
</body>
</html>