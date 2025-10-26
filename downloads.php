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
$is_admin = $_SESSION['role'] === 'admin';

// ====== LẤY DANH SÁCH DOWNLOAD ======
if ($is_admin) {
    // Admin thấy tất cả lượt tải
    $stmt = $conn->query("
        SELECT dl.download_id, dl.download_time, u.display_name, u.username, d.title, d.file_path
        FROM downloads dl
        JOIN users u ON dl.user_id = u.user_id
        JOIN documents d ON dl.doc_id = d.doc_id
        ORDER BY dl.download_time DESC
    ");
} else {
    // User bình thường chỉ thấy lượt tải của họ
    $stmt = $conn->prepare("
        SELECT dl.download_id, dl.download_time, u.display_name, u.username, d.title, d.file_path
        FROM downloads dl
        JOIN users u ON dl.user_id = u.user_id
        JOIN documents d ON dl.doc_id = d.doc_id
        WHERE dl.user_id = ?
        ORDER BY dl.download_time DESC
    ");
    $stmt->execute([$user_id]);
}

$downloads = $stmt->fetchAll();
?>

<div class="container my-4">
    <h2 class="mb-4">📥 Lịch sử tải tài liệu</h2>

    <?php if (!$downloads): ?>
        <div class="alert alert-info">Chưa có lượt tải nào.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <?php if ($is_admin): ?><th>Người tải</th><?php endif; ?>
                        <th>Tài liệu</th>
                        <th>Thời gian tải</th>
                        <th>Link tải</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($downloads as $index => $dl): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <?php if ($is_admin): ?>
                                <td><?= htmlspecialchars($dl['display_name'] ?? $dl['username']) ?></td>
                            <?php endif; ?>
                            <td><?= htmlspecialchars($dl['title']) ?></td>
                            <td><?= date("d/m/Y H:i", strtotime($dl['download_time'])) ?></td>
                            <td>
                                <a href="<?= htmlspecialchars($dl['file_path']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                    📥 Tải
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>