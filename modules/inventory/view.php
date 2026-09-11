<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('view');
require_once '../../includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT i.*, c.category_name FROM items i LEFT JOIN categories c ON i.category_id = c.category_id WHERE i.item_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) { header("Location: index.php"); exit(); }

$logs = $conn->prepare("SELECT l.*, u.full_name as recorder FROM logbook l LEFT JOIN users u ON l.recorded_by = u.user_id WHERE l.item_id = ? ORDER BY l.date_action DESC");
$logs->bind_param("i", $id);
$logs->execute();
$logs = $logs->get_result();
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-eye me-2 text-primary"></i>Item Details</h4>
    <div>
        <?php if (hasRole('admin')): ?>
            <a href="edit.php?id=<?php echo $item['item_id']; ?>" class="btn btn-warning btn-sm me-2">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?php echo htmlspecialchars($item['item_name']); ?></span>
        <?php if ($item['condition_status'] === 'Serviceable'): ?>
            <span class="badge-serviceable">Serviceable</span>
        <?php else: ?>
            <span class="badge-unserviceable">Unserviceable</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <small class="text-muted d-block">Tracking Number</small>
                <code><?php echo htmlspecialchars($item['tracking_number']); ?></code>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Serial Number</small>
                <?php echo htmlspecialchars($item['serial_number'] ?? 'N/A'); ?>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Category</small>
                <?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Quantity</small>
                <?php echo $item['quantity'] . ' ' . ($item['unit'] ?? ''); ?>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Date Purchased</small>
                <?php echo $item['date_purchased'] ? date('F d, Y', strtotime($item['date_purchased'])) : 'N/A'; ?>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Last Inventory Date</small>
                <?php echo $item['last_inventory_date'] ? date('F d, Y', strtotime($item['last_inventory_date'])) : 'N/A'; ?>
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Person in Charge</small>
                <?php echo htmlspecialchars($item['person_in_charge'] ?? 'N/A'); ?>
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Position</small>
                <?php echo htmlspecialchars($item['position'] ?? 'N/A'); ?>
            </div>
            <?php if ($item['notes']): ?>
            <div class="col-12">
                <small class="text-muted d-block">Notes</small>
                <?php echo htmlspecialchars($item['notes']); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-journal-text me-2 text-primary"></i>Logbook History
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
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
                    <?php while($log = $logs->fetch_assoc()): ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?php echo $log['action']; ?></span></td>
                        <td><?php echo $log['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($log['borrowed_by']); ?></td>
                        <td><?php echo htmlspecialchars($log['purpose'] ?? 'N/A'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($log['date_action'])); ?></td>
                        <td><?php echo $log['date_returned'] ? date('M d, Y', strtotime($log['date_returned'])) : 'Pending'; ?></td>
                        <td><?php echo htmlspecialchars($log['recorder'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">No logbook entries for this item.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
