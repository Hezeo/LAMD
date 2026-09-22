<?php
// logout.php
session_start();
require_once '../../config.php'; // Added: Needed for database connection

// 1. GET ADMIN USERNAME BEFORE DESTROYING THE SESSION
 $admin_username = $_SESSION['admin_username'] ?? null;
 $admin_id = null;

// 2. QUICK LOOKUP TO GET ADMIN_ID
if ($admin_username) {
    try {
        $stmt = $pdo->prepare("SELECT admin_id FROM tbl_admin WHERE username = :username");
        $stmt->execute(['username' => $admin_username]);
        $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($adminData) {
            $admin_id = $adminData['admin_id'];
        }
    } catch (PDOException $e) {
        // Ignore error, just proceed to logout
    }
}

// 3. INSERT ACTIVITY LOG
if ($admin_id) {
    try {
        $logStmt = $pdo->prepare("INSERT INTO tbl_activity_logs (activity, user_id, admin_id, dateAdded) VALUES (:activity, :user_id, :admin_id, NOW())");
        $logStmt->execute([
            ':activity' => 'Successfully logged out',
            ':user_id'  => null,
            ':admin_id' => $admin_id
        ]);
    } catch (PDOException $logErr) {
        // Fail silently
    }
}

// 4. NOW destroy the session and redirect
session_destroy();
header('Location: ../admin_login.php');
exit();
?>