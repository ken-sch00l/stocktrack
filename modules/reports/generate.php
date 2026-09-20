<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('add');
require_once '../../includes/db.php';

$type = isset($_GET['type']) ? $_GET['type'] : 'ICS';
$allowed_types = ['RIS', 'ICS', 'RIPE', 'PAR'];
if (!in_array($type, $allowed_types)) $type = 'ICS';

$period_type = isset($_POST['period_type']) ? $_POST['period_type'] : '';
$period_value = isset($_POST['period_value']) ? $_POST['period_value'] : '';
$items = [];
$error = '';
$report_generated = false;
$report_config = [
    'office_name' => trim((string)($_POST['office_name'] ?? 'BARANGAY PUGUIS')),
    'location' => trim((string)($_POST['location'] ?? 'La Trinidad, Benguet')),
    'report_date' => trim((string)($_POST['report_date'] ?? date('F d, Y'))),
    'fund_cluster' => trim((string)($_POST['fund_cluster'] ?? 'GENERAL FUND')),
    'accountable_person' => trim((string)($_POST['accountable_person'] ?? '')),
    'prepared_by' => trim((string)($_POST['prepared_by'] ?? '')),
    'certified_by' => trim((string)($_POST['certified_by'] ?? '')),
    'logo_position' => ($_POST['logo_position'] ?? 'left') === 'right' ? 'right' : 'left',
];
$logo_path = trim((string)($_POST['logo_path'] ?? ''));
$selected_item_ids = array_values(array_filter(array_map('intval', (array)($_POST['item_ids'] ?? []))));

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
        if ($type === 'RIS') {
            $stmt = $conn->prepare("SELECT i.*, c.category_name, l.quantity AS report_quantity, l.action AS report_action, l.date_action AS report_date FROM logbook l JOIN items i ON l.item_id = i.item_id LEFT JOIN categories c ON i.category_id = c.category_id WHERE l.date_action >= ? AND l.date_action < ? AND l.action IN ('Borrowed', 'Used') ORDER BY l.date_action ASC, i.item_name ASC");
        } else {
            $stmt = $conn->prepare("SELECT i.*, c.category_name FROM items i LEFT JOIN categories c ON i.category_id = c.category_id WHERE i.created_at >= ? AND i.created_at < ? ORDER BY i.item_name ASC");
        }
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

        if ($selected_item_ids) {
            $selected_lookup = array_flip($selected_item_ids);
            $items = array_values(array_filter($items, static function ($row) use ($selected_lookup) {
                return isset($selected_lookup[(int)$row['item_id']]);
            }));
        }

        if (!empty($_FILES['report_logo']['tmp_name']) && is_uploaded_file($_FILES['report_logo']['tmp_name'])) {
            $allowed_logo_types = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif'];
            $mime_type = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['report_logo']['tmp_name']);
            if (isset($allowed_logo_types[$mime_type]) && $_FILES['report_logo']['size'] <= 2 * 1024 * 1024) {
                $logo_filename = 'report-logo.' . $allowed_logo_types[$mime_type];
                $logo_target = __DIR__ . '/../../uploads/' . $logo_filename;
                if (move_uploaded_file($_FILES['report_logo']['tmp_name'], $logo_target)) {
                    $logo_path = '/stocktrack/uploads/' . $logo_filename;
                }
            } else {
                $error = 'The logo must be a PNG, JPG, or GIF image no larger than 2 MB.';
            }
        }

        if (!$error) {
            $log_stmt = $conn->prepare("INSERT INTO reports_log (report_type, period_type, period_value, generated_by) VALUES (?, ?, ?, ?)");
            $log_stmt->bind_param("sssi", $type, $period_type, $period_value, $_SESSION['user_id']);
            $log_stmt->execute();
            recordAudit('report_generated', 'report', $conn->insert_id, $type . ' ' . $period_type . ' ' . $period_value);
            $report_generated = true;
        }
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

<div class="card border-0 shadow-sm mb-4 no-print filter-toolbar">
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

