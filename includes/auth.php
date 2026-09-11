<?php
$is_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $is_https,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /stocktrack/login.php");
        exit();
    }
    if (!empty($_SESSION['must_change_password']) && basename($_SERVER['PHP_SELF']) !== 'change_password.php') {
        header("Location: /stocktrack/change_password.php");
        exit();
    }
}

function getCurrentUser() {
    return [
        'user_id'   => $_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['role']
    ];
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function hasAnyRole(array $roles) {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles, true);
}

function requirePermission($permission) {
    $permissions = [
        'view' => ['admin', 'secretary', 'treasurer', 'committee'],
        'add' => ['admin', 'secretary', 'treasurer', 'committee'],
        'edit' => ['admin'],
        'delete' => ['admin'],
        'manage_users' => ['admin'],
        'manage_categories' => ['admin'],
        'view_audit' => ['admin'],
        'view_notifications' => ['admin', 'treasurer']
    ];

    if (!isset($permissions[$permission]) || !hasAnyRole($permissions[$permission])) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token() {
    $submitted_token = $_POST['csrf_token'] ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';

    if (!$session_token || !$submitted_token || !hash_equals($session_token, $submitted_token)) {
        http_response_code(403);
        exit('Invalid CSRF token. Please go back and try again.');
    }
}

function login_identifier() {
    return substr(($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . ($_POST['username'] ?? ''), 0, 190);
}

function getLoginBlockSeconds($identifier) {
    global $conn;

    if (!isset($conn)) {
        return 0;
    }

    $stmt = $conn->prepare("SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), blocked_until)) AS seconds_remaining FROM login_attempts WHERE identifier = ?");
    $stmt->bind_param("s", $identifier);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int)$row['seconds_remaining'] : 0;
}

function isLoginRateLimited($identifier) {
    return getLoginBlockSeconds($identifier) > 0;
}

function recordLoginFailure($identifier) {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO login_attempts (identifier, attempts, window_started, blocked_until) VALUES (?, 1, NOW(), NULL) ON DUPLICATE KEY UPDATE attempts = IF(window_started < DATE_SUB(NOW(), INTERVAL 1 MINUTE), 1, attempts + 1), window_started = IF(window_started < DATE_SUB(NOW(), INTERVAL 1 MINUTE), NOW(), window_started), blocked_until = IF(attempts >= 5, DATE_ADD(NOW(), INTERVAL 1 MINUTE), blocked_until)");
    $stmt->bind_param("s", $identifier);
    $stmt->execute();
}

function clearLoginFailures($identifier) {
    global $conn;

    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE identifier = ?");
    $stmt->bind_param("s", $identifier);
    $stmt->execute();
}

function recordAudit($action, $entity_type = null, $entity_id = null, $details = null) {
    global $conn;

    if (!isset($conn)) {
        return;
    }

    $user_id = $_SESSION['user_id'] ?? null;
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt->bind_param("ississ", $user_id, $action, $entity_type, $entity_id, $details, $ip_address);
    $stmt->execute();
}
?>
