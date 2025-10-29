<?php
require_once __DIR__ . '/includes/header.php';
// Ensure DB connection (header may already include it when user is logged in, but require to be safe)
require_once __DIR__ . '/includes/db.php';

// Admin check
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'admin')) {
    echo '<div class="alert alert-danger">Bạn không có quyền truy cập trang này.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$error = null;
$success = null;

// Handle POST actions: add, edit, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['subject_name'] ?? '');
        $dept = trim($_POST['department'] ?? '');
        if ($name === '') {
            $error = 'Tên môn học không được để trống.';
        } else {
            $stmt = $conn->prepare('INSERT INTO subjects (subject_name, department) VALUES (?, ?)');
            try {
                $stmt->execute([$name, $dept === '' ? null : $dept]);
                $success = 'Thêm môn học thành công.';
            } catch (Exception $e) {
                $error = 'Lỗi khi thêm môn học: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['subject_id'] ?? 0);
        $name = trim($_POST['subject_name'] ?? '');
        $dept = trim($_POST['department'] ?? '');
        if ($id <= 0 || $name === '') {
            $error = 'Dữ liệu không hợp lệ.';
        } else {
            $stmt = $conn->prepare('UPDATE subjects SET subject_name = ?, department = ? WHERE subject_id = ?');
            try {
                $stmt->execute([$name, $dept === '' ? null : $dept, $id]);
                $success = 'Cập nhật môn học thành công.';
            } catch (Exception $e) {
                $error = 'Lỗi khi cập nhật: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['subject_id'] ?? 0);
        if ($id <= 0) {
            $error = 'ID không hợp lệ.';
        } else {
            $stmt = $conn->prepare('DELETE FROM subjects WHERE subject_id = ?');
            try {
                $stmt->execute([$id]);
                $success = 'Xóa môn học thành công.';
            } catch (Exception $e) {
                $error = 'Lỗi khi xóa: ' . $e->getMessage();
            }
        }
    }
}

// If editing via GET (show edit form)
$editing = false;
$edit_row = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    if ($id > 0) {
        $stmt = $conn->prepare('SELECT * FROM subjects WHERE subject_id = ?');
        $stmt->execute([$id]);
        $edit_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($edit_row) $editing = true;
    }
}

// Load subjects
$stmt = $conn->query('SELECT * FROM subjects ORDER BY subject_name');
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-12">
        <h2>Quản lý môn học</h2>
        <p class="text-muted">Trang này cho phép Admin thêm, chỉnh sửa hoặc xóa các môn học (bảng <code>subjects</code>).</p>
    </div>

    <?php if ($error): ?>
        <div class="col-12">
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="col-12">
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        </div>
    <?php endif; ?>

    <div class="col-md-4">
        <?php if ($editing && $edit_row): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Chỉnh sửa môn học</h5>
                    <form method="post" action="<?= $BASE_URL ?>/manage_subjects.php">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="subject_id" value="<?= (int)$edit_row['subject_id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Tên môn học</label>
                            <input class="form-control" name="subject_name" value="<?= htmlspecialchars($edit_row['subject_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Khoa / Bộ môn</label>
                            <input class="form-control" name="department" value="<?= htmlspecialchars($edit_row['department']) ?>">
                        </div>
                        <button class="btn btn-primary">Lưu</button>
                        <a class="btn btn-secondary" href="<?= $BASE_URL ?>/manage_subjects.php">Hủy</a>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Thêm môn học mới</h5>
                    <form method="post" action="<?= $BASE_URL ?>/manage_subjects.php">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Tên môn học</label>
                            <input class="form-control" name="subject_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Khoa / Bộ môn (tùy chọn)</label>
                            <input class="form-control" name="department">
                        </div>
                        <button class="btn btn-success">Thêm</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Danh sách môn học (<?= count($subjects) ?>)</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên môn</th>
                                <th>Khoa / Bộ môn</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $s): ?>
                                <tr>
                                    <td><?= (int)$s['subject_id'] ?></td>
                                    <td><?= htmlspecialchars($s['subject_name']) ?></td>
                                    <td><?= htmlspecialchars($s['department']) ?></td>
                                    <td style="white-space:nowrap;">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= $BASE_URL ?>/manage_subjects.php?edit=<?= (int)$s['subject_id'] ?>">Sửa</a>
                                        <form method="post" action="<?= $BASE_URL ?>/manage_subjects.php" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('Xác nhận xóa môn học này?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="subject_id" value="<?= (int)$s['subject_id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';

?>