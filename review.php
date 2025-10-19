<?php
session_start();
include 'includes/db.php';

// ====== KIỂM TRA QUYỀN ======
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $reason = 'chuadangnhap';
    include __DIR__ . '/!403.php';
    exit();
}

$user_id = $_SESSION['user_id'];
$doc_id = $_POST['doc_id'] ?? 0;
$review_type = $_POST['review_type'] ?? '';


if ($doc_id && in_array($review_type, ['positive', 'negative', 'none'])) {
    if ($review_type === 'none') {
        // Xóa review của user
        $stmt = $conn->prepare("DELETE FROM reviews WHERE user_id = ? AND doc_id = ?");
        $stmt->execute([$user_id, $doc_id]);
    } else {
        // Thêm hoặc cập nhật đánh giá
        $stmt = $conn->prepare("
            INSERT INTO reviews (user_id, doc_id, review_type)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE review_type = VALUES(review_type), created_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$user_id, $doc_id, $review_type]);
    }

    // Kiểm tra nếu là AJAX request
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($is_ajax) {
        // Đếm lại số lượt thích/dislike
        $stmt = $conn->prepare("SELECT COUNT(*) FROM reviews WHERE doc_id = ? AND review_type = 'positive'");
        $stmt->execute([$doc_id]);
        $positive_count = $stmt->fetchColumn();
        $stmt = $conn->prepare("SELECT COUNT(*) FROM reviews WHERE doc_id = ? AND review_type = 'negative'");
        $stmt->execute([$doc_id]);
        $negative_count = $stmt->fetchColumn();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'positive_count' => (int)$positive_count,
            'negative_count' => (int)$negative_count
        ]);
        exit;
    }
}

header("Location: document_detail.php?id=" . $doc_id);
exit;
