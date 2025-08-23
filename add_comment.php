<?php
session_start();
include 'includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_POST['comment_content']) || empty($_POST['doc_id'])) {
    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin!']);
    exit;
}
$doc_id = (int)$_POST['doc_id'];
$content = trim($_POST['comment_content']);
$user_id = (int)$_SESSION['user_id'];

if ($content === '') {
    echo json_encode(['success' => false, 'error' => 'Nội dung rỗng!']);
    exit;
}
$stmt = $conn->prepare("INSERT INTO comments (doc_id, user_id, content) VALUES (?, ?, ?)");
$stmt->execute([$doc_id, $user_id, $content]);
$comment_id = $conn->lastInsertId();

// Lấy thông tin user
$userStmt = $conn->prepare("SELECT username FROM users WHERE user_id=?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch();

// Trả về dữ liệu bình luận mới
if ($comment_id) {
    echo json_encode([
        'success' => true,
        'comment_id' => $comment_id,
        'username' => $user['username'],
        'content' => nl2br(htmlspecialchars($content)),
        'created_at' => date('H:i d/m/Y'),
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Không thể thêm bình luận!']);
}
