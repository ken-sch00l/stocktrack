<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('add');
require_once '../../includes/db.php';
require_once '../../includes/settings.php';

$report_settings = get_report_settings();

$type = isset($_GET['type']) ? $_GET['type'] : 'ICS';
$allowed_types = ['RIS', 'ICS', 'RIPE', 'PAR'];
if (!in_array($type, $allowed_types)) $type = 'ICS';

$period_type = isset($_POST['period_type']) ? $_POST['period_type'] : '';
$period_value = isset($_POST['period_value']) ? $_POST['period_value'] : '';
$items = [];
$ripe_par_items = [];
$ripe_ics_items = [];
$error = '';
$report_generated = false;
$default_report_title = $type === 'RIS' ? 'REQUISITION AND ISSUE SLIP (RIS)' : ($type === 'ICS' ? 'INVENTORY CUSTODIAN SLIP (ICS)' : ($type === 'PAR' ? 'PROPERTY ACKNOWLEDGMENT RECEIPT (PAR)' : 'REPORT ON INVENTORY OF PROPERTY AND EQUIPMENT'));
$report_config = [
    'office_name' => trim((string)($_POST['office_name'] ?? $report_settings['header_line_4'])),
    'location' => trim((string)($_POST['location'] ?? $report_settings['report_place'])),
    'report_date' => trim((string)($_POST['report_date'] ?? date('F d, Y'))),
    'fund_cluster' => trim((string)($_POST['fund_cluster'] ?? $report_settings['fund_cluster'])),
    'accountable_person' => trim((string)($_POST['accountable_person'] ?? $report_settings['accountable_person'])),
    'accountable_position' => trim((string)($_POST['accountable_position'] ?? $report_settings['accountable_position'])),
    'accountable_barangay' => trim((string)($_POST['accountable_barangay'] ?? $report_settings['report_place'])),
    'assumption_date' => trim((string)($_POST['assumption_date'] ?? $report_settings['assumption_date'])),
    'prepared_by' => trim((string)($_POST['prepared_by'] ?? trim($report_settings['prepared_by_name'] . ($report_settings['prepared_by_position'] ? ', ' . $report_settings['prepared_by_position'] : '')))),
    'certified_by' => trim((string)($_POST['certified_by'] ?? trim($report_settings['certified_by_name'] . ($report_settings['certified_by_position'] ? ', ' . $report_settings['certified_by_position'] : '')))),
    'header_line_1' => trim((string)($_POST['header_line_1'] ?? $report_settings['header_line_1'])),
    'header_line_2' => trim((string)($_POST['header_line_2'] ?? $report_settings['header_line_2'])),
    'header_line_3' => trim((string)($_POST['header_line_3'] ?? $report_settings['header_line_3'])),
    'header_line_4' => trim((string)($_POST['header_line_4'] ?? $report_settings['header_line_4'])),
    'logo_position' => ($_POST['logo_position'] ?? 'left') === 'right' ? 'right' : 'left',
    'header_title' => trim((string)($_POST['header_title'] ?? $default_report_title)),
    'header_subtitle' => trim((string)($_POST['header_subtitle'] ?? '')),
    'logo_x' => max(0, min(94, (float)($_POST['logo_x'] ?? 82))),
    'logo_y' => max(0, min(80, (float)($_POST['logo_y'] ?? 8))),
    'logo_width' => max(24, min(140, (float)($_POST['logo_width'] ?? 72))),
    'logo_height' => max(24, min(140, (float)($_POST['logo_height'] ?? 72))),
    'office_x' => max(0, min(90, (float)($_POST['office_x'] ?? 25))),
    'office_y' => max(0, min(210, (float)($_POST['office_y'] ?? 8))),
    'location_x' => max(0, min(90, (float)($_POST['location_x'] ?? 25))),
    'location_y' => max(0, min(210, (float)($_POST['location_y'] ?? 38))),
    'fund_x' => max(0, min(90, (float)($_POST['fund_x'] ?? 25))),
    'fund_y' => max(0, min(210, (float)($_POST['fund_y'] ?? 68))),
    'accountable_x' => max(0, min(90, (float)($_POST['accountable_x'] ?? 25))),
    'accountable_y' => max(0, min(210, (float)($_POST['accountable_y'] ?? 98))),
    'title_x' => max(0, min(90, (float)($_POST['title_x'] ?? 25))),
    'title_y' => max(0, min(210, (float)($_POST['title_y'] ?? 138))),
    'date_x' => max(0, min(90, (float)($_POST['date_x'] ?? 25))),
    'date_y' => max(0, min(210, (float)($_POST['date_y'] ?? 182))),
];
$logo_path = trim((string)($_POST['logo_path'] ?? get_report_logo_path($report_settings)));
$selected_item_ids = array_values(array_filter(array_map('intval', (array)($_POST['item_ids'] ?? []))));
$selection_submitted = array_key_exists('item_ids', $_POST);
$export_format = $_POST['export_format'] ?? '';

