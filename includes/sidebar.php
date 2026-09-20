<?php $current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH); ?>
<aside class="sidebar app-sidebar bg-dark text-white p-3">
    <ul class="nav flex-column gap-1">
        <li class="nav-item">
            <a href="/stocktrack/dashboard.php" class="nav-link text-white <?php echo $current_path === '/stocktrack/dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2 me-2"></i>Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="/stocktrack/modules/inventory/index.php" class="nav-link text-white <?php echo strpos($current_path, '/modules/inventory/') === 0 && basename($current_path) !== 'history.php' ? 'active' : ''; ?>">
                <i class="bi bi-archive me-2"></i>Inventory
            </a>
        </li>
        <li class="nav-item">
            <a href="/stocktrack/modules/inventory/history.php" class="nav-link text-white <?php echo strpos($current_path, '/modules/inventory/history.php') === 0 ? 'active' : ''; ?>">
                <i class="bi bi-clock-history me-2"></i>History
            </a>
        </li>
        <?php if (hasAnyRole(['admin', 'super_admin', 'treasurer'])): ?>
        <li class="nav-item">
            <a href="/stocktrack/modules/inventory/count.php" class="nav-link text-white <?php echo strpos($current_path, '/modules/inventory/count.php') === 0 ? 'active' : ''; ?>">
                <i class="bi bi-clipboard2-check me-2"></i>Physical Count
            </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a href="/stocktrack/modules/logbook/index.php" class="nav-link text-white <?php echo strpos($current_path, '/modules/logbook/') === 0 ? 'active' : ''; ?>">
                <i class="bi bi-journal-text me-2"></i>Logbook
            </a>
        </li>
        <?php if (hasAnyRole(['admin', 'super_admin', 'treasurer'])): ?>
        <?php $unread_notifications = $conn->query("SELECT COUNT(*) AS unread_count FROM notifications WHERE recipient_user_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0")->fetch_assoc()['unread_count']; ?>
        <li class="nav-item">
            <a href="/stocktrack/modules/notifications/index.php" class="nav-link text-white <?php echo strpos($current_path, '/modules/notifications/') === 0 ? 'active' : ''; ?>">
                <i class="bi bi-bell me-2"></i>Notifications <?php if ($unread_notifications): ?><span class="badge bg-warning text-dark"><?php echo (int)$unread_notifications; ?></span><?php endif; ?>
            </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a href="/stocktrack/modules/reports/index.php" class="nav-link text-white <?php echo strpos($current_path, '/modules/reports/') === 0 ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-text me-2"></i>Reports
            </a>
        </li>
        <li class="nav-item mt-3">
            <span class="text-muted small px-2">ACCOUNT</span>
        </li>
        <li class="nav-item">
            <a href="/stocktrack/profile.php" class="nav-link text-white <?php echo $current_path === '/stocktrack/profile.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-circle me-2"></i>My Profile
            </a>
        </li>
        <?php if (hasAnyRole(['admin', 'super_admin'])): ?>
        <li class="nav-item mt-2">
            <span class="text-muted small px-2">ADMIN</span>
        </li>
        <li class="nav-item">
            <a href="/stocktrack/modules/categories/index.php" class="nav-link text-white <?php echo strpos($current_path, '/modules/categories/') === 0 ? 'active' : ''; ?>">
                <i class="bi bi-tags me-2"></i>Categories
            </a>
        </li>
        <li class="nav-item">
            <a href="/stocktrack/modules/users/index.php" class="nav-link text-white <?php echo strpos($current_path, '/modules/users/') === 0 ? 'active' : ''; ?>">
                <i class="bi bi-people me-2"></i>Users
            </a>
        </li>
        <?php endif; ?>
        <?php if (hasAnyRole(['admin', 'super_admin', 'treasurer'])): ?>
        <li class="nav-item <?php echo hasAnyRole(['admin', 'super_admin']) ? 'mt-2' : 'mt-3'; ?>">
            <span class="text-muted small px-2">OVERSIGHT</span>
        </li>
        <li class="nav-item">
            <a href="/stocktrack/modules/audit/index.php" class="nav-link text-white <?php echo strpos($current_path, '/modules/audit/') === 0 ? 'active' : ''; ?>">
                <i class="bi bi-shield-check me-2"></i>Activity Log
            </a>
        </li>
        <?php endif; ?>
    </ul>
</aside>
<div class="content p-4 w-100">
