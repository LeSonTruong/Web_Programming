<?php
// Trang tìm kiếm nâng cao
session_start();
include 'includes/db.php';
include 'includes/header.php';
include 'vendor/autoload.php';

// ====== XỬ LÝ INPUT ======
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$subject = isset($_GET['subject']) ? intval($_GET['subject']) : null;
$department = isset($_GET['department']) ? trim($_GET['department']) : '';
$filetype = isset($_GET['filetype']) ? trim($_GET['filetype']) : '';
$sortby = isset($_GET['sortby']) ? $_GET['sortby'] : '';

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$results = [];
$totalResults = 0;

// Lấy danh sách subjects, departments cho select
$subjects = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name")->fetchAll(PDO::FETCH_ASSOC);
$departments = $conn->query("SELECT DISTINCT department FROM subjects WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// ====== FORM GIAO DIỆN (MỞ RỘNG VỚI FILTER) ======
?>
<div class="container my-4">
    <h2 class="mb-3">Tìm kiếm nâng cao</h2>
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-4">
            <input type="text" name="q" class="form-control" placeholder="🔍 Tìm tài liệu..."
                value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <div class="col-md-2">
            <select name="subject" class="form-select">
                <option value="">📚 Tất cả môn học</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?php echo $s['subject_id']; ?>" <?php echo ($subject == $s['subject_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['subject_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="department" class="form-select">
                <option value="">🏫 Tất cả khoa</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($department === $d) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="filetype" class="form-select">
                <option value="">📂 Tất cả định dạng</option>
                <?php
                $types = ['pdf' => 'PDF', 'doc' => 'Word', 'ppt' => 'PowerPoint', 'image' => 'Ảnh', 'code' => 'Code', 'other' => 'Khác'];
                foreach ($types as $k => $v): ?>
                    <option value="<?php echo $k; ?>" <?php echo ($filetype === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="sortby" class="form-select">
                <option value="">🔽 Sắp xếp theo...</option>
                <option value="likes" <?php echo ($sortby == 'likes') ? 'selected' : ''; ?>>Lượt thích nhiều nhất</option>
                <option value="views" <?php echo ($sortby == 'views') ? 'selected' : ''; ?>>Người xem nhiều nhất</option>
                <option value="downloads" <?php echo ($sortby == 'downloads') ? 'selected' : ''; ?>>Người tải nhiều nhất</option>
            </select>
        </div>

        <div class="col-md-12">
            <button type="submit" class="btn btn-primary">Tìm kiếm</button>
        </div>
    </form>
</div>

<?php
// ====== SEARCH LOGIC (LIKE NẾU KHÔNG FILTER, HOẶC ÁP DỤNG FILTER) ======
if ($search !== '' || $subject || $department || $filetype || $sortby) {
    // Nếu không có filter ngoài keyword, dùng LIKE đơn giản
    if (!$subject && !$department && !$filetype && !$sortby && $search) {
        $sql = "SELECT d.*, s.subject_name, s.department, u.username,
                       COALESCE(AVG(r.rating),0) as avg_rating,
                       SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) AS positive_count,
                       SUM(CASE WHEN r.rating = 0 THEN 1 ELSE 0 END) AS negative_count
                FROM documents d
                LEFT JOIN subjects s ON d.subject_id = s.subject_id
                LEFT JOIN users u ON d.user_id = u.user_id
                LEFT JOIN ratings r ON d.doc_id = r.doc_id
                WHERE d.status_id=2
                  AND (d.title LIKE :kw OR d.description LIKE :kw)
                GROUP BY d.doc_id
                ORDER BY d.upload_date DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':kw', "%$search%");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Đếm tổng số kết quả
        $countSql = "SELECT COUNT(*) FROM documents d WHERE d.status_id=2 AND (d.title LIKE :kw OR d.description LIKE :kw)";
        $countStmt = $conn->prepare($countSql);
        $countStmt->bindValue(':kw', "%$search%");
        $countStmt->execute();
        $totalResults = $countStmt->fetchColumn();
    } else {
        // Có filter: Dùng logic đầy đủ với LIKE và filter
        $sql = "SELECT d.*, s.subject_name, s.department, u.username,
               SUM(CASE WHEN rv.review_type = 'positive' THEN 1 ELSE 0 END) AS positive_count,
               SUM(CASE WHEN rv.review_type = 'negative' THEN 1 ELSE 0 END) AS negative_count
        FROM documents d
        LEFT JOIN subjects s ON d.subject_id = s.subject_id
        LEFT JOIN users u ON d.user_id = u.user_id
        LEFT JOIN reviews rv ON d.doc_id = rv.doc_id
                WHERE d.status_id=2";
        $params = [];
        if ($search) {
            $sql .= " AND (d.title LIKE :kw OR d.description LIKE :kw)";
            $params[':kw'] = "%$search%";
        }
        if ($subject) {
            $sql .= " AND d.subject_id = :subject";
            $params[':subject'] = $subject;
        }
        if ($department) {
            $sql .= " AND s.department = :department";
            $params[':department'] = $department;
        }
        if ($filetype) {
            $sql .= " AND d.document_type = :filetype";
            $params[':filetype'] = $filetype;
        }
        $sql .= " GROUP BY d.doc_id";
        if ($sortby === 'likes') $sql .= " ORDER BY positive_count DESC";
        elseif ($sortby === 'views') $sql .= " ORDER BY d.views DESC";
        elseif ($sortby === 'downloads') $sql .= " ORDER BY d.downloads DESC";
        else $sql .= " ORDER BY d.upload_date DESC";
        $sql .= " LIMIT :limit OFFSET :offset";

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Đếm tổng số kết quả cho pagination
        $countSql = "SELECT COUNT(*) FROM documents d LEFT JOIN subjects s ON d.subject_id = s.subject_id WHERE d.status_id=2";
        $countParams = [];
        if ($search) {
            $countSql .= " AND (d.title LIKE :kw OR d.description LIKE :kw)";
            $countParams[':kw'] = "%$search%";
        }
        if ($subject) {
            $countSql .= " AND d.subject_id = :subject";
            $countParams[':subject'] = $subject;
        }
        if ($department) {
            $countSql .= " AND s.department = :department";
            $countParams[':department'] = $department;
        }
        if ($filetype) {
            $countSql .= " AND d.document_type = :filetype";
            $countParams[':filetype'] = $filetype;
        }
        $countStmt = $conn->prepare($countSql);
        foreach ($countParams as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $totalResults = $countStmt->fetchColumn();
    }
}
?>

<h5>Kết quả tìm kiếm (<?php echo $totalResults; ?>)</h5>
<?php foreach ($results as $row): ?>
    <?php
    // Tính toán review_summary dựa trên positive_count và negative_count
    $total_reviews = ($row['positive_count'] ?? 0) + ($row['negative_count'] ?? 0);
    $review_summary = $total_reviews > 0 ? ($row['positive_count'] / $total_reviews >= 0.7 ? "Đánh giá tích cực" : ($row['positive_count'] / $total_reviews >= 0.4 ? "Đánh giá trung bình" : "Đánh giá tiêu cực")) : "Chưa có đánh giá";
    ?>
    <a href="document_view.php?id=<?php echo $row['doc_id'] ?? 0; ?>" class="text-decoration-none text-dark">
        <div class="card h-100 shadow-sm doc-card">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title"><?php echo htmlspecialchars($row['title'] ?? ''); ?></h5>
                <p class="card-text"><strong>Môn học:</strong> <?php echo htmlspecialchars($row['subject_name'] ?? ''); ?></p>
                <p class="card-text"><strong>Người đăng:</strong> <?php echo htmlspecialchars($row['username'] ?? ''); ?></p>
                <p class="card-text text-info">
                    <strong>Đánh giá:</strong> <?php echo $review_summary; ?>
                    (👍 <?php echo $row['positive_count'] ?? 0; ?> / 👎 <?php echo $row['negative_count'] ?? 0; ?>)
                </p>
                <p class="card-text"><strong>Lượt xem:</strong> <?php echo number_format($row['views'] ?? 0); ?></p>
                <?php if (!empty($row['description'])): ?>
                    <p class="card-text"><strong>Mô tả:</strong> <?php echo nl2br(htmlspecialchars($row['description'] ?? '')); ?></p>
                <?php endif; ?>
            </div>
            <div class="card-footer small text-muted">
                Đăng ngày: <?php echo !empty($row['upload_date']) ? date("d/m/Y H:i", strtotime($row['upload_date'])) : ''; ?>
            </div>
        </div>
    </a>
<?php endforeach; ?>

<!-- Pagination -->
<?php if ($totalResults > $limit): ?>
    <nav>
        <ul class="pagination">
            <?php for ($i = 1; $i <= ceil($totalResults / $limit); $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?q=<?php echo urlencode($search); ?>&subject=<?php echo $subject; ?>&department=<?php echo urlencode($department); ?>&filetype=<?php echo $filetype; ?>&sortby=<?php echo $sortby; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>