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

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['role'] === 'admin';

// ====== XỬ LÝ HÀNH ĐỘNG ======
if (isset($_GET['delete'])) {
    $doc_id = (int)$_GET['delete'];

    $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();

    if ($doc && ($is_admin || $doc['user_id'] == $user_id)) {
        if (file_exists($doc['file_path'])) unlink($doc['file_path']);
        $stmt = $conn->prepare("DELETE FROM documents WHERE doc_id=?");
        $stmt->execute([$doc_id]);
        echo '<div class="alert alert-success">✅ Tài liệu đã được xóa.</div>';
    } else {
        echo '<div class="alert alert-danger">⚠️ Bạn không có quyền xóa tài liệu này.</div>';
    }
}

if ($is_admin && isset($_GET['approve'])) {
    $doc_id = (int)$_GET['approve'];
    $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();

    if ($doc && $doc['status_id'] != 2) {
        $stmt = $conn->prepare("UPDATE documents SET status_id=2 WHERE doc_id=?");
        $stmt->execute([$doc_id]);
        $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt_notif->execute([$doc['user_id'], "✅ Tài liệu '{$doc['title']}' của bạn đã được duyệt bởi admin!"]);
        echo '<div class="alert alert-success">✅ Tài liệu đã được duyệt.</div>';
    }
}

if ($is_admin && isset($_GET['reject'])) {
    $doc_id = (int)$_GET['reject'];
    $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();

    if ($doc && $doc['status_id'] != 3) {
        $stmt = $conn->prepare("UPDATE documents SET status_id=3 WHERE doc_id=?");
        $stmt->execute([$doc_id]);
        $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt_notif->execute([$doc['user_id'], "❌ Tài liệu '{$doc['title']}' của bạn đã bị từ chối bởi admin!"]);
        echo '<div class="alert alert-danger">❌ Tài liệu đã bị từ chối.</div>';
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
                                case 0:
                                    echo 'Chờ duyệt';
                                    break;
                                case 1:
                                    echo 'Pending';
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
                            <a href="document_view.php?id=<?= $doc['doc_id'] ?>" target="_blank" class="btn btn-info btn-sm">Xem tài liệu</a>

                            <!-- Nút Sửa -->
                            <a href="edit_document.php?doc_id=<?= $doc['doc_id'] ?>" class="btn btn-primary btn-sm mb-1">Sửa</a>

                            <?php if ($is_admin): ?>
                                <?php if ($doc['status_id'] != 2): ?>
                                    <a href="?approve=<?= $doc['doc_id'] ?>" class="btn btn-success btn-sm mb-1">Duyệt</a>
                                <?php endif; ?>
                                <?php if ($doc['status_id'] != 3): ?>
                                    <a href="?reject=<?= $doc['doc_id'] ?>" class="btn btn-warning btn-sm mb-1">Từ chối</a>
                                <?php endif; ?>
                                <a href="?delete=<?= $doc['doc_id'] ?>" class="btn btn-danger btn-sm mb-1" onclick="return confirm('Bạn có chắc muốn xóa tài liệu này?')">Xóa</a>
                            <?php else: ?>
                                <a href="?delete=<?= $doc['doc_id'] ?>" class="btn btn-danger btn-sm mb-1" onclick="return confirm('Bạn có chắc muốn xóa tài liệu này?')">Xóa</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>