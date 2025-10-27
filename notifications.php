<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $reason = 'chuadangnhap';
    include __DIR__ . '/!403.php';
    exit();
}

include 'includes/db.php';
include 'includes/header.php';


$user_id = $_SESSION['user_id'];

// ====== ADMIN: gửi thông báo tới 1 user ======
$admin_error = '';
$admin_success = '';
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    // Handle send notification POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
        $target_username = trim($_POST['target_username'] ?? '');
        $notif_message = trim($_POST['notif_message'] ?? '');
        if ($target_username === '' || $notif_message === '') {
            $admin_error = 'Vui lòng nhập tên người dùng và nội dung thông báo.';
        } else {
            // find user by username
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$target_username]);
            $target_id = $stmt->fetchColumn();
            if (!$target_id) {
                $admin_error = 'Không tìm thấy người dùng với username đã nhập.';
            } else {
                try {
                    $ins = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $ins->execute([$target_id, $notif_message]);
                    $admin_success = 'Đã gửi thông báo cho ' . htmlspecialchars($target_username) . '.';
                } catch (Exception $e) {
                    $admin_error = 'Lỗi khi gửi thông báo: ' . htmlspecialchars($e->getMessage());
                }
            }
        }
    }

    ?>
    <div class="container my-5">
        <h2 class="mb-4">🔔 Gửi thông báo</h2>
        <?php if ($admin_error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($admin_error) ?></div>
        <?php elseif ($admin_success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($admin_success) ?></div>
        <?php endif; ?>

        <form method="post" class="mb-4">
            <input type="hidden" name="send_notification" value="1">
            <div class="mb-2">
                <label class="form-label">Tên người dùng</label>
                <input type="text" name="target_username" class="form-control" placeholder="vd: alice" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Nội dung thông báo</label>
                <textarea name="notif_message" class="form-control" rows="3" placeholder="Nội dung thông báo..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Gửi</button>
        </form>
    </div>
    <?php
}

// ====== Đánh dấu thông báo đã đọc nếu POST mark_read ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notif_id = (int)$_POST['mark_read'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE notification_id=? AND user_id=?");
    $stmt->execute([$notif_id, $user_id]);
    header("Location: notifications.php");
    exit();
}

// ====== Lấy danh sách thông báo ======
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container my-5">
    <h2 class="mb-4">🔔 Thông báo của bạn</h2>

    <?php if (!$notifications): ?>
        <div class="alert alert-info">Hiện tại bạn chưa có thông báo nào.</div>
    <?php else: ?>
        <ul class="list-group">
            <?php foreach ($notifications as $notif): ?>
                <li class="list-group-item d-flex justify-content-between align-items-start <?= $notif['is_read'] ? '' : 'list-group-item-warning' ?>">
                    <div>
                        <?= htmlspecialchars($notif['message']) ?>
                        <br>
                        <small class="text-muted"><?= date('H:i d/m/Y', strtotime($notif['created_at'])) ?></small>
                    </div>
                    <?php if (!$notif['is_read']): ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="mark_read" value="<?= $notif['notification_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-success">Đã đọc</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>