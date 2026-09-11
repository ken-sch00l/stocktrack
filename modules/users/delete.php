<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('manage_users');
require_once '../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /stocktrack/modules/users/index.php");
    exit();
}

verify_csrf_token();
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id) {
    $check = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $user = $check->get_result()->fetch_assoc();

    if ($user && $user['username'] !== 'admin') {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        recordAudit('user_deleted', 'user', $id);
        header("Location: /stocktrack/modules/users/index.php?success=User deleted successfully.");
    } else {
        header("Location: /stocktrack/modules/users/index.php");
    }
} else {
    header("Location: /stocktrack/modules/users/index.php");
}
exit();
