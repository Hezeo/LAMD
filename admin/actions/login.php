<?php
session_start();
require_once '../../config.php';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $_SESSION['admin_login_username'] = $username; // Save for repopulating

    try {
        $stmt = $pdo->prepare("SELECT * FROM tbl_admin WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && ($password === $admin['password'])) {
            // SUCCESS
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $admin['username'];

            // ==========================================
            // INSERT ACTIVITY LOG
            // ==========================================
            try {
                $logStmt = $pdo->prepare("INSERT INTO tbl_activity_logs (activity, user_id, admin_id, dateAdded) VALUES (:activity, :user_id, :admin_id, NOW())");
                $logStmt->execute([
                    ':activity' => 'Successfully logged in',
                    ':user_id'  => null, // NULL because an admin is logging in
                    ':admin_id' => $admin['admin_id'] 
                ]);
            } catch (PDOException $logErr) {
                // Fail silently. Do not block admin login if logging fails.
            }
            // ==========================================

            // GO BACK TWO LEVELS to reach the main folder
            header("Location: ../admin_dashboard.php");
            exit();
        } else {
            // WRONG CREDENTIALS
            $_SESSION['admin_login_error'] = "Access Denied: Invalid Admin Credentials";
            // GO BACK TWO LEVELS to reach the login page
            header("Location: ../admin_login.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['admin_login_error'] = "System Error: " . $e->getMessage();
        header("Location: ../admin_login.php");
        exit();
    }
}
?>