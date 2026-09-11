<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('view_audit');
require_once '../../includes/db.php';

$logs = $conn->query("SELECT a.*, u.full_name FROM audit_log a LEFT JOIN users u ON a.user_id = u.user_id ORDER BY a.created_at DESC LIMIT 100");
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-shield-check me-2 text-primary"></i>Activity Log</h4>
    <span class="text-muted small">Latest 100 events</span>
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