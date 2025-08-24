<?php
include 'includes/header.php';
include 'includes/db.php';
include 'includes/ai.php'; // file chứa hàm gọi AI

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

if ($query !== '') {
    try {
        // 1. Tạo embedding cho query
        $queryVector = createEmbedding($query); // trả về array 1536 chiều

        // 2. Lấy embedding documents từ DB
        $stmt = $conn->query("
            SELECT d.doc_id, d.title, d.description, d.file_path, d.upload_date, u.username, e.vector
            FROM documents d
            JOIN users u ON d.user_id = u.user_id
            JOIN document_embeddings e ON d.doc_id = e.doc_id
            WHERE d.status_id = 1
        ");
        $allDocs = $stmt->fetchAll();

        // 3. Tính cosine similarity
        function cosineSimilarity($vecA, $vecB)
        {
            $dot = 0.0;
            $normA = 0.0;
            $normB = 0.0;
            $len = min(count($vecA), count($vecB));
            for ($i = 0; $i < $len; $i++) {
                $dot += $vecA[$i] * $vecB[$i];
                $normA += $vecA[$i] ** 2;
                $normB += $vecB[$i] ** 2;
            }
            if ($normA == 0 || $normB == 0) return 0.0;
            return $dot / (sqrt($normA) * sqrt($normB));
        }

        $scored = [];
        foreach ($allDocs as $doc) {
            $docVector = json_decode($doc['vector'], true);
            $score = cosineSimilarity($queryVector, $docVector);
            $doc['score'] = $score;
            $scored[] = $doc;
        }

        // 4. Sắp xếp theo độ giống nhau
        usort($scored, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // 5. Phân trang
        $totalResults = count($scored);
        $totalPages = ceil($totalResults / $limit);
        $documents = array_slice($scored, $offset, $limit);

        // Nếu không có kết quả từ embedding, thử tìm kiếm truyền thống (LIKE)
        if (count($documents) === 0) {
            $stmt = $conn->prepare("SELECT d.*, u.username, s.subject_name,
                SUM(CASE WHEN r.review_type = 'positive' THEN 1 ELSE 0 END) AS positive_count,
                SUM(CASE WHEN r.review_type = 'negative' THEN 1 ELSE 0 END) AS negative_count
                FROM documents d
                JOIN users u ON d.user_id = u.user_id
                LEFT JOIN subjects s ON d.subject_id = s.subject_id
                LEFT JOIN reviews r ON d.doc_id = r.doc_id
                WHERE d.status_id = 2 AND (d.title LIKE :title OR d.description LIKE :desc)
                GROUP BY d.doc_id
                ORDER BY d.upload_date DESC
                LIMIT :limit OFFSET :offset");
            $likeQuery = "%$query%";
            $stmt->bindValue(':title', $likeQuery, PDO::PARAM_STR);
            $stmt->bindValue(':desc', $likeQuery, PDO::PARAM_STR);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            $documents = $stmt->fetchAll();
            // Đếm tổng số kết quả
            $countStmt = $conn->prepare("SELECT COUNT(*) FROM documents d WHERE d.status_id = 1 AND (d.title LIKE ? OR d.description LIKE ?)");
            $countStmt->execute([$likeQuery, $likeQuery]);
            $totalRows = $countStmt->fetchColumn();
            $totalPages = ceil($totalRows / $limit);
        }
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Lỗi khi tìm kiếm: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<div class="container my-4">
    <link rel="stylesheet" href="css/hover.css">
    <h2 class="mb-4">🔎 Tìm kiếm tài liệu (Semantic Search)</h2>

    <!-- Form tìm kiếm -->
    <form method="get" class="row g-2 mb-4">
        <div class="col-md-8">
            <input type="text" name="q" class="form-control" placeholder="Nhập nội dung cần tìm..."
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
                <?php
                $subject_name = $doc['subject_name'] ?? '';
                $username = $doc['username'] ?? '';
                $positive_count = $doc['positive_count'] ?? 0;
                $negative_count = $doc['negative_count'] ?? 0;
                $views = $doc['views'] ?? 0;
                $description = $doc['description'] ?? '';
                $total_reviews = $positive_count + $negative_count;
                if ($total_reviews > 0) {
                    $ratio = $positive_count / $total_reviews;
                    $review_summary = $ratio >= 0.7 ? "Đánh giá tích cực" : ($ratio >= 0.4 ? "Đánh giá trung bình" : "Đánh giá tiêu cực");
                } else {
                    $review_summary = "Chưa có đánh giá";
                }
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <a href="document_view.php?id=<?= $doc['doc_id'] ?? 0 ?>" class="text-decoration-none text-dark">
                        <div class="card h-100 shadow-sm doc-card">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?= htmlspecialchars($doc['title'] ?? '') ?></h5>
                                <p class="card-text"><strong>Môn học:</strong> <?= htmlspecialchars($subject_name) ?></p>
                                <p class="card-text"><strong>Người đăng:</strong> <?= htmlspecialchars($username) ?></p>
                                <p class="card-text text-info"><strong>Đánh giá:</strong> <?= $review_summary ?> (👍 <?= $positive_count ?> / 👎 <?= $negative_count ?>)</p>
                                <p class="card-text"><strong>Lượt xem:</strong> <?= number_format($views) ?></p>
                                <?php if (!empty($description)): ?>
                                    <p class="card-text"><strong>Mô tả:</strong> <?= nl2br(htmlspecialchars($description)) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer small text-muted">
                                Đăng ngày: <?= !empty($doc['upload_date']) ? date("d/m/Y H:i", strtotime($doc['upload_date'])) : '' ?>
                            </div>
                        </div>
                    </a>
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