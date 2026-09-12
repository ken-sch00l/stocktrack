<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';

// Get stats
$total_items     = $conn->query("SELECT COUNT(*) as cnt FROM items")->fetch_assoc()['cnt'];
$serviceable     = $conn->query("SELECT COUNT(*) as cnt FROM items WHERE condition_status='Serviceable'")->fetch_assoc()['cnt'];
$unserviceable   = $conn->query("SELECT COUNT(*) as cnt FROM items WHERE condition_status='Unserviceable'")->fetch_assoc()['cnt'];
$total_logbook   = $conn->query("SELECT COUNT(*) as cnt FROM logbook")->fetch_assoc()['cnt'];
$current_role    = $_SESSION['role'];
$outstanding_qty = $conn->query("SELECT COALESCE(SUM(CASE WHEN action = 'Borrowed' THEN quantity WHEN action = 'Returned' THEN -quantity ELSE 0 END), 0) AS cnt FROM logbook")->fetch_assoc()['cnt'];
$unread_count    = 0;
if (hasAnyRole(['admin', 'treasurer'])) {
    $unread_count = $conn->query("SELECT COUNT(*) AS cnt FROM notifications WHERE recipient_user_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0")->fetch_assoc()['cnt'];
}
$recent_activity = null;
if ($current_role === 'admin') {
    $recent_activity = $conn->query("SELECT a.action, a.details, a.created_at, u.full_name FROM audit_log a LEFT JOIN users u ON a.user_id = u.user_id ORDER BY a.created_at DESC LIMIT 5");
}

// Recent items
$recent = $conn->query("SELECT i.*, c.category_name FROM items i LEFT JOIN categories c ON i.category_id = c.category_id ORDER BY i.created_at DESC LIMIT 5");
?>
<?php require_once 'includes/header.php'; ?>
<?php require_once 'includes/sidebar.php'; ?>

<h4 class="fw-bold mb-4"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h4>

<div class="alert alert-light border mb-4">
    <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>,
    <?php if ($current_role === 'treasurer'): ?>review stock accountability and outstanding borrowed items.
    <?php elseif ($current_role === 'secretary'): ?>record new inventory and logbook transactions quickly.
    <?php elseif ($current_role === 'committee'): ?>review inventory condition and update the records that need attention.
    <?php else: ?>monitor system activity and keep inventory records controlled.
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <a href="/stocktrack/modules/inventory/index.php" class="card stat-card stat-card-link text-center p-3 h-100">
            <div class="text-primary" style="font-size:2rem;"><i class="bi bi-archive"></i></div>
            <h3 class="fw-bold mb-0"><?php echo $total_items; ?></h3>
            <small class="text-muted">Total Items</small>
        </a>
    </div>
    <div class="col-md-3">
        <a href="/stocktrack/modules/inventory/index.php?condition=Serviceable" class="card stat-card stat-card-link text-center p-3 h-100">
            <div class="text-success" style="font-size:2rem;"><i class="bi bi-check-circle"></i></div>
            <h3 class="fw-bold mb-0"><?php echo $serviceable; ?></h3>
            <small class="text-muted">Serviceable</small>
        </a>
    </div>
    <div class="col-md-3">
        <a href="/stocktrack/modules/inventory/index.php?condition=Unserviceable" class="card stat-card stat-card-link text-center p-3 h-100">
            <div class="text-danger" style="font-size:2rem;"><i class="bi bi-x-circle"></i></div>
            <h3 class="fw-bold mb-0"><?php echo $unserviceable; ?></h3>
            <small class="text-muted">Unserviceable</small>
        </a>
    </div>
    <div class="col-md-3">
        <a href="/stocktrack/modules/logbook/index.php" class="card stat-card stat-card-link text-center p-3 h-100">
            <div class="text-warning" style="font-size:2rem;"><i class="bi bi-journal-text"></i></div>
            <h3 class="fw-bold mb-0"><?php echo $total_logbook; ?></h3>
            <small class="text-muted">Logbook Entries</small>
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php if ($current_role === 'treasurer'): ?>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold"><i class="bi bi-bell me-2 text-warning"></i>Treasurer Attention</h6>
                <p class="mb-2">There are <strong><?php echo (int)$outstanding_qty; ?></strong> item(s) currently out.</p>
                <a href="/stocktrack/modules/logbook/index.php" class="btn btn-sm btn-outline-primary">Review borrowed items</a>
                <?php if ($unread_count > 0): ?><a href="/stocktrack/modules/notifications/index.php" class="btn btn-sm btn-warning ms-2"><?php echo (int)$unread_count; ?> notification(s)</a><?php endif; ?>
            </div>
        </div>
    </div>
    <?php elseif ($current_role === 'secretary'): ?>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body"><h6 class="fw-bold"><i class="bi bi-lightning me-2 text-primary"></i>Quick Recording</h6><p class="mb-2">Add records while the transaction details are available.</p><a href="/stocktrack/modules/inventory/add.php" class="btn btn-sm btn-primary">Add inventory</a><a href="/stocktrack/modules/logbook/add.php" class="btn btn-sm btn-outline-primary ms-2">Record logbook</a></div></div>
    </div>
    <?php elseif ($current_role === 'committee'): ?>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body"><h6 class="fw-bold"><i class="bi bi-clipboard-check me-2 text-success"></i>Inventory Review</h6><p class="mb-2">Check item conditions and review the latest inventory records.</p><a href="/stocktrack/modules/inventory/index.php" class="btn btn-sm btn-outline-primary">Review inventory</a><a href="/stocktrack/modules/inventory/history.php" class="btn btn-sm btn-outline-secondary ms-2">View history</a></div></div>
    </div>
    <?php else: ?>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body"><h6 class="fw-bold"><i class="bi bi-shield-check me-2 text-primary"></i>System Oversight</h6><p class="mb-2">Review recent security and data activity.</p><a href="/stocktrack/modules/audit/index.php" class="btn btn-sm btn-outline-primary">Open activity log</a></div></div>
    </div>
    <?php endif; ?>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body"><h6 class="fw-bold"><i class="bi bi-box-seam me-2 text-primary"></i>Stock Snapshot</h6><p class="mb-2"><strong><?php echo (int)$serviceable; ?></strong> serviceable and <strong><?php echo (int)$unserviceable; ?></strong> unserviceable item(s).</p><a href="/stocktrack/modules/inventory/index.php" class="btn btn-sm btn-outline-primary">Open inventory</a></div></div>
    </div>
</div>

<?php if ($current_role === 'admin' && $recent_activity): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-shield-check me-2 text-primary"></i>Recent System Activity</div>
    <div class="list-group list-group-flush">
        <?php if ($recent_activity->num_rows > 0): ?>
            <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                <div class="list-group-item d-flex justify-content-between gap-3">
                    <span><strong><?php echo htmlspecialchars($activity['action']); ?></strong><?php echo $activity['details'] ? ' - ' . htmlspecialchars($activity['details']) : ''; ?><br><small class="text-muted"><?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?></small></span>
                    <small class="text-muted text-nowrap"><?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?></small>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="list-group-item text-muted">No recent activity.</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-clock-history me-2 text-primary"></i>Recently Added Items
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Tracking No.</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Condition</th>
                    <th>Date Added</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recent->num_rows > 0): ?>
                    <?php while($row = $recent->fetch_assoc()): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($row['tracking_number']); ?></code></td>
                        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></td>
                        <td>
                            <?php if ($row['condition_status'] === 'Serviceable'): ?>
                                <span class="badge-serviceable">Serviceable</span>
                            <?php else: ?>
                                <span class="badge-unserviceable">Unserviceable</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No items recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
