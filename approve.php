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

// Display any flash messages set by action_approve.php
if (!empty($_SESSION['approve_flash'])) {
    foreach ($_SESSION['approve_flash'] as $m) {
        echo $m;
    }
    unset($_SESSION['approve_flash']);
}

// ====== DUYỆT TÀI LIỆU (POST) ======
if (isset($_POST['approve'])) {
    $doc_id = (int) $_POST['approve'];
    $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();

    if ($doc) {
        $stmt_user = $conn->prepare("SELECT role FROM users WHERE user_id=?");
        $stmt_user->execute([$doc['user_id']]);
        $user_post = $stmt_user->fetch();

        if ($doc['status_id'] != 1) {
            echo '<div class="alert alert-info">⚠️ Tài liệu này đã được xử lý trước đó.</div>';
        } else {
            $textContent = $doc['description'] ?: $doc['title'];
            $textContent = mb_substr($textContent, 0, 5000);

            // cập nhật trạng thái
            $stmt = $conn->prepare("UPDATE documents SET status_id=2 WHERE doc_id=?");
            $stmt->execute([$doc_id]);

            // Tạo thông báo cho user
            $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $message = "✅ Tài liệu '{$doc['title']}' của bạn đã được duyệt!";
            $stmt_notif->execute([$doc['user_id'], $message]);
            
            echo '<div class="alert alert-success">✅ Tài liệu đã được duyệt.</div>';
        }
    }
}

// ====== TỪ CHỐI TÀI LIỆU ======
if (isset($_POST['reject'])) {
    $doc_id = (int) $_POST['reject'];
    $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();

    if ($doc && $doc['status_id'] == 1) {
        $stmt = $conn->prepare("UPDATE documents SET status_id=3 WHERE doc_id=?");
        $stmt->execute([$doc_id]);

        $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $message = "❌ Tài liệu '{$doc['title']}' của bạn đã bị từ chối!";
        $stmt_notif->execute([$doc['user_id'], $message]);

        echo '<div class="alert alert-danger">❌ Đã từ chối tài liệu.</div>';
    }
}