function report_export_logo_data($logo_path) {
    $filename = basename(parse_url($logo_path, PHP_URL_PATH) ?: '');
    $full_path = __DIR__ . '/../../uploads/' . $filename;
    if (!$filename || !is_file($full_path)) {
        return '';
    }

    $mime_type = mime_content_type($full_path);
    return 'data:' . $mime_type . ';base64,' . base64_encode((string)file_get_contents($full_path));
}

function export_report_document($format, $type, $items, $config, $period_type, $period_value, $logo_path) {
    $escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    $logo_data = report_export_logo_data($logo_path);
    $title = $escape($config['header_title']);
    $header = '<table style="width:100%;border-collapse:collapse"><tr>';
    if ($logo_data) {
        $header .= '<td style="width:90px;vertical-align:top"><img src="' . $logo_data . '" style="width:' . (int)$config['logo_width'] . 'px;height:' . (int)$config['logo_height'] . 'px;object-fit:contain"></td>';
    }
    $cell = static fn($value) => '<td>' . $escape($value) . '</td>';
    if ($type === 'RIPE') {
        $assumption_date = $config['assumption_date'] ? date('F d, Y', strtotime($config['assumption_date'])) : '________________';
        $header .= '<td style="text-align:center">' . $escape($config['header_line_1']) . '<br>' . $escape($config['header_line_2']) . '<br>' . $escape($config['header_line_3']) . '<br><strong>' . $escape($config['header_line_4']) . '</strong><br><br><strong>REPORT ON INVENTORY OF PROPERTY AND EQUIPMENT</strong><br>As of ' . $escape($config['report_date']) . ' at ' . $escape($config['location']) . '<br>Fund Cluster: ' . $escape($config['fund_cluster']) . '<br>For which ' . $escape($config['accountable_person']) . ', ' . $escape($config['accountable_position']) . ', ' . $escape($config['accountable_barangay']) . ' is accountable, having assumed such accountability on ' . $escape($assumption_date) . '.</td></tr></table>';
    } else {
        $header .= '<td style="text-align:center">' . $escape($config['header_line_1']) . '<br>' . $escape($config['header_line_2']) . '<br>' . $escape($config['header_line_3']) . '<br><strong>' . $escape($config['header_line_4']) . '</strong><br>';
        if ($config['fund_cluster']) $header .= 'FUND CLUSTER: ' . $escape($config['fund_cluster']) . '<br>';
        if ($config['accountable_person']) $header .= 'Accountable for: ' . $escape($config['accountable_person']) . '<br>';
        $header .= '<hr><strong>' . $title . '</strong><br>As of ' . $escape($config['report_date']) . '</td></tr></table>';
    }

    if ($type === 'RIPE') {
        $table = '';
        foreach ([['title' => 'Part A - Property and Equipment covered by PAR', 'coverage' => 'PAR'], ['title' => 'Part B - Property and Equipment Covered by ICS', 'coverage' => 'ICS']] as $section) {
            $table .= '<h3>' . $escape($section['title']) . '</h3><table border="1" cellpadding="4" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:9pt"><thead><tr style="font-weight:bold;text-align:center"><th>Article</th><th>Description</th><th>Property/ICS Number</th><th>Date Acquired</th><th>Unit of Measure</th><th>Unit Value</th><th>Balance Per Card (Quantity)</th><th>On Hand Per Count (Quantity)</th><th>Shortage/Overage Quantity</th><th>Shortage/Overage Value</th><th>Remarks</th></tr></thead><tbody>';
            $section_items = array_filter($items, static fn($row) => ($row['coverage_type'] ?? 'ICS') === $section['coverage']);
            foreach ($section_items as $row) {
                $remarks = $row['condition_status'] . ($row['person_in_charge'] ? ' - c/o ' . $row['person_in_charge'] : '') . ($row['notes'] ? ' - ' . $row['notes'] : '');
                $date = !empty($row['date_acquired']) ? date('m/d/Y', strtotime($row['date_acquired'])) : '';
                $unit = (int)$row['quantity'] . ' ' . ($row['unit_measure'] ?: ($row['unit'] ?? ''));
                $table .= '<tr>' . $cell($row['category_name'] ?? '') . $cell($row['item_name']) . $cell($row['property_ics_number'] ?? '') . $cell($date) . $cell($unit) . $cell(number_format((float)($row['unit_value'] ?? 0), 2)) . $cell($row['balance_per_card'] ?? $row['quantity']) . $cell($row['on_hand_per_count'] ?? $row['quantity']) . $cell($row['shortage_overage_qty'] ?? 0) . $cell(number_format((float)($row['shortage_overage_value'] ?? 0), 2)) . $cell($remarks) . '</tr>';
            }
            $table .= '</tbody></table><br>';
        }
        $table .= '<table style="width:100%"><tr><td>Prepared by:<br><br><strong>' . $escape($config['prepared_by'] ?: '________________ (Name / Position)') . '</strong></td><td>Certified by:<br><br><strong>' . $escape($config['certified_by'] ?: '________________') . '</strong></td></tr></table>';
    } else {
    $table = '<table border="1" cellpadding="4" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:10pt"><thead><tr style="font-weight:bold;text-align:center">';
    if ($type === 'RIS') {
        foreach (['Stock No. / Tracking No.', 'Unit', 'Description', 'Quantity Requested', 'Quantity Issued', 'Remarks'] as $heading) $table .= '<th>' . $heading . '</th>';
    } elseif ($type === 'ICS') {
        foreach (['Tracking No.', 'Description', 'Serial No.', 'Qty', 'Unit', 'Date Purchased', 'Condition', 'Person in Charge'] as $heading) $table .= '<th>' . $heading . '</th>';
    } elseif ($type === 'PAR') {
        foreach (['Property No.', 'Description', 'Qty', 'Unit Cost', 'Total Cost', 'Serial No.', 'Accountable Person', 'Condition'] as $heading) $table .= '<th>' . $heading . '</th>';
    } else {
        foreach (['Article', 'Description', 'Property / Inventory No.', 'Date Acquired', 'Unit of Measure', 'Unit Value', 'Balance per Card', 'On Hand per Count', 'Shortage / Overage Qty', 'Shortage / Overage Value', 'Remarks'] as $heading) $table .= '<th>' . $heading . '</th>';
    }
    $table .= '</tr></thead><tbody>';
    foreach ($items as $row) {
        $property_number = $row['property_ics_number'] ?: $row['tracking_number'];
        $unit_value = (float)($row['unit_value'] ?? 0);
        $quantity = (int)($row['quantity'] ?? 0);
        if ($type === 'RIS') {
            $table .= '<tr>' . $cell($row['tracking_number']) . $cell($row['unit'] ?? '') . $cell($row['item_name']) . $cell($row['report_quantity'] ?? $quantity) . $cell($row['report_quantity'] ?? $quantity) . $cell($row['report_action'] ?? $row['condition_status']) . '</tr>';
        } elseif ($type === 'ICS') {
            $date = !empty($row['date_purchased']) ? date('m/d/Y', strtotime($row['date_purchased'])) : '';
            $table .= '<tr>' . $cell($row['tracking_number']) . $cell($row['item_name']) . $cell($row['serial_number'] ?? '') . $cell($quantity) . $cell($row['unit'] ?? '') . $cell($date) . $cell($row['condition_status']) . $cell(($row['person_in_charge'] ?? '') . ' ' . ($row['position'] ?? '')) . '</tr>';
        } elseif ($type === 'PAR') {
            $table .= '<tr>' . $cell($property_number) . $cell($row['item_name']) . $cell($quantity) . $cell(number_format($unit_value, 2)) . $cell(number_format($unit_value * $quantity, 2)) . $cell($row['serial_number'] ?? '') . $cell(($row['person_in_charge'] ?? '') . ' ' . ($row['position'] ?? '')) . $cell($row['condition_status']) . '</tr>';
        } else {
            $date = !empty($row['date_acquired']) ? date('m/d/Y', strtotime($row['date_acquired'])) : '';
            $table .= '<tr>' . $cell($row['category_name'] ?? '') . $cell($row['item_name']) . $cell($property_number) . $cell($date) . $cell($row['unit_measure'] ?: ($row['unit'] ?? '')) . $cell(number_format($unit_value, 2)) . $cell($row['balance_per_card'] ?? $quantity) . $cell($row['on_hand_per_count'] ?? $quantity) . $cell($row['shortage_overage_qty'] ?? 0) . $cell(number_format((float)($row['shortage_overage_value'] ?? 0), 2)) . $cell($row['remarks'] ?: ($row['notes'] ?: $row['condition_status'])) . '</tr>';
        }
    }
    $table .= '</tbody></table><br><br><table style="width:100%"><tr><td>Prepared by:<br><br><strong>' . $escape($config['prepared_by']) . '</strong></td><td>Certified / received by:<br><br><strong>' . $escape($config['certified_by']) . '</strong></td></tr></table>';
    }
    $document = '<html><head><meta charset="UTF-8"><title>' . $title . '</title></head><body>' . $header . $table . '</body></html>';
    $extension = $format === 'word' ? 'doc' : 'xls';
    $content_type = $format === 'word' ? 'application/msword' : 'application/vnd.ms-excel';
    header('Content-Type: ' . $content_type . '; charset=UTF-8');
    header('Content-Disposition: attachment; filename="stocktrack-' . strtolower($type) . '-' . date('Ymd-His') . '.' . $extension . '"');
    echo $document;
    exit;
}

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

        if ($selection_submitted) {
            $selected_lookup = array_flip($selected_item_ids);
            $items = array_values(array_filter($items, static function ($row) use ($selected_lookup) {
                return isset($selected_lookup[(int)$row['item_id']]);
            }));
        }

        if ($type === 'RIPE') {
            foreach ($items as $row) {
                if (($row['coverage_type'] ?? 'ICS') === 'PAR') {
                    $ripe_par_items[] = $row;
                } else {
                    $ripe_ics_items[] = $row;
                }
            }
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
            if (in_array($export_format, ['word', 'excel'], true)) {
                export_report_document($export_format, $type, $items, $report_config, $period_type, $period_value, $logo_path);
            }

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
            <?php if ($type === 'RIPE'): ?>
            <div class="row g-3 mt-1">
                <div class="col-md-4"><label class="form-label fw-semibold">As-of place</label><input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($report_config['location']); ?>" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Fund cluster</label><input type="text" name="fund_cluster" class="form-control" value="<?php echo htmlspecialchars($report_config['fund_cluster']); ?>" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">As-of date</label><input type="text" name="report_date" class="form-control" value="<?php echo htmlspecialchars($report_config['report_date']); ?>" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Accountable officer</label><input type="text" name="accountable_person" class="form-control" value="<?php echo htmlspecialchars($report_config['accountable_person']); ?>" placeholder="Full name" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Position</label><input type="text" name="accountable_position" class="form-control" value="<?php echo htmlspecialchars($report_config['accountable_position']); ?>" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Barangay / office</label><input type="text" name="accountable_barangay" class="form-control" value="<?php echo htmlspecialchars($report_config['accountable_barangay']); ?>" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Assumption date</label><input type="date" name="assumption_date" class="form-control" value="<?php echo htmlspecialchars($report_config['assumption_date']); ?>"></div>
            </div>
            <?php endif; ?>
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
    <?php foreach (['location', 'report_date', 'fund_cluster', 'accountable_person', 'accountable_position', 'accountable_barangay', 'assumption_date', 'office_name', 'prepared_by', 'certified_by', 'header_line_1', 'header_line_2', 'header_line_3', 'header_line_4'] as $report_field): ?>
        <input type="hidden" name="<?php echo $report_field; ?>" value="<?php echo htmlspecialchars($report_config[$report_field]); ?>">
    <?php endforeach; ?>
    <div class="alert alert-info no-print report-preview-notice" role="status"><i class="bi bi-pencil-square me-1"></i>Header text is editable for this print copy only. Changes are not saved to the database.</div>
    <div class="card border-0 shadow-sm mb-4 no-print">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="submit" name="export_format" value="word" class="btn btn-outline-primary"><i class="bi bi-file-earmark-word me-1"></i>Download Word template</button>
                <button type="submit" name="export_format" value="excel" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Download Excel template</button>
            </div>
            <p class="form-text mb-0 mt-2">Edit the downloaded file in Microsoft Word or Excel, then print it from that application.</p>
        </div>
    </div>

<div class="card border-0 shadow-sm" id="reportOutput">
    <div class="card-body">
        <!-- Report Header -->
        <div class="report-paper-header mb-4 text-center">
            <?php if ($logo_path): ?><div class="report-logo-caption-area" contenteditable="true"><img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Report logo" class="report-logo-static"></div><?php endif; ?>
            <div contenteditable="true"><?php echo htmlspecialchars($report_config['header_line_1']); ?></div>
            <div contenteditable="true"><?php echo htmlspecialchars($report_config['header_line_2']); ?></div>
            <div contenteditable="true"><?php echo htmlspecialchars($report_config['header_line_3']); ?></div>
            <div contenteditable="true" class="fw-bold"><?php echo htmlspecialchars($report_config['header_line_4']); ?></div>
            <h5 class="fw-bold mt-3" contenteditable="true"><?php echo $type === 'RIPE' ? 'REPORT ON INVENTORY OF PROPERTY AND EQUIPMENT' : htmlspecialchars($report_config['header_title']); ?></h5>
            <p class="mb-1" contenteditable="true">As of <?php echo htmlspecialchars($report_config['report_date']); ?> at <?php echo htmlspecialchars($report_config['location']); ?></p>
            <p class="mb-1" contenteditable="true">Fund Cluster: <?php echo htmlspecialchars($report_config['fund_cluster']); ?></p>
            <p class="mb-1" contenteditable="true">For which <?php echo htmlspecialchars($report_config['accountable_person']); ?>, <?php echo htmlspecialchars($report_config['accountable_position']); ?>, <?php echo htmlspecialchars($report_config['accountable_barangay']); ?> is accountable, having assumed such accountability on <?php echo $report_config['assumption_date'] ? htmlspecialchars(date('F d, Y', strtotime($report_config['assumption_date']))) : '________________'; ?>.</p>
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
                        <td><input class="no-print report-item-check" type="checkbox" name="item_ids[]" value="<?php echo (int)$row['item_id']; ?>" <?php echo (!$selection_submitted || in_array((int)$row['item_id'], $selected_item_ids, true)) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($row['tracking_number']); ?></td>
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
                    <span contenteditable="true"><?php echo htmlspecialchars($report_config['prepared_by'] ?: 'Barangay Secretary'); ?></span>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;">
                    <strong>Approved by:</strong><br>
                    <span contenteditable="true"><?php echo htmlspecialchars($report_config['certified_by'] ?: 'Barangay Captain'); ?></span>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;">
                    <strong>Issued by:</strong><br>
                    <?php echo htmlspecialchars($report_config['accountable_person'] ?: 'Barangay Treasurer'); ?>
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
                        <td><input class="no-print report-item-check" type="checkbox" name="item_ids[]" value="<?php echo (int)$row['item_id']; ?>" <?php echo (!$selection_submitted || in_array((int)$row['item_id'], $selected_item_ids, true)) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($row['tracking_number']); ?></td>
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
                    <span contenteditable="true"><?php echo htmlspecialchars($report_config['prepared_by'] ?: 'Barangay Treasurer / Property Custodian'); ?></span>
                </div>
            </div>
            <div class="col-md-6 text-center">
                <div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;">
                    <strong>Received by:</strong><br>
                    <span contenteditable="true"><?php echo htmlspecialchars($report_config['certified_by'] ?: 'Position / Designation'); ?></span>
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
                        <td><input class="no-print report-item-check" type="checkbox" name="item_ids[]" value="<?php echo (int)$row['item_id']; ?>" <?php echo (!$selection_submitted || in_array((int)$row['item_id'], $selected_item_ids, true)) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($row['property_ics_number'] ?: $row['tracking_number']); ?></td>
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
            <div class="col-md-6 text-center"><div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;"><strong>Issued by:</strong><br><span contenteditable="true"><?php echo htmlspecialchars($report_config['prepared_by'] ?: 'Barangay Treasurer / Property Custodian'); ?></span></div></div>
            <div class="col-md-6 text-center"><div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;"><strong>Received by:</strong><br><span contenteditable="true"><?php echo htmlspecialchars($report_config['accountable_person'] ?: 'Accountable Person'); ?></span></div></div>
        </div>
        <?php else: ?>
        <!-- RIPE FORMAT -->
        <?php foreach ([['title' => 'Part A - Property and Equipment covered by PAR', 'rows' => $ripe_par_items], ['title' => 'Part B - Property and Equipment Covered by ICS', 'rows' => $ripe_ics_items]] as $ripe_section): ?>
            <h6 class="fw-bold mt-3 mb-2"><?php echo htmlspecialchars($ripe_section['title']); ?></h6>
            <table class="table table-bordered ripe-table">
                <thead>
                    <tr class="text-center">
                        <th rowspan="2">Article</th>
                        <th rowspan="2">Description</th>
                        <th rowspan="2">Property/ICS Number</th>
                        <th rowspan="2">Date Acquired</th>
                        <th rowspan="2">Unit of Measure</th>
                        <th rowspan="2">Unit Value</th>
                        <th rowspan="2">Balance Per Card (Quantity)</th>
                        <th rowspan="2">On Hand Per Count (Quantity)</th>
                        <th colspan="2">Shortage/Overage</th>
                        <th rowspan="2">Remarks</th>
                    </tr>
                    <tr class="text-center"><th>Quantity</th><th>Value</th></tr>
                </thead>
                <tbody>
                    <?php if (count($ripe_section['rows']) > 0): ?>
                        <?php foreach ($ripe_section['rows'] as $row): ?>
                        <?php $remarks = $row['condition_status'] . ($row['person_in_charge'] ? ' - c/o ' . $row['person_in_charge'] : '') . ($row['notes'] ? ' - ' . $row['notes'] : ''); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['category_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['property_ics_number'] ?? ''); ?></td>
                            <td><?php echo $row['date_acquired'] ? date('m/d/Y', strtotime($row['date_acquired'])) : ''; ?></td>
                            <td class="text-center"><?php echo (int)$row['quantity'] . ' ' . htmlspecialchars($row['unit_measure'] ?: ($row['unit'] ?? '')); ?></td>
                            <td class="text-end"><?php echo number_format((float)($row['unit_value'] ?? 0), 2); ?></td>
                            <td class="text-center"><?php echo (int)($row['balance_per_card'] ?? $row['quantity']); ?></td>
                            <td class="text-center"><?php echo (int)($row['on_hand_per_count'] ?? $row['quantity']); ?></td>
                            <td class="text-center"><?php echo (int)($row['shortage_overage_qty'] ?? 0); ?></td>
                            <td class="text-end"><?php echo number_format((float)($row['shortage_overage_value'] ?? 0), 2); ?></td>
                            <td><?php echo htmlspecialchars($remarks); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="11" class="text-center text-muted py-3">No items found for this coverage type.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
        <div class="row mt-4">
            <div class="col-md-6 text-center">
                <div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;">
                    <strong>Prepared by:</strong><br>
                    <span contenteditable="true"><?php echo htmlspecialchars($report_config['prepared_by'] ?: '________________ (Name / Position)'); ?></span>
                </div>
            </div>
            <div class="col-md-6 text-center">
                <div style="border-top:1px solid #000; margin-top:40px; padding-top:5px;">
                    <strong>Certified correct by:</strong><br>
                    <span contenteditable="true"><?php echo htmlspecialchars($report_config['certified_by'] ?: '________________'); ?></span>
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
