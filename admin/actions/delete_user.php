<?php
require_once '../../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? '';

    if (empty($user_id)) {
        echo json_encode(['status' => 'error', 'message' => 'User ID is missing.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM tbl_users WHERE user_id = :id");
        $stmt->execute([':id' => $user_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'User removed successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'User not found or already deleted.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>