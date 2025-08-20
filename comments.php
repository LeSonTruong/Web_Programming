<?php include 'includes/header.php'; ?>

<div class="container my-4">
    <h2 class="mb-4">💬 Bình luận tài liệu</h2>

    <?php
    include 'includes/db.php';

    // Lấy tất cả comment, join documents và users
    $stmt = $pdo->query("
        SELECT c.comment_id, c.content, c.created_at, u.fullname, u.username, d.title
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        JOIN documents d ON c.doc_id = d.doc_id
        ORDER BY c.created_at DESC
    ");
    $comments = $stmt->fetchAll();
    ?>

    <?php if (!$comments): ?>
        <div class="alert alert-info">Chưa có bình luận nào.</div>
    <?php else: ?>
        <?php foreach ($comments as $c): ?>
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($c['fullname'] ?? $c['username']) ?>
                        <small class="text-muted">trên "<?= htmlspecialchars($c['title']) ?>"</small>
                    </h5>
                    <p class="card-text"><?= nl2br(htmlspecialchars($c['content'])) ?></p>
                </div>
                <div class="card-footer text-muted small">
                    <?= date("d/m/Y H:i", strtotime($c['created_at'])) ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>