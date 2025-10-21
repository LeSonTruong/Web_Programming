<?php
session_start();
require_once "includes/db.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $reason = 'chuadangnhap';
    include __DIR__ . '/!403.php';
    exit();
}

include 'includes/header.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$doc_id = isset($_GET['doc_id']) ? (int)$_GET['doc_id'] : 0;

// Lấy thông tin tài liệu
if ($role === 'admin') {
    // Admin có thể sửa tất cả tài liệu
    $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
    $stmt->execute([$doc_id]);
} else {
    // User chỉ sửa tài liệu của chính mình
    $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=? AND user_id=?");
    $stmt->execute([$doc_id, $user_id]);
}

$doc = $stmt->fetch();

if (!$doc) {
    echo '<div class="alert alert-danger">❌ Không tìm thấy tài liệu hoặc bạn không có quyền sửa.</div>';
    include 'includes/footer.php';
    exit();
}

// Xử lý form khi submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';

    // Xử lý upload file nếu có
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        $filename = time() . '_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            // Xóa file cũ nếu tồn tại
            if (file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
            $file_path = $targetFile;
        } else {
            $file_path = $doc['file_path'];
            echo '<div class="alert alert-warning">⚠️ Không thể tải lên file mới, giữ file cũ.</div>';
        }
    } else {
        $file_path = $doc['file_path'];
    }

    // Cập nhật cơ sở dữ liệu
    $stmt = $conn->prepare("UPDATE documents SET title=?, description=?, file_path=?, status_id=0 WHERE doc_id=?");
    $stmt->execute([$title, $description, $file_path, $doc_id]);

    echo '<div class="alert alert-success">✅ Tài liệu đã được cập nhật và gửi lại để duyệt.</div>';

    // Lấy lại dữ liệu mới
    if ($role === 'admin') {
        $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
        $stmt->execute([$doc_id]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=? AND user_id=?");
        $stmt->execute([$doc_id, $user_id]);
    }
    $doc = $stmt->fetch();
}
?>

<div class="container my-4">
    <h2 class="mb-4">✏️ Sửa tài liệu</h2>

    <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="title" class="form-label">Tiêu đề</label>
            <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($doc['title']) ?>" required>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Mô tả</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($doc['description']) ?></textarea>
        </div>

        <div class="mb-3">
            <label for="file" class="form-label">Thay file (nếu muốn)</label>
            <input type="file" class="form-control" id="file" name="file" accept=".pdf,.doc,.docx">
            <small class="text-muted">File hiện tại: <?= htmlspecialchars(basename($doc['file_path'])) ?></small>
        </div>

        <button type="submit" class="btn btn-primary">Cập nhật</button>
        <a href="my_documents.php" class="btn btn-secondary">Hủy</a>
    </form>
</div>

<?php include 'includes/footer.php'; ?>