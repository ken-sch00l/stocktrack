<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('edit');
require_once '../../includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) { header("Location: /stocktrack/modules/inventory/index.php"); exit(); }

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $item_name      = trim($_POST['item_name']);
    $serial_number  = trim($_POST['serial_number']);
    $category_id    = (int)$_POST['category_id'];
    $condition      = $_POST['condition_status'];
    $quantity       = (int)$_POST['quantity'];
    $unit           = trim($_POST['unit']);
    $date_purchased = $_POST['date_purchased'];
    $person_charge  = trim($_POST['person_in_charge']);
    $position       = trim($_POST['position']);
    $last_inventory = $_POST['last_inventory_date'];
    $notes          = trim($_POST['notes']);

    if (!$item_name) {
        $error = "Item name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE items SET serial_number=?, item_name=?, category_id=?, condition_status=?, quantity=?, unit=?, date_purchased=?, person_in_charge=?, position=?, last_inventory_date=?, notes=?, updated_at=NOW() WHERE item_id=?");
        $stmt->bind_param("ssissssssssi", $serial_number, $item_name, $category_id, $condition, $quantity, $unit, $date_purchased, $person_charge, $position, $last_inventory, $notes, $id);

        if ($stmt->execute()) {
            recordAudit('item_updated', 'item', $id, $item_name);
            header("Location: /stocktrack/modules/inventory/index.php?success=Item updated successfully.");
            exit();
        } else {
            $error = "Failed to update item. Please try again.";
        }
    }
}
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-pencil me-2 text-warning"></i>Edit Item</h4>
    <a href="/stocktrack/modules/inventory/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST">
            <?php csrf_field(); ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Tracking Number</label>
                    <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($item['tracking_number']); ?>" readonly>
                    <small class="text-muted">Cannot be changed</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Serial Number</label>
                    <input type="text" name="serial_number" class="form-control" value="<?php echo htmlspecialchars($item['serial_number']); ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Item Name <span class="text-danger">*</span></label>
                    <input type="text" name="item_name" class="form-control" value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">Select Category</option>
                        <?php while($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['category_id']; ?>" <?php echo $item['category_id'] == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Condition <span class="text-danger">*</span></label>
                    <select name="condition_status" class="form-select" required>
                        <option value="Serviceable" <?php echo $item['condition_status'] === 'Serviceable' ? 'selected' : ''; ?>>Serviceable</option>
                        <option value="Unserviceable" <?php echo $item['condition_status'] === 'Unserviceable' ? 'selected' : ''; ?>>Unserviceable</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" class="form-control" value="<?php echo $item['quantity']; ?>" min="1">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" class="form-control" value="<?php echo htmlspecialchars($item['unit']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date Purchased</label>
                    <input type="date" name="date_purchased" class="form-control" value="<?php echo $item['date_purchased']; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Last Inventory Date</label>
                    <input type="date" name="last_inventory_date" class="form-control" value="<?php echo $item['last_inventory_date']; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Person in Charge</label>
                    <input type="text" name="person_in_charge" class="form-control" value="<?php echo htmlspecialchars($item['person_in_charge']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Position</label>
                    <input type="text" name="position" class="form-control" value="<?php echo htmlspecialchars($item['position']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($item['notes']); ?></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-warning px-4">
                        <i class="bi bi-save me-1"></i>Update Item
                    </button>
                    <a href="/stocktrack/modules/inventory/index.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
