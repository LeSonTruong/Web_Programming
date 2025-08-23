<?php
include 'includes/header.php';
include 'includes/db.php';

// Lấy ID tài liệu
$doc_id = $_GET['id'] ?? 0;

// Lấy chi tiết tài liệu + tên môn học + thống kê đánh giá
$stmt = $conn->prepare("
    SELECT d.*, u.username, s.subject_name,
        SUM(CASE WHEN r.review_type = 'positive' THEN 1 ELSE 0 END) AS positive_count,
        SUM(CASE WHEN r.review_type = 'negative' THEN 1 ELSE 0 END) AS negative_count
    FROM documents d
    JOIN users u ON d.user_id = u.user_id
    LEFT JOIN subjects s ON d.subject_id = s.subject_id
    LEFT JOIN reviews r ON d.doc_id = r.doc_id
    WHERE d.doc_id = ?
    GROUP BY d.doc_id
");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

if (!$doc) {
    echo "<div class='container my-5 alert alert-danger'>❌ Không tìm thấy tài liệu!</div>";
    include 'includes/footer.php';
    exit;
}

// Tính tổng đánh giá
$total_reviews = ($doc['positive_count'] ?? 0) + ($doc['negative_count'] ?? 0);
if ($total_reviews > 0) {
    $ratio = ($doc['positive_count'] ?? 0) / $total_reviews;
    $review_summary = $ratio >= 0.7 ? "Đánh giá tích cực" : ($ratio >= 0.4 ? "Đánh giá trung bình" : "Đánh giá tiêu cực");
} else {
    $review_summary = "Chưa có đánh giá";
}

// Đếm lượt tải
$countStmt = $conn->prepare("SELECT COUNT(*) AS total_downloads FROM downloads WHERE doc_id = ?");
$countStmt->execute([$doc['doc_id']]);
$downloadData = $countStmt->fetch();
$total_downloads = $downloadData['total_downloads'] ?? 0;

// Xác định loại file
$file = $doc['file_path'] ?? '';
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
?>

<div class="container my-4">
    <h2><?= htmlspecialchars($doc['title'] ?? '') ?></h2>
    <p><strong>Môn học:</strong> <?= htmlspecialchars($doc['subject_name'] ?? '') ?></p>
    <p><strong>Người đăng:</strong> <?= htmlspecialchars($doc['username'] ?? '') ?></p>
    <p><strong>Mô tả:</strong> <?= nl2br(htmlspecialchars($doc['description'] ?? '')) ?></p>

    <p><strong>Đánh giá:</strong> <?= $review_summary ?>
        (👍 <?= $doc['positive_count'] ?? 0 ?> | 👎 <?= $doc['negative_count'] ?? 0 ?>)
    </p>

    <!-- Xem file trực tiếp -->
    <div class="file-viewer my-3">
        <?php if (in_array($ext, ['pdf'])): ?>
            <embed src="<?= htmlspecialchars($file) ?>" type="application/pdf" width="100%" height="600px" />
        <?php elseif (in_array($ext, ['doc', 'docx'])): ?>
            <iframe src="https://view.officeapps.live.com/op/embed.aspx?src=<?= urlencode('https://yourdomain.com/' . $file) ?>" width="100%" height="600px" frameborder="0"></iframe>
        <?php elseif (in_array($ext, ['py', 'js', 'cpp', 'c', 'java', 'ipynb'])): ?>
            <iframe src="code_viewer.php?file=<?= urlencode($file) ?>" width="100%" height="600px" frameborder="0"></iframe>
        <?php elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
            <img src="<?= htmlspecialchars($file) ?>" class="img-fluid" alt="Image document" />
        <?php else: ?>
            <p>📄 File không thể xem trực tiếp. Bạn có thể tải xuống để mở.</p>
        <?php endif; ?>
    </div>

    <hr>
    <p><strong>Lượt tải:</strong> <?= $total_downloads ?></p>

    <!-- Nút tải xuống thực sự -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="download.php?id=<?= $doc['doc_id'] ?? 0 ?>" class="btn btn-primary">📥 Tải xuống</a>
    <?php else: ?>
        <div class="alert alert-warning">
            ⚠️ Bạn cần <a href="register.php">tạo tài khoản</a> hoặc <a href="login.php">đăng nhập</a> để tải tài liệu này.
        </div>
    <?php endif; ?>

    <!-- Form đánh giá -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <form method="post" action="review.php" class="mt-3">
            <input type="hidden" name="doc_id" value="<?= $doc['doc_id'] ?? 0 ?>">
            <button type="submit" name="review_type" value="positive" class="btn btn-success">👍 Đánh giá tích cực</button>
            <button type="submit" name="review_type" value="negative" class="btn btn-danger">👎 Đánh giá tiêu cực</button>
        </form>
    <?php else: ?>
        <div class="alert alert-warning mt-3">⚠️ Bạn cần <a href="register.php">tạo tài khoản</a> hoặc <a href="login.php">đăng nhập</a> để đánh giá.</div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>