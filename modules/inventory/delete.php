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
    $usage_stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN action = 'Borrowed' THEN quantity WHEN action = 'Used' THEN quantity WHEN action = 'Returned' THEN -quantity ELSE 0 END), 0) AS allocated_quantity FROM logbook WHERE item_id = ?");
    $usage_stmt->bind_param("i", $id);
    $usage_stmt->execute();
    $allocated_quantity = (int)$usage_stmt->get_result()->fetch_assoc()['allocated_quantity'];

    if ($allocated_quantity > 0) {
        header("Location: /stocktrack/modules/inventory/index.php?error=" . urlencode("This item cannot be deleted because {$allocated_quantity} item(s) are currently allocated or borrowed."));
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM items WHERE item_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    recordAudit('item_deleted', 'item', $id);
    header("Location: /stocktrack/modules/inventory/index.php?success=Item deleted successfully.");
} else {
    header("Location: /stocktrack/modules/inventory/index.php");
}
exit();
