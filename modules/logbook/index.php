<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('view');
require_once '../../includes/db.php';

$search = trim($_GET['search'] ?? '');
$action_filter = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort'] ?? 'date_action';
$sort_direction = strtoupper($_GET['direction'] ?? 'DESC');
$sort_columns = [
    'date_action' => 'l.date_action',
    'item' => 'i.item_name',
    'borrowed_by' => 'l.borrowed_by',
    'action' => 'l.action',
    'quantity' => 'l.quantity',
    'date_recorded' => 'l.created_at'
];
$sort_by = array_key_exists($sort_by, $sort_columns) ? $sort_by : 'date_action';
$sort_direction = in_array($sort_direction, ['ASC', 'DESC'], true) ? $sort_direction : 'DESC';

$where = 'WHERE 1=1';
$params = [];
$types = '';
if ($search) {
    $where .= " AND (i.item_name LIKE ? OR i.tracking_number LIKE ? OR i.property_ics_number LIKE ? OR l.borrowed_by LIKE ? OR l.purpose LIKE ?)";
    $search_value = "%$search%";
    array_push($params, $search_value, $search_value, $search_value, $search_value, $search_value);
    $types .= 'sssss';
}
if (in_array($action_filter, ['Borrowed', 'Used', 'Returned'], true)) {
    $where .= ' AND l.action = ?';
    $params[] = $action_filter;
    $types .= 's';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $where .= ' AND l.date_action >= ?';
    $params[] = $date_from;
    $types .= 's';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $where .= ' AND l.date_action <= ?';
    $params[] = $date_to;
    $types .= 's';
}

$log_sql = "SELECT l.*, COALESCE(i.item_name, 'Deleted item') AS item_name, COALESCE(i.tracking_number, 'N/A') AS tracking_number, u.full_name as recorder, CASE WHEN l.action = 'Borrowed' AND COALESCE(rt.returned_quantity, 0) = 0 THEN 'Still out' WHEN l.action = 'Borrowed' AND COALESCE(rt.returned_quantity, 0) < l.quantity THEN 'Partially returned' WHEN l.action = 'Borrowed' THEN 'Fully returned' WHEN l.action = 'Returned' THEN 'Return recorded' ELSE 'Completed' END AS transaction_status FROM logbook l LEFT JOIN items i ON l.item_id = i.item_id LEFT JOIN users u ON l.recorded_by = u.user_id LEFT JOIN (SELECT return_for_log_id, SUM(quantity) AS returned_quantity FROM logbook WHERE action = 'Returned' GROUP BY return_for_log_id) rt ON rt.return_for_log_id = l.log_id $where ORDER BY {$sort_columns[$sort_by]} $sort_direction, l.log_id DESC";
$log_stmt = $conn->prepare($log_sql);
if ($params) {
    $log_stmt->bind_param($types, ...$params);
}
$log_stmt->execute();
$logs = $log_stmt->get_result();
$outstanding = $conn->query("SELECT l.borrowed_by, COALESCE(SUM(CASE WHEN l.action = 'Borrowed' THEN l.quantity WHEN l.action = 'Returned' THEN -l.quantity ELSE 0 END), 0) AS outstanding_quantity, GROUP_CONCAT(DISTINCT COALESCE(i.item_name, 'Deleted item') ORDER BY i.item_name SEPARATOR ', ') AS item_names FROM logbook l LEFT JOIN items i ON l.item_id = i.item_id GROUP BY l.borrowed_by HAVING outstanding_quantity > 0 ORDER BY l.borrowed_by ASC");
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Logbook</h4>
    <?php if (hasAnyRole(['admin', 'secretary', 'treasurer', 'committee'])): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Add Entry
        </a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible">
        <?php echo htmlspecialchars($_GET['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3 filter-toolbar">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Item, property no., tracking no., borrower, purpose" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Action</label>
                <select name="action" class="form-select">
                    <option value="">All actions</option>
                    <option value="Borrowed" <?php echo $action_filter === 'Borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                    <option value="Used" <?php echo $action_filter === 'Used' ? 'selected' : ''; ?>>Used</option>
                    <option value="Returned" <?php echo $action_filter === 'Returned' ? 'selected' : ''; ?>>Returned</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sort</label>
                <select name="sort" class="form-select">
                    <option value="date_action" <?php echo $sort_by === 'date_action' ? 'selected' : ''; ?>>Action date</option>
                    <option value="item" <?php echo $sort_by === 'item' ? 'selected' : ''; ?>>Item</option>
                    <option value="borrowed_by" <?php echo $sort_by === 'borrowed_by' ? 'selected' : ''; ?>>Borrower</option>
                    <option value="action" <?php echo $sort_by === 'action' ? 'selected' : ''; ?>>Action</option>
                    <option value="quantity" <?php echo $sort_by === 'quantity' ? 'selected' : ''; ?>>Quantity</option>
                    <option value="date_recorded" <?php echo $sort_by === 'date_recorded' ? 'selected' : ''; ?>>Date recorded</option>
                </select>
            </div>
            <div class="col-md-1">
                <select name="direction" class="form-select" aria-label="Sort direction">
                    <option value="DESC" <?php echo $sort_direction === 'DESC' ? 'selected' : ''; ?>>Down</option>
                    <option value="ASC" <?php echo $sort_direction === 'ASC' ? 'selected' : ''; ?>>Up</option>
                </select>
            </div>
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Apply filters</button>
                <a href="index.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Tracking No.</th>
                    <th>Action</th>
                    <th>Qty</th>
                    <th>Borrowed By</th>
                    <th>Purpose</th>
                    <th>Date</th>
                    <th>Date Returned</th>
                    <th>Status</th>
                    <th>Recorded By</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs->num_rows > 0): ?>
                    <?php while($row = $logs->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                        <td><code><?php echo htmlspecialchars($row['tracking_number']); ?></code></td>
                        <td><span class="badge bg-secondary"><?php echo $row['action']; ?></span></td>
                        <td><?php echo $row['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($row['borrowed_by']); ?></td>
                        <td><?php echo htmlspecialchars($row['purpose'] ?? 'N/A'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['date_action'])); ?></td>
                        <td><?php echo $row['date_returned'] ? date('M d, Y', strtotime($row['date_returned'])) : '<span class="text-warning">Pending</span>'; ?></td>
                        <td>
                            <?php if ($row['transaction_status'] === 'Still out'): ?>
                                <span class="badge bg-danger">Still out</span>
                            <?php elseif ($row['transaction_status'] === 'Partially returned'): ?>
                                <span class="badge bg-warning text-dark">Partially returned</span>
                            <?php elseif ($row['transaction_status'] === 'Fully returned' || $row['transaction_status'] === 'Return recorded'): ?>
                                <span class="badge bg-success"><?php echo htmlspecialchars($row['transaction_status']); ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Completed</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['recorder'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">No logbook entries yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-person-exclamation me-2 text-warning"></i>Outstanding Borrowed Items</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Borrowed By</th><th>Items</th><th>Quantity Still Out</th></tr></thead>
            <tbody>
                <?php if ($outstanding->num_rows > 0): ?>
                    <?php while ($borrower = $outstanding->fetch_assoc()): ?>
                    <tr><td><?php echo htmlspecialchars($borrower['borrowed_by']); ?></td><td><?php echo htmlspecialchars($borrower['item_names']); ?></td><td><span class="badge bg-warning text-dark"><?php echo (int)$borrower['outstanding_quantity']; ?></span></td></tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">No items are currently borrowed.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
