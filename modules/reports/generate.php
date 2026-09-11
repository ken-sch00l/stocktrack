<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('add');
require_once '../../includes/db.php';

$type = isset($_GET['type']) ? $_GET['type'] : 'ICS';
$allowed_types = ['RIS', 'ICS', 'PAR'];
if (!in_array($type, $allowed_types)) $type = 'ICS';

$period_type = isset($_POST['period_type']) ? $_POST['period_type'] : '';
$period_value = isset($_POST['period_value']) ? $_POST['period_value'] : '';
$items = [];
$error = '';
$report_generated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $period_type) {
    $start_date = '';
    $end_date = '';

    if ($period_type === 'Weekly') {
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d', strtotime('+1 day'));
    } elseif ($period_type === 'Monthly' && preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $period_value)) {
        $month_start = new DateTimeImmutable($period_value . '-01');
        $start_date = $month_start->format('Y-m-d');
        $end_date = $month_start->modify('+1 month')->format('Y-m-d');
    } elseif ($period_type === 'Yearly' && preg_match('/^20\d{2}$/', $period_value)) {
        $year_start = new DateTimeImmutable($period_value . '-01-01');
        $start_date = $year_start->format('Y-m-d');
        $end_date = $year_start->modify('+1 year')->format('Y-m-d');
    } else {
        $error = 'Please provide a valid report period.';
    }

    if (!$error) {
        $stmt = $conn->prepare("SELECT i.*, c.category_name FROM items i LEFT JOIN categories c ON i.category_id = c.category_id WHERE i.created_at >= ? AND i.created_at < ? ORDER BY i.item_name ASC");
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

        $log_stmt = $conn->prepare("INSERT INTO reports_log (report_type, period_type, period_value, generated_by) VALUES (?, ?, ?, ?)");
        $log_stmt->bind_param("sssi", $type, $period_type, $period_value, $_SESSION['user_id']);
        $log_stmt->execute();
        $report_generated = true;
    }
}
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">
        <i class="bi bi-file-earmark-text me-2 text-primary"></i>
        Generate <?php echo $type; ?> Report
    </h4>
    <a href="/stocktrack/modules/reports/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body">
        <form method="POST">
            <?php csrf_field(); ?>
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Period Type</label>
                    <select name="period_type" class="form-select" id="periodType" required onchange="togglePeriodValue()">
                        <option value="">Select Period</option>
                        <option value="Weekly" <?php echo $period_type === 'Weekly' ? 'selected' : ''; ?>>Weekly (Last 7 Days)</option>
                        <option value="Monthly" <?php echo $period_type === 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="Yearly" <?php echo $period_type === 'Yearly' ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </div>
                <div class="col-md-4" id="periodValueDiv" style="<?php echo $period_type === 'Weekly' ? 'display:none' : ''; ?>">
                    <label class="form-label fw-semibold">Period Value</label>
                    <input type="<?php echo $period_type === 'Yearly' ? 'number' : 'month'; ?>" 
                           name="period_value" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($period_value); ?>"
                           placeholder="<?php echo $period_type === 'Yearly' ? 'e.g. 2025' : ''; ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i>Generate Report
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php elseif ($report_generated): ?>

