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
    // Kiểm tra react tồn tại
    $stmt = $conn->prepare("SELECT react FROM comment_reacts WHERE comment_id=? AND user_id=? LIMIT 1");
    $stmt->execute([$comment_id, $user_id]);
    $existing = $stmt->fetchColumn();
    if ($existing !== false) {
        if ((int)$existing === 1) {
            // Nếu đã like thì xóa (undo)
            $delStmt = $conn->prepare("DELETE FROM comment_reacts WHERE comment_id=? AND user_id=?");
            $delStmt->execute([$comment_id, $user_id]);
            $action = 'unlike';
        } else {
            // Nếu trước đó là dislike, chuyển thành like
            $updStmt = $conn->prepare("UPDATE comment_reacts SET react = 1 WHERE comment_id=? AND user_id=?");
            $updStmt->execute([$comment_id, $user_id]);
            $action = 'like';
        }
    } else {
        // Thêm like mới
        $insStmt = $conn->prepare("INSERT INTO comment_reacts (comment_id, user_id, react) VALUES (?, ?, 1)");
        $insStmt->execute([$comment_id, $user_id]);
        $action = 'like';
    }

    // Đếm lại số like và dislike từ comment_reacts
    $countLikeStmt = $conn->prepare("SELECT COUNT(*) FROM comment_reacts WHERE comment_id=? AND react = 1");
    $countLikeStmt->execute([$comment_id]);
    $like_count = (int)$countLikeStmt->fetchColumn();
    $countDislikeStmt = $conn->prepare("SELECT COUNT(*) FROM comment_reacts WHERE comment_id=? AND react = 0");
    $countDislikeStmt->execute([$comment_id]);
    $dislike_count = (int)$countDislikeStmt->fetchColumn();
    echo json_encode(['action' => $action, 'like_count' => $like_count, 'dislike_count' => $dislike_count, 'type' => 'like']);
    exit;
} elseif ($type === 'dislike') {
    // Kiểm tra phản ứng tồn tại
    $stmt = $conn->prepare("SELECT react FROM comment_reacts WHERE comment_id=? AND user_id=? LIMIT 1");
    $stmt->execute([$comment_id, $user_id]);
    $existing = $stmt->fetchColumn();
    if ($existing !== false) {
        if ((int)$existing === 0) {
            // Nếu đã dislike thì xóa (undo)
            $delStmt = $conn->prepare("DELETE FROM comment_reacts WHERE comment_id=? AND user_id=?");
            $delStmt->execute([$comment_id, $user_id]);
            $action = 'undislike';
        } else {
            // Nếu trước đó là like, chuyển thành dislike
            $updStmt = $conn->prepare("UPDATE comment_reacts SET react = 0 WHERE comment_id=? AND user_id=?");
            $updStmt->execute([$comment_id, $user_id]);
            $action = 'dislike';
        }
    } else {
        // Thêm dislike mới
        $insStmt = $conn->prepare("INSERT INTO comment_reacts (comment_id, user_id, react) VALUES (?, ?, 0)");
        $insStmt->execute([$comment_id, $user_id]);
        $action = 'dislike';
    }

    // Đếm lại số like và dislike từ comment_reacts
    $countLikeStmt = $conn->prepare("SELECT COUNT(*) FROM comment_reacts WHERE comment_id=? AND react = 1");
    $countLikeStmt->execute([$comment_id]);
    $like_count = (int)$countLikeStmt->fetchColumn();
    $countDislikeStmt = $conn->prepare("SELECT COUNT(*) FROM comment_reacts WHERE comment_id=? AND react = 0");
    $countDislikeStmt->execute([$comment_id]);
    $dislike_count = (int)$countDislikeStmt->fetchColumn();
    echo json_encode(['action' => $action, 'like_count' => $like_count, 'dislike_count' => $dislike_count, 'type' => 'dislike']);
    exit;
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Loại hành động không hợp lệ!']);
    exit;
}