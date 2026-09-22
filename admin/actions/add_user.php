<?php
require_once '../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect inputs
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
    $permissionsJson= $_POST['permissions_json'] ?? '[]';

    // Validation
    if (empty($first_name) || empty($last_name) || empty($username) || empty($password) || empty($role_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Required fields are missing (including Role).']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 2. Insert User
        // NOTICE: We replaced :user_id with the MySQL DATE_FORMAT function!
        $sql = "INSERT INTO tbl_users (user_id, first_name, middle_initial, last_name, department, position, email, phone_number, username, password, profile_image, role_id, date_added) 
                VALUES (DATE_FORMAT(NOW(), '%y%m%d%H%i'), :fname, :mi, :lname, :dept, :pos, :email, :phone, :uname, :pass, :def_prof_img, :role_id, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            // ':user_id' removed from here!
            ':fname'        => $first_name,
            ':mi'           => $middle_initial,
            ':lname'        => $last_name,
            ':dept'         => $department,
            ':pos'          => $position,
            ':email'        => $email,
            ':phone'        => $phone_number,
            ':uname'        => $username,
            ':pass'         => $password, 
            ':def_prof_img' => 'default_profile_img_pineapple.png',
            ':role_id'      => $role_id
        ]);

        // 3. Update Role Permissions
        $permissions = json_decode($permissionsJson, true);
        
        $delStmt = $pdo->prepare("DELETE FROM tbl_role_permissions WHERE role_id = ?");
        $delStmt->execute([$role_id]);

        if (!empty($permissions)) {
            $permStmt = $pdo->prepare("INSERT INTO tbl_role_permissions (role_id, permission_id, can, dateAdded) VALUES (?, ?, ?, NOW())");
            foreach ($permissions as $p) {
                $permStmt->execute([$role_id, $p['id'], $p['can']]);
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'User registered and Role permissions updated!']);

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>