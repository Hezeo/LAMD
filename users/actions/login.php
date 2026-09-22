<?php
// actions/login.php - ALL PROCESSING LOGIC GOES HERE
session_start();
require_once '../../config.php';
require_once '../../config_session.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {

    // Get form data
    $user_id = trim($_POST['user_id']);
    $password = $_POST['password'];

    // Store user_id in session to repopulate form if error
    $_SESSION['login_username'] = $user_id;

    // Basic validation
    if (empty($user_id) || empty($password)) {
        $_SESSION['login_error'] = "Please fill in all fields";
        header("Location: ../user_login.php");
        exit();
    }

    try {
        // Prepare SQL statement
        $stmt = $pdo->prepare("SELECT * FROM tbl_users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        // Check if user exists
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Check password: first plain text comparison (if passwords are stored as plain text)
            $password_match = false;

            if ($password === $user['password']) {
                $password_match = true;
            }
            // Otherwise, try hashed password verification
            else if (isset($user['password']) && password_verify($password, $user['password'])) {
                $password_match = true;
            }

            if ($password_match) {
                // --- LOGIN SUCCESS ---

                // 1. Assign session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['logged_in'] = true;

                // ==========================================
                // 2. INSERT ACTIVITY LOG (UPDATED)
                // ==========================================
                try {
                    $logStmt = $pdo->prepare("INSERT INTO tbl_activity_logs (activity, action, user_id, admin_id, dateAdded) VALUES (:activity, :action, :user_id, :admin_id, NOW())");
                    $logStmt->execute([
                        ':activity' => 'Successfully logged in',
                        ':action'   => 'login',
                        ':user_id'  => $user['user_id'],
                        ':admin_id' => null // NULL because a regular user is logging in
                    ]);
                } catch (PDOException $logErr) {
                    // Fail silently. Do not interrupt the user's login if logging fails.
                }
                // ==========================================

                // 3. LOAD PERMISSIONS IMMEDIATELY
                // This populates $_SESSION['user_permissions'] so hasPermission() works now.
                loadUserPermissions($user['user_id'], $pdo);

                // --- START: Force Password Change Logic ---
                // Check if the user's status is 'pending' in the database
                if (isset($user['force_password_change']) && $user['force_password_change'] === 'pending') {
                    // Redirect to change password page immediately
                    // We exit here so the permission loop below is ignored
                    header("Location: ../change_password.php");
                    exit();
                }
                // --- END: Force Password Change Logic ---

                // Clear error messages
                unset($_SESSION['login_error']);
                unset($_SESSION['login_username']);

                // 4. DETERMINE REDIRECTION BASED ON PERMISSIONS
                $redirect_url = '';

                // Define the pages and their corresponding permission names (Must match your DB)
                $access_map = [
                    'user_dashboard.php' => 'Dashboard',
                    'land_owners.php'    => 'Land Owners',
                    'user_exp.php'       => 'Expiry Tracker',
                    'user_report.php'    => 'Reports Generator',
                    'user_profile.php'   => null // Allow access to profile without specific permission
                ];

                // Loop through the map to find the first page they can access
                foreach ($access_map as $page => $permission_name) {
                    // If permission_name is null (like for profile), or user has the permission
                    if ($permission_name === null || hasPermission($permission_name)) {
                        $redirect_url = $page;
                        break; // Stop at the first allowed page
                    }
                }

                // 5. REDIRECT
                if (!empty($redirect_url)) {
                    header("Location: ../" . $redirect_url);
                } else {
                    // If loop finished and no page was found, user has NO permissions
                    // Instead of destroying session, redirect to profile page
                    header("Location: ../user_profile.php");
                }
                exit();
            } else {
                $_SESSION['login_error'] = "Wrong User ID or password";
                header("Location: ../user_login.php");
                exit();
            }
        } else {
            $_SESSION['login_error'] = "Wrong User ID or password";
            header("Location: ../user_login.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['login_error'] = "Database error: " . $e->getMessage();
        header("Location: ../user_login.php");
        exit();
    }
} else {
    // If someone tries to access this file directly without submitting form
    header("Location: ../user_login.php");
    exit();
}
