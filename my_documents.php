<?php
include 'includes/header.php';
include 'includes/db.php';

// ====== KIỂM TRA ĐĂNG NHẬP ======
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $reason = 'chuadangnhap';
    include __DIR__ . '/!403.php';
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['role'] === 'admin';
// Actions (delete/approve/reject/enqueue) are handled centrally in action_approve.php
// Display any flash messages set by action_approve.php
if (!empty($_SESSION['approve_flash'])) {
    foreach ($_SESSION['approve_flash'] as $m) {
        echo $m;
    }
    unset($_SESSION['approve_flash']);
}

// ====== ENQUEUE AI ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enqueue_ai'])) {
    $doc_id = (int)$_POST['enqueue_ai'];

    // fetch document
    $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();

    // permission check
    if (!$doc || (! $is_admin && $doc['user_id'] != $user_id)) {
        echo '<div class="alert alert-danger">⚠️ Không tìm thấy tài liệu hoặc bạn không có quyền thực hiện.</div>';
    } else {
        // 1) Check for existing pending or processing jobs
        $stmt = $conn->prepare("SELECT COUNT(*) FROM ai_queue WHERE document_id = ? AND status IN ('pending','processing')");
        $stmt->execute([$doc_id]);
        $count = (int)$stmt->fetchColumn();

        if ($count > 0) {
            echo '<div class="alert alert-warning">⚠️ Tài liệu này đã có tiến trình đang chạy (pending/processing). Không thể thêm vào hàng đợi.</div>';
        } else {
            // 2) Get the most recent ai_queue row for this document (if any)
            $stmt = $conn->prepare("SELECT * FROM ai_queue WHERE document_id = ? ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 1");
            $stmt->execute([$doc_id]);
            $last = $stmt->fetch(PDO::FETCH_ASSOC);

            $need_confirm = false;

            if ($last) {
                $last_status = $last['status'];
                // if last was 'done', check last_updated within 30 minutes
                if ($last_status === 'done') {
                    $last_time = $last['updated_at'] ?? $last['created_at'];
                    $last_ts = $last_time ? strtotime($last_time) : 0;
                    if ($last_ts >= time() - 30 * 60) {
                        $need_confirm = true;
                    }
                }
                // if last was 'failed' we allow enqueue immediately
            }

            // If confirmation is required and not yet confirmed, show prompt
            if ($need_confirm && !isset($_POST['confirm'])) {
                $msg = 'Tài liệu này đã được xử lý gần đây (trong vòng 30 phút). Bạn có chắc muốn gửi lại vào hàng đợi AI?';
                echo '<div class="alert alert-warning">' . htmlspecialchars($msg) . ' ';
                // show a small POST form for confirmation
                echo '<form method="post" class="d-inline ms-2">'
                    . '<input type="hidden" name="enqueue_ai" value="' . $doc_id . '">'
                    . '<input type="hidden" name="confirm" value="1">'
                    . '<button type="submit" class="btn btn-sm btn-primary">Yes</button>'
                    . '</form>';
                echo ' <a href="' . htmlspecialchars($_SERVER['PHP_SELF']) . '" class="btn btn-sm btn-secondary ms-1">No</a>';
                echo '</div>';
            } else {
                // Either no confirm needed, or user confirmed
                try {
                    $stmt = $conn->prepare("INSERT INTO ai_queue (document_id, status, created_at) VALUES (?, 'pending', NOW())");
                    $stmt->execute([$doc_id]);
                    echo '<div class="alert alert-success">✅ Đã thêm tài liệu vào hàng đợi AI (pending).</div>';
                } catch (Exception $e) {
                    echo '<div class="alert alert-danger">❌ Lỗi khi thêm vào ai_queue: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }
}

// ====== LẤY DANH SÁCH TÀI LIỆU ======
if ($is_admin) {
    $docs = $conn->query("
        SELECT documents.*, users.username 
        FROM documents 
        JOIN users ON documents.user_id = users.user_id
        ORDER BY upload_date DESC
    ")->fetchAll();
} else {
    $stmt = $conn->prepare("SELECT * FROM documents WHERE user_id=? ORDER BY upload_date DESC");
    $stmt->execute([$user_id]);
    $docs = $stmt->fetchAll();
}
?>

<div class="container my-4">
    <h2 class="mb-4">📄 Quản lý tài liệu</h2>

    <?php if (!$docs): ?>
        <div class="alert alert-info">Hiện tại không có tài liệu nào.</div>
    <?php else: ?>
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <?php if ($is_admin): ?><th>Người đăng</th><?php endif; ?>
                    <th>Tiêu đề</th>
                    <th>Mô tả</th>
                    <th>Trạng thái</th>
                    <th>Ngày tải lên</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($docs as $i => $doc): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <?php if ($is_admin): ?><td><?= htmlspecialchars($doc['username']) ?></td><?php endif; ?>
                        <td><?= htmlspecialchars($doc['title']) ?></td>
                        <td><?= nl2br(htmlspecialchars($doc['description'])) ?></td>
                        <td>
                            <?php
                            switch ($doc['status_id']) {
                                case 1:
                                    echo 'Chờ duyệt';
                                    break;
                                case 2:
                                    echo 'Đã duyệt';
                                    break;
                                case 3:
                                    echo 'Từ chối';
                                    break;
                                default:
                                    echo 'Không xác định';
                            }
                            ?>
                        </td>
                        <td><?= $doc['upload_date'] ?></td>
                        <td>
                            <a href="document_view.php?id=<?= $doc['doc_id'] ?>" target="_blank" class="btn btn-info btn-sm">Xem</a>

                            <!-- Nút Sửa -->
                            <a href="edit_document.php?doc_id=<?= $doc['doc_id'] ?>" class="btn btn-primary btn-sm mb-1">Sửa</a>

                            <?php if ($is_admin): ?>
                                <!-- Nút AI: enqueue vào ai_queue (POST form with client confirm) -->
                                <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn gửi tài liệu này vào hàng đợi AI?')">
                                    <input type="hidden" name="enqueue_ai" value="<?= $doc['doc_id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm mb-1">AI</button>
                                </form>
                                <?php if ($doc['status_id'] != 2): ?>
                                    <form method="post" action="action_approve.php" class="d-inline">
                                        <input type="hidden" name="approve" value="<?= $doc['doc_id'] ?>">
                                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                        <button type="submit" class="btn btn-success btn-sm mb-1">Duyệt</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($doc['status_id'] != 3): ?>
                                    <form method="post" action="action_approve.php" class="d-inline">
                                        <input type="hidden" name="reject" value="<?= $doc['doc_id'] ?>">
                                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                        <button type="submit" class="btn btn-warning btn-sm mb-1">Từ chối</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="action_approve.php" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa tài liệu này?')">
                                    <input type="hidden" name="delete" value="<?= $doc['doc_id'] ?>">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm mb-1">Xóa</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="action_approve.php" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa tài liệu này?')">
                                    <input type="hidden" name="delete" value="<?= $doc['doc_id'] ?>">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm mb-1">Xóa</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>