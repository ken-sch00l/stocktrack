<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('view');
require_once '../../includes/db.php';

$logs = $conn->query("SELECT l.*, COALESCE(i.item_name, 'Deleted item') AS item_name, COALESCE(i.tracking_number, 'N/A') AS tracking_number, u.full_name as recorder FROM logbook l LEFT JOIN items i ON l.item_id = i.item_id LEFT JOIN users u ON l.recorded_by = u.user_id ORDER BY l.date_action DESC");
$outstanding = $conn->query("SELECT l.borrowed_by, COALESCE(SUM(CASE WHEN l.action = 'Borrowed' AND l.date_returned IS NULL THEN l.quantity WHEN l.action = 'Returned' THEN -l.quantity ELSE 0 END), 0) AS outstanding_quantity, GROUP_CONCAT(DISTINCT COALESCE(i.item_name, 'Deleted item') ORDER BY i.item_name SEPARATOR ', ') AS item_names FROM logbook l LEFT JOIN items i ON l.item_id = i.item_id GROUP BY l.borrowed_by HAVING outstanding_quantity > 0 ORDER BY l.borrowed_by ASC");
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Logbook</h4>
    <?php if (hasAnyRole(['admin', 'secretary', 'treasurer', 'committee'])): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Add Entry
        </a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible">
        <?php echo htmlspecialchars($_GET['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Tracking No.</th>
                    <th>Action</th>
                    <th>Qty</th>
                    <th>Borrowed By</th>
                    <th>Purpose</th>
                    <th>Date</th>
                    <th>Date Returned</th>
                    <th>Recorded By</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs->num_rows > 0): ?>
                    <?php while($row = $logs->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                        <td><code><?php echo htmlspecialchars($row['tracking_number']); ?></code></td>
                        <td><span class="badge bg-secondary"><?php echo $row['action']; ?></span></td>
                        <td><?php echo $row['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($row['borrowed_by']); ?></td>
                        <td><?php echo htmlspecialchars($row['purpose'] ?? 'N/A'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['date_action'])); ?></td>
                        <td><?php echo $row['date_returned'] ? date('M d, Y', strtotime($row['date_returned'])) : '<span class="text-warning">Pending</span>'; ?></td>
                        <td><?php echo htmlspecialchars($row['recorder'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No logbook entries yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-person-exclamation me-2 text-warning"></i>Outstanding Borrowed Items</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Borrowed By</th><th>Items</th><th>Quantity Still Out</th></tr></thead>
            <tbody>
                <?php if ($outstanding->num_rows > 0): ?>
                    <?php while ($borrower = $outstanding->fetch_assoc()): ?>
                    <tr><td><?php echo htmlspecialchars($borrower['borrowed_by']); ?></td><td><?php echo htmlspecialchars($borrower['item_names']); ?></td><td><span class="badge bg-warning text-dark"><?php echo (int)$borrower['outstanding_quantity']; ?></span></td></tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">No items are currently borrowed.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
