<?php
session_start();
include 'includes/db.php';


if (!isset($_SESSION['user_id']) || !isset($_POST['comment_id']) || !isset($_POST['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu thông tin!']);
    exit;
}
$comment_id = (int)$_POST['comment_id'];
$user_id = (int)$_SESSION['user_id'];
$type = $_POST['type']; // 'like' hoặc 'dislike'


if ($type === 'like') {
    // Kiểm tra đã like chưa
    $stmt = $conn->prepare("SELECT * FROM comment_likes WHERE comment_id=? AND user_id=?");
    $stmt->execute([$comment_id, $user_id]);
    if ($stmt->rowCount() > 0) {
        // Nếu đã like thì xóa like
        $delStmt = $conn->prepare("DELETE FROM comment_likes WHERE comment_id=? AND user_id=?");
        $delStmt->execute([$comment_id, $user_id]);
        $action = 'unlike';
    } else {
        // Nếu chưa thì thêm like
        $insStmt = $conn->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
        $insStmt->execute([$comment_id, $user_id]);
        $action = 'like';
        // Nếu đang dislike thì xóa dislike
        $delDislike = $conn->prepare("DELETE FROM comment_dislikes WHERE comment_id=? AND user_id=?");
        $delDislike->execute([$comment_id, $user_id]);
    }
    // Đếm lại số like và dislike
    $countLikeStmt = $conn->prepare("SELECT COUNT(*) FROM comment_likes WHERE comment_id=?");
    $countLikeStmt->execute([$comment_id]);
    $like_count = (int)$countLikeStmt->fetchColumn();
    $countDislikeStmt = $conn->prepare("SELECT COUNT(*) FROM comment_dislikes WHERE comment_id=?");
    $countDislikeStmt->execute([$comment_id]);
    $dislike_count = (int)$countDislikeStmt->fetchColumn();
    echo json_encode(['action' => $action, 'like_count' => $like_count, 'dislike_count' => $dislike_count, 'type' => 'like']);
    exit;
} elseif ($type === 'dislike') {
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
        // Nếu đang like thì xóa like
        $delLike = $conn->prepare("DELETE FROM comment_likes WHERE comment_id=? AND user_id=?");
        $delLike->execute([$comment_id, $user_id]);
    }
    // Đếm lại số like và dislike
    $countLikeStmt = $conn->prepare("SELECT COUNT(*) FROM comment_likes WHERE comment_id=?");
    $countLikeStmt->execute([$comment_id]);
    $like_count = (int)$countLikeStmt->fetchColumn();
    $countDislikeStmt = $conn->prepare("SELECT COUNT(*) FROM comment_dislikes WHERE comment_id=?");
    $countDislikeStmt->execute([$comment_id]);
    $dislike_count = (int)$countDislikeStmt->fetchColumn();
    echo json_encode(['action' => $action, 'like_count' => $like_count, 'dislike_count' => $dislike_count, 'type' => 'dislike']);
    exit;
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Loại hành động không hợp lệ!']);
    exit;
}
