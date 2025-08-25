<?php
session_start();
include 'includes/db.php';
include 'includes/header.php';
include 'vendor/autoload.php';

// ...existing code...

// ====== XỬ LÝ INPUT ======
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$subject = isset($_GET['subject']) ? intval($_GET['subject']) : null;
$department = isset($_GET['department']) ? trim($_GET['department']) : '';
$filetype = isset($_GET['filetype']) ? trim($_GET['filetype']) : '';
$rating = isset($_GET['rating']) ? intval($_GET['rating']) : null;
$sortby = isset($_GET['sortby']) ? $_GET['sortby'] : '';

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$results = [];
$totalResults = 0;

// ====== FORM GIAO DIỆN ======
?>
<div class="container my-4">
    <form method="get" class="row g-2 mb-4">
        <div class="col-md-4">
            <input type="text" name="q" class="form-control" placeholder="🔍 Tìm tài liệu..."
                value="<?= htmlspecialchars($search) ?>">
        </div>

        <div class="col-md-2">
            <select name="subject" class="form-select">
                <option value="">📚 Tất cả môn học</option>
                <?php
                $subStmt = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");
                while ($s = $subStmt->fetch(PDO::FETCH_ASSOC)) {
                    $sel = ($subject == $s['subject_id']) ? 'selected' : '';
                    echo "<option value='{$s['subject_id']}' $sel>" . htmlspecialchars($s['subject_name']) . "</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="department" class="form-select">
                <option value="">🏫 Tất cả khoa</option>
                <?php
                $depStmt = $conn->query("SELECT DISTINCT department FROM subjects WHERE department IS NOT NULL ORDER BY department");
                while ($d = $depStmt->fetch(PDO::FETCH_ASSOC)) {
                    $sel = ($department === $d['department']) ? 'selected' : '';
                    echo "<option value='" . htmlspecialchars($d['department']) . "' $sel>" . htmlspecialchars($d['department']) . "</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="filetype" class="form-select">
                <option value="">📂 Tất cả định dạng</option>
                <?php
                $types = ['pdf' => 'PDF', 'doc' => 'Word', 'ppt' => 'PowerPoint', 'image' => 'Ảnh', 'code' => 'Code', 'other' => 'Khác'];
                foreach ($types as $k => $v) {
                    $sel = ($filetype === $k) ? 'selected' : '';
                    echo "<option value='$k' $sel>$v</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="sortby" class="form-select">
                <option value="">🔽 Sắp xếp theo...</option>
                <option value="likes" <?= isset($_GET['sortby']) && $_GET['sortby'] == 'likes' ? 'selected' : '' ?>>Lượt thích nhiều nhất</option>
                <option value="views" <?= isset($_GET['sortby']) && $_GET['sortby'] == 'views' ? 'selected' : '' ?>>Người xem nhiều nhất</option>
                <option value="downloads" <?= isset($_GET['sortby']) && $_GET['sortby'] == 'downloads' ? 'selected' : '' ?>>Người tải nhiều nhất</option>
            </select>
        </div>

        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Tìm kiếm</button>
        </div>
    </form>
</div>

<?php
// ====== SEARCH LOGIC ======
if ($search !== '') {
    // Thử semantic search
    $stmt = $conn->query("
        SELECT d.doc_id, d.title, d.description, d.document_type, d.upload_date, d.user_id, d.subject_id,
               s.subject_name, s.department,
               u.username,
               e.vector,
               COALESCE(AVG(r.rating), 0) AS avg_rating
        FROM documents d
        LEFT JOIN subjects s ON d.subject_id = s.subject_id
        LEFT JOIN users u ON d.user_id = u.user_id
        LEFT JOIN document_embeddings e ON d.doc_id = e.doc_id
        LEFT JOIN ratings r ON d.doc_id = r.doc_id
        WHERE d.status_id = 1
        GROUP BY d.doc_id, d.title, d.description, d.document_type, d.upload_date, d.user_id, d.subject_id,
                 s.subject_name, s.department,
                 u.username,
                 e.vector
    ");
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hàm cosine: tính độ tương đồng cosine giữa hai vector
    function cosine($a, $b)
    {
        $dot = 0;
        $normA = 0;
        $normB = 0;
        foreach ($a as $i => $v) {
            $dot += $v * $b[$i];
            $normA += $v * $v;
            $normB += $b[$i] * $b[$i];
        }
        return $dot / (sqrt($normA) * sqrt($normB) + 1e-10);
    }
    $scored = [];
    foreach ($docs as $doc) {
        if (!$doc['vector']) continue;
        $vec = json_decode($doc['vector'], true);
        $sim = cosine($queryVec, $vec);
        // áp dụng filter
        if ($subject && $doc['subject_id'] != $subject) continue;
        if ($department && $doc['department'] !== $department) continue;
        if ($filetype && $doc['document_type'] !== $filetype) continue;
        $doc['similarity'] = $sim;
        $scored[] = $doc;
    }
    // Sắp xếp theo tiêu chí nếu có chọn
    if ($sortby === 'likes') {
        usort($scored, fn($a, $b) => ($b['likes'] ?? 0) <=> ($a['likes'] ?? 0));
    } elseif ($sortby === 'views') {
        usort($scored, fn($a, $b) => ($b['views'] ?? 0) <=> ($a['views'] ?? 0));
    } elseif ($sortby === 'downloads') {
        usort($scored, fn($a, $b) => ($b['downloads'] ?? 0) <=> ($a['downloads'] ?? 0));
    } else {
        usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
    }

    $semanticIds = array_column($scored, 'doc_id');

    // LIKE search luôn chạy song song
    $sql = "SELECT d.*, s.subject_name, s.department, u.username,
                   COALESCE(AVG(r.rating),0) as avg_rating
            FROM documents d
            LEFT JOIN subjects s ON d.subject_id = s.subject_id
            LEFT JOIN users u ON d.user_id = u.user_id
            LEFT JOIN ratings r ON d.doc_id = r.doc_id
            WHERE d.status_id=1
              AND (d.title LIKE :kw OR d.description LIKE :kw)";
    if ($subject) $sql .= " AND d.subject_id = :subject";
    if ($department) $sql .= " AND s.department = :department";
    if ($filetype) $sql .= " AND d.document_type = :filetype";
    // Sắp xếp theo tiêu chí nếu có chọn
    if ($sortby === 'likes') {
        $sql .= " ORDER BY d.likes DESC";
    } elseif ($sortby === 'views') {
        $sql .= " ORDER BY d.views DESC";
    } elseif ($sortby === 'downloads') {
        $sql .= " ORDER BY d.downloads DESC";
    }
    $sql .= " GROUP BY d.doc_id";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':kw', "%$search%");
    if ($subject) $stmt->bindValue(':subject', $subject, PDO::PARAM_INT);
    if ($department) $stmt->bindValue(':department', $department, PDO::PARAM_STR);
    if ($filetype) $stmt->bindValue(':filetype', $filetype, PDO::PARAM_STR);
    $stmt->execute();
    $likeResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Loại bỏ các doc_id đã có trong semantic
    $likeResults = array_filter($likeResults, function ($row) use ($semanticIds) {
        return !in_array($row['doc_id'], $semanticIds);
    });

    // Gộp kết quả, ưu tiên semantic lên đầu
    $allResults = array_merge($scored, $likeResults);
    $totalResults = count($allResults);
    $results = array_slice($allResults, $offset, $limit);
    // ...existing code...

    // ====== HIỂN THỊ KẾT QUẢ ======
?>
<?php } ?>
<div class="container">
    <h5>Kết quả tìm kiếm (<?= $totalResults ?>)</h5>
    <?php foreach ($results as $row): ?>
        <div class="card mb-3 p-3 shadow-sm">
            <h6><?= htmlspecialchars($row['title']) ?></h6>
            <p class="text-muted mb-1"><?= htmlspecialchars($row['subject_name']) ?> - <?= htmlspecialchars($row['department']) ?></p>
            <p><?= htmlspecialchars(substr($row['description'], 0, 150)) ?>...</p>
            <small>👤 <?= htmlspecialchars($row['username']) ?> | 📂 <?= htmlspecialchars($row['document_type']) ?> | ⭐ <?= round($row['avg_rating'], 1) ?>/5</small>
        </div>
    <?php endforeach; ?>

    <!-- Pagination -->
    <?php if ($totalResults > $limit): ?>
        <nav>
            <ul class="pagination">
                <?php for ($i = 1; $i <= ceil($totalResults / $limit); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?q=<?= urlencode($search) ?>&subject=<?= $subject ?>&department=<?= urlencode($department) ?>&filetype=<?= $filetype ?>&rating=<?= $rating ?>&page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>