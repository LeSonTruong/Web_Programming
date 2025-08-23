<?php
include 'includes/header.php';
?>
<link rel="stylesheet" href="css/hover.css">
<div class="container my-5">
    <!-- Phần chào mừng -->
    <div class="text-center mb-5">
        <h2 class="display-5">
            <?php if (isset($_SESSION['fullname'])): ?>
                👋 Xin chào, <?= htmlspecialchars($_SESSION['fullname']) ?>!
            <?php else: ?>
                Chào mừng đến với StudyShare
            <?php endif; ?>
        </h2>
        <p class="lead">Nơi bạn có thể tải lên và chia sẻ tài liệu học tập theo môn học hoặc ngành học.</p>
    </div>

    <!-- Form tìm kiếm -->
    <div class="mb-5">
        <h3>Tìm kiếm tài liệu</h3>
        <form action="search.php" method="get" class="input-group">
            <input
                type="text"
                name="q"
                class="form-control"
                placeholder="Nhập tiêu đề hoặc môn học...">
            <button type="submit" class="btn btn-primary">
                🔍 Tìm kiếm
            </button>
        </form>
    </div>

    <!-- Tài liệu mới nhất -->
    <div>
        <h3>Tài liệu mới nhất</h3>
        <div class="row mt-3">
            <?php
            include 'includes/db.php';

            $stmt = $conn->query("
            SELECT d.*, u.username, s.subject_name,
                SUM(CASE WHEN r.review_type = 'positive' THEN 1 ELSE 0 END) AS positive_count,
                SUM(CASE WHEN r.review_type = 'negative' THEN 1 ELSE 0 END) AS negative_count
            FROM documents d
            JOIN users u ON d.user_id = u.user_id
            JOIN subjects s ON d.subject_id = s.subject_id
            LEFT JOIN reviews r ON d.doc_id = r.doc_id
            WHERE d.status_id = 2
            GROUP BY d.doc_id
            ORDER BY d.upload_date DESC
            LIMIT 5
        ");
            $docs = $stmt->fetchAll();

            if (!$docs):
            ?>
                <div class="alert alert-info">Hiện chưa có tài liệu nào được duyệt.</div>
            <?php else: ?>
                <?php foreach ($docs as $doc): ?>
                    <?php
                    $total_reviews = $doc['positive_count'] + $doc['negative_count'];
                    if ($total_reviews > 0) {
                        $ratio = $doc['positive_count'] / $total_reviews;
                        $review_summary = $ratio >= 0.7 ? "Đánh giá tích cực" : ($ratio >= 0.4 ? "Đánh giá trung bình" : "Đánh giá tiêu cực");
                    } else {
                        $review_summary = "Chưa có đánh giá";
                    }
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <a href="document_view.php?id=<?= $doc['doc_id'] ?>" class="text-decoration-none text-dark">
                            <div class="card h-100 shadow-sm doc-card">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?= htmlspecialchars($doc['title']) ?></h5>
                                    <p class="card-text"><strong>Môn học:</strong> <?= htmlspecialchars($doc['subject_name']) ?></p>
                                    <p class="card-text"><strong>Người đăng:</strong> <?= htmlspecialchars($doc['username']) ?></p>
                                    <p class="card-text text-info"><strong>Đánh giá:</strong> <?= $review_summary ?> (👍 <?= $doc['positive_count'] ?> / 👎 <?= $doc['negative_count'] ?>)</p>

                                    <?php if (!empty($doc['description'])): ?>
                                        <p class="card-text"><strong>Mô tả:</strong> <?= nl2br(htmlspecialchars($doc['description'])) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer small text-muted">
                                    Đăng ngày: <?= date("d/m/Y H:i", strtotime($doc['upload_date'])) ?>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>