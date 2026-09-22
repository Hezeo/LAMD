<?php
require_once '../../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect inputs
    $user_id        = $_POST['user_id'] ?? '';
    $first_name     = $_POST['first_name'] ?? '';
    $middle_initial = $_POST['middle_initial'] ?? '';
    $last_name      = $_POST['last_name'] ?? '';
    $department     = $_POST['department'] ?? '';
    $position       = $_POST['position'] ?? '';
    $email          = $_POST['email'] ?? '';
    $phone_number   = $_POST['phone_number'] ?? '';
    $username       = $_POST['username'] ?? '';
    $password       = $_POST['password'] ?? ''; 
    $role_id        = $_POST['role_id'] ?? NULL;
    
    // REMOVED: $permissionsJson. We do not touch permissions here.

    if (empty($user_id) || empty($first_name) || empty($last_name) || empty($username) || empty($role_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Required fields are missing.']);
        exit;
    }

    try {
        // No need for beginTransaction/commit for a single simple update, 
        // but keeping it is fine too. Removed the permission logic entirely.
        
        // 1. Update User Details ONLY
        $sql_pass = "";
        $params = [
            ':fname' => $first_name,
            ':mi'    => $middle_initial,
            ':lname' => $last_name,
            ':dept'  => $department,
            ':pos'   => $position,
            ':email' => $email,
            ':phone' => $phone_number,
            ':uname' => $username,
            ':role_id'=> $role_id,
            ':id'    => $user_id
        ];

        if (!empty($password)) {
            $sql_pass = ", password = :pass";
            $params[':pass'] = $password; // Hash in production!
        }

        $sql = "UPDATE tbl_users SET 
                first_name = :fname, 
                middle_initial = :mi, 
                last_name = :lname, 
                department = :dept, 
                position = :pos, 
                email = :email, 
                phone_number = :phone, 
                username = :uname 
                $sql_pass,
                role_id = :role_id
                WHERE user_id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // REMOVED: The entire section that DELETED permissions.
        // Permissions are managed ONLY in admin_role_permissions.php

        echo json_encode(['status' => 'success', 'message' => 'User updated successfully!']);

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>