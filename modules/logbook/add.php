<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('add');
require_once '../../includes/db.php';

$error = '';
$items = $conn->query("SELECT item_id, item_name, tracking_number FROM items ORDER BY item_name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $item_id      = (int)$_POST['item_id'];
    $action       = $_POST['action'];
    $quantity     = (int)$_POST['quantity'];
    $borrowed_by  = trim($_POST['borrowed_by']);
    $purpose      = trim($_POST['purpose']);
    $date_action  = $_POST['date_action'];
    $date_returned = $_POST['date_returned'] ?: null;
    $recorded_by  = $_SESSION['user_id'];

    if (!$item_id || !$borrowed_by || !$date_action) {
        $error = "Please fill in all required fields.";
    } else {
        $stmt = $conn->prepare("INSERT INTO logbook (item_id, action, quantity, borrowed_by, purpose, date_action, date_returned, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isissssi", $item_id, $action, $quantity, $borrowed_by, $purpose, $date_action, $date_returned, $recorded_by);

        if ($stmt->execute()) {
            header("Location: /stocktrack/modules/logbook/index.php?success=Logbook entry added successfully.");
            exit();
        } else {
            $error = "Failed to add entry. Please try again.";
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
                            <option value="<?php echo $item['item_id']; ?>">
                                <?php echo htmlspecialchars($item['item_name'] . ' (' . $item['tracking_number'] . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Action <span class="text-danger">*</span></label>
                    <select name="action" class="form-select" required>
                        <option value="Borrowed">Borrowed</option>
                        <option value="Used">Used</option>
                        <option value="Returned">Returned</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" class="form-control" value="1" min="1">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Borrowed/Used By <span class="text-danger">*</span></label>
                    <input type="text" name="borrowed_by" class="form-control" placeholder="Full name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Purpose</label>
                    <input type="text" name="purpose" class="form-control" placeholder="Reason for borrowing/use">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" name="date_action" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date Returned <small class="text-muted">(if applicable)</small></label>
                    <input type="date" name="date_returned" class="form-control">
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
