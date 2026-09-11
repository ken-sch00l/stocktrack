<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('delete');
require_once '../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /stocktrack/modules/inventory/index.php");
    exit();
}

verify_csrf_token();
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id) {
    $stmt = $conn->prepare("DELETE FROM items WHERE item_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    recordAudit('item_deleted', 'item', $id);
    header("Location: /stocktrack/modules/inventory/index.php?success=Item deleted successfully.");
} else {
    header("Location: /stocktrack/modules/inventory/index.php");
}
exit();
