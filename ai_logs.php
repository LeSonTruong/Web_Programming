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

// ====== KIỂM TRA QUYỀN ADMIN ======
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo '<div class="container my-5">
            <div class="alert alert-danger text-center">
                ❌ Bạn không có quyền truy cập trang này!
            </div>
          </div>';
    include 'includes/footer.php';
    exit();
}

// ====== XỬ LÝ BỘ LỌC ======
$filters = [];
$params = [];

if (!empty($_GET['status'])) {
    $filters[] = "ai_logs.status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['action'])) {
    $filters[] = "ai_logs.action = ?";
    $params[] = $_GET['action'];
}

if (!empty($_GET['doc_id'])) {
    $filters[] = "ai_logs.doc_id = ?";
    $params[] = (int)$_GET['doc_id'];
}

if (!empty($_GET['q'])) {
    $filters[] = "ai_logs.message LIKE ?";
    $params[] = "%" . $_GET['q'] . "%";
}

$where = $filters ? "WHERE " . implode(" AND ", $filters) : "";

// ====== LẤY LOGS ======
$stmt = $conn->prepare("
    SELECT ai_logs.*, documents.title 
    FROM ai_logs 
    LEFT JOIN documents ON ai_logs.doc_id = documents.doc_id 
    $where
    ORDER BY ai_logs.created_at DESC
    LIMIT 200
");
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<div class="container my-4">
    <h2 class="mb-4">📜 Nhật ký AI (AI Logs)</h2>

    <!-- Bộ lọc -->
    <form class="row g-3 mb-4" method="get">
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">-- Trạng thái --</option>
                <option value="success" <?= ($_GET['status'] ?? '') == 'success' ? 'selected' : '' ?>>Thành công</option>
                <option value="error" <?= ($_GET['status'] ?? '') == 'error' ? 'selected' : '' ?>>Thất bại</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="action" class="form-select">
                <option value="">-- Hành động --</option>
                <option value="summary" <?= ($_GET['action'] ?? '') == 'summary' ? 'selected' : '' ?>>Tóm tắt</option>
                <option value="embedding" <?= ($_GET['action'] ?? '') == 'embedding' ? 'selected' : '' ?>>Embedding</option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="number" name="doc_id" class="form-control" placeholder="Doc ID" value="<?= htmlspecialchars($_GET['doc_id'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <input type="text" name="q" class="form-control" placeholder="Tìm trong thông điệp..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100" type="submit">🔎 Lọc</button>
        </div>
    </form>

    <?php if (!$logs): ?>
        <div class="alert alert-info text-center">Không tìm thấy log nào.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Tài liệu</th>
                        <th>Hành động</th>
                        <th>Trạng thái</th>
                        <th>Thông điệp</th>
                        <th>Thời gian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= $log['id'] ?></td>
                            <td>
                                <?php if ($log['doc_id']): ?>
                                    <a href="approve.php?doc=<?= $log['doc_id'] ?>" target="_blank">
                                        <?= htmlspecialchars($log['title'] ?? 'Không rõ') ?>
                                    </a>
                                <?php else: ?>
                                    <em>Không có</em>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td>
                                <?php if ($log['status'] === 'success'): ?>
                                    <span class="badge bg-success">Thành công</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Thất bại</span>
                                <?php endif; ?>
                            </td>
                            <td><?= nl2br(htmlspecialchars($log['message'])) ?></td>
                            <td><?= $log['created_at'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>