<form method="POST" enctype="multipart/form-data">
    <?php csrf_field(); ?>
    <input type="hidden" name="period_type" value="<?php echo htmlspecialchars($period_type); ?>">
    <input type="hidden" name="period_value" value="<?php echo htmlspecialchars($period_value); ?>">
    <input type="hidden" name="logo_path" value="<?php echo htmlspecialchars($logo_path); ?>">
    <div class="card border-0 shadow-sm mb-4 no-print report-designer">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-sliders me-2 text-primary"></i>Customize printed report</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Office / Barangay name</label><input name="office_name" class="form-control" value="<?php echo htmlspecialchars($report_config['office_name']); ?>"></div>
                <div class="col-md-4"><label class="form-label">Location</label><input name="location" class="form-control" value="<?php echo htmlspecialchars($report_config['location']); ?>"></div>
                <div class="col-md-4"><label class="form-label">Report date</label><input name="report_date" class="form-control" value="<?php echo htmlspecialchars($report_config['report_date']); ?>"></div>
                <div class="col-md-4"><label class="form-label">Fund cluster</label><input name="fund_cluster" class="form-control" value="<?php echo htmlspecialchars($report_config['fund_cluster']); ?>"></div>
                <div class="col-md-4"><label class="form-label">Accountable person</label><input name="accountable_person" class="form-control" value="<?php echo htmlspecialchars($report_config['accountable_person']); ?>"></div>
                <div class="col-md-4"><label class="form-label">Logo (PNG, JPG, GIF; max 2 MB)</label><input type="file" name="report_logo" class="form-control" accept="image/png,image/jpeg,image/gif"></div>
                <div class="col-md-4"><label class="form-label">Logo position</label><select name="logo_position" class="form-select"><option value="left" <?php echo $report_config['logo_position'] === 'left' ? 'selected' : ''; ?>>Top left</option><option value="right" <?php echo $report_config['logo_position'] === 'right' ? 'selected' : ''; ?>>Top right</option></select></div>
                <div class="col-md-4"><label class="form-label">Prepared by</label><input name="prepared_by" class="form-control" value="<?php echo htmlspecialchars($report_config['prepared_by']); ?>"></div>
                <div class="col-md-4"><label class="form-label">Certified / received by</label><input name="certified_by" class="form-control" value="<?php echo htmlspecialchars($report_config['certified_by']); ?>"></div>
            </div>
            <p class="form-text mb-0 mt-3">Select the records below, then click Update preview. Blank signatory fields remain available for handwritten signatures.</p>
        </div>
    </div>

