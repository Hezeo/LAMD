<?php
// logout.php
session_start();
require_once '../../config.php'; // Added: Needed for database connection

// 1. GET USER ID BEFORE DESTROYING THE SESSION
 $user_id = $_SESSION['user_id'] ?? null;

// 2. INSERT ACTIVITY LOG
if ($user_id) {
    try {
        $logStmt = $pdo->prepare("INSERT INTO tbl_activity_logs (activity, action, user_id, admin_id, dateAdded) VALUES (:activity, :action, :user_id, :admin_id, NOW())");
        $logStmt->execute([
            ':activity' => 'Successfully logged out',
            ':action'   => 'logout',
            ':user_id'  => $user_id,
            ':admin_id' => null
        ]);
    } catch (PDOException $logErr) {
        // Fail silently
    }
}

// 3. NOW destroy the session and redirect
session_destroy();
header('Location: ../user_login.php');
exit();
?>