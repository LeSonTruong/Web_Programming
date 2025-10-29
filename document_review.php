<?php
include 'includes/db.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

session_start();

$edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
if (!$edit_id) {
    http_response_code(400);
    echo "Missing edit_id";
    exit();
}

$stmt = $conn->prepare(
    "SELECT e.*, d.doc_id AS orig_doc_id, d.title AS orig_title, d.author_name AS orig_author_name, d.description AS orig_description, d.file_path AS orig_file_path, d.subject_id AS orig_subject_id, d.user_id AS orig_user_id
     FROM document_edits e
     JOIN documents d ON e.doc_id = d.doc_id
     WHERE e.edit_id = ? LIMIT 1"
);
$stmt->execute([$edit_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    include __DIR__ . '/!404.php';
    exit();
}

$doc_id = (int)$row['doc_id'];

// Basic access check: admin or owner of the original document
$has_access = (
    (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
    (isset($_SESSION['user_id']) && isset($row['orig_user_id']) && (int)$row['orig_user_id'] === (int)$_SESSION['user_id'])
);
if (!$has_access) {
    http_response_code(403);
    echo "Forbidden";
    exit();
}

// Helper: get subject name
function subject_name($conn, $id) {
    if (empty($id)) return '';
    $s = $conn->prepare("SELECT subject_name FROM subjects WHERE subject_id = ? LIMIT 1");
    $s->execute([$id]);
    return $s->fetchColumn() ?: '';
}

$orig = [
    'title' => $row['orig_title'] ?? '',
    'author_name' => $row['orig_author_name'] ?? '',
    'description' => $row['orig_description'] ?? '',
    'file_path' => $row['orig_file_path'] ?? '',
    'subject_id' => $row['orig_subject_id'] ?? null,
];

$edit = [
    'title' => $row['title'] ?? '',
    'author_name' => $row['author_name'] ?? '',
    'description' => $row['description'] ?? '',
    'file_path' => $row['file_path'] ?? '',
    'subject_id' => $row['subject_id'] ?? null,
];

// Prepare human-friendly subject names
$orig['subject_name'] = subject_name($conn, $orig['subject_id']);
$edit['subject_name'] = subject_name($conn, $edit['subject_id']);

// Determine which fields differ
$fields = ['title', 'author_name', 'description', 'subject_id', 'file_path'];
$diffs = [];
foreach ($fields as $f) {
    if ($f === 'subject_id') {
        $origVal = (string)($orig['subject_id'] ?? '');
        $editVal = (string)($edit['subject_id'] ?? '');
        if ($origVal !== $editVal) {
            $diffs[] = $f;
        }
    } elseif ($f === 'file_path') {
        $origVal = trim((string)$orig['file_path']);
        $editVal = trim((string)$edit['file_path']);
        if ($origVal !== $editVal) $diffs[] = $f;
    } else {
        $origVal = trim((string)$orig[$f]);
        $editVal = trim((string)$edit[$f]);
        if ($origVal !== $editVal) $diffs[] = $f;
    }
}

// If no diffs, inform the reviewer
include 'includes/header.php';
?>
<div class="container my-4">
    <h2>So sánh bản chỉnh sửa (edit_id = <?= htmlspecialchars($edit_id) ?>) với bản đang công khai (doc_id = <?= htmlspecialchars($doc_id) ?>)</h2>
    <p class="text-muted">Chỉ hiển thị những trường có khác nhau.</p>

    <?php if (empty($diffs)): ?>
        <div class="alert alert-info">Không có sự khác biệt giữa bản sửa và bản công khai.</div>
    <?php else: ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Trường</th>
                    <th>Bản công khai</th>
                    <th>Bản sửa (edit)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (in_array('title', $diffs)): ?>
                    <tr>
                        <td>Tiêu đề (title)</td>
                        <td><?= htmlspecialchars($orig['title']) ?></td>
                        <td><?= htmlspecialchars($edit['title']) ?></td>
                    </tr>
                <?php endif; ?>

                <?php if (in_array('author_name', $diffs)): ?>
                    <tr>
                        <td>Tác giả (author_name)</td>
                        <td><?= htmlspecialchars($orig['author_name']) ?></td>
                        <td><?= htmlspecialchars($edit['author_name']) ?></td>
                    </tr>
                <?php endif; ?>

                <?php if (in_array('subject_id', $diffs)): ?>
                    <tr>
                        <td>Môn học (subject)</td>
                        <td><?= htmlspecialchars($orig['subject_name'] ?: '---') ?> (ID: <?= htmlspecialchars($orig['subject_id']) ?>)</td>
                        <td><?= htmlspecialchars($edit['subject_name'] ?: '---') ?> (ID: <?= htmlspecialchars($edit['subject_id']) ?>)</td>
                    </tr>
                <?php endif; ?>

                <?php if (in_array('description', $diffs)): ?>
                    <tr>
                        <td>Mô tả (description)</td>
                        <td style="white-space:pre-wrap;"><?= nl2br(htmlspecialchars($orig['description'])) ?></td>
                        <td style="white-space:pre-wrap;"><?= nl2br(htmlspecialchars($edit['description'])) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (in_array('file_path', $diffs)): ?>
            <h4>So sánh file</h4>
            <div class="row">
                <div class="col-md-6">
                    <h5>Bản công khai</h5>
                    <?php
                    $orig_fp = $orig['file_path'];
                    if (!$orig_fp) {
                        echo '<p class="text-muted">(Không có file)</p>';
                    } else {
                        $orig_url = (stripos($orig_fp, 'http://') === 0 || stripos($orig_fp, 'https://') === 0) ? $orig_fp : 'https://studyshare.banhgao.net/' . ltrim($orig_fp, '/');
                        echo '<p><a class="btn btn-outline-primary" href="' . htmlspecialchars($orig_url) . '" target="_blank" rel="noopener">Tải bản công khai</a></p>';
                    }
                    ?>
                </div>
                <div class="col-md-6">
                    <h5>Bản sửa (edit)</h5>
                    <?php
                    $edit_fp = $edit['file_path'];
                    if (!$edit_fp) {
                        echo '<p class="text-muted">(Không có file)</p>';
                    } else {
                        $edit_url = (stripos($edit_fp, 'http://') === 0 || stripos($edit_fp, 'https://') === 0) ? $edit_fp : 'https://studyshare.banhgao.net/' . ltrim($edit_fp, '/');
                        echo '<p><a class="btn btn-outline-primary" href="' . htmlspecialchars($edit_url) . '" target="_blank" rel="noopener">Tải bản sửa</a></p>';
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <div class="mt-4">
        <a href="document_view.php?id=<?= htmlspecialchars($doc_id) ?>" class="btn btn-secondary">Xem tài liệu công khai</a>
    </div>
</div>

<?php include 'includes/footer.php';
