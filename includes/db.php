<?php
// Database Configuration
define('DB_HOST', getenv('STOCKTRACK_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('STOCKTRACK_DB_USER') ?: 'root');
define('DB_PASS', getenv('STOCKTRACK_DB_PASS') ?: '');
define('DB_NAME', getenv('STOCKTRACK_DB_NAME') ?: 'stocktrack');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    error_log('StockTrack database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    exit('Database connection failed.');
}

$conn->set_charset("utf8");
?>
