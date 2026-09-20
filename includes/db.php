<?php
// Database Configuration
$stocktrack_env = getenv('STOCKTRACK_ENV') ?: 'local';
$db_host = getenv('STOCKTRACK_DB_HOST') ?: 'localhost';
$db_user = getenv('STOCKTRACK_DB_USER') ?: 'root';
$db_pass = getenv('STOCKTRACK_DB_PASS');
$db_name = getenv('STOCKTRACK_DB_NAME') ?: 'stocktrack';

if ($stocktrack_env === 'production' && (!$db_user || $db_pass === false || $db_pass === '')) {
    error_log('StockTrack production database credentials are not configured.');
    http_response_code(500);
    exit('Database configuration is incomplete.');
}

define('DB_HOST', $db_host);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass === false ? '' : $db_pass);
define('DB_NAME', $db_name);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    error_log('StockTrack database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    exit('Database connection failed.');
}

$conn->set_charset("utf8mb4");
?>
