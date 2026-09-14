<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('view_notifications');
require_once '../../includes/db.php';

$filter = $_GET['filter'] ?? 'all';
$allowed_filters = ['all', 'unread', 'borrowed', 'returned'];
$filter = in_array($filter, $allowed_filters, true) ? $filter : 'all';
$sort = $_GET['sort'] ?? 'date';
$direction = strtoupper($_GET['direction'] ?? 'DESC');
$sort_columns = ['date' => 'created_at', 'type' => 'notification_type', 'status' => 'is_read'];
$sort = array_key_exists($sort, $sort_columns) ? $sort : 'date';
$direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $notification_id = (int)($_POST['notification_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND recipient_user_id = ?");
    $stmt->bind_param("ii", $notification_id, $_SESSION['user_id']);
    $stmt->execute();
}

$notification_where = 'WHERE recipient_user_id = ?';
$notification_types = 'i';
$notification_params = [$_SESSION['user_id']];
if ($filter === 'unread') {
    $notification_where .= ' AND is_read = 0';
} elseif ($filter === 'borrowed') {
    $notification_where .= " AND notification_type = 'logbook_borrowed'";
} elseif ($filter === 'returned') {
    $notification_where .= " AND notification_type = 'logbook_returned'";
}
$notifications = $conn->prepare("SELECT * FROM notifications $notification_where ORDER BY {$sort_columns[$sort]} $direction, notification_id DESC LIMIT 100");
$notifications->bind_param($notification_types, ...$notification_params);
$notifications->execute();
$notifications = $notifications->get_result();
$notification_stats = $conn->prepare("SELECT COUNT(*) AS total_count, SUM(is_read = 0) AS unread_count, SUM(notification_type = 'logbook_borrowed') AS borrowed_count, SUM(notification_type = 'logbook_returned') AS returned_count FROM notifications WHERE recipient_user_id = ?");
$notification_stats->bind_param("i", $_SESSION['user_id']);
$notification_stats->execute();
$notification_stats = $notification_stats->get_result()->fetch_assoc();
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-bell me-2 text-primary"></i>Notifications</h4>
    <span class="text-muted small">Latest 100 notifications</span>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><a href="?filter=all" class="card stat-card stat-card-link text-center p-3 h-100"><div class="text-primary fs-2"><i class="bi bi-bell"></i></div><h3 class="fw-bold mb-0"><?php echo (int)($notification_stats['total_count'] ?? 0); ?></h3><small class="text-muted">All notifications</small></a></div>
    <div class="col-md-3"><a href="?filter=unread" class="card stat-card stat-card-link text-center p-3 h-100"><div class="text-warning fs-2"><i class="bi bi-envelope"></i></div><h3 class="fw-bold mb-0"><?php echo (int)($notification_stats['unread_count'] ?? 0); ?></h3><small class="text-muted">Unread</small></a></div>
    <div class="col-md-3"><a href="?filter=borrowed" class="card stat-card stat-card-link text-center p-3 h-100"><div class="text-danger fs-2"><i class="bi bi-box-arrow-right"></i></div><h3 class="fw-bold mb-0"><?php echo (int)($notification_stats['borrowed_count'] ?? 0); ?></h3><small class="text-muted">Borrowed alerts</small></a></div>
    <div class="col-md-3"><a href="?filter=returned" class="card stat-card stat-card-link text-center p-3 h-100"><div class="text-success fs-2"><i class="bi bi-box-arrow-in-left"></i></div><h3 class="fw-bold mb-0"><?php echo (int)($notification_stats['returned_count'] ?? 0); ?></h3><small class="text-muted">Returned alerts</small></a></div>
</div>
<div class="card border-0 shadow-sm mb-3 filter-toolbar">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <div class="col-md-4"><label class="form-label">Sort by</label><select name="sort" class="form-select"><option value="date" <?php echo $sort === 'date' ? 'selected' : ''; ?>>Date</option><option value="type" <?php echo $sort === 'type' ? 'selected' : ''; ?>>Type</option><option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>Read status</option></select></div>
            <div class="col-md-4"><label class="form-label">Direction</label><select name="direction" class="form-select"><option value="DESC" <?php echo $direction === 'DESC' ? 'selected' : ''; ?>>Descending</option><option value="ASC" <?php echo $direction === 'ASC' ? 'selected' : ''; ?>>Ascending</option></select></div>
            <div class="col-md-4"><button type="submit" class="btn btn-primary"><i class="bi bi-sort-down me-1"></i>Apply sorting</button></div>
        </form>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="list-group list-group-flush">
        <?php if ($notifications->num_rows > 0): ?>
            <?php while ($notification = $notifications->fetch_assoc()): ?>
                <div class="list-group-item <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                    <div class="d-flex justify-content-between gap-3">
                        <div><strong><?php echo htmlspecialchars($notification['message']); ?></strong><br><small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></small></div>
                        <?php if (!$notification['is_read']): ?>
                            <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="notification_id" value="<?php echo (int)$notification['notification_id']; ?>"><button class="btn btn-sm btn-outline-primary" type="submit">Mark read</button></form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="list-group-item text-center text-muted py-4">No notifications yet.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>