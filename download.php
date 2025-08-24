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

// Xác định MIME type qua phần mở rộng file
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime_types = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'bmp' => 'image/bmp',
    'txt' => 'text/plain',
    'csv' => 'text/csv',
    'mp3' => 'audio/mpeg',
    'mp4' => 'video/mp4',
    'py' => 'text/x-python',
    'js' => 'application/javascript',
    'html' => 'text/html',
    'css' => 'text/css',
    'json' => 'application/json',
    'xml' => 'application/xml',
    'ipynb' => 'application/x-ipynb+json',
];
$mime = $mime_types[$ext] ?? 'application/octet-stream';

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
