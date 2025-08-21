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

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Hàm sinh summary đơn giản
function generateSummary($text)
{
    $text = strip_tags($text);
    return strlen($text) > 200 ? mb_substr($text, 0, 200) . "..." : $text;
}

// Lấy danh sách môn học hiện có
$subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll(PDO::FETCH_ASSOC);

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $subject_name = trim($_POST['subject_name']);
    $department = trim($_POST['department'] ?? '');
    $description = trim($_POST['description']);
    $file = $_FILES['document'];

    // Lấy status_id của 'pending'
    $stmt = $conn->prepare("SELECT status_id FROM document_status WHERE status_name='pending' LIMIT 1");
    $stmt->execute();
    $status = $stmt->fetch(PDO::FETCH_ASSOC);
    $status_id = $status['status_id'] ?? 1;

    // Gom môn học gần giống
    $subject_id = null;
    $minDistance = 3;
    foreach ($subjects as $sub) {
        if (levenshtein(strtolower($subject_name), strtolower($sub['subject_name'])) <= $minDistance) {
            $subject_id = $sub['subject_id'];
            $subject_name = $sub['subject_name'];
            break;
        }
    }

    if (!$subject_id) {
        // Thêm môn học mới
        $stmt = $conn->prepare("INSERT INTO subjects (subject_name, department) VALUES (?, ?)");
        try {
            $stmt->execute([$subject_name, $department]);
            $subject_id = $conn->lastInsertId();
            $subjects[] = ['subject_id' => $subject_id, 'subject_name' => $subject_name];
        } catch (PDOException $e) {
            $error = "❌ Lỗi khi tạo môn học: " . $e->getMessage();
        }
    }

    // Nếu chưa có lỗi, tiếp tục kiểm tra file
    if (!$error) {
        $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_types)) {
            $error = "❌ Chỉ cho phép file PDF, DOC, DOCX, PPT, PPTX.";
        } elseif ($file['size'] > 20 * 1024 * 1024) {
            $error = "❌ File quá lớn, tối đa 20MB.";
        } else {
            $filename = uniqid() . '.' . $ext;
            $file_path = 'uploads/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $summary = generateSummary($description);

                $stmt = $conn->prepare("INSERT INTO documents 
                    (user_id, title, description, subject_id, file_path, summary, status_id, upload_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                try {
                    $stmt->execute([$_SESSION['user_id'], $title, $description, $subject_id, $file_path, $summary, $status_id]);
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

<div class="container mt-5" style="max-width: 600px;">
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
                    <label class="form-label">📂 File tài liệu</label>
                    <input type="file" name="document" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-success w-100">🚀 Tải lên</button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>