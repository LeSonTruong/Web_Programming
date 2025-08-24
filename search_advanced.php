<?php
// Trang tìm kiếm nâng cao
session_start();
include 'includes/db.php';

// Lấy danh sách tags, môn học
$tags = $conn->query("SELECT tag_name FROM tags ORDER BY tag_name")->fetchAll(PDO::FETCH_COLUMN);
$subjects = $conn->query("SELECT subject_name FROM subjects ORDER BY subject_name")->fetchAll(PDO::FETCH_COLUMN);
// Nếu có bảng departments thì lấy, không thì bỏ qua
$departments = [];
try {
    $departments = $conn->query("SELECT department_name FROM departments ORDER BY department_name")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $departments = [];
}

// Lấy danh sách username
$usernames = $conn->query("SELECT username FROM users ORDER BY username")->fetchAll(PDO::FETCH_COLUMN);

// Xử lý tìm kiếm
$results = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    // Luôn lọc chỉ lấy tài liệu đã duyệt
    $where[] = 'd.status_id = 2';
    $keyword = trim($_GET['keyword'] ?? '');
    $selected_tags = array_filter(array_map('trim', explode(',', $_GET['tags'] ?? '')));
    $selected_subjects = [];
    if (!empty($_GET['subjects'])) {
        $selected_subjects[] = trim(is_array($_GET['subjects']) ? $_GET['subjects'][0] : $_GET['subjects']);
    }
    $selected_departments = [];
    if (!empty($_GET['departments'])) {
        $selected_departments[] = trim(is_array($_GET['departments']) ? $_GET['departments'][0] : $_GET['departments']);
    }
    $selected_usernames = [];
    if (!empty($_GET['usernames'])) {
        $selected_usernames[] = trim(is_array($_GET['usernames']) ? $_GET['usernames'][0] : $_GET['usernames']);
    }

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
    // Chỉ lọc theo khoa nếu có bảng departments
    if ($selected_departments && !empty($departments)) {
        $where[] = 'dep.department_name IN (' . str_repeat('?,', count($selected_departments) - 1) . '?)';
        $params = array_merge($params, $selected_departments);
    }
    if ($selected_tags) {
        // Lọc tài liệu có bất kỳ tag nào trong danh sách (OR)
        $tag_conditions = array();
        foreach ($selected_tags as $tag) {
            $tag_conditions[] = 't.tag_name = ?';
        }
        if (count($tag_conditions) > 0) {
            $where[] = '(' . implode(' OR ', $tag_conditions) . ')';
            $params = array_merge($params, $selected_tags);
        }
    }
    if ($selected_usernames) {
        $where[] = 'u.username IN (' . str_repeat('?,', count($selected_usernames) - 1) . '?)';
        $params = array_merge($params, $selected_usernames);
    }
    $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    if (!empty($departments)) {
        $sql = "SELECT d.*, s.subject_name, dep.department_name, u.username
            FROM documents d
            LEFT JOIN subjects s ON d.subject_id = s.subject_id
            LEFT JOIN departments dep ON d.department_id = dep.department_id
            LEFT JOIN document_tags dt ON d.doc_id = dt.doc_id
            LEFT JOIN tags t ON dt.tag_id = t.tag_id
            LEFT JOIN users u ON d.user_id = u.user_id
            $where_sql
            GROUP BY d.doc_id
            ORDER BY d.upload_date DESC
            LIMIT 100";
    } else {
        $sql = "SELECT d.*, s.subject_name, u.username
            FROM documents d
            LEFT JOIN subjects s ON d.subject_id = s.subject_id
            LEFT JOIN document_tags dt ON d.doc_id = dt.doc_id
            LEFT JOIN tags t ON dt.tag_id = t.tag_id
            LEFT JOIN users u ON d.user_id = u.user_id
            $where_sql
            GROUP BY d.doc_id
            ORDER BY d.upload_date DESC
            LIMIT 100";
    }
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
                <label class="form-label">Tags (có thể nhập nhiều, cách nhau bởi dấu phẩy)</label>
                <input type="text" name="tags" class="form-control" list="tags-list" value="<?= htmlspecialchars(implode(',', $_GET['tags'] ?? [])) ?>" placeholder="Nhập tag...">
                <datalist id="tags-list">
                    <?php foreach ($tags as $tag): ?>
                        <option value="<?= htmlspecialchars($tag) ?>">
                        <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-2">
                <label class="form-label">Môn học</label>
                <input type="text" name="subjects" class="form-control" list="subjects-list" value="<?= htmlspecialchars($_GET['subjects'][0] ?? $_GET['subjects'] ?? '') ?>" placeholder="Nhập môn học...">
                <datalist id="subjects-list">
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= htmlspecialchars($subject) ?>">
                        <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-2">
                <label class="form-label">Khoa</label>
                <input type="text" name="departments" class="form-control" list="departments-list" value="<?= htmlspecialchars($_GET['departments'][0] ?? $_GET['departments'] ?? '') ?>" placeholder="Nhập khoa...">
                <datalist id="departments-list">
                    <?php foreach ($departments as $dep): ?>
                        <option value="<?= htmlspecialchars($dep) ?>">
                        <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-2">
                <label class="form-label">Người đăng</label>
                <input type="text" name="usernames" class="form-control" list="usernames-list" value="<?= htmlspecialchars($_GET['usernames'][0] ?? $_GET['usernames'] ?? '') ?>" placeholder="Nhập username...">
                <datalist id="usernames-list">
                    <?php foreach ($usernames as $username): ?>
                        <option value="<?= htmlspecialchars($username) ?>">
                        <?php endforeach; ?>
                </datalist>
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
                    <?php if (isset($doc['department_name'])): ?>
                        <span class="badge bg-warning ms-2">Khoa: <?= htmlspecialchars($doc['department_name']) ?></span>
                    <?php endif; ?>
                    <span class="badge bg-success ms-2">Người đăng: <?= htmlspecialchars($doc['username'] ?? '') ?></span>
                    <span class="text-muted ms-2">Ngày: <?= !empty($doc['upload_date']) ? date('d/m/Y', strtotime($doc['upload_date'])) : '' ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)): ?>
        <div class="alert alert-warning">Không tìm thấy kết quả phù hợp.</div>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>