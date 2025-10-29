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

// Kiểm tra xem có bản sửa nào đang chờ duyệt chưa — lấy edit_id gần nhất nếu có
$pendingStmt = $conn->prepare("SELECT edit_id FROM document_edits WHERE doc_id = ? AND status = 'pending' ORDER BY updated_at DESC LIMIT 1");
$pendingStmt->execute([$doc_id]);
$pending_row = $pendingStmt->fetch(PDO::FETCH_ASSOC);
$has_pending_edit = !empty($pending_row);
$pending_edit_id = $has_pending_edit ? $pending_row['edit_id'] : null;

// Lấy danh sách môn học để hiển thị dropdown
$subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll(PDO::FETCH_ASSOC);

// Xử lý form khi submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $author_name = $_POST['author_name'] ?? ($doc['author_name'] ?? '');
    $description = $_POST['description'] ?? '';
    $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : ($doc['subject_id'] ?? null);

    // Xử lý upload file nếu có. Lưu file mới (nếu upload thành công) nhưng KHÔNG ghi đè / xóa file gốc —
    // thay vào đó sẽ chèn một hàng vào `document_edits` để admin duyệt trước khi cập nhật bản chính.
    $new_file_path = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        $filename = time() . '_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            // Không xóa file gốc ở đây — chờ admin duyệt.
            $new_file_path = $targetFile;
        } else {
            // Nếu upload thất bại, giữ new_file_path là null và thông báo cho người dùng
            echo '<div class="alert alert-warning">⚠️ Không thể tải lên file mới; thay đổi file sẽ không được lưu.</div>';
        }
    }

    // Nếu người dùng không tải file mới, kế thừa các giá trị file từ bản gốc
    $original_file_path = $doc['file_path'] ?? null;
    $original_file_size = $doc['file_size'] ?? null;
    $original_document_type = $doc['document_type'] ?? null;

    $final_file_path = $new_file_path ?? $original_file_path;
    // file_size: nếu có file upload mới, lấy kích thước từ $_FILES; nếu không, dùng giá trị gốc
    $final_file_size = null;
    if (!empty($new_file_path) && isset($_FILES['file']) && isset($_FILES['file']['size'])) {
        $final_file_size = (int)$_FILES['file']['size'];
    } else {
        $final_file_size = $original_file_size;
    }

    // document_type: sử dụng cùng luật phân loại như upload.php
    $final_document_type = null;
    if (!empty($new_file_path)) {
        $ext = strtolower(pathinfo($new_file_path, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'jpg': case 'jpeg': case 'png': case 'gif':
                $final_document_type = 'image';
                break;
            case 'pdf':
                $final_document_type = 'pdf';
                break;
            case 'ipynb': case 'py': case 'js': case 'java': case 'c': case 'cpp': case 'html': case 'css': case 'json': case 'rb': case 'go': case 'ts':
                $final_document_type = 'code';
                break;
            default:
                $final_document_type = 'other';
                break;
        }
    } else {
        $final_document_type = $original_document_type;
    }

    // Nếu đang có bản sửa chờ duyệt và người gửi không phải admin thì không cho gửi thêm
    if ($has_pending_edit && $role !== 'admin') {
        $pending_link = $pending_edit_id ? 'document_review.php?edit_id=' . urlencode($pending_edit_id) : 'document_review.php';
        echo '<div class="alert alert-warning">⚠️ Đã có bản sửa đang chờ duyệt cho tài liệu này. Vui lòng chờ quản trị viên xử lý trước khi gửi thay đổi mới. <a href="' . htmlspecialchars($pending_link) . '">Xem thay đổi đang chờ</a></div>';
    } else {
        // Kiểm tra có thực sự có khác biệt so với bản gốc không
        $fields_changed = [];
        if (trim((string)$title) !== trim((string)($doc['title'] ?? ''))) $fields_changed[] = 'title';
        if (trim((string)$author_name) !== trim((string)($doc['author_name'] ?? ''))) $fields_changed[] = 'author_name';
        if (trim((string)$description) !== trim((string)($doc['description'] ?? ''))) $fields_changed[] = 'description';
        if ((int)$subject_id !== (int)($doc['subject_id'] ?? 0)) $fields_changed[] = 'subject_id';
        // Với file: chỉ coi là thay đổi nếu người dùng upload file mới
        if ($new_file_path !== null) {
            $fields_changed[] = 'file_path';
            if ((int)$final_file_size !== (int)($original_file_size ?? 0)) $fields_changed[] = 'file_size';
            if (($final_document_type ?? '') !== ($original_document_type ?? '')) $fields_changed[] = 'document_type';
        }

        if (empty($fields_changed)) {
            echo '<div class="alert alert-info">⚠️ Không phát hiện thay đổi nào so với nội dung hiện tại.</div>';
        } else {
            // Nếu người sửa là admin thì ghi đè luôn vào documents (bỏ qua chờ duyệt)
            if ($role === 'admin') {
                try {
                    $up = $conn->prepare(
                        "UPDATE documents SET title = ?, author_name = ?, description = ?, subject_id = ?, file_path = ?, file_size = ?, document_type = ?, updated_at = NOW() WHERE doc_id = ?"
                    );
                    $up->execute([
                        $title,
                        $author_name,
                        $description,
                        $subject_id,
                        $final_file_path,
                        $final_file_size,
                        $final_document_type,
                        $doc_id,
                    ]);
                    echo '<div class="alert alert-success">✅ Thay đổi đã được lưu lại (admin).</div>';
                } catch (Exception $e) {
                    echo '<div class="alert alert-danger">❌ Lỗi khi cập nhật tài liệu: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            } elseif (isset($doc['status_id']) && (int)$doc['status_id'] === 1) {
                // Nếu status_id của tài liệu là 1 thì ghi thẳng vào documents luôn
                try {
                    $up = $conn->prepare(
                        "UPDATE documents SET title = ?, author_name = ?, description = ?, subject_id = ?, file_path = ?, file_size = ?, document_type = ?, updated_at = NOW() WHERE doc_id = ?"
                    );
                    $up->execute([
                        $title,
                        $author_name,
                        $description,
                        $subject_id,
                        $final_file_path,
                        $final_file_size,
                        $final_document_type,
                        $doc_id,
                    ]);
                    echo '<div class="alert alert-success">✅ Thay đổi đã được lưu lại.</div>';
                } catch (Exception $e) {
                    echo '<div class="alert alert-danger">❌ Lỗi khi cập nhật tài liệu: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            } else {
                // Thay vì cập nhật trực tiếp vào `documents`, chèn một hàng vào `document_edits`
                try {
                    $stmt = $conn->prepare(
                        "INSERT INTO document_edits (doc_id, title, author_name, description, subject_id, file_path, file_size, document_type, status, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
                    );
                    $stmt->execute([
                        $doc_id,
                        $title,
                        $author_name,
                        $description,
                        $subject_id,
                        $final_file_path,
                        $final_file_size,
                        $final_document_type,
                    ]);

                    $new_edit_id = $conn->lastInsertId();
                    $link = 'document_review.php?edit_id=' . urlencode($new_edit_id);
                    echo '<div class="alert alert-success">✅ Thay đổi đã được gửi để chờ duyệt bởi admin. <a href="' . htmlspecialchars($link) . '">Xem thay đổi</a></div>';
                } catch (Exception $e) {
                    // Nếu chèn thất bại, báo lỗi (vẫn không ghi đè bản chính)
                    echo '<div class="alert alert-danger">❌ Lỗi khi lưu sửa đổi: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }

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

    <?php if ($has_pending_edit): ?>
        <?php $pending_link_ui = $pending_edit_id ? 'document_review.php?edit_id=' . urlencode($pending_edit_id) : 'document_review.php'; ?>
        <div class="alert alert-warning">⚠️ Hiện có một bản sửa đang chờ duyệt cho tài liệu này. Bạn không thể gửi thêm sửa đổi cho đến khi bản sửa đó được xử lý. <a href="<?= htmlspecialchars($pending_link_ui) ?>">Xem thay đổi đang chờ</a></div>
    <?php else: ?>
    <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="title" class="form-label">Tiêu đề</label>
            <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($doc['title']) ?>" required>
        </div>

        <div class="mb-3">
            <label for="author_name" class="form-label">Tác giả</label>
            <input type="text" class="form-control" id="author_name" name="author_name" value="<?= htmlspecialchars($doc['author_name'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Mô tả</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($doc['description']) ?></textarea>
        </div>

            <div class="mb-3">
                <label for="subject_id" class="form-label">Môn học</label>
                <select class="form-select" id="subject_id" name="subject_id" required>
                    <?php foreach ($subjects as $sub): ?>
                        <option value="<?= $sub['subject_id'] ?>" <?= ($doc['subject_id'] == $sub['subject_id']) ? 'selected' : '' ?>><?= htmlspecialchars($sub['subject_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

        <div class="mb-3">
            <label for="file" class="form-label">Thay file (nếu muốn)</label>
            <input type="file" class="form-control" id="file" name="file" accept=".pdf">
        </div>

        <button type="submit" class="btn btn-primary">Cập nhật</button>
        <a href="my_documents.php" class="btn btn-secondary">Hủy</a>
    </form>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>