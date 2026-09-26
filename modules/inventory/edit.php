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
$donor_name_field = 'donor_name_organization';
$donor_office_field = 'donor_office_department';
$donor_name_check = $conn->query("SHOW COLUMNS FROM items LIKE 'donor_name_organization'");
if ($donor_name_check && $donor_name_check->num_rows === 0) {
    $legacy_donor_check = $conn->query("SHOW COLUMNS FROM items LIKE 'donor_name'");
    if ($legacy_donor_check && $legacy_donor_check->num_rows > 0) {
        $donor_name_field = 'donor_name';
        $donor_office_field = 'donor_organization';
    }
}
$error = '';
$form = $item;
if (empty($form['acquisition_type'])) {
    $form['acquisition_type'] = 'Purchased';
}
if (empty($form['donor_name_organization'])) {
    $form['donor_name_organization'] = '';
}
if (empty($form['donor_office_department'])) {
    $form['donor_office_department'] = '';
}

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
    $property_ics_number      = trim((string)($_POST['property_ics_number'] ?? $item['property_ics_number'] ?? ''));
    $acquisition_type         = $_POST['acquisition_type'] ?? 'Purchased';
    $donor_name_organization  = trim((string)($_POST['donor_name_organization'] ?? ''));
    $donor_office_department  = trim((string)($_POST['donor_office_department'] ?? ''));
    $person_charge            = trim((string)($_POST['person_in_charge'] ?? ''));
    $position                 = trim((string)($_POST['position'] ?? ''));
    $last_inventory           = ($_POST['last_inventory_date'] ?? '') ?: null;
    $remarks                  = trim((string)($_POST['remarks'] ?? ''));

    if (!in_array($acquisition_type, ['Purchased', 'Donated', 'Other'], true)) {
        $acquisition_type = 'Purchased';
    }
    if ($acquisition_type !== 'Donated') {
        $donor_name_organization = null;
        $donor_office_department = null;
    } else {
        $donor_name_organization = $donor_name_organization !== '' ? $donor_name_organization : null;
        $donor_office_department = $donor_office_department !== '' ? $donor_office_department : null;
    }

    $usage_stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN action = 'Borrowed' THEN quantity WHEN action = 'Used' THEN quantity WHEN action = 'Returned' THEN -quantity ELSE 0 END), 0) AS allocated_quantity FROM logbook WHERE item_id = ?");
    $usage_stmt->bind_param("i", $id);
    $usage_stmt->execute();
    $allocated_quantity = (int)$usage_stmt->get_result()->fetch_assoc()['allocated_quantity'];

    if (!$item_name || !$date_acquired || !$unit_measure || !in_array($acquisition_type, ['Purchased', 'Donated', 'Other'], true)) {
        $error = 'Description, date acquired, unit of measure, and a valid acquisition type are required.';
    } elseif ($quantity < $allocated_quantity) {
        $error = "On-hand quantity cannot be lower than the {$allocated_quantity} item(s) currently allocated or borrowed.";
    }

    if (!$error) {
        $stmt = $conn->prepare("UPDATE items SET property_ics_number=?, serial_number=?, item_name=?, category_id=?, condition_status=?, quantity=?, unit=?, date_purchased=?, date_acquired=?, unit_measure=?, unit_value=?, balance_per_card=?, on_hand_per_count=?, shortage_overage_qty=?, shortage_overage_value=?, person_in_charge=?, position=?, last_inventory_date=?, remarks=?, updated_at=NOW() WHERE item_id=?");
        $stmt->bind_param("sssisissssdiiidssssi", $property_ics_number, $serial_number, $item_name, $category_id, $condition, $quantity, $unit, $date_purchased, $date_acquired, $unit_measure, $unit_value, $balance_per_card, $on_hand_per_count, $shortage_overage_qty, $shortage_overage_value, $person_charge, $position, $last_inventory, $remarks, $id);

        if ($stmt->execute()) {
            $metadata_sql = "UPDATE items SET acquisition_type = ?, {$donor_name_field} = ?, {$donor_office_field} = ? WHERE item_id = ?";
            $metadata_stmt = $conn->prepare($metadata_sql);
            $metadata_stmt->bind_param("sssi", $acquisition_type, $donor_name_organization, $donor_office_department, $id);

            if (!$metadata_stmt->execute()) {
                $error = "Failed to update acquisition details. Please try again.";
            } else {
                recordAudit('item_updated', 'item', $id, $item_name . ' | Acquisition: ' . $acquisition_type);
                header("Location: /stocktrack/modules/inventory/index.php?success=Item updated successfully.");
                exit();
            }
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

<div class="card paper-entry-card border-0">
    <div class="card-body">
        <form method="POST">
            <?php csrf_field(); ?>
            <div class="paper-entry-grid">
                <div class="paper-entry-field span-3">
                    <label class="paper-entry-label">Property / ICS No.</label>
                    <input type="text" name="property_ics_number" class="paper-entry-input" value="<?php echo htmlspecialchars($form['property_ics_number'] ?? ''); ?>" placeholder="e.g. 1-07-05-030">
                    <div class="form-text">If blank, the item name will be used as the reference.</div>
                </div>
                <div class="paper-entry-field span-3">
                    <label class="paper-entry-label">Description</label>
                    <input type="text" name="item_name" class="paper-entry-input" value="<?php echo htmlspecialchars($form['item_name']); ?>" placeholder="e.g. Motorcycle" required>
                    <div class="form-text">Leave blank if the description is not available yet.</div>
                </div>
                <div class="paper-entry-field span-2">
                    <label class="paper-entry-label">Acquisition Type</label>
                    <select name="acquisition_type" id="acquisition_type" class="paper-entry-input" required>
                        <option value="Purchased" <?php echo ($form['acquisition_type'] ?? 'Purchased') === 'Purchased' ? 'selected' : ''; ?>>Purchased</option>
                        <option value="Donated" <?php echo ($form['acquisition_type'] ?? 'Purchased') === 'Donated' ? 'selected' : ''; ?>>Donated</option>
                        <option value="Other" <?php echo ($form['acquisition_type'] ?? 'Purchased') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="paper-entry-field span-2">
                    <label class="paper-entry-label">Date Acquired</label>
                    <input type="date" name="date_acquired" class="paper-entry-input" value="<?php echo htmlspecialchars($form['date_acquired'] ?: $form['date_purchased']); ?>" required>
                </div>
                <div class="paper-entry-field span-3 donor-field" id="donor_fields" style="display: none;">
                    <label class="paper-entry-label">Donor Name / Organization</label>
                    <input type="text" name="donor_name_organization" class="paper-entry-input" value="<?php echo htmlspecialchars($form['donor_name_organization'] ?? ''); ?>" placeholder="Individual, agency, LGU, NGO, or organization">
                </div>
                <div class="paper-entry-field span-3 donor-field" id="donor_organization_field" style="display: none;">
                    <label class="paper-entry-label">Donor Office / Department</label>
                    <input type="text" name="donor_office_department" class="paper-entry-input" value="<?php echo htmlspecialchars($form['donor_office_department'] ?? ''); ?>" placeholder="Optional office, division, or department">
                </div>
                <div class="paper-entry-field span-1">
                    <label class="paper-entry-label">Unit of Measure</label>
                    <input type="text" name="unit_measure" class="paper-entry-input" value="<?php echo htmlspecialchars($form['unit_measure'] ?: $form['unit']); ?>" required>
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
                    <input type="number" name="on_hand_per_count" class="paper-entry-input" value="<?php echo htmlspecialchars($form['on_hand_per_count'] ?? $form['quantity']); ?>" min="0" required>
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
                    <select name="condition_status" class="paper-entry-input" required>
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

const acquisitionTypeField = document.getElementById('acquisition_type');
const donorFields = document.querySelectorAll('.donor-field');
const toggleDonorFields = () => {
    const show = acquisitionTypeField && acquisitionTypeField.value === 'Donated';
    donorFields.forEach((field) => {
        field.style.display = show ? '' : 'none';
        const input = field.querySelector('input');
        if (input && !show) {
            input.value = '';
        }
    });
};

if (acquisitionTypeField) {
    acquisitionTypeField.addEventListener('change', toggleDonorFields);
    toggleDonorFields();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
