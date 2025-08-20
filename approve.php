<?php
include 'includes/header.php';
include 'includes/db.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo '<div class="alert alert-danger">Bạn không có quyền truy cập trang này!</div>';
    include 'includes/footer.php';
    exit();
}

// Xử lý duyệt hoặc từ chối
if (isset($_GET['approve'])) {
    $stmt = $pdo->prepare("UPDATE documents SET status='approved' WHERE id=?");
    $stmt->execute([$_GET['approve']]);
    echo '<div class="alert alert-success">Tài liệu đã được duyệt.</div>';
}

if (isset($_GET['reject'])) {
    $stmt = $pdo->prepare("UPDATE documents SET status=\'rejected\' WHERE id=?");
    $stmt->execute([$_GET['reject']]);
    echo '<div class="alert alert-danger">Tài liệu đã bị từ chối.</div>';
}

// Lấy danh sách tài liệu chờ duyệt
$docs = $pdo->query("
    SELECT documents.*, users.username 
    FROM documents 
    JOIN users ON documents.user_id = users.user_id 
    WHERE status='pending' 
    ORDER BY created_at DESC
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
                            <p class="card-text"><strong>Môn học:</strong> <?= htmlspecialchars($doc['subject']) ?></p>
                            <p class="card-text"><strong>Người đăng:</strong> <?= htmlspecialchars($doc['username']) ?></p>
                            <?php if (!empty($doc['description'])): ?>
                                <p class="card-text"><strong>Mô tả:</strong> <?= nl2br(htmlspecialchars($doc['description'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer d-flex justify-content-between">
                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-info btn-sm">Xem/Tải</a>
                            <div>
                                <a href="?approve=<?= $doc['id'] ?>" class="btn btn-success btn-sm">Duyệt</a>
                                <a href="?reject=<?= $doc['id'] ?>" class="btn btn-danger btn-sm">Từ chối</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>