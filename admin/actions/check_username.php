<?php
require_once '../../config.php';
header('Content-Type: application/json');

$username = $_GET['username'] ?? '';
$user_id  = $_GET['user_id'] ?? ''; // Pass this for EDIT modal to ignore current user

if (empty($username)) {
    echo json_encode(['exists' => false]);
    exit;
}

// If user_id is provided, we ignore that ID (so you can keep your own username when editing)
$sql = "SELECT COUNT(*) FROM tbl_users WHERE username = :uname";
if (!empty($user_id)) {
    $sql .= " AND user_id != :id";
}

$stmt = $pdo->prepare($sql);
$params = [':uname' => $username];
if (!empty($user_id)) { $params[':id'] = $user_id; }

$stmt->execute($params);
$exists = $stmt->fetchColumn() > 0;

echo json_encode(['exists' => $exists]);