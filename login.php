<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    $destination = !empty($_SESSION['must_change_password'])
        ? '/stocktrack/change_password.php'
        : '/stocktrack/dashboard.php';
    header("Location: {$destination}");
    exit();
}

$error = '';
$lockout_seconds = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $login_identifier = login_identifier();

    if (isLoginRateLimited($login_identifier)) {
        $lockout_seconds = getLoginBlockSeconds($login_identifier);
        $error = "Too many failed attempts.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            clearLoginFailures($login_identifier);
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['must_change_password'] = (int)$user['must_change_password'];
            recordAudit('login_success', 'user', $user['user_id']);
            $destination = $_SESSION['must_change_password']
                ? '/stocktrack/change_password.php'
                : '/stocktrack/dashboard.php';
            header("Location: {$destination}");
            exit();
        }

        recordLoginFailure($login_identifier);
        recordAudit('login_failure', 'user', null, 'Invalid credentials');
        $error = "Invalid username or password.";
        $lockout_seconds = getLoginBlockSeconds($login_identifier);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StockTrack - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f0f4ff; min-height: 100vh; display:flex; align-items:center; justify-content:center; }
        .login-card { width: 100%; max-width: 420px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .login-header { background: #0d6efd; color: white; border-radius: 16px 16px 0 0; padding: 30px; text-align: center; }
    </style>
</head>
<body>
<div class="login-card bg-white">
    <div class="login-header">
        <i class="bi bi-box-seam" style="font-size:2.5rem;"></i>
        <h4 class="mt-2 mb-0 fw-bold">StockTrack</h4>
        <small>Barangay Puguis Inventory System</small>
    </div>
    <div class="p-4">
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible" <?php echo $lockout_seconds > 0 ? 'data-lockout-seconds="' . $lockout_seconds . '"' : ''; ?>>
                <?php echo htmlspecialchars($error); ?>
                <?php if ($lockout_seconds > 0): ?>
                    Try again in <strong id="lockoutCountdown"><?php echo $lockout_seconds; ?></strong> seconds.
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <form method="POST">
            <?php csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" <?php echo $lockout_seconds > 0 ? 'disabled' : ''; ?>>
                <i class="bi bi-box-arrow-in-right me-2"></i>Login
            </button>
        </form>
        <p class="text-center text-muted mt-3 small">Barangay Puguis, La Trinidad, Benguet</p>
    </div>
</div>
<?php if ($lockout_seconds > 0): ?>
<script>
    let remainingSeconds = <?php echo $lockout_seconds; ?>;
    const countdown = document.getElementById('lockoutCountdown');
    const loginButton = document.querySelector('button[type="submit"]');
    const timer = setInterval(function() {
        remainingSeconds -= 1;
        if (remainingSeconds <= 0) {
            clearInterval(timer);
            window.location.reload();
            return;
        }
        countdown.textContent = remainingSeconds;
    }, 1000);
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
