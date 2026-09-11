<?php
require_once '../../includes/auth.php';
requireLogin();
requirePermission('view_notifications');
require_once '../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $notification_id = (int)($_POST['notification_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND recipient_user_id = ?");
    $stmt->bind_param("ii", $notification_id, $_SESSION['user_id']);
    $stmt->execute();
}

$notifications = $conn->prepare("SELECT * FROM notifications WHERE recipient_user_id = ? ORDER BY created_at DESC LIMIT 100");
$notifications->bind_param("i", $_SESSION['user_id']);
$notifications->execute();
$notifications = $notifications->get_result();
?>
<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-bell me-2 text-primary"></i>Notifications</h4>
    <span class="text-muted small">Latest 100 notifications</span>
</div>
<div class="card border-0 shadow-sm">
    <div class="list-group list-group-flush">
        <?php if ($notifications->num_rows > 0): ?>
            <?php while ($notification = $notifications->fetch_assoc()): ?>
                <div class="list-group-item <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                    <div class="d-flex justify-content-between gap-3">
                        <div><strong><?php echo htmlspecialchars($notification['message']); ?></strong><br><small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></small></div>
                        <?php if (!$notification['is_read']): ?>
                            <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="notification_id" value="<?php echo (int)$notification['notification_id']; ?>"><button class="btn btn-sm btn-outline-primary" type="submit">Mark read</button></form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="list-group-item text-center text-muted py-4">No notifications yet.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>