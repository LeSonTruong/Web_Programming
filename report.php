<?php
session_start();
include 'includes/header.php';
include 'includes/db.php';

// Danh sách lý do và hành vi vi phạm
$reasons = [
    'Spam',
    'Ngôn từ không phù hợp',
    'Quấy rối',
    'Chia sẻ thông tin sai lệch',
    'Quảng cáo',
    'Khác',
];
$behaviors = [
    'Bình luận',
    'Tài liệu',
    'Tin nhắn',
    'Hành vi khác',
];

// Xử lý gửi báo cáo
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $reported_user = trim($_POST['reported_user'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $behavior = trim($_POST['behavior'] ?? '');
    $evidence = trim($_POST['evidence'] ?? '');
    if ($reported_user && $reason && $behavior) {
        $stmt = $conn->prepare("INSERT INTO reports (reporter_id, reported_user, reason, behavior, evidence) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $reported_user,
            $reason,
            $behavior,
            $evidence
        ]);
        $success = true;
    }
}
?>
<div class="container my-5">
    <h2 class="mb-4">Báo cáo người dùng vi phạm</h2>
    <?php if ($success): ?>
        <div class="alert alert-success">Đã gửi báo cáo thành công! Admin sẽ xem xét và xử lý.</div>
    <?php endif; ?>
    <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="alert alert-warning">Bạn cần <a href="login.php">đăng nhập</a> để gửi báo cáo.</div>
    <?php else: ?>
        <form method="post" class="row g-3">
            <div class="col-md-6">
                <label for="reported_user" class="form-label">Tên người dùng bị báo cáo</label>
                <input type="text" class="form-control" id="reported_user" name="reported_user" required placeholder="Nhập username hoặc tên hiển thị">
            </div>
            <div class="col-md-6">
                <label for="behavior" class="form-label">Hành vi vi phạm</label>
                <select class="form-select" id="behavior" name="behavior" required>
                    <option value="">-- Chọn hành vi --</option>
                    <?php foreach ($behaviors as $b): ?>
                        <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-12">
                <label for="reason" class="form-label">Lý do báo cáo</label>
                <select class="form-select" id="reason" name="reason" required>
                    <option value="">-- Chọn lý do --</option>
                    <?php foreach ($reasons as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-12">
                <label for="evidence" class="form-label">Bằng chứng (link, hình ảnh, mô tả...)</label>
                <textarea class="form-control" id="evidence" name="evidence" rows="3" placeholder="Nhập bằng chứng hoặc mô tả chi tiết"></textarea>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-danger">Gửi báo cáo</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>