<?php
include 'includes/header.php';
include 'includes/db.php';

// Lấy từ khóa tìm kiếm
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;
$documents = [];

// Hàm highlight từ khóa
function highlight($text, $keyword)
{
    if (!$keyword) return htmlspecialchars($text);
    return preg_replace("/(" . preg_quote($keyword, '/') . ")/i", "<mark>$1</mark>", htmlspecialchars($text));
}

// Truy vấn nếu có từ khóa
if ($query !== '') {
    // Đếm tổng kết quả
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) FROM documents 
        JOIN users ON documents.user_id = users.user_id
        WHERE status='approved' AND (title LIKE ? OR subject LIKE ? OR description LIKE ?)
    ");
    $stmtCount->execute(["%$query%", "%$query%", "%$query%"]);
    $totalResults = $stmtCount->fetchColumn();

    // Lấy dữ liệu phân trang
    $stmt = $pdo->prepare("
        SELECT documents.*, users.username 
        FROM documents 
        JOIN users ON documents.user_id = users.user_id 
        WHERE status='approved' AND (title LIKE ? OR subject LIKE ? OR description LIKE ?)
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(["%$query%", "%$query%", "%$query%", $limit, $offset]);
    $documents = $stmt->fetchAll();
    $totalPages = ceil($totalResults / $limit);
}
?>

<div class="container my-4">
    <h2 class="mb-4">🔎 Tìm kiếm tài liệu</h2>

    <!-- Form tìm kiếm -->
    <form method="get" class="row g-2 mb-4">
        <div class="col-md-8">
            <input type="text" name="q" class="form-control" placeholder="Nhập tiêu đề, môn học hoặc mô tả..."
                value="<?= htmlspecialchars($query) ?>">
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100">Tìm kiếm</button>
        </div>
    </form>

    <?php if ($query === ''): ?>
        <div class="alert alert-info">Nhập từ khóa để tìm kiếm tài liệu.</div>
    <?php elseif (!$documents): ?>
        <div class="alert alert-warning">Không tìm thấy tài liệu nào phù hợp với từ khóa "<strong><?= htmlspecialchars($query) ?></strong>".</div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($documents as $doc): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= highlight($doc['title'], $query) ?></h5>
                            <p class="card-text"><strong>Môn học:</strong> <?= highlight($doc['subject'], $query) ?></p>
                            <p class="card-text"><strong>Người đăng:</strong> <?= htmlspecialchars($doc['username']) ?></p>
                            <?php if (!empty($doc['description'])): ?>
                                <p class="card-text"><strong>Mô tả:</strong> <?= highlight($doc['description'], $query) ?></p>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-success mt-auto">
                                📥 Tải xuống
                            </a>
                        </div>
                        <div class="card-footer text-muted small">
                            Đăng ngày: <?= date("d/m/Y H:i", strtotime($doc['created_at'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Phân trang -->
        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?q=<?= urlencode($query) ?>&page=<?= $page - 1 ?>">« Trước</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?q=<?= urlencode($query) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?q=<?= urlencode($query) ?>&page=<?= $page + 1 ?>">Sau »</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>