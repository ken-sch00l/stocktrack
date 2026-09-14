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
$form = $item;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $form = array_merge($item, $_POST);
    $item_name                = trim((string)($_POST['item_name'] ?? ''));
    $serial_number            = trim((string)($_POST['serial_number'] ?? ''));
    $category_id              = (int)($_POST['category_id'] ?? 0);
    $category_id              = $category_id > 0 ? $category_id : null;
    $condition                = $_POST['condition_status'] ?? 'Serviceable';
    $condition                = in_array($condition, ['Serviceable', 'Unserviceable'], true) ? $condition : 'Serviceable';
    $date_acquired            = ($_POST['date_acquired'] ?? '') ?: null;
    $unit_measure             = trim((string)($_POST['unit_measure'] ?? ''));
    $unit                     = $unit_measure;
    $unit_value               = max(0, (float)str_replace(',', '', (string)($_POST['unit_value'] ?? 0)));
    $balance_per_card         = max(0, (int)($_POST['balance_per_card'] ?? 0));
    $on_hand_per_count        = max(0, (int)($_POST['on_hand_per_count'] ?? 0));
    $quantity                 = $on_hand_per_count;
    $date_purchased           = $date_acquired;
    $shortage_overage_qty     = (int)($_POST['shortage_overage_qty'] ?? 0);
    $shortage_overage_value   = max(0, (float)str_replace(',', '', (string)($_POST['shortage_overage_value'] ?? 0)));
    $property_ics_number      = trim((string)($_POST['property_ics_number'] ?? $item['property_ics_number'] ?? $item['tracking_number']));
    $person_charge            = trim((string)($_POST['person_in_charge'] ?? ''));
    $position                 = trim((string)($_POST['position'] ?? ''));
    $last_inventory           = ($_POST['last_inventory_date'] ?? '') ?: null;
    $remarks                  = trim((string)($_POST['remarks'] ?? ''));

    $stmt = $conn->prepare("UPDATE items SET property_ics_number=?, serial_number=?, item_name=?, category_id=?, condition_status=?, quantity=?, unit=?, date_purchased=?, date_acquired=?, unit_measure=?, unit_value=?, balance_per_card=?, on_hand_per_count=?, shortage_overage_qty=?, shortage_overage_value=?, person_in_charge=?, position=?, last_inventory_date=?, remarks=?, updated_at=NOW() WHERE item_id=?");
        $stmt->bind_param("sssisissssdiiidssssi", $property_ics_number, $serial_number, $item_name, $category_id, $condition, $quantity, $unit, $date_purchased, $date_acquired, $unit_measure, $unit_value, $balance_per_card, $on_hand_per_count, $shortage_overage_qty, $shortage_overage_value, $person_charge, $position, $last_inventory, $remarks, $id);

        if ($stmt->execute()) {
            recordAudit('item_updated', 'item', $id, $item_name);
            header("Location: /stocktrack/modules/inventory/index.php?success=Item updated successfully.");
            exit();
        } else {
            $error = "Failed to update item. Please try again.";
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

<div class="card paper-entry-card border-0">
    <div class="card-body">
        <form method="POST">
            <?php csrf_field(); ?>
            <div class="paper-entry-grid">
                <div class="paper-entry-field span-3">
                    <label class="paper-entry-label">Property / Inventory No.</label>
                    <input type="text" name="property_ics_number" class="paper-entry-input" value="<?php echo htmlspecialchars($form['property_ics_number'] ?: $form['tracking_number']); ?>" placeholder="e.g. 1-07-05-030">
                    <div class="form-text">Enter the official property number, not the item name.</div>
                </div>
                <div class="paper-entry-field span-3">
                    <label class="paper-entry-label">Description</label>
                    <input type="text" name="item_name" class="paper-entry-input" value="<?php echo htmlspecialchars($form['item_name']); ?>" placeholder="e.g. Motorcycle">
                    <div class="form-text">Leave blank if the description is not available yet.</div>
                </div>
                <div class="paper-entry-field span-2">
                    <label class="paper-entry-label">Date Acquired</label>
                    <input type="date" name="date_acquired" class="paper-entry-input" value="<?php echo htmlspecialchars($form['date_acquired'] ?: $form['date_purchased']); ?>">
                </div>
                <div class="paper-entry-field span-1">
                    <label class="paper-entry-label">Unit of Measure</label>
                    <input type="text" name="unit_measure" class="paper-entry-input" value="<?php echo htmlspecialchars($form['unit_measure'] ?: $form['unit']); ?>">
                </div>
                <div class="paper-entry-field span-2">
                    <label class="paper-entry-label">Unit Value</label>
                    <input type="text" inputmode="decimal" name="unit_value" class="paper-entry-input money-input" value="<?php echo htmlspecialchars(number_format((float)str_replace(',', '', (string)($form['unit_value'] ?? 0)), 2, '.', ',')); ?>">
                </div>
                <div class="paper-entry-field span-1">
                    <label class="paper-entry-label">Balance per Card</label>
                    <input type="number" name="balance_per_card" class="paper-entry-input" value="<?php echo htmlspecialchars($form['balance_per_card'] ?? $form['quantity']); ?>" min="0">
                </div>
                <div class="paper-entry-field span-1">
                    <label class="paper-entry-label">On Hand</label>
                    <input type="number" name="on_hand_per_count" class="paper-entry-input" value="<?php echo htmlspecialchars($form['on_hand_per_count'] ?? $form['quantity']); ?>" min="0">
                </div>
                <div class="paper-entry-field span-2 variance-field">
                    <label class="paper-entry-label">Shortage / Overage Quantity</label>
                    <input type="number" name="shortage_overage_qty" class="paper-entry-input" value="<?php echo htmlspecialchars($form['shortage_overage_qty'] ?? '0'); ?>">
                </div>
                <div class="paper-entry-field span-2 variance-field">
                    <label class="paper-entry-label">Shortage / Overage Value</label>
                    <input type="text" inputmode="decimal" name="shortage_overage_value" class="paper-entry-input money-input" value="<?php echo htmlspecialchars(number_format((float)str_replace(',', '', (string)($form['shortage_overage_value'] ?? 0)), 2, '.', ',')); ?>">
                </div>
                <div class="paper-entry-field span-3">
                    <label class="paper-entry-label">Remarks</label>
                    <input type="text" name="remarks" class="paper-entry-input" value="<?php echo htmlspecialchars($form['remarks'] ?: ($form['notes'] ?? '')); ?>">
                </div>
                <div class="paper-entry-field span-2">
                    <label class="paper-entry-label">Serial No.</label>
                    <input type="text" name="serial_number" class="paper-entry-input" value="<?php echo htmlspecialchars($form['serial_number']); ?>">
                </div>
                <div class="paper-entry-field span-2">
                    <label class="paper-entry-label">Article</label>
                    <select name="category_id" class="paper-entry-input">
                        <option value="">Select</option>
                        <?php while($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['category_id']; ?>" <?php echo $form['category_id'] == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="paper-entry-field span-2">
                    <label class="paper-entry-label">Condition</label>
                    <select name="condition_status" class="paper-entry-input">
                        <option value="Serviceable" <?php echo $form['condition_status'] === 'Serviceable' ? 'selected' : ''; ?>>Serviceable</option>
                        <option value="Unserviceable" <?php echo $form['condition_status'] === 'Unserviceable' ? 'selected' : ''; ?>>Unserviceable</option>
                    </select>
                </div>
                <div class="paper-entry-field span-2">
                    <label class="paper-entry-label">Person in Charge</label>
                    <input type="text" name="person_in_charge" class="paper-entry-input" value="<?php echo htmlspecialchars($form['person_in_charge']); ?>">
                </div>
                <div class="paper-entry-field span-2">
                    <label class="paper-entry-label">Position</label>
                    <input type="text" name="position" class="paper-entry-input" value="<?php echo htmlspecialchars($form['position']); ?>">
                </div>
                <div class="paper-entry-field span-1">
                    <label class="paper-entry-label">Last Inventory</label>
                    <input type="date" name="last_inventory_date" class="paper-entry-input" value="<?php echo htmlspecialchars($form['last_inventory_date']); ?>">
                </div>
            </div>

            <div class="paper-entry-actions">
                <button type="submit" class="btn btn-warning px-4">
                    <i class="bi bi-save me-1"></i>Update Item
                </button>
                <a href="/stocktrack/modules/inventory/index.php" class="btn btn-outline-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.money-input').forEach((input) => {
    input.addEventListener('input', () => {
        const digits = input.value.replace(/[^0-9.]/g, '');
        const parts = digits.split('.');
        const whole = parts[0] || '';
        input.value = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + (parts.length > 1 ? '.' + parts[1].slice(0, 2) : '');
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
