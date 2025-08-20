<?php include 'includes/header.php'; ?>

<div class="container my-4">
    <h2 class="mb-4">📥 Lịch sử tải tài liệu</h2>

    <?php
    include 'includes/db.php';

    // Lấy danh sách download, join documents và users
    $stmt = $pdo->query("
        SELECT dl.download_id, dl.download_time, u.fullname, u.username, d.title, d.file_path
        FROM downloads dl
        JOIN users u ON dl.user_id = u.user_id
        JOIN documents d ON dl.doc_id = d.doc_id
        ORDER BY dl.download_time DESC
    ");
    $downloads = $stmt->fetchAll();
    ?>

    <?php if (!$downloads): ?>
        <div class="alert alert-info">Chưa có lượt tải nào.</div>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Người tải</th>
                    <th>Tài liệu</th>
                    <th>Thời gian tải</th>
                    <th>Link tải</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($downloads as $index => $dl): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($dl['fullname'] ?? $dl['username']) ?></td>
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
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>