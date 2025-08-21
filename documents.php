<?php include 'includes/header.php'; ?>

<div class="container my-4">
    <h2 class="mb-4">📚 Danh sách tài liệu</h2>

    <?php
    include 'includes/db.php';

    // Lấy danh sách tài liệu đã được duyệt (status_id = 2)
    $docs = $conn->query("
        SELECT documents.*, users.username 
        FROM documents 
        JOIN users ON documents.user_id = users.user_id
        WHERE status_id = 2
        ORDER BY upload_date DESC
    ")->fetchAll();
    ?>

    <?php if (!$docs): ?>
        <div class="alert alert-info">Hiện chưa có tài liệu nào được duyệt.</div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($docs as $doc): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($doc['title']) ?></h5>
                            <p class="card-text"><strong>Môn học:</strong> <?= htmlspecialchars($doc['subject_id']) ?></p>
                            <p class="card-text"><strong>Người đăng:</strong> <?= htmlspecialchars($doc['username']) ?></p>

                            <?php if (!empty($doc['description'])): ?>
                                <p class="card-text">
                                    <strong>Mô tả:</strong> <?= nl2br(htmlspecialchars($doc['description'])) ?>
                                </p>
                            <?php endif; ?>

                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-primary mt-auto">
                                📥 Tải xuống
                            </a>
                        </div>
                        <div class="card-footer text-muted small">
                            Đăng ngày: <?= date("d/m/Y H:i", strtotime($doc['upload_date'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>