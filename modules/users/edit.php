<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('manage_users');
require_once '../../includes/db.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT user_id, full_name, username, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: /stocktrack/modules/users/index.php");
    exit();
}

$error = '';
$form_full_name = $user['full_name'];
$form_username = $user['username'];
$form_role = $user['role'];
$is_super_admin_target = $user['role'] === 'super_admin';

if ($is_super_admin_target && !hasRole('super_admin')) {
    http_response_code(403);
    exit('Only a super administrator can edit this account.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $form_full_name = trim($_POST['full_name'] ?? '');
    $form_username = trim($_POST['username'] ?? '');
    $form_role = $_POST['role'] ?? '';
    $reset_password = isset($_POST['reset_password']);
    $allowed_roles = hasRole('super_admin')
        ? ['admin', 'super_admin', 'secretary', 'treasurer', 'committee']
        : ['admin', 'secretary', 'treasurer', 'committee'];

    if (!$form_full_name || !$form_username) {
        $error = 'Full name and username are required.';
    } elseif (!in_array($form_role, $allowed_roles, true)) {
        $error = 'Invalid role selected.';
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id <> ?");
        $check->bind_param("si", $form_username, $id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = 'Username already exists.';
        } else {
            if ($reset_password) {
                $temporary_password = bin2hex(random_bytes(6));
                $hashed_password = password_hash($temporary_password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET full_name = ?, username = ?, role = ?, password = ?, must_change_password = 1 WHERE user_id = ?");
                $update->bind_param("ssssi", $form_full_name, $form_username, $form_role, $hashed_password, $id);
            } else {
                $update = $conn->prepare("UPDATE users SET full_name = ?, username = ?, role = ? WHERE user_id = ?");
                $update->bind_param("sssi", $form_full_name, $form_username, $form_role, $id);
            }

            if ($update->execute()) {
                if ($reset_password) {
                    $_SESSION['temporary_user_credentials'] = [
                        'username' => $form_username,
                        'password' => $temporary_password
                    ];
                }
                recordAudit($reset_password ? 'user_password_reset' : 'user_updated', 'user', $id, $reset_password ? 'Password reset to a random temporary password' : 'Account details updated');
                $message = $reset_password ? 'User account updated and a temporary password was generated.' : 'User account updated successfully.';
                header("Location: /stocktrack/modules/users/index.php?success=" . urlencode($message));
                exit();
            }

            $error = 'Failed to update user account.';
        }
    }
}
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-person-gear me-2 text-primary"></i>Edit User Account</h4>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="max-width: 700px;">
    <div class="card-body">
        <form method="POST">
            <?php csrf_field(); ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($form_full_name); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($form_username); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="admin" <?php echo $form_role === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                        <option value="secretary" <?php echo $form_role === 'secretary' ? 'selected' : ''; ?>>Secretary</option>
                        <option value="treasurer" <?php echo $form_role === 'treasurer' ? 'selected' : ''; ?>>Treasurer</option>
                        <option value="committee" <?php echo $form_role === 'committee' ? 'selected' : ''; ?>>Committee on Inventory</option>
                        <?php if (hasRole('super_admin')): ?><option value="super_admin" <?php echo $form_role === 'super_admin' ? 'selected' : ''; ?>>Super Administrator</option><?php endif; ?>
                    </select>
                </div>
                <div class="col-12">
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-key me-2"></i>Password reset generates a random temporary password and forces the user to change it after login.
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Account</button>
                    <button type="submit" name="reset_password" value="1" class="btn btn-warning ms-2" onclick="return confirm('Generate a new temporary password? The user must change it after login.');"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset Password</button>
                    <a href="index.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
