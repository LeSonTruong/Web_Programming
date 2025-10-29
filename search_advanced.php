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
$sortby = isset($_GET['sortby']) ? $_GET['sortby'] : '';
$selected_tags = isset($_GET['tags']) ? (array)$_GET['tags'] : [];

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$results = [];
$totalResults = 0;

// Lấy danh sách subjects, departments, tags cho select
$subjects = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name")->fetchAll(PDO::FETCH_ASSOC);
$departments = $conn->query("SELECT DISTINCT department FROM subjects WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
$tags = $conn->query("SELECT tag_id, tag_name FROM tags ORDER BY tag_name")->fetchAll(PDO::FETCH_ASSOC);

// ====== FORM GIAO DIỆN (MỞ RỘNG VỚI FILTER) ======
?>
<link rel="stylesheet" href="css/hover.css">
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
                    <option value="<?php echo $s['subject_id']; ?>" <?php echo ($subject == $s['subject_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['subject_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="department" class="form-select">
                <option value="">🏫 Tất cả khoa</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($department === $d) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($d); ?>
                    </option>
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

        <!-- Bộ lọc tags -->
        <div class="col-md-12">
            <label><strong>Tags:</strong></label>
            <input type="text" id="tag-input" class="form-control" placeholder="Nhập tag...">
            <div id="tag-suggestions" class="list-group"></div>
            <div id="selected-tags" class="mt-2">
                <?php foreach ($selected_tags as $tag_id):
                    $tag_name = '';
                    foreach ($tags as $t) {
                        if ($t['tag_id'] == $tag_id) {
                            $tag_name = $t['tag_name'];
                            break;
                        }
                    }
                ?>
                    <span class="badge bg-primary me-1">
                        <?php echo htmlspecialchars($tag_name); ?>
                        <input type="hidden" name="tags[]" value="<?php echo $tag_id; ?>">
                        <button type="button" class="btn-close btn-close-white ms-1 remove-tag" aria-label="X"></button>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-md-12">
            <button type="submit" class="btn btn-primary">Tìm kiếm</button>
        </div>
    </form>
</div>

<?php
// ====== SEARCH LOGIC ======
// Khởi tạo biến dùng chung cho phần đếm / params để tránh "undefined variable"
$countSql = '';
$countParams = [];
if ($search !== '' || $subject || $department || $sortby || !empty($selected_tags)) {
    // Nếu chỉ có keyword, không filter khác
    if (!$subject && !$department && !$sortby && empty($selected_tags) && $search) {
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

        $countSql = "SELECT COUNT(*) FROM documents d WHERE d.status_id=2 AND (d.title LIKE :kw OR d.description LIKE :kw)";
        $countStmt = $conn->prepare($countSql);
        $countStmt->bindValue(':kw', "%$search%");
        $countStmt->execute();
        $totalResults = $countStmt->fetchColumn();
    } else {
        // Có filter: Dùng query đầy đủ
        $sql = "SELECT d.*, s.subject_name, s.department, u.username,
                       SUM(CASE WHEN rv.review_type = 'positive' THEN 1 ELSE 0 END) AS positive_count,
                       SUM(CASE WHEN rv.review_type = 'negative' THEN 1 ELSE 0 END) AS negative_count
                FROM documents d
                LEFT JOIN subjects s ON d.subject_id = s.subject_id
                LEFT JOIN users u ON d.user_id = u.user_id
                LEFT JOIN reviews rv ON d.doc_id = rv.doc_id
                LEFT JOIN document_tags dt ON d.doc_id = dt.doc_id
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
        if (!empty($selected_tags)) {
            $tagPlaceholders = [];
            foreach ($selected_tags as $idx => $tag_id) {
                $ph = ":ctag$idx";
                $tagPlaceholders[] = $ph;
                // truyền vào cả params dùng cho query chính và count query
                $params[$ph] = $tag_id;
                $countParams[$ph] = $tag_id;
            }
            $sql .= " AND dt.tag_id IN (" . implode(',', $tagPlaceholders) . ")";
        }
        $sql .= " GROUP BY d.doc_id";
        // If multiple tags selected, require documents to have ALL selected tags (AND) by using HAVING
        if (!empty($selected_tags)) {
            $sql .= " HAVING COUNT(DISTINCT dt.tag_id) = " . count($selected_tags);
        }
        if ($sortby === 'likes') $sql .= " ORDER BY positive_count DESC";
        elseif ($sortby === 'views') $sql .= " ORDER BY d.views DESC";
        elseif ($sortby === 'downloads') $sql .= " ORDER BY d.downloads DESC";
        else $sql .= " ORDER BY d.upload_date DESC";
        $sql .= " LIMIT :limit OFFSET :offset";

        $stmt = $conn->prepare($sql);
        $i = 1;
        foreach ($params as $key => $value) {
            if (is_int($key)) {
                $stmt->bindValue($i, $value, PDO::PARAM_INT);
                $i++;
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Đếm tổng: when tags are selected we must count documents that have ALL selected tags.
        if (!empty($selected_tags)) {
            // Use a subquery that selects doc_id grouped and having count = number of selected tags,
            // then count rows of that subquery for pagination accuracy.
            $countSql = "SELECT COUNT(*) FROM (
                         SELECT d.doc_id
                         FROM documents d
                         LEFT JOIN subjects s ON d.subject_id = s.subject_id
                         LEFT JOIN document_tags dt ON d.doc_id = dt.doc_id
                         WHERE d.status_id=2";
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
            // reuse the tag placeholders prepared earlier in $countParams
            $tagPlaceholders = [];
            foreach ($selected_tags as $idx => $tag_id) {
                $tagPlaceholders[] = ":ctag$idx";
                $countParams[":ctag$idx"] = $tag_id;
            }
            $countSql .= " AND dt.tag_id IN (" . implode(',', $tagPlaceholders) . ")";
            $countSql .= " GROUP BY d.doc_id HAVING COUNT(DISTINCT dt.tag_id) = " . count($selected_tags) . ") as tmp";

            $countStmt = $conn->prepare($countSql);
            $i = 1;
            foreach ($countParams as $key => $value) {
                if (is_int($key)) {
                    $countStmt->bindValue($i, $value, PDO::PARAM_INT);
                    $i++;
                } else {
                    $countStmt->bindValue($key, $value);
                }
            }
            $countStmt->execute();
            $totalResults = $countStmt->fetchColumn();
        } else {
            // No tag filters: simple count query
            $countSql = "SELECT COUNT(DISTINCT d.doc_id)
                         FROM documents d
                         LEFT JOIN subjects s ON d.subject_id = s.subject_id
                         LEFT JOIN document_tags dt ON d.doc_id = dt.doc_id
                         WHERE d.status_id=2";
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
            $countStmt = $conn->prepare($countSql);
            $i = 1;
            foreach ($countParams as $key => $value) {
                if (is_int($key)) {
                    $countStmt->bindValue($i, $value, PDO::PARAM_INT);
                    $i++;
                } else {
                    $countStmt->bindValue($key, $value);
                }
            }
            $countStmt->execute();
            $totalResults = $countStmt->fetchColumn();
        }
    }
}
?>

<h5>Kết quả tìm kiếm (<?php echo $totalResults; ?>)</h5>
<div class="row">
    <?php foreach ($results as $row): ?>
        <?php
        $total_reviews = ($row['positive_count'] ?? 0) + ($row['negative_count'] ?? 0);
        $review_summary = $total_reviews > 0
            ? ($row['positive_count'] / $total_reviews >= 0.7 ? "Đánh giá tích cực"
                : ($row['positive_count'] / $total_reviews >= 0.4 ? "Đánh giá trung bình" : "Đánh giá tiêu cực"))
            : "Chưa có đánh giá";
        ?>
        <div class="col-md-6 col-lg-4 mb-4">
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
        </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalResults > $limit): ?>
    <nav>
        <ul class="pagination">
            <?php for ($i = 1; $i <= ceil($totalResults / $limit); $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link"
                        href="?q=<?php echo urlencode($search); ?>&subject=<?php echo $subject; ?>&department=<?php echo urlencode($department); ?>&sortby=<?php echo $sortby; ?>&<?php echo http_build_query(['tags' => $selected_tags]); ?>&page=<?php echo $i; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const tagInput = document.getElementById("tag-input");
        const tagSuggestions = document.getElementById("tag-suggestions");
        const selectedTagsDiv = document.getElementById("selected-tags");

        const allTags = <?php echo json_encode($tags); ?>;

        // Gợi ý tag khi nhập
        tagInput.addEventListener("input", function() {
            const query = this.value.toLowerCase();
            tagSuggestions.innerHTML = "";
            if (query.length > 0) {
                const matches = allTags.filter(t => t.tag_name.toLowerCase().includes(query));
                matches.forEach(tag => {
                    const item = document.createElement("button");
                    item.type = "button";
                    item.classList.add("list-group-item", "list-group-item-action");
                    item.textContent = tag.tag_name;
                    item.dataset.id = tag.tag_id;
                    tagSuggestions.appendChild(item);
                });
            }
        });

        // Chọn tag từ gợi ý
        tagSuggestions.addEventListener("click", function(e) {
            if (e.target.tagName === "BUTTON") {
                const tagId = e.target.dataset.id;
                const tagName = e.target.textContent;

                // Kiểm tra trùng
                if (selectedTagsDiv.querySelector('input[value="' + tagId + '"]')) return;

                const badge = document.createElement("span");
                badge.classList.add("badge", "bg-primary", "me-1");
                badge.innerHTML = `${tagName}
                <input type="hidden" name="tags[]" value="${tagId}">
                <button type="button" class="btn-close btn-close-white ms-1 remove-tag" aria-label="X"></button>`;
                selectedTagsDiv.appendChild(badge);

                tagInput.value = "";
                tagSuggestions.innerHTML = "";
            }
        });

        // Xóa tag đã chọn
        selectedTagsDiv.addEventListener("click", function(e) {
            if (e.target.classList.contains("remove-tag") || e.target.classList.contains("btn-close")) {
                e.target.closest("span").remove();
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>