<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';

$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';

$stmt = $conn->prepare("SELECT full_name, username, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: /stocktrack/login.php");
    exit();
}

$form_full_name = $user['full_name'];
$form_username = $user['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $form_full_name = trim($_POST['full_name'] ?? '');
    $form_username = trim($_POST['username'] ?? '');

    if (!$form_full_name || !$form_username) {
        $error = 'Full name and username are required.';
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id <> ?");
        $check->bind_param("si", $form_username, $user_id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = 'Username already exists.';
        } else {
            $update = $conn->prepare("UPDATE users SET full_name = ?, username = ? WHERE user_id = ?");
            $update->bind_param("ssi", $form_full_name, $form_username, $user_id);

            if ($update->execute()) {
                $_SESSION['full_name'] = $form_full_name;
                recordAudit('profile_updated', 'user', $user_id, 'Own profile details updated');
                $success = 'Profile updated successfully.';
            } else {
                $error = 'Failed to update profile.';
            }
        }
    }
}
?>
<?php require_once 'includes/header.php'; ?>
<?php require_once 'includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-person-circle me-2 text-primary"></i>My Profile</h4>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
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
                    <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['role']))); ?>" readonly>
                    <small class="text-muted">Only an administrator can change your role.</small>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Profile</button>
                    <a href="change_password.php" class="btn btn-outline-secondary ms-2"><i class="bi bi-key me-1"></i>Change Password</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
