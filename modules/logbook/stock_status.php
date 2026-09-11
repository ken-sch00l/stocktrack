<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('add');
require_once '../../includes/db.php';

header('Content-Type: application/json');

$item_id = (int)($_GET['item_id'] ?? 0);
if (!$item_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid item.']);
    exit();
}

$stmt = $conn->prepare("SELECT i.quantity AS total_quantity, COALESCE(SUM(CASE WHEN l.action = 'Borrowed' AND l.date_returned IS NULL THEN l.quantity WHEN l.action = 'Used' THEN l.quantity WHEN l.action = 'Returned' THEN -l.quantity ELSE 0 END), 0) AS allocated_quantity, COALESCE(SUM(CASE WHEN l.action = 'Borrowed' AND l.date_returned IS NULL THEN l.quantity WHEN l.action = 'Returned' THEN -l.quantity ELSE 0 END), 0) AS borrowed_quantity FROM items i LEFT JOIN logbook l ON l.item_id = i.item_id WHERE i.item_id = ? GROUP BY i.item_id, i.quantity");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    http_response_code(404);
    echo json_encode(['error' => 'Item not found.']);
    exit();
}

echo json_encode([
    'total' => (int)$item['total_quantity'],
    'available' => max(0, (int)$item['total_quantity'] - (int)$item['allocated_quantity']),
    'borrowed' => max(0, (int)$item['borrowed_quantity'])
]);
