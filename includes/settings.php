<?php
function get_report_settings() {
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    $settings = [
        'header_line_1' => 'Republic of the Philippines',
        'header_line_2' => 'Province of Benguet',
        'header_line_3' => 'Municipality of La Trinidad',
        'header_line_4' => 'Barangay Puguis',
        'fund_cluster' => 'GENERAL FUND',
        'accountable_person' => '',
        'accountable_position' => '',
        'assumption_date' => '',
        'report_place' => 'Barangay Puguis, La Trinidad, Benguet',
        'prepared_by_name' => '',
        'prepared_by_position' => '',
        'certified_by_name' => '',
        'certified_by_position' => '',
        'logo_path' => '',
        'logo_x' => 50,
        'logo_y' => 8,
        'logo_width' => 72,
        'logo_height' => 72
    ];

    global $conn;
    if (!isset($conn)) {
        require_once __DIR__ . '/db.php';
    }

    try {
        $result = $conn->query("SELECT setting_key, setting_value FROM report_settings");
    } catch (mysqli_sql_exception $exception) {
        error_log('StockTrack report settings table is unavailable: ' . $exception->getMessage());
        return $settings;
    }
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (array_key_exists($row['setting_key'], $settings)) {
                $settings[$row['setting_key']] = (string)$row['setting_value'];
            }
        }
    }

    return $settings;
}

function get_report_logo_path($settings) {
    $logo_path = trim((string)($settings['logo_path'] ?? ''));
    if (!$logo_path) {
        return '';
    }

    $filename = basename(parse_url($logo_path, PHP_URL_PATH) ?: '');
    $full_path = __DIR__ . '/../uploads/' . $filename;
    return $filename && is_file($full_path) ? $logo_path : '';
}
?>
