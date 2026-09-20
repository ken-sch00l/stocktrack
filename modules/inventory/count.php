<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('edit');
require_once '../../includes/db.php';

$search = trim((string)($_GET['search'] ?? $_POST['search'] ?? ''));
$item = null;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $item_id = (int)($_POST['item_id'] ?? 0);
    $on_hand_per_count = filter_var($_POST['on_hand_per_count'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $condition_status = $_POST['condition_status'] ?? '';

    if ($item_id < 1 || $on_hand_per_count === false || !in_array($condition_status, ['Serviceable', 'Unserviceable'], true)) {
        $error = 'Select a valid item, non-negative on-hand count, and condition.';
    } else {
        $stmt = $conn->prepare("UPDATE items SET condition_status = ?, on_hand_per_count = ?, last_inventory_date = CURDATE() WHERE item_id = ?");
        $stmt->bind_param("sii", $condition_status, $on_hand_per_count, $item_id);
        if ($stmt->execute() && $stmt->affected_rows >= 0) {
            recordAudit('physical_count_updated', 'item', $item_id, $condition_status . ' count: ' . $on_hand_per_count);
            $success = 'Physical count saved.';
        } else {
            $error = 'Unable to save the physical count.';
        }
    }
}

if ($search !== '') {
    $search_value = '%' . $search . '%';
    $stmt = $conn->prepare("SELECT item_id, tracking_number, item_name, condition_status, on_hand_per_count, unit_measure FROM items WHERE tracking_number LIKE ? OR item_name LIKE ? ORDER BY item_name ASC LIMIT 50");
    $stmt->bind_param("ss", $search_value, $search_value);
    $stmt->execute();
    $items = $stmt->get_result();
    if ($items->num_rows === 1) {
        $item = $items->fetch_assoc();
    }
}

if ($item && $_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $item['condition_status'] = $_POST['condition_status'];
    $item['on_hand_per_count'] = (int)$_POST['on_hand_per_count'];
}
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-clipboard2-check me-2 text-primary"></i>Physical Count</h4>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Inventory</a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-10"><label class="form-label fw-semibold" for="countSearch">Search tracking number or item name</label><input id="countSearch" type="search" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="e.g. TRK00001 or Printer" required></div>
            <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Find item</button></div>
        </form>
    </div>
</div>

<?php if ($search !== '' && !$item && isset($items) && $items->num_rows > 1): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">Select an item</div>
    <div class="list-group list-group-flush">
        <?php while ($result_item = $items->fetch_assoc()): ?>
            <a href="?search=<?php echo urlencode($result_item['tracking_number']); ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <span><strong><?php echo htmlspecialchars($result_item['item_name']); ?></strong><small class="d-block text-muted"><?php echo htmlspecialchars($result_item['tracking_number']); ?></small></span>
                <span class="badge <?php echo $result_item['condition_status'] === 'Serviceable' ? 'badge-serviceable' : 'badge-unserviceable'; ?>"><?php echo htmlspecialchars($result_item['condition_status']); ?></span>
            </a>
        <?php endwhile; ?>
    </div>
</div>
<?php elseif ($search !== '' && !$item): ?>
<div class="alert alert-warning">No matching item found.</div>
<?php endif; ?>

<?php if ($item): ?>
<div class="card border-0 shadow-sm count-item-card">
    <div class="card-body">
        <div class="mb-3"><small class="text-muted">Tracking number</small><div class="fw-semibold"><?php echo htmlspecialchars($item['tracking_number']); ?></div><h5 class="fw-bold mt-2 mb-0"><?php echo htmlspecialchars($item['item_name']); ?></h5><small class="text-muted"><?php echo htmlspecialchars($item['unit_measure'] ?? ''); ?></small></div>
        <form method="POST">
            <?php csrf_field(); ?>
            <input type="hidden" name="item_id" value="<?php echo (int)$item['item_id']; ?>">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            <label class="form-label fw-semibold">Condition</label>
            <div class="btn-group w-100 mb-3" role="group" aria-label="Condition">
                <input type="radio" class="btn-check" name="condition_status" id="serviceable" value="Serviceable" <?php echo $item['condition_status'] === 'Serviceable' ? 'checked' : ''; ?> required>
                <label class="btn btn-outline-success" for="serviceable">Serviceable</label>
                <input type="radio" class="btn-check" name="condition_status" id="unserviceable" value="Unserviceable" <?php echo $item['condition_status'] === 'Unserviceable' ? 'checked' : ''; ?>>
                <label class="btn btn-outline-danger" for="unserviceable">Unserviceable</label>
            </div>
            <label class="form-label fw-semibold" for="onHandCount">On-hand count</label>
            <input id="onHandCount" type="number" name="on_hand_per_count" class="form-control form-control-lg mb-3" min="0" value="<?php echo (int)$item['on_hand_per_count']; ?>" required>
            <button type="submit" class="btn btn-primary btn-lg w-100"><i class="bi bi-save me-1"></i>Save Count</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
