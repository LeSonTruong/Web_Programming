<?php
include 'includes/header.php';
include 'includes/db.php';

// ====== KIỂM TRA QUYỀN ADMIN ======
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo '<div class="container my-5">
            <div class="alert alert-danger text-center">
                ⚠️ Bạn không có quyền truy cập trang này!
            </div>
          </div>';
    include 'includes/footer.php';
    exit();
}

// ====== XỬ LÝ HÀNH ĐỘNG ADMIN ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($user_id > 0) {
        switch ($action) {
            case 'delete':
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id=? AND role='user'");
                $stmt->execute([$user_id]);
                break;
            case 'ban':
                $stmt = $conn->prepare("UPDATE users SET banned=1 WHERE user_id=? AND role='user'");
                $stmt->execute([$user_id]);
                break;
            case 'unban':
                $stmt = $conn->prepare("UPDATE users SET banned=0 WHERE user_id=? AND role='user'");
                $stmt->execute([$user_id]);
                break;
            case 'lock_comments':
                $stmt = $conn->prepare("UPDATE users SET comment_locked=1 WHERE user_id=? AND role='user'");
                $stmt->execute([$user_id]);
                break;
            case 'unlock_comments':
                $stmt = $conn->prepare("UPDATE users SET comment_locked=0 WHERE user_id=? AND role='user'");
                $stmt->execute([$user_id]);
                break;
            case 'lock_uploads':
                $stmt = $conn->prepare("UPDATE users SET upload_locked=1 WHERE user_id=? AND role='user'");
                $stmt->execute([$user_id]);
                break;
            case 'unlock_uploads':
                $stmt = $conn->prepare("UPDATE users SET upload_locked=0 WHERE user_id=? AND role='user'");
                $stmt->execute([$user_id]);
                break;
        }
    }
}

// ====== LẤY DANH SÁCH NGƯỜI DÙNG ======
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container my-4">
    <h2 class="mb-4">👥 Quản lý tài khoản người dùng</h2>

    <?php if (!$users): ?>
        <div class="alert alert-info">Hiện chưa có người dùng nào.</div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($users as $user): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm user-card">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($user['display_name'] ?: $user['username']) ?></h5>
                            <p class="card-text"><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                            <p class="card-text"><strong>Role:</strong> <?= htmlspecialchars($user['role']) ?></p>
                            <p class="card-text">
                                <strong>Trạng thái:</strong>
                                <?= ($user['banned'] ?? 0) ? '<span class="text-danger">Bị khóa</span>' : '<span class="text-success">Hoạt động</span>' ?>
                            </p>
                            <p class="card-text">
                                <strong>Bình luận:</strong>
                                <?= ($user['comment_locked'] ?? 0) ? '🔒 Khóa' : '🟢 Mở' ?>
                                <br>
                                <strong>Tải tài liệu:</strong>
                                <?= ($user['upload_locked'] ?? 0) ? '🔒 Khóa' : '🟢 Mở' ?>
                            </p>
                        </div>
                        <div class="card-footer d-flex flex-wrap gap-2">
                            <?php if ($user['role'] !== 'admin'): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                                    <button type="submit" name="action" value="<?= ($user['banned'] ?? 0) ? 'unban' : 'ban' ?>" class="btn btn-sm <?= ($user['banned'] ?? 0) ? 'btn-success' : 'btn-warning' ?>">
                                        <?= ($user['banned'] ?? 0) ? 'Mở khóa' : 'Khóa tài khoản' ?>
                                    </button>
                                </form>

                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                                    <button type="submit" name="action" value="<?= ($user['comment_locked'] ?? 0) ? 'unlock_comments' : 'lock_comments' ?>" class="btn btn-sm btn-secondary">
                                        <?= ($user['comment_locked'] ?? 0) ? 'Mở bình luận' : 'Khóa bình luận' ?>
                                    </button>
                                </form>

                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                                    <button type="submit" name="action" value="<?= ($user['upload_locked'] ?? 0) ? 'unlock_uploads' : 'lock_uploads' ?>" class="btn btn-sm btn-info">
                                        <?= ($user['upload_locked'] ?? 0) ? 'Mở tải lên' : 'Khóa tải lên' ?>
                                    </button>
                                </form>

                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc muốn xóa người dùng này?')">
                                        Xóa
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">Admin</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>