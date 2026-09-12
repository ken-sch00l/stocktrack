<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('manage_users');
require_once '../../includes/db.php';

$user_sort = $_GET['sort'] ?? 'name';
$user_direction = strtoupper($_GET['direction'] ?? 'ASC');
$user_sort_columns = ['name' => 'full_name', 'username' => 'username', 'role' => 'role', 'date' => 'created_at'];
$user_sort = array_key_exists($user_sort, $user_sort_columns) ? $user_sort : 'name';
$user_direction = in_array($user_direction, ['ASC', 'DESC'], true) ? $user_direction : 'ASC';
$user_visibility = hasRole('super_admin') ? '' : "WHERE role <> 'super_admin'";
$users = $conn->query("SELECT * FROM users $user_visibility ORDER BY {$user_sort_columns[$user_sort]} $user_direction");
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>User Management</h4>
    <a href="add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Add User
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible">
        <?php echo htmlspecialchars($_GET['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="GET" class="row g-2 mb-3 align-items-end">
    <div class="col-md-4"><label class="form-label">Sort users by</label><select name="sort" class="form-select"><option value="name" <?php echo $user_sort === 'name' ? 'selected' : ''; ?>>Name</option><option value="username" <?php echo $user_sort === 'username' ? 'selected' : ''; ?>>Username</option><option value="role" <?php echo $user_sort === 'role' ? 'selected' : ''; ?>>Role</option><option value="date" <?php echo $user_sort === 'date' ? 'selected' : ''; ?>>Date added</option></select></div>
    <div class="col-md-4"><label class="form-label">Direction</label><select name="direction" class="form-select"><option value="ASC" <?php echo $user_direction === 'ASC' ? 'selected' : ''; ?>>Ascending</option><option value="DESC" <?php echo $user_direction === 'DESC' ? 'selected' : ''; ?>>Descending</option></select></div>
    <div class="col-md-4"><button type="submit" class="btn btn-outline-primary">Apply sorting</button></div>
</form>

<?php if (isset($_SESSION['temporary_user_credentials'])): ?>
    <div class="alert alert-warning">
        Temporary credentials for <strong><?php echo htmlspecialchars($_SESSION['temporary_user_credentials']['username']); ?></strong>:
        <code><?php echo htmlspecialchars($_SESSION['temporary_user_credentials']['password']); ?></code>
        <br><small>Share this once through a secure channel. It will not be shown again.</small>
    </div>
    <?php unset($_SESSION['temporary_user_credentials']); ?>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Date Added</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($user = $users->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><span class="badge bg-primary"><?php echo ucfirst($user['role']); ?></span></td>
                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <?php if ($user['role'] !== 'super_admin' || hasRole('super_admin')): ?>
                        <a href="edit.php?id=<?php echo (int)$user['user_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit account">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($user['username'] !== 'admin' && $user['user_id'] != $_SESSION['user_id'] && ($user['role'] !== 'super_admin' || hasRole('super_admin'))): ?>
                        <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $user['user_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php elseif ($user['role'] === 'super_admin' || $user['user_id'] == $_SESSION['user_id']): ?>
                        <span class="text-muted small">Protected</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