// ====== DUYỆT / TỪ CHỐI SỬA ĐỔI NGƯỜI DÙNG (document_edits) ======
if (isset($_POST['accept_edit'])) {
    $edit_id = (int)$_POST['accept_edit'];
    $stmt = $conn->prepare("SELECT * FROM document_edits WHERE edit_id=? AND status='pending'");
    $stmt->execute([$edit_id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edit) {
        // Apply changes to documents
        $apply = $conn->prepare("UPDATE documents SET title=?, author_name=?, description=?, subject_id=?, file_path=?, file_size=?, document_type=?, updated_at=NOW() WHERE doc_id=?");
        $apply->execute([
            $edit['title'],
            $edit['author_name'],
            $edit['description'],
            $edit['subject_id'],
            $edit['file_path'],
            $edit['file_size'],
            $edit['document_type'],
            $edit['doc_id'],
        ]);

        // Mark edit as approved
        $up = $conn->prepare("UPDATE document_edits SET status='approved', updated_at=NOW() WHERE edit_id=?");
        $up->execute([$edit_id]);

        // Optional: notify document owner (if available)
        try {
            $stmt_doc = $conn->prepare("SELECT user_id FROM documents WHERE doc_id=? LIMIT 1");
            $stmt_doc->execute([$edit['doc_id']]);
            $owner_id = $stmt_doc->fetchColumn();
            if ($owner_id) {
                $msg = "✅ Sửa đổi cho tài liệu đã được chấp nhận.";
                $stmt_not = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $stmt_not->execute([$owner_id, $msg]);
            }
        } catch (Exception $e) {
            // ignore notification errors
        }

        echo '<div class="alert alert-success">✅ Đã chấp nhận sửa đổi và cập nhật vào tài liệu.</div>';
    } else {
        echo '<div class="alert alert-info">⚠️ Không tìm thấy bản sửa đang chờ với edit_id này.</div>';
    }
}

if (isset($_POST['reject_edit'])) {
    $edit_id = (int)$_POST['reject_edit'];
    $stmt = $conn->prepare("SELECT * FROM document_edits WHERE edit_id=? AND status='pending'");
    $stmt->execute([$edit_id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edit) {
        $up = $conn->prepare("UPDATE document_edits SET status='rejected', updated_at=NOW() WHERE edit_id=?");
        $up->execute([$edit_id]);
        echo '<div class="alert alert-danger">❌ Đã từ chối sửa đổi.</div>';
    } else {
        echo '<div class="alert alert-info">⚠️ Không tìm thấy bản sửa đang chờ với edit_id này.</div>';
    }
}

// ====== DANH SÁCH CHỜ DUYỆT ======
$view = $_GET['view'] ?? 'new';

if ($view === 'edits') {
    $items = $conn->query("SELECT de.*, d.title AS original_title, d.user_id AS owner_id FROM document_edits de JOIN documents d ON de.doc_id = d.doc_id WHERE de.status = 'pending' ORDER BY de.updated_at DESC")->fetchAll();
} else {
    $items = $conn->query("SELECT documents.*, users.username FROM documents JOIN users ON documents.user_id = users.user_id WHERE status_id=1 ORDER BY upload_date DESC")->fetchAll();
}
?>

<div class="container my-4">
    <h2 class="mb-4">📝 Duyệt tài liệu</h2>

    <form method="get" class="mb-3">
        <label for="view-select" class="form-label">Chọn danh sách:</label>
        <select id="view-select" name="view" class="form-select" style="max-width:320px;" onchange="this.form.submit()">
            <option value="new" <?= $view === 'new' ? 'selected' : '' ?>>Tài liệu mới</option>
            <option value="edits" <?= $view === 'edits' ? 'selected' : '' ?>>Sửa đổi của người dùng</option>
        </select>
    </form>

    <?php if ($view === 'edits'): ?>
        <?php if (empty($items)): ?>
            <div class="alert alert-info">Hiện tại không có sửa đổi nào chờ duyệt.</div>
        <?php else: ?>
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Doc ID</th>
                        <th>Tiêu đề gốc</th>
                        <th>Thời gian</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $e): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= (int)$e['doc_id'] ?></td>
                            <td><?= htmlspecialchars($e['original_title'] ?? '') ?></td>
                            <td><?= htmlspecialchars($e['updated_at'] ?? '') ?></td>
                            <td>
                                <a class="btn btn-info btn-sm" href="document_review.php?edit_id=<?= urlencode($e['edit_id']) ?>">Xem</a>
                                <form method="POST" action="action_approve.php" class="d-inline ms-1" onsubmit="return confirm('Bạn có chắc muốn chấp nhận sửa đổi này?')">
                                    <input type="hidden" name="accept_edit" value="<?= (int)$e['edit_id'] ?>">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                    <button class="btn btn-success btn-sm" type="submit">Chấp nhận</button>
                                </form>
                                <form method="POST" action="action_approve.php" class="d-inline ms-1" onsubmit="return confirm('Bạn có chắc muốn từ chối sửa đổi này?')">
                                    <input type="hidden" name="reject_edit" value="<?= (int)$e['edit_id'] ?>">
                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">Từ chối</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php else: ?>
        <?php if (empty($items)): ?>
            <div class="alert alert-info">Hiện tại không có tài liệu nào chờ duyệt.</div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($items as $doc): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?= htmlspecialchars($doc['title']) ?></h5>
                                <p class="card-text"><strong>Người đăng:</strong> <?= htmlspecialchars($doc['username']) ?></p>
                                <?php if (!empty($doc['description'])): ?>
                                    <p class="card-text"><strong>Mô tả:</strong> <?= nl2br(htmlspecialchars($doc['description'])) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer d-flex justify-content-between">
                                <a href="document_view.php?id=<?= $doc['doc_id'] ?>" target="_blank" class="btn btn-info btn-sm">Xem tài liệu</a>
                                <div class="d-flex">
                                    <form method="POST" action="action_approve.php" class="me-1">
                                        <input type="hidden" name="approve" value="<?= $doc['doc_id'] ?>">
                                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                        <button class="btn btn-success btn-sm" type="submit">Duyệt</button>
                                    </form>
                                    <form method="POST" action="action_approve.php">
                                        <input type="hidden" name="reject" value="<?= $doc['doc_id'] ?>">
                                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                        <button class="btn btn-danger btn-sm" type="submit">Từ chối</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>