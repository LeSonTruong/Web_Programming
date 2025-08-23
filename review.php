<?php
session_start();
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
$doc_id = $_POST['doc_id'] ?? 0;
$review_type = $_POST['review_type'] ?? '';

if ($doc_id && in_array($review_type, ['positive', 'negative'])) {
    // Thêm hoặc cập nhật đánh giá
    $stmt = $conn->prepare("
        INSERT INTO reviews (user_id, doc_id, review_type)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE review_type = VALUES(review_type), created_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$user_id, $doc_id, $review_type]);
}

header("Location: document_detail.php?id=" . $doc_id);
exit;
