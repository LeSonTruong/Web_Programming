<?php
// Trang tìm kiếm nâng cao
session_start();
include 'includes/db.php';

// Lấy danh sách tags, môn học, khoa
$tags = $conn->query("SELECT tag_name FROM tags ORDER BY tag_name")->fetchAll(PDO::FETCH_COLUMN);
$subjects = $conn->query("SELECT subject_name FROM subjects ORDER BY subject_name")->fetchAll(PDO::FETCH_COLUMN);
$departments = $conn->query("SELECT department_name FROM departments ORDER BY department_name")->fetchAll(PDO::FETCH_COLUMN);

// Xử lý tìm kiếm
$results = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    $keyword = trim($_GET['keyword'] ?? '');
    $selected_tags = $_GET['tags'] ?? [];
    $selected_subjects = $_GET['subjects'] ?? [];
    $selected_departments = $_GET['departments'] ?? [];

    $where = [];
    $params = [];
    if ($keyword) {
        $where[] = '(d.title LIKE ? OR d.description LIKE ?)';
        $params[] = "%$keyword%";
        $params[] = "%$keyword%";
    }
    if ($selected_subjects) {
        $where[] = 's.subject_name IN (' . str_repeat('?,', count($selected_subjects) - 1) . '?)';
        $params = array_merge($params, $selected_subjects);
    }
    if ($selected_departments) {
        $where[] = 'dep.department_name IN (' . str_repeat('?,', count($selected_departments) - 1) . '?)';
        $params = array_merge($params, $selected_departments);
    }
    if ($selected_tags) {
        $where[] = 't.tag_name IN (' . str_repeat('?,', count($selected_tags) - 1) . '?)';
        $params = array_merge($params, $selected_tags);
    }
    $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sql = "SELECT d.*, s.subject_name, dep.department_name
        FROM documents d
        LEFT JOIN subjects s ON d.subject_id = s.subject_id
        LEFT JOIN departments dep ON d.department_id = dep.department_id
        LEFT JOIN document_tags dt ON d.doc_id = dt.doc_id
        LEFT JOIN tags t ON dt.tag_id = t.tag_id
        $where_sql
        GROUP BY d.doc_id
        ORDER BY d.created_at DESC
        LIMIT 100";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<?php include 'includes/header.php'; ?>
<div class="container my-4">
    <h2 class="mb-3">Tìm kiếm nâng cao</h2>
    <form method="get" class="mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Từ khóa</label>
                <input type="text" name="keyword" class="form-control" value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>" placeholder="Nhập từ khóa...">
            </div>
            <div class="col-md-4">
                <label class="form-label">Tags</label>
                <select name="tags[]" class="form-select" multiple>
                    <?php foreach ($tags as $tag): ?>
                        <option value="<?= htmlspecialchars($tag) ?>" <?= !empty($_GET['tags']) && in_array($tag, $_GET['tags']) ? 'selected' : '' ?>><?= htmlspecialchars($tag) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Môn học</label>
                <select name="subjects[]" class="form-select" multiple>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= htmlspecialchars($subject) ?>" <?= !empty($_GET['subjects']) && in_array($subject, $_GET['subjects']) ? 'selected' : '' ?>><?= htmlspecialchars($subject) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Khoa</label>
                <select name="departments[]" class="form-select" multiple>
                    <?php foreach ($departments as $dep): ?>
                        <option value="<?= htmlspecialchars($dep) ?>" <?= !empty($_GET['departments']) && in_array($dep, $_GET['departments']) ? 'selected' : '' ?>><?= htmlspecialchars($dep) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button class="btn btn-primary mt-3">Tìm kiếm</button>
    </form>
    <?php if ($results): ?>
        <h4>Kết quả tìm kiếm (<?= count($results) ?>):</h4>
        <div class="list-group mb-4">
            <?php foreach ($results as $doc): ?>
                <a href="document_view.php?id=<?= $doc['doc_id'] ?>" class="list-group-item list-group-item-action">
                    <strong><?= htmlspecialchars($doc['title']) ?></strong>
                    <span class="badge bg-info ms-2">Môn: <?= htmlspecialchars($doc['subject_name']) ?></span>
                    <span class="badge bg-warning ms-2">Khoa: <?= htmlspecialchars($doc['department_name']) ?></span>
                    <span class="text-muted ms-2">Ngày: <?= date('d/m/Y', strtotime($doc['created_at'])) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)): ?>
        <div class="alert alert-warning">Không tìm thấy kết quả phù hợp.</div>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>