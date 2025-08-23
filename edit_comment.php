<?php
session_start();
include 'includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_POST['comment_id']) || empty($_POST['edit_content'])) {
    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin!']);
    exit;
}
$comment_id = (int)$_POST['comment_id'];
$content = trim($_POST['edit_content']);
$user_id = (int)$_SESSION['user_id'];

if ($content === '') {
    echo json_encode(['success' => false, 'error' => 'Nội dung rỗng!']);
    exit;
}
// Kiểm tra quyền sửa
$stmt = $conn->prepare("SELECT user_id FROM comments WHERE comment_id=?");
$stmt->execute([$comment_id]);
$row = $stmt->fetch();
if (!$row || $row['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'error' => 'Không có quyền!']);
    exit;
}
// Cập nhật nội dung và thời gian chỉnh sửa
$upStmt = $conn->prepare("UPDATE comments SET content=?, edited_at=NOW() WHERE comment_id=?");
$upStmt->execute([$content, $comment_id]);
if ($upStmt->rowCount() > 0) {
    echo json_encode([
        'success' => true,
        'content' => nl2br(htmlspecialchars($content)),
        'edited' => true
    ]);
} else {
    // Nếu nội dung không đổi, vẫn trả về thành công
    echo json_encode([
        'success' => true,
        'content' => nl2br(htmlspecialchars($content)),
        'edited' => false
    ]);
}
