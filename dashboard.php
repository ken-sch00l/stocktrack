<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';

// Get stats
$total_items     = $conn->query("SELECT COUNT(*) as cnt FROM items")->fetch_assoc()['cnt'];
$serviceable     = $conn->query("SELECT COUNT(*) as cnt FROM items WHERE condition_status='Serviceable'")->fetch_assoc()['cnt'];
$unserviceable   = $conn->query("SELECT COUNT(*) as cnt FROM items WHERE condition_status='Unserviceable'")->fetch_assoc()['cnt'];
$total_logbook   = $conn->query("SELECT COUNT(*) as cnt FROM logbook")->fetch_assoc()['cnt'];

// Recent items
$recent = $conn->query("SELECT i.*, c.category_name FROM items i LEFT JOIN categories c ON i.category_id = c.category_id ORDER BY i.created_at DESC LIMIT 5");
?>
<?php require_once 'includes/header.php'; ?>
<?php require_once 'includes/sidebar.php'; ?>

<h4 class="fw-bold mb-4"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h4>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card text-center p-3">
            <div class="text-primary" style="font-size:2rem;"><i class="bi bi-archive"></i></div>
            <h3 class="fw-bold mb-0"><?php echo $total_items; ?></h3>
            <small class="text-muted">Total Items</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-center p-3">
            <div class="text-success" style="font-size:2rem;"><i class="bi bi-check-circle"></i></div>
            <h3 class="fw-bold mb-0"><?php echo $serviceable; ?></h3>
            <small class="text-muted">Serviceable</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-center p-3">
            <div class="text-danger" style="font-size:2rem;"><i class="bi bi-x-circle"></i></div>
            <h3 class="fw-bold mb-0"><?php echo $unserviceable; ?></h3>
            <small class="text-muted">Unserviceable</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-center p-3">
            <div class="text-warning" style="font-size:2rem;"><i class="bi bi-journal-text"></i></div>
            <h3 class="fw-bold mb-0"><?php echo $total_logbook; ?></h3>
            <small class="text-muted">Logbook Entries</small>
        </div>
    </div>
</div>

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
