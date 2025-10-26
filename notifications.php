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

// ====== ADMIN ======
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    echo '<div class="container my-5">';
    echo '<h2 class="mb-4">🔔 Thông báo quản trị viên</h2>';
    // Duyệt tài liệu
    $pending_docs = $conn->query("SELECT COUNT(*) FROM documents WHERE status_id=1")->fetchColumn();
    echo '<div class="mb-3"><strong>✅ Tài liệu chờ duyệt:</strong> ' . $pending_docs . ' <a href="approve.php" class="btn btn-sm btn-primary ms-2">Xem chi tiết</a></div>';
    // Bình luận được phản hồi
    $reply_stmt = $conn->query("SELECT COUNT(*) FROM comments WHERE parent_comment_id IS NOT NULL AND created_at >= NOW() - INTERVAL 1 DAY");
    $recent_replies = $reply_stmt->fetchColumn();
    echo '<div class="mb-3"><strong>🔁 Bình luận vừa được phản hồi (24h):</strong> ' . $recent_replies . '</div>';
    echo '</div>';
}

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