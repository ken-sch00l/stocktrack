<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('manage_users');
require_once '../../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $full_name = trim($_POST['full_name']);
    $username  = trim($_POST['username']);
    $role      = $_POST['role'];

    if (!$full_name || !$username) {
        $error = "Full name and username are required.";
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Username already exists.";
        } else {
            $temporary_password = bin2hex(random_bytes(6));
            $hashed = password_hash($temporary_password, PASSWORD_DEFAULT);
            $must_change_password = 1;
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, role, must_change_password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $full_name, $username, $hashed, $role, $must_change_password);
            if ($stmt->execute()) {
                recordAudit('user_created', 'user', $conn->insert_id, 'Role: ' . $role);
                $_SESSION['temporary_user_credentials'] = [
                    'username' => $username,
                    'password' => $temporary_password
                ];
                header("Location: /stocktrack/modules/users/index.php?success=User added. Share the temporary password securely.");
                exit();
            } else {
                $error = "Failed to add user.";
            }
        }
    }
}
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-person-plus me-2 text-primary"></i>Add User</h4>
    <a href="/stocktrack/modules/users/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            A random temporary password will be generated. The user must change it after logging in.
        </div>
        <form method="POST">
            <?php csrf_field(); ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" placeholder="Enter full name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <option value="secretary">Secretary</option>
                        <option value="treasurer">Treasurer</option>
                        <option value="committee">Committee on Inventory</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save me-1"></i>Add User
                    </button>
                    <a href="/stocktrack/modules/users/index.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
