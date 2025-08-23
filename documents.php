<?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="css/hover.css">
<div class="container my-4">
    <h2 class="mb-4">📚 Danh sách tài liệu</h2>

    <?php
    include 'includes/db.php';

    // Lấy danh sách tài liệu, tên môn học, tên người đăng và thống kê đánh giá
    $docs = $conn->query("
        SELECT d.*, u.username, s.subject_name,
            SUM(CASE WHEN r.review_type = 'positive' THEN 1 ELSE 0 END) AS positive_count,
            SUM(CASE WHEN r.review_type = 'negative' THEN 1 ELSE 0 END) AS negative_count
        FROM documents d
        JOIN users u ON d.user_id = u.user_id
        LEFT JOIN subjects s ON d.subject_id = s.subject_id
        LEFT JOIN reviews r ON d.doc_id = r.doc_id
        WHERE d.status_id = 2
        GROUP BY d.doc_id
        ORDER BY d.upload_date DESC
    ")->fetchAll();
    ?>

    <?php if (!$docs): ?>
        <div class="alert alert-info">Hiện chưa có tài liệu nào được duyệt.</div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($docs as $doc): ?>
                <?php
                $total_reviews = ($doc['positive_count'] ?? 0) + ($doc['negative_count'] ?? 0);
                if ($total_reviews > 0) {
                    $ratio = $doc['positive_count'] / $total_reviews;
                    $review_summary = $ratio >= 0.7 ? "Đánh giá tích cực" : ($ratio >= 0.4 ? "Đánh giá trung bình" : "Đánh giá tiêu cực");
                } else {
                    $review_summary = "Chưa có đánh giá";
                }
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <a href="document_view.php?id=<?= $doc['doc_id'] ?? 0 ?>" class="text-decoration-none text-dark">
                        <div class="card h-100 shadow-sm doc-card">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?= htmlspecialchars($doc['title'] ?? '') ?></h5>
                                <p class="card-text"><strong>Môn học:</strong> <?= htmlspecialchars($doc['subject_name'] ?? '') ?></p>
                                <p class="card-text"><strong>Người đăng:</strong> <?= htmlspecialchars($doc['username'] ?? '') ?></p>
                                <p class="card-text text-info">
                                    <strong>Đánh giá:</strong> <?= $review_summary ?>
                                    (👍 <?= $doc['positive_count'] ?? 0 ?> / 👎 <?= $doc['negative_count'] ?? 0 ?>)
                                </p>

                                <?php if (!empty($doc['description'])): ?>
                                    <p class="card-text"><strong>Mô tả:</strong> <?= nl2br(htmlspecialchars($doc['description'] ?? '')) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer small text-muted">
                                Đăng ngày: <?= !empty($doc['upload_date']) ? date("d/m/Y H:i", strtotime($doc['upload_date'])) : '' ?>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>