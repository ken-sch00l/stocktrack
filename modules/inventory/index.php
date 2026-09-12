<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('view');
require_once '../../includes/db.php';

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$condition_filter = isset($_GET['condition']) ? $_GET['condition'] : '';
$sort_by = $_GET['sort'] ?? 'item_name';
$sort_direction = strtoupper($_GET['direction'] ?? 'ASC');
$sort_columns = [
    'tracking' => 'i.tracking_number',
    'item_name' => 'i.item_name',
    'category' => 'c.category_name',
    'condition' => 'i.condition_status',
    'quantity' => 'i.quantity',
    'date_purchased' => 'i.date_purchased',
    'date_added' => 'i.created_at'
];
$sort_by = array_key_exists($sort_by, $sort_columns) ? $sort_by : 'item_name';
$sort_direction = in_array($sort_direction, ['ASC', 'DESC'], true) ? $sort_direction : 'ASC';

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

$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

$count_stmt = $conn->prepare("SELECT COUNT(*) AS total_items FROM items i $where");
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_items = (int)$count_stmt->get_result()->fetch_assoc()['total_items'];
$total_pages = max(1, (int)ceil($total_items / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$query_params = $_GET;
unset($query_params['page']);
$pagination_url = function ($target_page) use ($query_params) {
    return '?' . htmlspecialchars(http_build_query(array_merge($query_params, ['page' => $target_page])), ENT_QUOTES, 'UTF-8');
};

$sql = "SELECT i.*, c.category_name FROM items i LEFT JOIN categories c ON i.category_id = c.category_id $where ORDER BY {$sort_columns[$sort_by]} $sort_direction, i.item_id ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$limit_params = $params;
$limit_params[] = $per_page;
$limit_params[] = $offset;
$stmt->bind_param($types . 'ii', ...$limit_params);
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
                <select name="sort" class="form-select" aria-label="Sort inventory">
                    <option value="item_name" <?php echo $sort_by === 'item_name' ? 'selected' : ''; ?>>Name</option>
                    <option value="category" <?php echo $sort_by === 'category' ? 'selected' : ''; ?>>Category</option>
                    <option value="condition" <?php echo $sort_by === 'condition' ? 'selected' : ''; ?>>Condition</option>
                    <option value="quantity" <?php echo $sort_by === 'quantity' ? 'selected' : ''; ?>>Quantity</option>
                    <option value="date_purchased" <?php echo $sort_by === 'date_purchased' ? 'selected' : ''; ?>>Purchase date</option>
                    <option value="date_added" <?php echo $sort_by === 'date_added' ? 'selected' : ''; ?>>Date added</option>
                    <option value="tracking" <?php echo $sort_by === 'tracking' ? 'selected' : ''; ?>>Tracking number</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="direction" class="form-select" aria-label="Sort direction">
                    <option value="ASC" <?php echo $sort_direction === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                    <option value="DESC" <?php echo $sort_direction === 'DESC' ? 'selected' : ''; ?>>Descending</option>
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
    <?php if ($total_items > 0): ?>
    <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small class="text-muted">
            Showing <?php echo (($page - 1) * $per_page) + 1; ?>-
            <?php echo min($page * $per_page, $total_items); ?> of <?php echo $total_items; ?> item(s)
        </small>
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Inventory pages">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $pagination_url(max(1, $page - 1)); ?>" aria-label="Previous">&laquo;</a>
                </li>
                <?php for ($page_number = 1; $page_number <= $total_pages; $page_number++): ?>
                    <li class="page-item <?php echo $page_number === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo $pagination_url($page_number); ?>"><?php echo $page_number; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $pagination_url(min($total_pages, $page + 1)); ?>" aria-label="Next">&raquo;</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
