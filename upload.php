<?php
include 'includes/header.php';
include 'includes/db.php';

// ====== KIỂM TRA ĐĂNG NHẬP ======
if (!isset($_SESSION['user_id'])) {
    echo '<div class="container my-5">
            <div class="alert alert-warning text-center">
                ⚠️ Tạo tài khoản hoặc đăng nhập đi bạn ÊYYYYY!
            </div>
          </div>';
    include 'includes/footer.php';
    exit();
}

// Hàm sinh summary đơn giản
function generateSummary($text)
{
    $text = strip_tags($text);
    return strlen($text) > 200 ? mb_substr($text, 0, 200) . "..." : $text;
}

// Lấy danh sách môn học
$subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll(PDO::FETCH_ASSOC);

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title        = trim($_POST['title']);
    $author_name  = trim($_POST['author_name']);
    $subject_name = trim($_POST['subject_name']);
    $department   = trim($_POST['department'] ?? '');
    $description  = trim($_POST['description']);
    $tags         = trim($_POST['tags']);
    $file         = $_FILES['document'];

    // Lấy status_id của 'pending'
    $stmt = $conn->prepare("SELECT status_id FROM document_status WHERE status_name='pending' LIMIT 1");
    $stmt->execute();
    $status = $stmt->fetch(PDO::FETCH_ASSOC);
    $status_id = $status['status_id'] ?? 1;

    // Gom môn học gần giống
    $subject_id = null;
    foreach ($subjects as $sub) {
        if (strtolower($subject_name) === strtolower($sub['subject_name'])) {
            $subject_id = $sub['subject_id'];
            $subject_name = $sub['subject_name'];
            break;
        }
    }

    if (!$subject_id) {
        $stmt = $conn->prepare("INSERT INTO subjects (subject_name, department) VALUES (?, ?)");
        try {
            $stmt->execute([$subject_name, $department]);
            $subject_id = $conn->lastInsertId();
        } catch (PDOException $e) {
            $error = "❌ Lỗi khi tạo môn học: " . $e->getMessage();
        }
    }

    // Nếu chưa có lỗi, xử lý file upload
    if (!$error) {
        $allowed_types = [
            'pdf',
            'doc',
            'docx',
            'ppt',
            'pptx',
            'jpg',
            'jpeg',
            'png',
            'gif',
            // Các file code
            'ipynb',
            'py',
            'js',
            'java',
            'c',
            'cpp',
            'html',
            'css',
            'json',
            'rb',
            'go',
            'ts'
        ];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_types)) {
            $error = "❌ Chỉ cho phép file PDF, DOC, DOCX, PPT, PPTX, hình ảnh hoặc các tệp code (.ipynb, .py, .js, ...).";
        } elseif ($file['size'] > 20 * 1024 * 1024) {
            $error = "❌ File quá lớn, tối đa 20MB.";
        } else {
            $filename = uniqid() . '.' . $ext;
            $file_path = 'uploads/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $summary = generateSummary($description);

                // Thumbnail: ảnh -> tự làm thumbnail, file khác -> icon mặc định
                $thumbnail_path = 'uploads/thumbnails/';
                if (!is_dir($thumbnail_path)) {
                    mkdir($thumbnail_path, 0777, true);
                }

                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $thumb_file = $thumbnail_path . uniqid() . '.' . $ext;
                    copy($file_path, $thumb_file); // đơn giản: copy làm thumbnail
                } else {
                    $thumb_file = "assets/icons/$ext.png";
                    if (!file_exists($thumb_file)) {
                        $thumb_file = "assets/icons/file.png";
                    }
                }

                // Document type
                $doc_type = match ($ext) {
                    'jpg', 'jpeg', 'png', 'gif' => 'image',
                    'pdf' => 'pdf',
                    'doc', 'docx' => 'doc',
                    'ppt', 'pptx' => 'ppt',
                    'ipynb', 'py', 'js', 'java', 'c', 'cpp', 'html', 'css', 'json', 'rb', 'go', 'ts' => 'code',
                    default => 'other',
                };

                $stmt = $conn->prepare("INSERT INTO documents
                (user_id, title, author_name, description, subject_id, file_path, thumbnail_path, file_size,
                 document_type, tags, summary, status_id, upload_date, updated_at, views, downloads)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 0, 0)");
                try {
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $title,
                        $author_name,
                        $description,
                        $subject_id,
                        $file_path,
                        $thumb_file,
                        $file['size'],
                        $doc_type,
                        $tags,
                        $summary,
                        $status_id
                    ]);
                    $success = "✅ Tải lên thành công, chờ admin duyệt.";
                } catch (PDOException $e) {
                    $error = "❌ Lỗi khi lưu tài liệu: " . $e->getMessage();
                }
            } else {
                $error = "❌ Tải lên thất bại!";
            }
        }
    }
}
?>

<div class="container mt-5" style="max-width: 700px;">
    <div class="card shadow-lg">
        <div class="card-body">
            <h2 class="card-title text-center mb-4">📤 Tải tài liệu lên</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">📌 Tiêu đề</label>
                    <input type="text" name="title" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">✍️ Tác giả</label>
                    <input type="text" name="author_name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">📚 Môn học</label>
                    <input type="text" name="subject_name" list="subjects-list" class="form-control" required>
                    <datalist id="subjects-list">
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= htmlspecialchars($sub['subject_name']) ?>">
                            <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="mb-3">
                    <label class="form-label">🏫 Khoa (tùy chọn)</label>
                    <input type="text" name="department" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">📝 Mô tả</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">🏷️ Tags (ngăn cách bởi dấu phẩy)</label>
                    <input type="text" name="tags" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">📂 File tài liệu</label>
                    <input type="file" name="document" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-success w-100">🚀 Tải lên</button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>