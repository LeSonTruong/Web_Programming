<?php
include 'includes/db.php';

session_start();

// ====== KIỂM TRA QUYỀN ======
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    $reason = '';
    include __DIR__ . '/!403.php';
    exit();
}

include 'includes/header.php';

// ====== XỬ LÝ BỘ LỌC ======
$filters = [];
$params = [];

if (!empty($_GET['status'])) {
    $filters[] = "ai_queue.status = ?";
    $params[] = $_GET['status'];
}

// document_id filter
if (!empty($_GET['doc_id'])) {
    $filters[] = "ai_queue.document_id = ?";
    $params[] = (int)$_GET['doc_id'];
}

// Search in summary or log
if (!empty($_GET['q'])) {
    $filters[] = "(ai_queue.summary LIKE ? OR ai_queue.log LIKE ?)";
    $params[] = "%" . $_GET['q'] . "%";
    $params[] = "%" . $_GET['q'] . "%";
}

$where = $filters ? "WHERE " . implode(" AND ", $filters) : "";

// ====== LẤY DỮ LIỆU TỪ ai_queue ======
$stmt = $conn->prepare("
    SELECT ai_queue.*, documents.title
    FROM ai_queue
    LEFT JOIN documents ON ai_queue.document_id = documents.doc_id
    $where
    ORDER BY ai_queue.created_at DESC
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
                <option value="pending" <?= ($_GET['status'] ?? '') == 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="processing" <?= ($_GET['status'] ?? '') == 'processing' ? 'selected' : '' ?>>Processing</option>
                <option value="done" <?= ($_GET['status'] ?? '') == 'done' ? 'selected' : '' ?>>Done</option>
                <option value="failed" <?= ($_GET['status'] ?? '') == 'failed' ? 'selected' : '' ?>>Failed</option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="number" name="doc_id" class="form-control" placeholder="Doc ID" value="<?= htmlspecialchars($_GET['doc_id'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <input type="text" name="q" class="form-control" placeholder="Tìm trong summary/log..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
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
                        <th>Trạng thái</th>
                        <th>Log</th>
                        <th>Created At</th>
                        <th>Updated At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= $log['id'] ?></td>
                            <td>
                                <?php if ($log['document_id']): ?>
                                    <a href="approve.php?doc=<?= $log['document_id'] ?>" target="_blank">
                                        <?= htmlspecialchars($log['title'] ?? 'Không rõ') ?>
                                    </a>
                                <?php else: ?>
                                    <em>Không có</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['status'] === 'pending'): ?>
                                    <span class="badge bg-secondary">Pending</span>
                                <?php elseif ($log['status'] === 'processing'): ?>
                                    <span class="badge bg-primary">Processing</span>
                                <?php elseif ($log['status'] === 'done'): ?>
                                    <span class="badge bg-success">Done</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><?= htmlspecialchars($log['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= nl2br(htmlspecialchars($log['log'] ?? '')) ?></td>
                            <td><?= $log['created_at'] ?></td>
                            <td><?= $log['updated_at'] ?? '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>