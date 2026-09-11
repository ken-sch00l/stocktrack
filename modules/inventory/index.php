<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('view');
require_once '../../includes/db.php';

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$condition_filter = isset($_GET['condition']) ? $_GET['condition'] : '';

$where = "WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $where .= " AND (i.item_name LIKE ? OR i.tracking_number LIKE ? OR i.serial_number LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
    $types .= 'sss';
}
if ($category_filter) {
    $where .= " AND i.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}
if ($condition_filter) {
    $where .= " AND i.condition_status = ?";
    $params[] = $condition_filter;
    $types .= 's';
}

$sql = "SELECT i.*, c.category_name FROM items i LEFT JOIN categories c ON i.category_id = c.category_id $where ORDER BY i.item_name ASC";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$items = $stmt->get_result();

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-archive me-2 text-primary"></i>Inventory</h4>
    <?php if (hasAnyRole(['admin', 'secretary', 'treasurer', 'committee'])): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Add Item
        </a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible">
        <?php echo htmlspecialchars($_GET['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by name, tracking no., serial no..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php $categories->data_seek(0); while($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $cat['category_id']; ?>" <?php echo $category_filter == $cat['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="condition" class="form-select">
                    <option value="">All Conditions</option>
                    <option value="Serviceable" <?php echo $condition_filter === 'Serviceable' ? 'selected' : ''; ?>>Serviceable</option>
                    <option value="Unserviceable" <?php echo $condition_filter === 'Unserviceable' ? 'selected' : ''; ?>>Unserviceable</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i>Search
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Tracking No.</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Condition</th>
                    <th>Qty</th>
                    <th>Date Purchased</th>
                    <th>Person in Charge</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($items->num_rows > 0): ?>
                    <?php while($row = $items->fetch_assoc()): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($row['tracking_number']); ?></code></td>
                        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                        <td><small><?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></small></td>
                        <td>
                            <?php if ($row['condition_status'] === 'Serviceable'): ?>
                                <span class="badge-serviceable">Serviceable</span>
                            <?php else: ?>
                                <span class="badge-unserviceable">Unserviceable</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $row['quantity']; ?></td>
                        <td><?php echo $row['date_purchased'] ? date('M d, Y', strtotime($row['date_purchased'])) : 'N/A'; ?></td>
                        <td><?php echo htmlspecialchars($row['person_in_charge'] ?? 'N/A'); ?></td>
                        <td>
                            <a href="view.php?id=<?php echo $row['item_id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (hasRole('admin')): ?>
                                <a href="edit.php?id=<?php echo $row['item_id']; ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo $row['item_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No items found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
