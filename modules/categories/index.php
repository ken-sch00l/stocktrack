<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('manage_categories');
require_once '../../includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    if (($_POST['action'] ?? '') === 'delete') {
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        if ($category_id) {
            $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            recordAudit('category_deleted', 'category', $category_id);
            $success = "Category deleted.";
        }
    } else {
        $name = trim($_POST['category_name'] ?? '');
        if (!$name) {
            $error = "Category name is required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                recordAudit('category_created', 'category', $conn->insert_id, $name);
                $success = "Category added successfully.";
            } else {
                $error = "Failed to add category.";
            }
        }
    }
}

$category_sort = $_GET['sort'] ?? 'name';
$category_direction = strtoupper($_GET['direction'] ?? 'ASC');
$category_sort_columns = ['name' => 'c.category_name', 'items' => 'item_count', 'date' => 'c.created_at'];
$category_sort = array_key_exists($category_sort, $category_sort_columns) ? $category_sort : 'name';
$category_direction = in_array($category_direction, ['ASC', 'DESC'], true) ? $category_direction : 'ASC';
$categories = $conn->query("SELECT c.*, COUNT(i.item_id) as item_count FROM categories c LEFT JOIN items i ON c.category_id = i.category_id GROUP BY c.category_id ORDER BY {$category_sort_columns[$category_sort]} $category_direction");
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-tags me-2 text-primary"></i>Categories</h4>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible"><?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible"><?php echo htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<form method="GET" class="row g-2 mb-3 align-items-end">
    <div class="col-md-4"><label class="form-label">Sort categories by</label><select name="sort" class="form-select"><option value="name" <?php echo $category_sort === 'name' ? 'selected' : ''; ?>>Name</option><option value="items" <?php echo $category_sort === 'items' ? 'selected' : ''; ?>>Item count</option><option value="date" <?php echo $category_sort === 'date' ? 'selected' : ''; ?>>Date added</option></select></div>
    <div class="col-md-4"><label class="form-label">Direction</label><select name="direction" class="form-select"><option value="ASC" <?php echo $category_direction === 'ASC' ? 'selected' : ''; ?>>Ascending</option><option value="DESC" <?php echo $category_direction === 'DESC' ? 'selected' : ''; ?>>Descending</option></select></div>
    <div class="col-md-4"><button type="submit" class="btn btn-outline-primary">Apply sorting</button></div>
</form>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Add New Category</div>
            <div class="card-body">
                <form method="POST">
                    <?php csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="category_name" class="form-control" placeholder="e.g. Office Supplies" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle me-1"></i>Add Category
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">All Categories</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Items</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($categories->num_rows > 0): ?>
                            <?php while($cat = $categories->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo $cat['item_count']; ?></span></td>
                                <td>
                                    <?php if ($cat['item_count'] == 0): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="category_id" value="<?php echo $cat['category_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-muted small">Has items</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No categories yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
