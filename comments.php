<?php
include 'includes/header.php';
include 'includes/db.php';

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

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['role'] === 'admin';

// ====== XỬ LÝ FORM BÌNH LUẬN MỚI ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doc_id'], $_POST['content'])) {
    $doc_id = (int)$_POST['doc_id'];
    $content = trim($_POST['content']);

    if ($content) {
        $stmt = $conn->prepare("INSERT INTO comments (doc_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$doc_id, $user_id, $content]);
        echo '<div class="alert alert-success">✅ Bình luận đã được thêm!</div>';
    }
}

// ====== LẤY DANH SÁCH COMMENTS ======
if ($is_admin) {
    // Admin thấy tất cả bình luận
    $stmt = $conn->query("
        SELECT c.comment_id, c.content, c.created_at, u.fullname, u.username, d.title, d.doc_id
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        JOIN documents d ON c.doc_id = d.doc_id
        ORDER BY c.created_at DESC
    ");
} else {
    // User chỉ thấy bình luận của họ hoặc bình luận công khai tài liệu
    $stmt = $conn->prepare("
        SELECT c.comment_id, c.content, c.created_at, u.fullname, u.username, d.title, d.doc_id
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        JOIN documents d ON c.doc_id = d.doc_id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute();
}

$comments = $stmt->fetchAll();
?>

<div class="container my-4">
    <h2 class="mb-4">💬 Bình luận tài liệu</h2>

    <!-- Form bình luận -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label for="doc_id" class="form-label">Chọn tài liệu</label>
                    <select name="doc_id" id="doc_id" class="form-select" required>
                        <?php
                        $docs = $conn->query("SELECT doc_id, title FROM documents ORDER BY upload_date DESC")->fetchAll();
                        foreach ($docs as $d) {
                            echo '<option value="' . $d['doc_id'] . '">' . htmlspecialchars($d['title']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="content" class="form-label">Nội dung bình luận</label>
                    <textarea name="content" id="content" class="form-control" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Gửi bình luận</button>
            </form>
        </div>
    </div>

    <!-- Hiển thị bình luận -->
    <?php if (!$comments): ?>
        <div class="alert alert-info">Chưa có bình luận nào.</div>
    <?php else: ?>
        <?php foreach ($comments as $c): ?>
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($c['fullname'] ?? $c['username']) ?>
                        <small class="text-muted">trên "<?= htmlspecialchars($c['title']) ?>"</small>
                    </h5>
                    <p class="card-text"><?= nl2br(htmlspecialchars($c['content'])) ?></p>
                </div>
                <div class="card-footer text-muted small d-flex justify-content-between">
                    <span><?= date("d/m/Y H:i", strtotime($c['created_at'])) ?></span>
                    <?php if ($is_admin || $c['user_id'] == $user_id): ?>
                        <a href="delete_comment.php?id=<?= $c['comment_id'] ?>" class="text-danger" onclick="return confirm('Bạn có chắc muốn xóa bình luận này?')">Xóa</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>