<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /stocktrack/login.php");
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
        'manage_categories' => ['admin']
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
?>