<div class="card border-0 shadow-sm" id="reportOutput">
    <div class="card-body">
        <!-- Report Header -->
        <div class="report-paper-header mb-4 <?php echo $report_config['logo_position'] === 'right' ? 'logo-right' : 'logo-left'; ?>">
            <?php if ($logo_path): ?><img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Report logo" class="report-logo"><?php endif; ?>
            <div class="text-center flex-grow-1">
            <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($report_config['office_name']); ?></h5>
            <p class="mb-0"><?php echo htmlspecialchars($report_config['location']); ?></p>
            <?php if ($report_config['fund_cluster']): ?><p class="mb-0">FUND CLUSTER: <?php echo htmlspecialchars($report_config['fund_cluster']); ?></p><?php endif; ?>
            <hr>
            <?php if ($type === 'RIS'): ?>
                <h5 class="fw-bold">REQUISITION AND ISSUE SLIP (RIS)</h5>
            <?php elseif ($type === 'ICS'): ?>
                <h5 class="fw-bold">INVENTORY CUSTODIAN SLIP (ICS)</h5>
            <?php elseif ($type === 'PAR'): ?>
                <h5 class="fw-bold">PROPERTY ACKNOWLEDGMENT RECEIPT (PAR)</h5>
            <?php else: ?>
                <h5 class="fw-bold">REPORT ON INVENTORY OF PROPERTY AND EQUIPMENT (RIPE)</h5>
            <?php endif; ?>
            <p class="mb-0">
                Period: <?php echo $period_type; ?> 
                <?php echo $period_type !== 'Weekly' ? '- ' . $period_value : '(Last 7 Days)'; ?>
            </p>
            <p class="mb-0">Date: <?php echo htmlspecialchars($report_config['report_date']); ?></p>
            </div>
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
                        <td><input class="no-print report-item-check" type="checkbox" name="item_ids[]" value="<?php echo (int)$row['item_id']; ?>" <?php echo (!$selected_item_ids || in_array((int)$row['item_id'], $selected_item_ids, true)) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($row['tracking_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['unit'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                        <td class="text-center"><?php echo (int)($row['report_quantity'] ?? $row['quantity']); ?></td>
                        <td class="text-center"><?php echo (int)($row['report_quantity'] ?? $row['quantity']); ?></td>
                        <td><?php echo htmlspecialchars($row['report_action'] ?? $row['condition_status']); ?></td>
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
                        <td><input class="no-print report-item-check" type="checkbox" name="item_ids[]" value="<?php echo (int)$row['item_id']; ?>" <?php echo (!$selected_item_ids || in_array((int)$row['item_id'], $selected_item_ids, true)) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($row['tracking_number']); ?></td>
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

        <?php elseif ($type === 'PAR'): ?>
        <!-- PAR FORMAT -->
        <table class="table table-bordered">
            <thead>
                <tr class="text-center">
                    <th>Property No.</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Cost</th>
                    <th>Total Cost</th>
                    <th>Serial No.</th>
                    <th>Accountable Person</th>
                    <th>Condition</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $row): ?>
                    <tr>
                        <td><input class="no-print report-item-check" type="checkbox" name="item_ids[]" value="<?php echo (int)$row['item_id']; ?>" <?php echo (!$selected_item_ids || in_array((int)$row['item_id'], $selected_item_ids, true)) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($row['property_ics_number'] ?: $row['tracking_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                        <td class="text-center"><?php echo (int)$row['quantity']; ?></td>
                        <td class="text-end"><?php echo number_format((float)($row['unit_value'] ?? 0), 2); ?></td>
                        <td class="text-end"><?php echo number_format((float)($row['unit_value'] ?? 0) * (int)$row['quantity'], 2); ?></td>
                        <td><?php echo htmlspecialchars($row['serial_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['person_in_charge'] ?? 'N/A'); ?><br><small><?php echo htmlspecialchars($row['position'] ?? ''); ?></small></td>
                        <td><?php echo htmlspecialchars($row['condition_status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">No items found for selected period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="row mt-4">
            <div class="col-md-6 text-center"><div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;"><strong>Issued by:</strong><br>Barangay Treasurer / Property Custodian</div></div>
            <div class="col-md-6 text-center"><div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;"><strong>Received by:</strong><br>Accountable Person</div></div>
        </div>
        <?php else: ?>
        <!-- RIPE FORMAT -->
        <table class="table table-bordered">
            <thead>
                <tr class="text-center">
                    <th rowspan="2">Article</th>
                    <th rowspan="2">Description</th>
                    <th rowspan="2">Property / Inventory No.</th>
                    <th rowspan="2">Date Acquired</th>
                    <th rowspan="2">Unit of Measure</th>
                    <th rowspan="2">Unit Value</th>
                    <th rowspan="2">Balance per Card</th>
                    <th rowspan="2">On Hand per Count</th>
                    <th colspan="2">Shortage / Overage</th>
                    <th rowspan="2">Remarks</th>
                </tr>
                <tr class="text-center">
                    <th>Quantity</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $row): ?>
                    <tr>
                        <td><input class="no-print report-item-check" type="checkbox" name="item_ids[]" value="<?php echo (int)$row['item_id']; ?>" <?php echo (!$selected_item_ids || in_array((int)$row['item_id'], $selected_item_ids, true)) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['property_ics_number'] ?: $row['tracking_number']); ?></td>
                        <td><?php echo $row['date_acquired'] ? date('m/d/Y', strtotime($row['date_acquired'])) : 'N/A'; ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($row['unit_measure'] ?: ($row['unit'] ?? '')); ?></td>
                        <td class="text-end"><?php echo number_format((float)($row['unit_value'] ?? 0), 2); ?></td>
                        <td class="text-center"><?php echo (int)($row['balance_per_card'] ?? $row['quantity']); ?></td>
                        <td class="text-center"><?php echo (int)($row['on_hand_per_count'] ?? $row['quantity']); ?></td>
                        <td class="text-center"><?php echo (int)($row['shortage_overage_qty'] ?? 0); ?></td>
                        <td class="text-end"><?php echo number_format((float)($row['shortage_overage_value'] ?? 0), 2); ?></td>
                        <td><?php echo htmlspecialchars($row['remarks'] ?: ($row['notes'] ?: $row['condition_status'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="11" class="text-center text-muted py-3">No items found for selected period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="row mt-4">
            <div class="col-md-6 text-center">
                <div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;">
                    <strong>Prepared by:</strong><br>
                    Barangay Record Keeper
                </div>
            </div>
            <div class="col-md-6 text-center">
                <div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;">
                    <strong>Certified correct by:</strong><br>
                    Barangay Treasurer / Property Custodian
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="text-center mt-4 no-print d-flex justify-content-center gap-2">
            <button type="submit" class="btn btn-outline-primary">
                <i class="bi bi-arrow-repeat me-2"></i>Update preview
            </button>
            <button type="button" onclick="window.print()" class="btn btn-primary px-5">
                <i class="bi bi-printer me-2"></i>Print Report
            </button>
        </div>
    </div>
</div>
</form>

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
