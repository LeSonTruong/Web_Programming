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
    $filters[] = "(ai_queue.log LIKE ?)";
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
    <h2 class="mb-4">📜 Nhật ký AI</h2>

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
            <input type="text" name="q" class="form-control" placeholder="Tìm trong log..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
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
                        <th>KQ lọc</th>
                        <th>Created At</th>
                        <th>Updated At</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= $log['id'] ?></td>
                            <td>
                                <?php if ($log['document_id']): ?>
                                    <a href="document_view.php?id=<?= $log['document_id'] ?>" target="_blank">
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
                            <?php
                                if (isset($log['checkstatus']) && $log['checkstatus'] !== null && $log['checkstatus'] !== '') {
                                    $checkstatus = htmlspecialchars((string)$log['checkstatus']);
                                } else {
                                    $checkstatus = '-';
                                }
                            ?>
                            <td><?= $checkstatus ?></td>
                            <td><?= $log['created_at'] ?></td>
                            <td><?= $log['updated_at'] ?? '' ?></td>
                            <td><button class="btn btn-sm btn-outline-secondary view-log-btn" data-log-id="<?= $log['id'] ?>">Xem log</button></td>
                            <td style="display:none" id="log-content-<?= $log['id'] ?>"><?= htmlspecialchars($log['log'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
<style>
/* Simple modal for viewing log content */
.ai-log-modal { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); z-index: 1050; }
.ai-log-modal .ai-log-box { background: #fff; padding: 16px; max-width: 90%; max-height: 80%; overflow:auto; border-radius:6px; box-shadow:0 10px 30px rgba(0,0,0,0.3);} 
.ai-log-modal pre { white-space: pre-wrap; word-wrap: break-word; font-family: monospace; }
.ai-log-close { position: absolute; top:12px; right:16px; cursor:pointer; }
</style>

<div class="ai-log-modal" id="aiLogModal">
    <div class="ai-log-box">
        <button class="btn btn-sm btn-danger ai-log-close" id="aiLogClose">Đóng</button>
        <h5>Nội dung log</h5>
        <pre id="aiLogContent">(no log)</pre>
    </div>
</div>

<script>
document.addEventListener('click', function(e){
    if (e.target && e.target.classList && e.target.classList.contains('view-log-btn')){
        var id = e.target.getAttribute('data-log-id');
        var hidden = document.getElementById('log-content-' + id);
        var content = hidden ? hidden.textContent : '(no log)';
        document.getElementById('aiLogContent').textContent = content;
        document.getElementById('aiLogModal').style.display = 'flex';
    }
});
document.getElementById('aiLogClose').addEventListener('click', function(){ document.getElementById('aiLogModal').style.display='none'; });
document.getElementById('aiLogModal').addEventListener('click', function(e){ if (e.target === this) this.style.display='none'; });
</script>