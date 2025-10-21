<?php
include 'includes/db.php';

session_start();

// ====== KIỂM TRA QUYỀN ======
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    $reason = '';
    include __DIR__ . '/!403.php';
    exit();
}

include 'includes/header.php';

// Tạo token đơn giản cho thao tác POST (CSRF-lite)
if (empty($_SESSION['approve_token'])) {
    $_SESSION['approve_token'] = bin2hex(random_bytes(16));
}

// Kiểm tra token khi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    if (!hash_equals($_SESSION['approve_token'], $token)) {
        echo '<div class="alert alert-danger">Yêu cầu không hợp lệ (token).</div>';
        include 'includes/footer.php';
        exit();
    }
}

// ====== ĐẾM SỐ BÀI ĐANG CHỜ DUYỆT ======
/*$pending_count = $conn->query("SELECT COUNT(*) FROM documents WHERE status_id=1")->fetchColumn();
if ($pending_count > 0) {
    echo "<div class='alert alert-info'>Hiện có $pending_count tài liệu đang chờ duyệt.</div>";
}*/

// ====== DUYỆT TÀI LIỆU (POST) ======
if (isset($_POST['approve'])) {
    $doc_id = (int) $_POST['approve'];
    $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();

    if ($doc) {
        $stmt_user = $conn->prepare("SELECT role FROM users WHERE user_id=?");
        $stmt_user->execute([$doc['user_id']]);
        $user_post = $stmt_user->fetch();

        if ($doc['status_id'] != 1) {
            echo '<div class="alert alert-info">⚠️ Tài liệu này đã được xử lý trước đó.</div>';
        } else {
            $textContent = $doc['description'] ?: $doc['title'];
            $textContent = mb_substr($textContent, 0, 5000);

            // cập nhật trạng thái
            $stmt = $conn->prepare("UPDATE documents SET status_id=2 WHERE doc_id=?");
            $stmt->execute([$doc_id]);

            // Tạo thông báo cho user
            $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $message = "✅ Tài liệu '{$doc['title']}' của bạn đã được duyệt!";
            $stmt_notif->execute([$doc['user_id'], $message]);

            // Thêm vào hàng đợi AI để xử lý (document_id, status='pending')
            $stmt_ai = $conn->prepare("INSERT INTO ai_queue (document_id, status, created_at) VALUES (?, ?, NOW())");
            $stmt_ai->execute([$doc_id, 'pending']);

            echo '<div class="alert alert-success">✅ Tài liệu đã được duyệt.</div>';
        }
    }
}

// ====== TỪ CHỐI TÀI LIỆU ======
if (isset($_POST['reject'])) {
    $doc_id = (int) $_POST['reject'];
    $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();

    if ($doc && $doc['status_id'] == 1) {
        $stmt = $conn->prepare("UPDATE documents SET status_id=3 WHERE doc_id=?");
        $stmt->execute([$doc_id]);

        $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $message = "❌ Tài liệu '{$doc['title']}' của bạn đã bị từ chối!";
        $stmt_notif->execute([$doc['user_id'], $message]);

        echo '<div class="alert alert-danger">❌ Đã từ chối tài liệu.</div>';
    }
}

// ====== DANH SÁCH CHỜ DUYỆT ======
$docs = $conn->query("
    SELECT documents.*, users.username 
    FROM documents 
    JOIN users ON documents.user_id = users.user_id 
    WHERE status_id=1
    ORDER BY upload_date DESC
")->fetchAll();
?>

<div class="container my-4">
    <h2 class="mb-4">📝 Duyệt tài liệu</h2>

    <?php if (!$docs): ?>
        <div class="alert alert-info">Hiện tại không có tài liệu nào chờ duyệt.</div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($docs as $doc): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($doc['title']) ?></h5>
                            <p class="card-text"><strong>Người đăng:</strong> <?= htmlspecialchars($doc['username']) ?></p>
                            <?php if (!empty($doc['description'])): ?>
                                <p class="card-text"><strong>Mô tả:</strong> <?= nl2br(htmlspecialchars($doc['description'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer d-flex justify-content-between">
                            <a href="document_view.php?id=<?= $doc['doc_id'] ?>" target="_blank" class="btn btn-info btn-sm">Xem tài liệu</a>
                            <div class="d-flex">
                                <form method="POST" class="me-1">
                                    <input type="hidden" name="approve" value="<?= $doc['doc_id'] ?>">
                                    <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['approve_token']) ?>">
                                    <button class="btn btn-success btn-sm" type="submit">Duyệt</button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="reject" value="<?= $doc['doc_id'] ?>">
                                    <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['approve_token']) ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">Từ chối</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>