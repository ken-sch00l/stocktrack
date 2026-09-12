<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('view_audit');
require_once '../../includes/db.php';

$filter = $_GET['filter'] ?? 'all';
$allowed_filters = ['all', 'login', 'inventory', 'users', 'security'];
$filter = in_array($filter, $allowed_filters, true) ? $filter : 'all';
$sort = $_GET['sort'] ?? 'date';
$direction = strtoupper($_GET['direction'] ?? 'DESC');
$sort_columns = ['date' => 'a.created_at', 'action' => 'a.action', 'entity' => 'a.entity_type', 'user' => 'u.full_name'];
$sort = array_key_exists($sort, $sort_columns) ? $sort : 'date';
$direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC';

$audit_where = '';
if ($filter === 'login') {
    $audit_where = "WHERE a.action IN ('login_success', 'login_failure')";
} elseif ($filter === 'inventory') {
    $audit_where = "WHERE a.action IN ('item_created', 'item_updated', 'item_deleted', 'logbook_created')";
} elseif ($filter === 'users') {
    $audit_where = "WHERE a.action IN ('user_created', 'user_updated', 'user_deleted', 'category_created', 'category_deleted')";
} elseif ($filter === 'security') {
    $audit_where = "WHERE a.action IN ('password_changed', 'login_failure')";
}

$logs = $conn->query("SELECT a.*, u.full_name FROM audit_log a LEFT JOIN users u ON a.user_id = u.user_id $audit_where ORDER BY {$sort_columns[$sort]} $direction, a.audit_id DESC LIMIT 100");
$audit_stats = $conn->query("SELECT COUNT(*) AS total_count, SUM(action IN ('login_success', 'login_failure')) AS login_count, SUM(action IN ('item_created', 'item_updated', 'item_deleted', 'logbook_created')) AS inventory_count, SUM(action IN ('user_created', 'user_updated', 'user_deleted', 'category_created', 'category_deleted')) AS user_count, SUM(action IN ('password_changed', 'login_failure')) AS security_count FROM audit_log")->fetch_assoc();
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-shield-check me-2 text-primary"></i>Activity Log</h4>
    <span class="text-muted small">Latest 100 events</span>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><a href="?filter=all" class="card stat-card stat-card-link text-center p-3 h-100"><div class="text-primary fs-2"><i class="bi bi-list-check"></i></div><h3 class="fw-bold mb-0"><?php echo (int)$audit_stats['total_count']; ?></h3><small class="text-muted">All activity</small></a></div>
    <div class="col-md-3"><a href="?filter=login" class="card stat-card stat-card-link text-center p-3 h-100"><div class="text-info fs-2"><i class="bi bi-box-arrow-in-right"></i></div><h3 class="fw-bold mb-0"><?php echo (int)$audit_stats['login_count']; ?></h3><small class="text-muted">Login activity</small></a></div>
    <div class="col-md-3"><a href="?filter=inventory" class="card stat-card stat-card-link text-center p-3 h-100"><div class="text-success fs-2"><i class="bi bi-box-seam"></i></div><h3 class="fw-bold mb-0"><?php echo (int)$audit_stats['inventory_count']; ?></h3><small class="text-muted">Inventory activity</small></a></div>
    <div class="col-md-3"><a href="?filter=users" class="card stat-card stat-card-link text-center p-3 h-100"><div class="text-warning fs-2"><i class="bi bi-people"></i></div><h3 class="fw-bold mb-0"><?php echo (int)$audit_stats['user_count']; ?></h3><small class="text-muted">User/admin activity</small></a></div>
    <div class="col-md-3"><a href="?filter=security" class="card stat-card stat-card-link text-center p-3 h-100"><div class="text-danger fs-2"><i class="bi bi-shield-exclamation"></i></div><h3 class="fw-bold mb-0"><?php echo (int)$audit_stats['security_count']; ?></h3><small class="text-muted">Security activity</small></a></div>
</div>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <div class="col-md-4"><label class="form-label">Sort by</label><select name="sort" class="form-select"><option value="date" <?php echo $sort === 'date' ? 'selected' : ''; ?>>Date</option><option value="action" <?php echo $sort === 'action' ? 'selected' : ''; ?>>Action</option><option value="entity" <?php echo $sort === 'entity' ? 'selected' : ''; ?>>Entity</option><option value="user" <?php echo $sort === 'user' ? 'selected' : ''; ?>>User</option></select></div>
            <div class="col-md-4"><label class="form-label">Direction</label><select name="direction" class="form-select"><option value="DESC" <?php echo $direction === 'DESC' ? 'selected' : ''; ?>>Descending</option><option value="ASC" <?php echo $direction === 'ASC' ? 'selected' : ''; ?>>Ascending</option></select></div>
            <div class="col-md-4"><button type="submit" class="btn btn-primary"><i class="bi bi-sort-down me-1"></i>Apply sorting</button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs->num_rows > 0): ?>
                        <?php while ($log = $logs->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($log['full_name'] ?? 'System/Unknown'); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($log['action']); ?></span></td>
                            <td><?php echo htmlspecialchars($log['entity_type'] ?? 'N/A'); ?><?php echo $log['entity_id'] ? ' #' . (int)$log['entity_id'] : ''; ?></td>
                            <td><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                            <td><code><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></code></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No activity recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>