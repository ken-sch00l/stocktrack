<div class="sidebar bg-dark text-white p-3" style="min-height:calc(100vh - 56px); width:230px; min-width:230px;">
    <ul class="nav flex-column gap-1">
        <li class="nav-item">
            <a href="/stocktrack/dashboard.php" class="nav-link text-white">
                <i class="bi bi-speedometer2 me-2"></i>Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="/stocktrack/modules/inventory/index.php" class="nav-link text-white">
                <i class="bi bi-archive me-2"></i>Inventory
            </a>
        </li>
        <li class="nav-item">
            <a href="/stocktrack/modules/inventory/history.php" class="nav-link text-white">
                <i class="bi bi-clock-history me-2"></i>History
            </a>
        </li>
        <li class="nav-item">
            <a href="/stocktrack/modules/logbook/index.php" class="nav-link text-white">
                <i class="bi bi-journal-text me-2"></i>Logbook
            </a>
        </li>
        <li class="nav-item">
            <a href="/stocktrack/modules/reports/index.php" class="nav-link text-white">
                <i class="bi bi-file-earmark-text me-2"></i>Reports
            </a>
        </li>
        <li class="nav-item mt-3">
            <span class="text-muted small px-2">ACCOUNT</span>
        </li>
        <li class="nav-item">
            <a href="/stocktrack/change_password.php" class="nav-link text-white">
                <i class="bi bi-key me-2"></i>Change Password
            </a>
        </li>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <li class="nav-item mt-2">
            <span class="text-muted small px-2">ADMIN</span>
        </li>
        <li class="nav-item">
            <a href="/stocktrack/modules/categories/index.php" class="nav-link text-white">
                <i class="bi bi-tags me-2"></i>Categories
            </a>
        </li>
        <li class="nav-item">
            <a href="/stocktrack/modules/users/index.php" class="nav-link text-white">
                <i class="bi bi-people me-2"></i>Users
            </a>
        </li>
        <li class="nav-item">
            <a href="/stocktrack/modules/audit/index.php" class="nav-link text-white">
                <i class="bi bi-shield-check me-2"></i>Activity Log
            </a>
        </li>
        <?php endif; ?>
    </ul>
</div>
<div class="content p-4 w-100">
