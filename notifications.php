<?php
session_start(); // Bắt đầu session trước

// ====== KIỂM TRA ĐĂNG NHẬP ======
if (!isset($_SESSION['user_id'])) {
    echo '<div class="container my-5">
            <div class="alert alert-warning text-center">
                ⚠️ Tạo tài khoản hoặc đăng nhập đi bạn ÊYYYYY!
            </div>
          </div>';
    include 'includes/footer.php';
    exit();
}

// ====== KIỂM TRA ROLE ADMIN ======
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    echo '<div class="container my-5">
            <div class="alert alert-info text-center">
                👑 Bạn là ADMIN cơ mà! Bạn đã được thông báo rồi!
            </div>
          </div>';
    include 'includes/footer.php';
    exit();
}

// ====== KIỂM TRA ĐĂNG NHẬP USER ======
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/db.php';

$user_id = $_SESSION['user_id'];

// ====== Đánh dấu thông báo đã đọc nếu có param mark_read ======
if (isset($_GET['mark_read'])) {
    $notif_id = (int)$_GET['mark_read'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE notification_id=? AND user_id=?");
    $stmt->execute([$notif_id, $user_id]);
    header("Location: notifications.php");
    exit();
}

// ====== Lấy danh sách thông báo ======
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ====== Bắt đầu include header ======
include 'includes/header.php';
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
                        <a href="?mark_read=<?= $notif['notification_id'] ?>" class="btn btn-sm btn-outline-success">Đã đọc</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>