<div class="card border-0 shadow-sm" id="reportOutput">
    <div class="card-body">
        <!-- Report Header -->
        <div class="text-center mb-4">
            <h5 class="fw-bold mb-0">BARANGAY PUGUIS</h5>
            <p class="mb-0">La Trinidad, Benguet</p>
            <hr>
            <?php if ($type === 'RIS'): ?>
                <h5 class="fw-bold">REQUISITION AND ISSUE SLIP (RIS)</h5>
            <?php elseif ($type === 'ICS'): ?>
                <h5 class="fw-bold">INVENTORY CUSTODIAN SLIP (ICS)</h5>
            <?php else: ?>
                <h5 class="fw-bold">PROPERTY ACKNOWLEDGEMENT RECEIPT (PAR)</h5>
            <?php endif; ?>
            <p class="mb-0">
                Period: <?php echo $period_type; ?> 
                <?php echo $period_type !== 'Weekly' ? '- ' . $period_value : '(Last 7 Days)'; ?>
            </p>
            <p class="mb-0">Date Generated: <?php echo date('F d, Y'); ?></p>
        </div>

        <?php if ($type === 'RIS'): ?>
        <!-- RIS FORMAT -->
        <table class="table table-bordered">
            <thead>
                <tr class="text-center">
                    <th>Stock No. / Tracking No.</th>
                    <th>Unit</th>
                    <th>Description</th>
                    <th>Quantity Requested</th>
                    <th>Quantity Issued</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['tracking_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['unit'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                        <td class="text-center"><?php echo $row['quantity']; ?></td>
                        <td class="text-center"><?php echo $row['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($row['condition_status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No items found for selected period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="row mt-4">
            <div class="col-md-4 text-center">
                <div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;">
                    <strong>Requested by:</strong><br>
                    Barangay Secretary
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;">
                    <strong>Approved by:</strong><br>
                    Barangay Captain
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;">
                    <strong>Issued by:</strong><br>
                    Barangay Treasurer
                </div>
            </div>
        </div>

        <?php elseif ($type === 'ICS'): ?>
        <!-- ICS FORMAT -->
        <table class="table table-bordered">
            <thead>
                <tr class="text-center">
                    <th>Tracking No.</th>
                    <th>Description</th>
                    <th>Serial No.</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Date Purchased</th>
                    <th>Condition</th>
                    <th>Person in Charge</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['tracking_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['serial_number'] ?? 'N/A'); ?></td>
                        <td class="text-center"><?php echo $row['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($row['unit'] ?? ''); ?></td>
                        <td><?php echo $row['date_purchased'] ? date('m/d/Y', strtotime($row['date_purchased'])) : 'N/A'; ?></td>
                        <td>
                            <?php if ($row['condition_status'] === 'Serviceable'): ?>
                                <span style="color:green; font-weight:bold;">Serviceable</span>
                            <?php else: ?>
                                <span style="color:red; font-weight:bold;">Unserviceable</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['person_in_charge'] ?? 'N/A'); ?><br>
                            <small><?php echo htmlspecialchars($row['position'] ?? ''); ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">No items found for selected period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="row mt-4">
            <div class="col-md-6 text-center">
                <div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;">
                    <strong>Received from:</strong><br>
                    Barangay Treasurer / Property Custodian
                </div>
            </div>
            <div class="col-md-6 text-center">
                <div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;">
                    <strong>Received by:</strong><br>
                    Position / Designation
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- PAR FORMAT -->
        <table class="table table-bordered">
            <thead>
                <tr class="text-center">
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Description</th>
                    <th>Tracking No.</th>
                    <th>Serial No.</th>
                    <th>Date Purchased</th>
                    <th>Condition</th>
                    <th>Person in Charge</th>
                    <th>Position</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $row): ?>
                    <tr>
                        <td class="text-center"><?php echo $row['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($row['unit'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['tracking_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['serial_number'] ?? 'N/A'); ?></td>
                        <td><?php echo $row['date_purchased'] ? date('m/d/Y', strtotime($row['date_purchased'])) : 'N/A'; ?></td>
                        <td>
                            <?php if ($row['condition_status'] === 'Serviceable'): ?>
                                <span style="color:green; font-weight:bold;">S</span>
                            <?php else: ?>
                                <span style="color:red; font-weight:bold;">U</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['person_in_charge'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['position'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center text-muted py-3">No items found for selected period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="row mt-4">
            <div class="col-md-6 text-center">
                <div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;">
                    <strong>Received from:</strong><br>
                    Barangay Treasurer / Property Custodian
                </div>
            </div>
            <div class="col-md-6 text-center">
                <div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;">
                    <strong>Received by:</strong><br>
                    Signature over Printed Name / Position
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="text-center mt-4 no-print">
            <button onclick="window.print()" class="btn btn-primary px-5">
                <i class="bi bi-printer me-2"></i>Print Report
            </button>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
function togglePeriodValue() {
    const type = document.getElementById('periodType').value;
    const div = document.getElementById('periodValueDiv');
    const input = div.querySelector('input');

    if (type === 'Weekly') {
        div.style.display = 'none';
    } else if (type === 'Monthly') {
        div.style.display = 'block';
        input.type = 'month';
    } else if (type === 'Yearly') {
        div.style.display = 'block';
        input.type = 'number';
        input.placeholder = 'e.g. 2025';
        input.min = '2000';
        input.max = '2099';
    } else {
        div.style.display = 'none';
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
