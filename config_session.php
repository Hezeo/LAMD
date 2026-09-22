<?php
// Only start the session if it's not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =============================================================
// --- AUTOMATIC STATUS UPDATE LOGIC (GLOBAL TRIGGER) ---
// =============================================================

try {
    // Ensure $pdo exists before trying to use it
    if (isset($pdo)) {
        $sql_update_status = "
            UPDATE tbl_main 
            SET lease_status = 
                CASE 
                    WHEN expiry_date < CURDATE() THEN 'Expired'
                    WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 YEAR) THEN 'Near Expiration'
                    ELSE 'Active'
                END
            WHERE expiry_date IS NOT NULL 
              AND expiry_date != '0000-00-00'
        ";
        
        $stmt_update = $pdo->prepare($sql_update_status);
        $stmt_update->execute();
    }
} catch (PDOException $e) {
    // Optional: Log error if needed
}

// =============================================================
// --- ROLE & PERMISSION LOADER ---
// =============================================================

/**
 * Loads user role and permissions into the session.
 * Stores Name, Link, Icon, and Access Level.
 */
function loadUserPermissions($user_id, $pdo) {
    // 1. Get Role ID
    $stmt = $pdo->prepare("SELECT role_id FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $role_id = $stmt->fetchColumn();

    // 2. Initialize Session Data
    $_SESSION['user_permissions'] = []; 
    $_SESSION['user_role_id'] = $role_id;

    if ($role_id) {
        // Updated SQL: Added p.permission_icon
        $permStmt = $pdo->prepare("
            SELECT p.permission_name, p.permission_link, p.permission_icon, rp.can 
            FROM tbl_role_permissions rp
            JOIN tbl_permissions p ON rp.permission_id = p.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.permission_id ASC
        ");
        $permStmt->execute([$role_id]);
        
        // Store as a structured array including the icon
        while ($row = $permStmt->fetch(PDO::FETCH_ASSOC)) {
            $_SESSION['user_permissions'][$row['permission_name']] = [
                'link' => $row['permission_link'],
                'icon' => $row['permission_icon'], // New Icon Key
                'can'  => $row['can']
            ];
        }
    }
}

/**
 * Checks if user has access to a specific permission.
 */
function hasPermission($permission_name) {
    if (!isset($_SESSION['user_permissions'])) {
        return false;
    }

    if (!isset($_SESSION['user_permissions'][$permission_name])) {
        return false;
    }

    // Handle new array structure
    $permission_data = $_SESSION['user_permissions'][$permission_name];
    
    // If it's an array (new format), check the 'can' key. Otherwise fallback to old string format.
    $access_level = is_array($permission_data) ? ($permission_data['can'] ?? null) : $permission_data;

    // Return true if they have view OR edit access
    return ($access_level === 'view' || $access_level === 'edit');
}

/**
 * Blocks access if user does not have the specific permission.
 * Use this at the top of pages.
 */
function requirePermission($permission_name) {
    if (!hasPermission($permission_name)) {
        // Stop the page and show error with a Logout link
        die("
        <div style='text-align:center; padding:50px; font-family: sans-serif; border: 1px solid #eee; max-width: 400px; margin: 50px auto; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
            <h1 style='color: #d32f2f; margin-bottom: 10px;'>Access Denied</h1>
            <p style='color: #666; margin-bottom: 20px;'>You do not have permission to view this page.</p>
            <a href='actions/logout.php' style='color: #fff; background-color: #d32f2f; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Logout</a>
        </div>
        ");
    }
}

// Check if user is logged in
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: user_login.php');
        exit();
    }
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// =============================================================
// --- AUTO-LOAD PERMISSIONS ---
// =============================================================
// Always reload permissions if user is logged in to ensure data is fresh from DB

// if (isset($_SESSION['user_id']) && isset($pdo) && !isset($_SESSION['user_permissions'])) {
//     loadUserPermissions($_SESSION['user_id'], $pdo);
// }

if (isset($_SESSION['user_id']) && isset($pdo)) {
    loadUserPermissions($_SESSION['user_id'], $pdo);
}
?>