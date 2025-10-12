<?php
include 'includes/header.php';
include 'includes/db.php';
include 'includes/ai.php'; // Gọi các hàm createSummary(), createEmbedding()

// ====== HÀM LOG AI ======
function logAI($conn, $doc_id, $action, $status, $message = '')
{
    $stmt = $conn->prepare("INSERT INTO ai_logs (doc_id, action, status, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$doc_id, $action, $status, $message]);
}

// ====== KIỂM TRA QUYỀN ADMIN ======
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo '<div class="alert alert-danger">Bạn không có quyền truy cập trang này!</div>';
    include 'includes/footer.php';
    exit();
}

// ====== ĐẾM SỐ BÀI ĐANG CHỜ DUYỆT ======
$pending_count = $conn->query("SELECT COUNT(*) FROM documents WHERE status_id=1")->fetchColumn();
if ($pending_count > 0) {
    echo "<div class='alert alert-info'>Hiện có $pending_count tài liệu đang chờ duyệt.</div>";
}

// ====== DUYỆT TÀI LIỆU ======
if (isset($_GET['approve'])) {
    $doc_id = (int) $_GET['approve'];
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

            // Tạo summary & embedding
            $summary = createSummary($textContent);
            $embedding = createEmbedding($textContent);

            // Lưu summary và cập nhật trạng thái
            $stmt = $conn->prepare("UPDATE documents SET status_id=2, summary=? WHERE doc_id=?");
            $stmt->execute([$summary, $doc_id]);
            logAI($conn, $doc_id, 'summary', 'success', 'Tóm tắt thành công');

            // Lưu embedding
            if (!empty($embedding)) {
                $stmt = $conn->prepare("INSERT INTO document_embeddings (doc_id, vector) VALUES (?, ?)");
                $stmt->execute([$doc_id, json_encode($embedding)]);
                logAI($conn, $doc_id, 'embedding', 'success', 'Embedding thành công');
            }

            // Tạo thông báo cho user
            $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $message = "✅ Tài liệu '{$doc['title']}' của bạn đã được duyệt!";
            $stmt_notif->execute([$doc['user_id'], $message]);

            echo '<div class="alert alert-success">✅ Tài liệu đã được duyệt, tóm tắt & embedding đã lưu.</div>';
        }
    }
}

// ====== TỪ CHỐI TÀI LIỆU ======
if (isset($_GET['reject'])) {
    $doc_id = (int) $_GET['reject'];
    $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();

    if ($doc && $doc['status_id'] == 1) {
        $stmt = $conn->prepare("UPDATE documents SET status_id=3 WHERE doc_id=?");
        $stmt->execute([$doc_id]);

        $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $message = "❌ Tài liệu '{$doc['title']}' của bạn đã bị từ chối!";
        $stmt_notif->execute([$doc['user_id'], $message]);

        echo '<div class="alert alert-danger">❌ Tài liệu đã bị từ chối.</div>';
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
                            <div>
                                <a href="?approve=<?= $doc['doc_id'] ?>" class="btn btn-success btn-sm">Duyệt</a>
                                <a href="?reject=<?= $doc['doc_id'] ?>" class="btn btn-danger btn-sm">Từ chối</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>