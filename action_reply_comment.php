<?php
session_start();
include 'includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_POST['parent_comment_id']) || empty($_POST['reply_content'])) {
    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin!']);
    exit;
}
$parent_comment_id = (int)$_POST['parent_comment_id'];
$content = trim($_POST['reply_content']);
$user_id = (int)$_SESSION['user_id'];
$doc_id = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;

if ($content === '') {
    echo json_encode(['success' => false, 'error' => 'Nội dung rỗng!']);
    exit;
}

// Lấy doc_id từ parent comment nếu chưa có
if (!$doc_id) {
    $stmt = $conn->prepare("SELECT doc_id FROM comments WHERE comment_id=?");
    $stmt->execute([$parent_comment_id]);
    $doc_id = (int)$stmt->fetchColumn();
}

$stmt = $conn->prepare("INSERT INTO comments (doc_id, user_id, content, parent_comment_id, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->execute([$doc_id, $user_id, $content, $parent_comment_id]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Không thể gửi phản hồi!']);
}
