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
$form_return_for_log_id = '';
$items = $conn->query("SELECT i.item_id, i.item_name, i.tracking_number, i.quantity AS total_quantity, COALESCE(SUM(CASE WHEN l.action = 'Borrowed' THEN l.quantity WHEN l.action = 'Used' THEN l.quantity WHEN l.action = 'Returned' THEN -l.quantity ELSE 0 END), 0) AS allocated_quantity, COALESCE(SUM(CASE WHEN l.action = 'Borrowed' THEN l.quantity WHEN l.action = 'Returned' THEN -l.quantity ELSE 0 END), 0) AS borrowed_quantity FROM items i LEFT JOIN logbook l ON l.item_id = i.item_id GROUP BY i.item_id ORDER BY i.item_name ASC");
$borrowings = $conn->query("SELECT b.log_id, b.item_id, b.borrowed_by, b.quantity, b.date_action, i.item_name, b.quantity - COALESCE(SUM(r.quantity), 0) AS remaining_quantity FROM logbook b JOIN items i ON i.item_id = b.item_id LEFT JOIN logbook r ON r.return_for_log_id = b.log_id AND r.action = 'Returned' WHERE b.action = 'Borrowed' GROUP BY b.log_id, b.item_id, b.borrowed_by, b.quantity, b.date_action, i.item_name HAVING remaining_quantity > 0 ORDER BY b.date_action ASC, i.item_name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $form_item_id = $_POST['item_id'] ?? '';
    $form_action = $_POST['action'] ?? 'Borrowed';
    $form_quantity = $_POST['quantity'] ?? 1;
    $form_borrowed_by = trim($_POST['borrowed_by'] ?? '');
    $form_purpose = trim($_POST['purpose'] ?? '');
    $form_date_action = $_POST['date_action'] ?? '';
    $form_date_returned = $_POST['date_returned'] ?? '';
    $form_return_for_log_id = $_POST['return_for_log_id'] ?? '';

    $item_id      = (int)$form_item_id;
    $action       = $form_action;
    $quantity     = (int)$form_quantity;
    $borrowed_by  = $form_borrowed_by;
    $purpose      = $form_purpose;
    $date_action  = $form_date_action;
    $date_returned = $form_date_returned ?: null;
    if ($action !== 'Returned') {
        $date_returned = null;
    }
    $recorded_by  = $_SESSION['user_id'];

    $allowed_actions = ['Borrowed', 'Used', 'Returned'];
    if (!$item_id || !$borrowed_by || !$date_action) {
        $error = "Please fill in all required fields.";
    } elseif (!in_array($action, $allowed_actions, true)) {
        $error = "Invalid logbook action.";
    } elseif ($quantity < 1) {
        $error = "Quantity must be at least 1.";
    } elseif ($action === 'Returned' && !(int)$form_return_for_log_id) {
        $error = "Select the specific borrowing transaction being returned.";
    } else {
        $conn->begin_transaction();

        $item_stmt = $conn->prepare("SELECT item_name, quantity FROM items WHERE item_id = ? FOR UPDATE");
        $item_stmt->bind_param("i", $item_id);
        $item_stmt->execute();
        $item = $item_stmt->get_result()->fetch_assoc();

        if (!$item) {
            $conn->rollback();
            $error = "Selected item was not found.";
        } else {
            $usage_stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN action = 'Borrowed' THEN quantity WHEN action = 'Used' THEN quantity WHEN action = 'Returned' THEN -quantity ELSE 0 END), 0) AS allocated_quantity, COALESCE(SUM(CASE WHEN action = 'Borrowed' THEN quantity WHEN action = 'Returned' THEN -quantity ELSE 0 END), 0) AS borrowed_quantity FROM logbook WHERE item_id = ?");
            $usage_stmt->bind_param("i", $item_id);
            $usage_stmt->execute();
            $usage = $usage_stmt->get_result()->fetch_assoc();
            $available_quantity = (int)$item['quantity'] - (int)$usage['allocated_quantity'];

            $returning_borrow = null;
            if ($action === 'Returned') {
                $return_stmt = $conn->prepare("SELECT b.item_id, b.borrowed_by, b.quantity - COALESCE(SUM(r.quantity), 0) AS remaining_quantity FROM logbook b LEFT JOIN logbook r ON r.return_for_log_id = b.log_id AND r.action = 'Returned' WHERE b.log_id = ? AND b.action = 'Borrowed' GROUP BY b.log_id, b.item_id, b.borrowed_by, b.quantity FOR UPDATE");
                $return_stmt->bind_param("i", $form_return_for_log_id);
                $return_stmt->execute();
                $returning_borrow = $return_stmt->get_result()->fetch_assoc();
            }

            if (($action === 'Borrowed' || $action === 'Used') && $quantity > $available_quantity) {
                $conn->rollback();
                $error = "Insufficient available stock. Only {$available_quantity} item(s) are available.";
            } elseif ($action === 'Returned' && (!$returning_borrow || (int)$returning_borrow['item_id'] !== $item_id)) {
                $conn->rollback();
                $error = "The selected borrowing transaction does not belong to this item.";
            } elseif ($action === 'Returned' && $quantity > (int)$returning_borrow['remaining_quantity']) {
                $conn->rollback();
                $error = "Only {$returning_borrow['remaining_quantity']} item(s) remain to be returned for this borrowing transaction.";
            } else {
                if ($action === 'Returned') {
                    $borrowed_by = $returning_borrow['borrowed_by'];
                    $return_for_log_id = (int)$form_return_for_log_id;
                    $stmt = $conn->prepare("INSERT INTO logbook (item_id, return_for_log_id, action, quantity, borrowed_by, purpose, date_action, date_returned, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iisissssi", $item_id, $return_for_log_id, $action, $quantity, $borrowed_by, $purpose, $date_action, $date_returned, $recorded_by);
                } else {
                    $stmt = $conn->prepare("INSERT INTO logbook (item_id, return_for_log_id, action, quantity, borrowed_by, purpose, date_action, date_returned, recorded_by) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isissssi", $item_id, $action, $quantity, $borrowed_by, $purpose, $date_action, $date_returned, $recorded_by);
                }

                if ($stmt->execute()) {
                    $logbook_id = $conn->insert_id;
                    $notification_message = $action . ' ' . $quantity . ' of ' . $item['item_name'] . ' by ' . $borrowed_by . '.';
                    $recipient_stmt = $conn->prepare("SELECT user_id FROM users WHERE role IN ('admin', 'treasurer')");
                    $recipient_stmt->execute();
                    $recipients = $recipient_stmt->get_result();
                    $notification_stmt = $conn->prepare("INSERT INTO notifications (recipient_user_id, logbook_id, notification_type, message) VALUES (?, ?, ?, ?)");
                    $notification_type = 'logbook_' . strtolower($action);
                    while ($recipient = $recipients->fetch_assoc()) {
                        $notification_stmt->bind_param("iiss", $recipient['user_id'], $logbook_id, $notification_type, $notification_message);
                        $notification_stmt->execute();
                    }
                    $conn->commit();
                    recordAudit('logbook_created', 'logbook', $logbook_id, $action . ': ' . $quantity);
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
                    <input type="search" id="itemSearch" class="form-control mb-2" placeholder="Search item name or tracking number" autocomplete="off">
                    <select name="item_id" class="form-select" required>
                        <option value="">Select Item</option>
                        <?php while($item = $items->fetch_assoc()): ?>
                            <option value="<?php echo $item['item_id']; ?>" data-total="<?php echo (int)$item['total_quantity']; ?>" data-available="<?php echo max(0, (int)$item['total_quantity'] - (int)$item['allocated_quantity']); ?>" data-borrowed="<?php echo max(0, (int)$item['borrowed_quantity']); ?>" <?php echo (string)$form_item_id === (string)$item['item_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['item_name'] . ' (' . $item['tracking_number'] . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-12">
                    <div id="stockTracker" class="alert alert-secondary mb-0" role="status">
                        Select an item to see its current stock status.
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Action <span class="text-danger">*</span></label>
                    <select name="action" class="form-select" required>
                        <option value="Borrowed" <?php echo $form_action === 'Borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                        <option value="Used" <?php echo $form_action === 'Used' ? 'selected' : ''; ?>>Used</option>
                        <option value="Returned" <?php echo $form_action === 'Returned' ? 'selected' : ''; ?>>Returned</option>
                    </select>
                </div>
                <div class="col-md-6" id="returnTransactionField">
                    <label class="form-label">Borrowing to return <span class="text-danger">*</span></label>
                    <select name="return_for_log_id" class="form-select">
                        <option value="">Select borrowing transaction</option>
                        <?php while ($borrowing = $borrowings->fetch_assoc()): ?>
                            <option value="<?php echo (int)$borrowing['log_id']; ?>" data-item-id="<?php echo (int)$borrowing['item_id']; ?>" data-remaining="<?php echo (int)$borrowing['remaining_quantity']; ?>" <?php echo (string)$form_return_for_log_id === (string)$borrowing['log_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($borrowing['item_name'] . ' - ' . $borrowing['borrowed_by'] . ' (' . $borrowing['remaining_quantity'] . ' remaining)'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small class="text-muted">This ties the return to the exact borrower and item.</small>
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

<script>
    const itemSelect = document.querySelector('select[name="item_id"]');
    const itemSearch = document.getElementById('itemSearch');
    const actionSelect = document.querySelector('select[name="action"]');
    const stockTracker = document.getElementById('stockTracker');
    const returnTransactionField = document.getElementById('returnTransactionField');
    const returnTransactionSelect = document.querySelector('select[name="return_for_log_id"]');
    let latestStock = null;

    function renderStockTracker(stock) {
        const selectedItem = itemSelect.options[itemSelect.selectedIndex];
        if (!selectedItem || !selectedItem.value) {
            stockTracker.className = 'alert alert-secondary mb-0';
            stockTracker.textContent = 'Select an item to see its current stock status.';
            return;
        }

        const available = stock.available;
        const borrowed = stock.borrowed;
        const total = stock.total;
        const action = actionSelect.value;
        const amount = action === 'Returned' ? borrowed : available;
        stockTracker.className = amount > 0 ? 'alert alert-success mb-0' : 'alert alert-warning mb-0';
        stockTracker.innerHTML = '<strong>' + (action === 'Returned' ? 'Can return now: ' + borrowed : 'Available now: ' + available) + '</strong>' +
            ' &middot; Total stock: ' + total + ' &middot; Currently out: ' + borrowed +
            '<br><small>' + (action === 'Returned' ? 'Enter the quantity being returned.' : 'You cannot record more than this available quantity.') + '</small>';
    }

    async function refreshStockTracker() {
        if (!itemSelect.value) {
            renderStockTracker(null);
            return;
        }

        try {
            const response = await fetch('stock_status.php?item_id=' + encodeURIComponent(itemSelect.value), { cache: 'no-store' });
            if (!response.ok) throw new Error('Stock status unavailable');
            latestStock = await response.json();
            renderStockTracker(latestStock);
        } catch (error) {
            stockTracker.className = 'alert alert-warning mb-0';
            stockTracker.textContent = 'Stock status could not be refreshed. The server will still verify the quantity when you save.';
        }
    }

    itemSearch.addEventListener('input', function() {
        const searchTerm = itemSearch.value.trim().toLowerCase();
        Array.from(itemSelect.options).forEach(function(option) {
            if (!option.value) return;
            option.hidden = searchTerm !== '' && !option.textContent.toLowerCase().includes(searchTerm);
        });
    });

    itemSelect.addEventListener('change', refreshStockTracker);
    actionSelect.addEventListener('change', function() {
        returnTransactionField.style.display = actionSelect.value === 'Returned' ? 'block' : 'none';
        if (latestStock) renderStockTracker(latestStock);
    });
    returnTransactionField.style.display = actionSelect.value === 'Returned' ? 'block' : 'none';
    refreshStockTracker();
    setInterval(refreshStockTracker, 10000);
</script>

<?php require_once '../../includes/footer.php'; ?>
