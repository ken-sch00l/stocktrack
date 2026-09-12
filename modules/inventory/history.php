<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('view');
require_once '../../includes/db.php';

$period = isset($_GET['period']) ? $_GET['period'] : 'monthly';
$value  = isset($_GET['value'])  ? $_GET['value']  : date('Y-m');
$sort_by = $_GET['sort'] ?? 'item_name';
$sort_direction = strtoupper($_GET['direction'] ?? 'ASC');
$sort_columns = [
    'item_name' => 'i.item_name',
    'category' => 'c.category_name',
    'condition' => 'i.condition_status',
    'quantity' => 'i.quantity',
    'date_purchased' => 'i.date_purchased',
    'date_recorded' => 'i.created_at'
];
$sort_by = array_key_exists($sort_by, $sort_columns) ? $sort_by : 'item_name';
$sort_direction = in_array($sort_direction, ['ASC', 'DESC'], true) ? $sort_direction : 'ASC';

$start_date = '';
$end_date = '';
$label = 'All';

if ($period === 'weekly') {
    $start_date = date('Y-m-d', strtotime('-7 days'));
    $end_date = date('Y-m-d', strtotime('+1 day'));
    $label = 'Last 7 Days';
} elseif ($period === 'monthly' && preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $value)) {
    $month_start = new DateTimeImmutable($value . '-01');
    $start_date = $month_start->format('Y-m-d');
    $end_date = $month_start->modify('+1 month')->format('Y-m-d');
    $label = $month_start->format('F Y');
} elseif ($period === 'yearly' && preg_match('/^20\d{2}$/', $value)) {
    $year_start = new DateTimeImmutable($value . '-01-01');
    $start_date = $year_start->format('Y-m-d');
    $end_date = $year_start->modify('+1 year')->format('Y-m-d');
    $label = $value;
} else {
    http_response_code(400);
    exit('Invalid inventory history period.');
}

$where = "WHERE i.created_at >= ? AND i.created_at < ?";

$item_stmt = $conn->prepare("SELECT i.*, c.category_name FROM items i LEFT JOIN categories c ON i.category_id = c.category_id $where ORDER BY {$sort_columns[$sort_by]} $sort_direction, i.item_id ASC");
$item_stmt->bind_param("ss", $start_date, $end_date);
$item_stmt->execute();
$items = $item_stmt->get_result();

$count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM items i $where");
$count_stmt->bind_param("ss", $start_date, $end_date);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['cnt'];

$serviceable_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM items i $where AND i.condition_status = 'Serviceable'");
$serviceable_stmt->bind_param("ss", $start_date, $end_date);
$serviceable_stmt->execute();
$serv = $serviceable_stmt->get_result()->fetch_assoc()['cnt'];

$unserviceable_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM items i $where AND i.condition_status = 'Unserviceable'");
$unserviceable_stmt->bind_param("ss", $start_date, $end_date);
$unserviceable_stmt->execute();
$unserv = $unserviceable_stmt->get_result()->fetch_assoc()['cnt'];
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Inventory History</h4>
</div>

<div class="card border-0 shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Period</label>
                <select name="period" class="form-select" id="periodSel" onchange="toggleValue()">
                    <option value="weekly"  <?php echo $period === 'weekly'  ? 'selected' : ''; ?>>Weekly (Last 7 Days)</option>
                    <option value="monthly" <?php echo $period === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    <option value="yearly"  <?php echo $period === 'yearly'  ? 'selected' : ''; ?>>Yearly</option>
                </select>
            </div>
            <div class="col-md-3" id="valueDiv">
                <label class="form-label fw-semibold">Select Period</label>
                <input type="<?php echo $period === 'yearly' ? 'number' : 'month'; ?>"
                       name="value"
                       class="form-control"
                       value="<?php echo htmlspecialchars($value); ?>"
                       <?php echo $period === 'yearly' ? 'min="2000" max="2099"' : ''; ?>>
            </div>
            <div class="col-md-2">
                <select name="sort" class="form-select" aria-label="Sort history">
                    <option value="item_name" <?php echo $sort_by === 'item_name' ? 'selected' : ''; ?>>Name</option>
                    <option value="category" <?php echo $sort_by === 'category' ? 'selected' : ''; ?>>Category</option>
                    <option value="condition" <?php echo $sort_by === 'condition' ? 'selected' : ''; ?>>Condition</option>
                    <option value="quantity" <?php echo $sort_by === 'quantity' ? 'selected' : ''; ?>>Quantity</option>
                    <option value="date_purchased" <?php echo $sort_by === 'date_purchased' ? 'selected' : ''; ?>>Purchase date</option>
                    <option value="date_recorded" <?php echo $sort_by === 'date_recorded' ? 'selected' : ''; ?>>Date recorded</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="direction" class="form-select" aria-label="Sort direction">
                    <option value="ASC" <?php echo $sort_direction === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                    <option value="DESC" <?php echo $sort_direction === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
            </div>
            <div class="col-md-2">
                <button type="button" onclick="window.print()" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card stat-card text-center p-3">
            <div class="text-primary" style="font-size:1.8rem;"><i class="bi bi-archive"></i></div>
            <h4 class="fw-bold mb-0"><?php echo $total; ?></h4>
            <small class="text-muted">Total Items</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card text-center p-3">
            <div class="text-success" style="font-size:1.8rem;"><i class="bi bi-check-circle"></i></div>
            <h4 class="fw-bold mb-0"><?php echo $serv; ?></h4>
            <small class="text-muted">Serviceable</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card text-center p-3">
            <div class="text-danger" style="font-size:1.8rem;"><i class="bi bi-x-circle"></i></div>
            <h4 class="fw-bold mb-0"><?php echo $unserv; ?></h4>
            <small class="text-muted">Unserviceable</small>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between">
        <span class="fw-semibold">Records for: <?php echo $label ?: 'All'; ?></span>
        <span class="text-muted small"><?php echo $total; ?> item(s)</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Tracking No.</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Condition</th>
                    <th>Qty</th>
                    <th>Date Purchased</th>
                    <th>Person in Charge</th>
                    <th>Date Recorded</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($items->num_rows > 0): ?>
                    <?php while($row = $items->fetch_assoc()): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($row['tracking_number']); ?></code></td>
                        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                        <td><small><?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></small></td>
                        <td>
                            <?php if ($row['condition_status'] === 'Serviceable'): ?>
                                <span class="badge-serviceable">Serviceable</span>
                            <?php else: ?>
                                <span class="badge-unserviceable">Unserviceable</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $row['quantity']; ?></td>
                        <td><?php echo $row['date_purchased'] ? date('M d, Y', strtotime($row['date_purchased'])) : 'N/A'; ?></td>
                        <td><?php echo htmlspecialchars($row['person_in_charge'] ?? 'N/A'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No records found for this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleValue() {
    const period = document.getElementById('periodSel').value;
    const div    = document.getElementById('valueDiv');
    const input  = div.querySelector('input');
    if (period === 'weekly') {
        div.style.display = 'none';
    } else if (period === 'monthly') {
        div.style.display = 'block';
        input.type = 'month';
    } else {
        div.style.display = 'block';
        input.type = 'number';
        input.min  = '2000';
        input.max  = '2099';
    }
}
toggleValue();
</script>

<?php require_once '../../includes/footer.php'; ?>
