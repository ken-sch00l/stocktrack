<?php
require_once '../../includes/auth.php';
requireLogin();
require_once '../../includes/db.php';
require_once '../../includes/settings.php';

if (!hasAnyRole(['admin', 'super_admin'])) {
    http_response_code(403);
    exit('You do not have permission to access report settings.');
}

$settings = get_report_settings();
$error = '';
$success = '';
$setting_keys = [
    'header_line_1', 'header_line_2', 'header_line_3', 'header_line_4',
    'fund_cluster', 'accountable_person', 'accountable_position',
    'assumption_date', 'report_place', 'prepared_by_name',
    'prepared_by_position', 'certified_by_name', 'certified_by_position'
];
$logo_layout_keys = ['logo_x', 'logo_y', 'logo_width', 'logo_height'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $submitted = [];
    foreach ($setting_keys as $key) {
        $submitted[$key] = trim((string)($_POST[$key] ?? ''));
    }
    foreach ($logo_layout_keys as $key) {
        $submitted[$key] = (string)(float)($_POST[$key] ?? $settings[$key]);
    }

    if (!$submitted['header_line_1'] || !$submitted['header_line_2'] || !$submitted['header_line_3'] || !$submitted['header_line_4'] || !$submitted['fund_cluster'] || !$submitted['report_place']) {
        $error = 'Header lines, fund cluster, and report place are required.';
    } elseif ($submitted['assumption_date'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $submitted['assumption_date'])) {
        $error = 'Assumption date must be a valid date.';
    } elseif ((float)$submitted['logo_x'] < 0 || (float)$submitted['logo_x'] > 100 || (float)$submitted['logo_y'] < 0 || (float)$submitted['logo_y'] > 100 || (float)$submitted['logo_width'] < 24 || (float)$submitted['logo_width'] > 220 || (float)$submitted['logo_height'] < 24 || (float)$submitted['logo_height'] > 220) {
        $error = 'Logo position and size must remain within the allowed range.';
    }

    $logo_path = $settings['logo_path'];
    if (!$error && !empty($_FILES['logo']['tmp_name'])) {
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK || $_FILES['logo']['size'] > 2 * 1024 * 1024) {
            $error = 'The logo must be a PNG or JPG no larger than 2 MB.';
        } else {
            $image_info = @getimagesize($_FILES['logo']['tmp_name']);
            $allowed_types = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg'];
            if (!$image_info || !isset($allowed_types[$image_info[2]])) {
                $error = 'The logo must be a valid PNG or JPG image.';
            } else {
                $filename = 'report-logo-' . bin2hex(random_bytes(12)) . '.' . $allowed_types[$image_info[2]];
                $target = __DIR__ . '/../../uploads/' . $filename;
                if (!move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
                    $error = 'The logo could not be saved.';
                } else {
                    $logo_path = '/stocktrack/uploads/' . $filename;
                }
            }
        }
    }

    if (!$error) {
        $stmt = $conn->prepare("INSERT INTO report_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($submitted as $key => $value) {
            $stmt->bind_param('ss', $key, $value);
            $stmt->execute();
        }
        $logo_key = 'logo_path';
        $stmt->bind_param('ss', $logo_key, $logo_path);
        $stmt->execute();
        $settings = array_merge($settings, $submitted, ['logo_path' => $logo_path]);
        recordAudit('report_settings_updated', 'report_settings', null, 'Report header settings updated');
        $success = 'Report settings saved.';
    }
}
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-card-heading me-2 text-primary"></i>Report Settings</h4>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Reports</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <?php csrf_field(); ?>
    <input type="hidden" name="logo_x" id="logoX" value="<?php echo htmlspecialchars($settings['logo_x']); ?>">
    <input type="hidden" name="logo_y" id="logoY" value="<?php echo htmlspecialchars($settings['logo_y']); ?>">
    <input type="hidden" name="logo_width" id="logoWidth" value="<?php echo htmlspecialchars($settings['logo_width']); ?>">
    <input type="hidden" name="logo_height" id="logoHeight" value="<?php echo htmlspecialchars($settings['logo_height']); ?>">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Header and signature fields</div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ([
                            'header_line_1' => 'Header line 1', 'header_line_2' => 'Header line 2',
                            'header_line_3' => 'Header line 3', 'header_line_4' => 'Header line 4',
                            'fund_cluster' => 'Fund cluster', 'accountable_person' => 'Accountable person',
                            'accountable_position' => 'Accountable position', 'report_place' => 'Report place',
                            'prepared_by_name' => 'Prepared by name', 'prepared_by_position' => 'Prepared by position',
                            'certified_by_name' => 'Certified by name', 'certified_by_position' => 'Certified by position'
                        ] as $key => $label): ?>
                        <div class="col-md-6"><label class="form-label" for="<?php echo $key; ?>"><?php echo $label; ?></label><input id="<?php echo $key; ?>" type="text" name="<?php echo $key; ?>" class="form-control" value="<?php echo htmlspecialchars($settings[$key]); ?>" <?php echo in_array($key, ['header_line_1', 'header_line_2', 'header_line_3', 'header_line_4', 'fund_cluster', 'report_place'], true) ? 'required' : ''; ?>></div>
                        <?php endforeach; ?>
                        <div class="col-md-6"><label class="form-label" for="assumption_date">Assumption date</label><input id="assumption_date" type="date" name="assumption_date" class="form-control" value="<?php echo htmlspecialchars($settings['assumption_date']); ?>"></div>
                        <div class="col-md-6"><label class="form-label" for="logo">Logo</label><input id="logo" type="file" name="logo" class="form-control" accept="image/png,image/jpeg"><div class="form-text">PNG or JPG only, maximum 2 MB.</div></div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-4"><i class="bi bi-save me-1"></i>Save Report Settings</button>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Live preview</div>
                <div class="card-body">
                    <div class="report-settings-preview text-center">
                        <div id="logoCanvas" class="report-logo-canvas">
                            <?php if (get_report_logo_path($settings)): ?><div id="logoObject" class="report-logo-editor-object" style="left:<?php echo htmlspecialchars($settings['logo_x']); ?>%;top:<?php echo htmlspecialchars($settings['logo_y']); ?>%;width:<?php echo htmlspecialchars($settings['logo_width']); ?>px;height:<?php echo htmlspecialchars($settings['logo_height']); ?>px;"><img id="logoPreview" src="<?php echo htmlspecialchars(get_report_logo_path($settings)); ?>" alt="Report logo"><span class="report-logo-resize-handle" aria-hidden="true"></span></div><?php else: ?><div id="logoPreview" class="text-muted small">Upload a logo to position it</div><?php endif; ?>
                        </div>
                        <div data-preview="header_line_1"><?php echo htmlspecialchars($settings['header_line_1']); ?></div>
                        <div data-preview="header_line_2"><?php echo htmlspecialchars($settings['header_line_2']); ?></div>
                        <div data-preview="header_line_3"><?php echo htmlspecialchars($settings['header_line_3']); ?></div>
                        <div data-preview="header_line_4"><?php echo htmlspecialchars($settings['header_line_4']); ?></div>
                        <div class="mt-2">REPORT ON INVENTORY OF PROPERTY AND EQUIPMENT</div>
                        <div>Fund Cluster: <span data-preview="fund_cluster"><?php echo htmlspecialchars($settings['fund_cluster']); ?></span></div>
                        <div class="mt-2 small">For which <span data-preview="accountable_person"><?php echo htmlspecialchars($settings['accountable_person']); ?></span>, <span data-preview="accountable_position"><?php echo htmlspecialchars($settings['accountable_position']); ?></span>, <span data-preview="report_place"><?php echo htmlspecialchars($settings['report_place']); ?></span> is accountable.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.querySelectorAll('[data-preview]').forEach((preview) => {
    const input = document.getElementById(preview.dataset.preview);
    if (input) input.addEventListener('input', () => { preview.textContent = input.value; });
});
const logoInput = document.getElementById('logo');
if (logoInput) logoInput.addEventListener('change', () => {
    const file = logoInput.files[0];
    const preview = document.getElementById('logoPreview');
    if (!file || !preview) return;
    const reader = new FileReader();
    reader.onload = (event) => {
        if (preview.tagName === 'IMG') preview.src = event.target.result;
        else { const image = document.createElement('img'); image.id = 'logoPreview'; image.className = 'report-logo-static mb-2'; image.alt = 'Report logo'; image.src = event.target.result; preview.replaceWith(image); }
    };
    reader.readAsDataURL(file);
});
</script>

<?php require_once '../../includes/footer.php'; ?>
