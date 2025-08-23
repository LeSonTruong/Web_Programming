<?php
session_start();
include 'includes/db.php';

// ====== KIỂM TRA ĐĂNG NHẬP ======
if (!isset($_SESSION['user_id'])) {
    echo '<div class="container my-5">
            <div class="alert alert-warning text-center">
                ⚠️ Bạn cần <a href="login.php">đăng nhập</a> để tải tài liệu!
            </div>
          </div>';
    exit();
}

$user_id = $_SESSION['user_id'];
$doc_id = $_GET['id'] ?? 0;

// ====== LẤY FILE ======
$stmt = $conn->prepare("SELECT doc_id, file_path, title FROM documents WHERE doc_id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

if (!$doc) {
    echo "<div class='container my-5 alert alert-danger'>❌ Tài liệu không tồn tại!</div>";
    exit();
}

// ====== GHI LỊCH SỬ TẢI ======
$insert = $conn->prepare("INSERT INTO downloads (doc_id, user_id) VALUES (?, ?)");
$insert->execute([$doc_id, $user_id]);

// ====== XỬ LÝ TẢI FILE ======
$file = $doc['file_path'];
if (!file_exists($file)) {
    echo "<div class='container my-5 alert alert-danger'>❌ File không tồn tại trên server!</div>";
    exit();
}

// Lấy MIME type tự động
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file);
finfo_close($finfo);

// Gửi header để trình duyệt hiện pop-up lưu file
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file));

// Đẩy file ra trình duyệt
readfile($file);
exit();
