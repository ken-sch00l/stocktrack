<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('add');
require_once '../../includes/db.php';

$error = '';
$success = '';

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $item_name       = trim($_POST['item_name']);
    $serial_number   = trim($_POST['serial_number']);
    $category_id     = (int)$_POST['category_id'];
    $condition       = $_POST['condition_status'];
    $quantity        = (int)$_POST['quantity'];
    $unit            = trim($_POST['unit']);
    $date_purchased  = $_POST['date_purchased'];
    $person_charge   = trim($_POST['person_in_charge']);
    $position        = trim($_POST['position']);
    $last_inventory  = $_POST['last_inventory_date'];
    $notes           = trim($_POST['notes']);
    $created_by      = $_SESSION['user_id'];

    if (!$item_name) {
        $error = "Item name is required.";
    } else {
        $temporary_tracking_number = 'TMP-' . bin2hex(random_bytes(16));
        $conn->begin_transaction();

        $stmt = $conn->prepare("INSERT INTO items (tracking_number, serial_number, item_name, category_id, condition_status, quantity, unit, date_purchased, person_in_charge, position, last_inventory_date, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssisissssssi", $temporary_tracking_number, $serial_number, $item_name, $category_id, $condition, $quantity, $unit, $date_purchased, $person_charge, $position, $last_inventory, $notes, $created_by);

        if ($stmt->execute()) {
            $item_id = $conn->insert_id;
            $tracking_number = 'TRK' . str_pad($item_id, 5, '0', STR_PAD_LEFT);
            $update = $conn->prepare("UPDATE items SET tracking_number = ? WHERE item_id = ?");
            $update->bind_param("si", $tracking_number, $item_id);

            if (!$update->execute()) {
                $conn->rollback();
                $error = "Failed to generate tracking number. Please try again.";
            } else {
                $conn->commit();
                recordAudit('item_created', 'item', $item_id, $item_name);
                header("Location: /stocktrack/modules/inventory/index.php?success=Item added successfully.");
                exit();
            }
        } else {
            $conn->rollback();
            $error = "Failed to add item. Please try again.";
        }
    }
}
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-plus-circle me-2 text-primary"></i>Add New Item</h4>
    <a href="index.php" class="btn btn-outline-secondary">
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
                    <input type="text" class="form-control bg-light" value="Generated after saving" readonly>
                    <small class="text-muted">Generated from the saved item ID</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Serial Number</label>
                    <input type="text" name="serial_number" class="form-control" placeholder="Enter serial number">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Item Name <span class="text-danger">*</span></label>
                    <input type="text" name="item_name" class="form-control" placeholder="Enter item name" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">Select Category</option>
                        <?php while($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Condition <span class="text-danger">*</span></label>
                    <select name="condition_status" class="form-select" required>
                        <option value="Serviceable">Serviceable</option>
                        <option value="Unserviceable">Unserviceable</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" class="form-control" value="1" min="1">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" class="form-control" placeholder="e.g. pieces, sets, units">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date Purchased</label>
                    <input type="date" name="date_purchased" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Last Inventory Date</label>
                    <input type="date" name="last_inventory_date" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Person in Charge</label>
                    <input type="text" name="person_in_charge" class="form-control" placeholder="Full name">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Position</label>
                    <input type="text" name="position" class="form-control" placeholder="e.g. Barangay Treasurer">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes or remarks"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save me-1"></i>Save Item
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
