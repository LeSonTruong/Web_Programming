<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

// Bắt đầu session (phòng khi header chưa làm)
if (session_status() === PHP_SESSION_NONE) session_start();

// Kiểm tra quyền admin
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'admin')) {
    echo '<div class="alert alert-danger">Bạn không có quyền truy cập trang này.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$error = null;
$success = null;

// Xử lý form POST (thêm, sửa, xóa)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $name = trim($_POST['subject_name'] ?? '');
            $dept = trim($_POST['department'] ?? '');
            if ($name === '') throw new Exception('Tên môn học không được để trống.');

            $stmt = $conn->prepare('INSERT INTO subjects (subject_name, department) VALUES (?, ?)');
            $stmt->execute([$name, $dept === '' ? null : $dept]);
            header("Location: manage_subjects.php?success=added");
            exit;
        } elseif ($action === 'edit') {
            $id = intval($_POST['subject_id'] ?? 0);
            $name = trim($_POST['subject_name'] ?? '');
            $dept = trim($_POST['department'] ?? '');
            if ($id <= 0 || $name === '') throw new Exception('Dữ liệu không hợp lệ.');

            $stmt = $conn->prepare('UPDATE subjects SET subject_name = ?, department = ? WHERE subject_id = ?');
            $stmt->execute([$name, $dept === '' ? null : $dept, $id]);
            header("Location: manage_subjects.php?success=edited");
            exit;
        } elseif ($action === 'delete') {
            $id = intval($_POST['subject_id'] ?? 0);
            if ($id <= 0) throw new Exception('ID không hợp lệ.');

            $stmt = $conn->prepare('DELETE FROM subjects WHERE subject_id = ?');
            $stmt->execute([$id]);
            header("Location: manage_subjects.php?success=deleted");
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Lấy thông báo từ redirect
$success = match ($_GET['success'] ?? '') {
    'added' => 'Thêm môn học thành công.',
    'edited' => 'Cập nhật môn học thành công.',
    'deleted' => 'Xóa môn học thành công.',
    default => null
};

// Lọc & sắp xếp
$search_name = trim($_GET['search_name'] ?? '');
$search_dept = trim($_GET['search_department'] ?? '');
$sort_by = $_GET['sort_by'] ?? 'name';
$sort_dir = (isset($_GET['sort_dir']) && strtolower($_GET['sort_dir']) === 'desc') ? 'DESC' : 'ASC';
$allowed_sort = ['id' => 'subject_id', 'name' => 'subject_name'];
$order_col = $allowed_sort[$sort_by] ?? 'subject_name';

// Tạo query
$sql = 'SELECT * FROM subjects';
$where = [];
$params = [];
if ($search_name !== '') {
    $where[] = 'subject_name LIKE ?';
    $params[] = "%{$search_name}%";
}
if ($search_dept !== '') {
    $where[] = 'department LIKE ?';
    $params[] = "%{$search_dept}%";
}
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= " ORDER BY {$order_col} {$sort_dir}";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Giao diện -->
<div class="row">
    <div class="col-12">
        <h2 class="mb-3">🎓 Quản lý môn học</h2>
        <p class="text-muted">Trang quản lý cho phép Admin thêm, chỉnh sửa hoặc xóa môn học.</p>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h5 class="card-title">Thêm môn học mới</h5>
                <form method="post" action="">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Tên môn học</label>
                        <input class="form-control" name="subject_name" required value="<?= htmlspecialchars($_POST['subject_name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Khoa / Bộ môn (tùy chọn)</label>
                        <input class="form-control" name="department" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
                    </div>
                    <button class="btn btn-success w-100">➕ Thêm</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Danh sách môn học (<?= count($subjects) ?>)</h5>

                <!-- Bộ lọc -->
                <form method="get" class="row g-2 mb-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Tên</label>
                        <input name="search_name" class="form-control" value="<?= htmlspecialchars($search_name) ?>" placeholder="Tìm theo tên">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Khoa / Bộ môn</label>
                        <input name="search_department" class="form-control" value="<?= htmlspecialchars($search_dept) ?>" placeholder="Tìm theo khoa">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sắp xếp</label>
                        <select name="sort_by" class="form-select">
                            <option value="name" <?= ($sort_by === 'name') ? 'selected' : '' ?>>Tên (A → Z)</option>
                            <option value="id" <?= ($sort_by === 'id') ? 'selected' : '' ?>>ID</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Hướng</label>
                        <select name="sort_dir" class="form-select">
                            <option value="asc" <?= ($sort_dir === 'ASC') ? 'selected' : '' ?>>Tăng dần</option>
                            <option value="desc" <?= ($sort_dir === 'DESC') ? 'selected' : '' ?>>Giảm dần</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-grid">
                        <button class="btn btn-primary">Lọc</button>
                    </div>
                </form>

                <!-- Tìm kiếm tức thời -->
                <input id="filterInput" class="form-control mb-2" placeholder="🔍 Tìm nhanh trong danh sách...">

                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Tên môn</th>
                                <th>Khoa / Bộ môn</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($subjects) === 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Không có môn học nào.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subjects as $s): ?>
                                    <tr>
                                        <td><?= (int)$s['subject_id'] ?></td>
                                        <td><?= htmlspecialchars($s['subject_name']) ?></td>
                                        <td><?= htmlspecialchars($s['department']) ?></td>
                                        <td style="white-space:nowrap;">
                                            <button class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editModal"
                                                data-id="<?= (int)$s['subject_id'] ?>"
                                                data-name="<?= htmlspecialchars($s['subject_name']) ?>"
                                                data-dept="<?= htmlspecialchars($s['department']) ?>">
                                                Sửa
                                            </button>
                                            <form method="post" action="" style="display:inline-block; margin-left:6px;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="subject_id" value="<?= (int)$s['subject_id'] ?>">
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(this)">Xóa</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal chỉnh sửa -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_id" name="subject_id">
                <div class="modal-header">
                    <h5 class="modal-title">Chỉnh sửa môn học</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Tên môn học</label>
                    <input class="form-control mb-2" id="edit_name" name="subject_name" required>
                    <label class="form-label">Khoa / Bộ môn</label>
                    <input class="form-control" id="edit_dept" name="department">
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Lưu</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SweetAlert2 + JS tương tác -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', e => {
        const btn = e.relatedTarget;
        document.getElementById('edit_id').value = btn.dataset.id;
        document.getElementById('edit_name').value = btn.dataset.name;
        document.getElementById('edit_dept').value = btn.dataset.dept;
    });

    // Tìm nhanh trong bảng
    document.getElementById('filterInput').addEventListener('input', e => {
        const keyword = e.target.value.toLowerCase();
        document.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(keyword) ? '' : 'none';
        });
    });

    // Xác nhận xóa bằng SweetAlert
    function confirmDelete(btn) {
        event.preventDefault();
        Swal.fire({
            title: 'Xác nhận xóa?',
            text: 'Bạn sẽ không thể hoàn tác hành động này!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Xóa',
            cancelButtonText: 'Hủy'
        }).then(result => {
            if (result.isConfirmed) btn.closest('form').submit();
        });
    }

    // Hiển thị thông báo đẹp
    <?php if ($success): ?>
        Swal.fire({
            icon: 'success',
            title: '<?= addslashes($success) ?>',
            timer: 2000,
            showConfirmButton: false
        });
    <?php endif; ?>
    <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: '<?= addslashes($error) ?>'
        });
    <?php endif; ?>
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>