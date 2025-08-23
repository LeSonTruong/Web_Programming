<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['comment_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu thông tin!']);
    exit;
}
$comment_id = (int)$_POST['comment_id'];
$user_id = (int)$_SESSION['user_id'];

// Kiểm tra đã dislike chưa
$stmt = $conn->prepare("SELECT * FROM comment_dislikes WHERE comment_id=? AND user_id=?");
$stmt->execute([$comment_id, $user_id]);
if ($stmt->rowCount() > 0) {
    // Nếu đã dislike thì xóa dislike
    $delStmt = $conn->prepare("DELETE FROM comment_dislikes WHERE comment_id=? AND user_id=?");
    $delStmt->execute([$comment_id, $user_id]);
    $action = 'undislike';
} else {
    // Nếu chưa thì thêm dislike
    $insStmt = $conn->prepare("INSERT INTO comment_dislikes (comment_id, user_id) VALUES (?, ?)");
    $insStmt->execute([$comment_id, $user_id]);
    $action = 'dislike';
}
// Đếm lại số dislike
$countStmt = $conn->prepare("SELECT COUNT(*) FROM comment_dislikes WHERE comment_id=?");
$countStmt->execute([$comment_id]);
$count = (int)$countStmt->fetchColumn();

echo json_encode(['action' => $action, 'count' => $count]);
