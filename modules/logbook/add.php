<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('add');
require_once '../../includes/db.php';

$error = '';
$form_item_id = '';
$form_action = 'Borrowed';
$form_quantity = 1;
$form_borrowed_by = '';
$form_purpose = '';
$form_date_action = '';
$form_date_returned = '';
$items = $conn->query("SELECT item_id, item_name, tracking_number FROM items ORDER BY item_name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $form_item_id = $_POST['item_id'] ?? '';
    $form_action = $_POST['action'] ?? 'Borrowed';
    $form_quantity = $_POST['quantity'] ?? 1;
    $form_borrowed_by = trim($_POST['borrowed_by'] ?? '');
    $form_purpose = trim($_POST['purpose'] ?? '');
    $form_date_action = $_POST['date_action'] ?? '';
    $form_date_returned = $_POST['date_returned'] ?? '';

    $item_id      = (int)$form_item_id;
    $action       = $form_action;
    $quantity     = (int)$form_quantity;
    $borrowed_by  = $form_borrowed_by;
    $purpose      = $form_purpose;
    $date_action  = $form_date_action;
    $date_returned = $form_date_returned ?: null;
    $recorded_by  = $_SESSION['user_id'];

    $allowed_actions = ['Borrowed', 'Used', 'Returned'];
    if (!$item_id || !$borrowed_by || !$date_action) {
        $error = "Please fill in all required fields.";
    } elseif (!in_array($action, $allowed_actions, true)) {
        $error = "Invalid logbook action.";
    } elseif ($quantity < 1) {
        $error = "Quantity must be at least 1.";
    } else {
        $conn->begin_transaction();

        $item_stmt = $conn->prepare("SELECT quantity FROM items WHERE item_id = ? FOR UPDATE");
        $item_stmt->bind_param("i", $item_id);
        $item_stmt->execute();
        $item = $item_stmt->get_result()->fetch_assoc();

        if (!$item) {
            $conn->rollback();
            $error = "Selected item was not found.";
        } else {
            $usage_stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN action = 'Borrowed' AND date_returned IS NULL THEN quantity WHEN action = 'Used' THEN quantity WHEN action = 'Returned' THEN -quantity ELSE 0 END), 0) AS allocated_quantity, COALESCE(SUM(CASE WHEN action = 'Borrowed' AND date_returned IS NULL THEN quantity WHEN action = 'Returned' THEN -quantity ELSE 0 END), 0) AS borrowed_quantity FROM logbook WHERE item_id = ?");
            $usage_stmt->bind_param("i", $item_id);
            $usage_stmt->execute();
            $usage = $usage_stmt->get_result()->fetch_assoc();
            $available_quantity = (int)$item['quantity'] - (int)$usage['allocated_quantity'];

            if (($action === 'Borrowed' || $action === 'Used') && $quantity > $available_quantity) {
                $conn->rollback();
                $error = "Insufficient available stock. Only {$available_quantity} item(s) are available.";
            } elseif ($action === 'Returned' && $quantity > (int)$usage['borrowed_quantity']) {
                $conn->rollback();
                $error = "Cannot return more items than are currently borrowed.";
            } else {
                $stmt = $conn->prepare("INSERT INTO logbook (item_id, action, quantity, borrowed_by, purpose, date_action, date_returned, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isissssi", $item_id, $action, $quantity, $borrowed_by, $purpose, $date_action, $date_returned, $recorded_by);

                if ($stmt->execute()) {
                    $conn->commit();
                    recordAudit('logbook_created', 'logbook', $conn->insert_id, $action . ': ' . $quantity);
                    header("Location: /stocktrack/modules/logbook/index.php?success=Logbook entry added successfully.");
                    exit();
                }

                $conn->rollback();
                $error = "Failed to add entry. Please try again.";
            }
        }
    }
}
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-plus-circle me-2 text-primary"></i>Add Logbook Entry</h4>
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
                    <label class="form-label">Item <span class="text-danger">*</span></label>
                    <select name="item_id" class="form-select" required>
                        <option value="">Select Item</option>
                        <?php while($item = $items->fetch_assoc()): ?>
                            <option value="<?php echo $item['item_id']; ?>" <?php echo (string)$form_item_id === (string)$item['item_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['item_name'] . ' (' . $item['tracking_number'] . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Action <span class="text-danger">*</span></label>
                    <select name="action" class="form-select" required>
                        <option value="Borrowed" <?php echo $form_action === 'Borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                        <option value="Used" <?php echo $form_action === 'Used' ? 'selected' : ''; ?>>Used</option>
                        <option value="Returned" <?php echo $form_action === 'Returned' ? 'selected' : ''; ?>>Returned</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" class="form-control" value="<?php echo htmlspecialchars((string)$form_quantity, ENT_QUOTES, 'UTF-8'); ?>" min="1">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Borrowed/Used By <span class="text-danger">*</span></label>
                    <input type="text" name="borrowed_by" class="form-control" placeholder="Full name" value="<?php echo htmlspecialchars($form_borrowed_by, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Purpose</label>
                    <input type="text" name="purpose" class="form-control" placeholder="Reason for borrowing/use" value="<?php echo htmlspecialchars($form_purpose, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" name="date_action" class="form-control" value="<?php echo htmlspecialchars($form_date_action, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date Returned <small class="text-muted">(if applicable)</small></label>
                    <input type="date" name="date_returned" class="form-control" value="<?php echo htmlspecialchars($form_date_returned, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save me-1"></i>Save Entry
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
