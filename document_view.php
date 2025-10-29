<?php
include 'includes/db.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$khoabinhluan = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT comment_locked FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $khoabinhluan = $stmt->fetchColumn() == 1;
}

$khongtuongtac = $khoabinhluan || !isset($_SESSION['user_id']) || empty($_SESSION['user_id']);

$doc_id = (int)($_POST['doc_id'] ?? $_GET['id'] ?? 0);

// ===== LẤY THÔNG TIN TÀI LIỆU =====
$tagsStmt = $conn->prepare("SELECT t.tag_name FROM document_tags dt JOIN tags t ON dt.tag_id = t.tag_id WHERE dt.doc_id = ?");
$tagsStmt->execute([$doc_id]);
$doc_tags = $tagsStmt->fetchAll(PDO::FETCH_COLUMN);
$stmt = $conn->prepare("
    SELECT d.*, u.username, s.subject_name,
        SUM(CASE WHEN r.review_type = 'positive' THEN 1 ELSE 0 END) AS positive_count,
        SUM(CASE WHEN r.review_type = 'negative' THEN 1 ELSE 0 END) AS negative_count
    FROM documents d
    JOIN users u ON d.user_id = u.user_id
    LEFT JOIN subjects s ON d.subject_id = s.subject_id
    LEFT JOIN reviews r ON d.doc_id = r.doc_id
    WHERE d.doc_id = ?
    GROUP BY d.doc_id
");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

$has_access = (
    (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
    (isset($_SESSION['user_id']) && isset($doc['user_id']) && (int)$doc['user_id'] === (int)$_SESSION['user_id'])
);

if (!$doc || ($doc['status_id'] != 2 && !$has_access)) {
     http_response_code(404);
    include __DIR__ . '/!404.php';
    exit();
}

include 'includes/header.php';

// Tăng lượt xem mỗi lần truy cập
if ($doc_id) {
    $conn->prepare("UPDATE documents SET views = views + 1 WHERE doc_id = ?")->execute([$doc_id]);
}

// ===== TÍNH TỔNG ĐÁNH GIÁ =====
$total_reviews = ($doc['positive_count'] ?? 0) + ($doc['negative_count'] ?? 0);
$review_summary = "Chưa có đánh giá";
if ($total_reviews > 0) {
    $ratio = ($doc['positive_count'] ?? 0) / $total_reviews;
    $review_summary = $ratio >= 0.7 ? "Đánh giá tích cực" : ($ratio >= 0.4 ? "Đánh giá trung bình" : "Đánh giá tiêu cực");
}

// ===== ĐẾM LƯỢT TẢI =====
$countStmt = $conn->prepare("SELECT COUNT(*) AS total_downloads FROM downloads WHERE doc_id=?");
$countStmt->execute([$doc_id]);
$downloadData = $countStmt->fetch();
$total_downloads = $downloadData['total_downloads'] ?? 0;

// ===== XÁC ĐỊNH LOẠI FILE =====
$file = $doc['file_path'] ?? '';
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$file_url = 'https://studyshare.banhgao.net/' . $file;

// ===== LẤY TRẠNG THÁI REVIEW CỦA NGƯỜI DÙNG HIỆN TẠI =====
$user_review_type = '';
if (isset($_SESSION['user_id'])) {
    $reviewStmt = $conn->prepare("SELECT review_type FROM reviews WHERE user_id = ? AND doc_id = ? LIMIT 1");
    $reviewStmt->execute([$_SESSION['user_id'], $doc_id]);
    $user_review_type = $reviewStmt->fetchColumn() ?: '';
}

// ===== XỬ LÝ XÓA/SỬA BÌNH LUẬN =====
// ===== XÓA TAG KHỎI TÀI LIỆU (CHỈ ADMIN) =====
if (isset($_GET['delete_tag']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $tag_name = trim($_GET['delete_tag']);
    // Tìm tag_id
    $tagStmt = $conn->prepare("SELECT tag_id FROM tags WHERE tag_name = ? LIMIT 1");
    $tagStmt->execute([$tag_name]);
    $tag_id = $tagStmt->fetchColumn();
    if ($tag_id) {
        // Xóa liên kết tag khỏi document_tags
        $delTagStmt = $conn->prepare("DELETE FROM document_tags WHERE doc_id = ? AND tag_id = ?");
        $delTagStmt->execute([$doc_id, $tag_id]);
    }
    header("Location: document_view.php?id=$doc_id");
    exit();
}
if (isset($_GET['delete_comment']) && isset($_SESSION['user_id'])) {
    $comment_id = (int)$_GET['delete_comment'];
    $is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    // Chỉ admin hoặc chủ bình luận mới được xóa
    $stmt = $conn->prepare("SELECT user_id FROM comments WHERE comment_id=?");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch();
    if ($comment && ($is_admin || $comment['user_id'] == $_SESSION['user_id'])) {
        $delStmt = $conn->prepare("DELETE FROM comments WHERE comment_id=?");
        $delStmt->execute([$comment_id]);
    }
    header("Location: document_view.php?id=$doc_id");
    exit();
}

// Sửa bình luận
if (isset($_GET['edit_comment']) && isset($_SESSION['user_id'])) {
    $edit_id = (int)$_GET['edit_comment'];
    $stmt = $conn->prepare("SELECT * FROM comments WHERE comment_id=?");
    $stmt->execute([$edit_id]);
    $edit_comment = $stmt->fetch();
    $is_owner = $edit_comment && $edit_comment['user_id'] == $_SESSION['user_id'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_owner && isset($_POST['edit_content'])) {
        $new_content = trim($_POST['edit_content']);
        if ($new_content) {
            $upStmt = $conn->prepare("UPDATE comments SET content=? WHERE comment_id=?");
            $upStmt->execute([$new_content, $edit_id]);
        }
        header("Location: document_view.php?id=$doc_id");
        exit();
    }
}

// ===== XỬ LÝ BÌNH LUẬN MỚI =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['edit_comment'])) {
    // Bình luận
    if (isset($_POST['comment_content']) && isset($_SESSION['user_id'])) {
        $content = trim($_POST['comment_content']);
        if ($content) {
            $stmt = $conn->prepare("INSERT INTO comments (doc_id, user_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$doc_id, $_SESSION['user_id'], $content]);
        }
    }
    // Thêm tag mới nếu có (độc lập với bình luận)
    if (!empty($_POST['add_tag']) && isset($_SESSION['user_id'])) {
        $tags_input = trim($_POST['add_tag']);
        if ($tags_input) {
            $tags_arr = array_filter(array_map('trim', explode(',', $tags_input)));
            foreach ($tags_arr as $new_tag) {
                // Kiểm tra tag đã tồn tại chưa
                $tagStmt = $conn->prepare("SELECT tag_id FROM tags WHERE tag_name = ? LIMIT 1");
                $tagStmt->execute([$new_tag]);
                $tag_id = $tagStmt->fetchColumn();
                if (!$tag_id) {
                    // Thêm tag mới
                    $insertTagStmt = $conn->prepare("INSERT INTO tags (tag_name) VALUES (?)");
                    $insertTagStmt->execute([$new_tag]);
                    $tag_id = $conn->lastInsertId();
                }
                // Liên kết tag với tài liệu
                $linkStmt = $conn->prepare("INSERT IGNORE INTO document_tags (doc_id, tag_id) VALUES (?, ?)");
                $linkStmt->execute([$doc_id, $tag_id]);
            }
        }
    }
    header("Location: document_view.php?id=$doc_id");
    exit();
}
?>
<script>
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.classList.contains('edit-comment-form')) {
            e.preventDefault();
            const commentId = form.getAttribute('data-comment-id');
            const content = form.querySelector('textarea').value;
            fetch('edit_comment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'comment_id=' + encodeURIComponent(commentId) + '&content=' + encodeURIComponent(content)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Cập nhật nội dung bình luận trên giao diện
                        const commentContent = form.closest('.comment').querySelector('.comment-content');
                        commentContent.textContent = data.content;
                        // Hiển thị (Đã chỉnh sửa) nếu có
                        if (data.edited) {
                            let editedSpan = commentContent.parentElement.querySelector('.edited-label');
                            if (!editedSpan) {
                                editedSpan = document.createElement('span');
                                editedSpan.className = 'edited-label';
                                editedSpan.textContent = ' (Đã chỉnh sửa)';
                                commentContent.parentElement.appendChild(editedSpan);
                            }
                        }
                        form.parentElement.removeChild(form);
                    }
                });
            return;
        }
        if (form.classList.contains('comment-form')) {
            e.preventDefault();
            const formData = new FormData(form);
            fetch('action_add_comment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Tạo HTML cho bình luận mới
                        const commentList = document.querySelector('.container.my-4');
                        if (commentList) {
                            const newComment = document.createElement('div');
                            newComment.className = 'card mb-2 shadow-sm';
                            newComment.id = 'comment-' + data.comment_id;
                            newComment.innerHTML = `
                            <div class="card-body">
                                <strong><a href="profile.php?user=${encodeURIComponent(data.username)}" class="text-decoration-none">${data.username}</a></strong>
                                <small class="comment-time">${data.created_at} (Vừa xong)</small>
                                <div class="comment-content-area"><p>${data.content}</p></div>
                                <div class="d-flex gap-2 align-items-center">
                                    <button class="btn btn-sm btn-outline-primary like-comment-btn" data-id="${data.comment_id}">👍 <span class="like-count">0</span></button>
                                    <button class="btn btn-sm btn-outline-danger dislike-comment-btn" data-id="${data.comment_id}">👎 <span class="dislike-count">0</span></button>
                                    <button class="btn btn-sm btn-outline-secondary reply-comment-btn" data-id="${data.comment_id}">↩️ Phản hồi</button>
                                    <a href="?edit_comment=${data.comment_id}&id=${form.querySelector('[name=doc_id]').value}#comment-${data.comment_id}" class="btn btn-sm btn-warning">Sửa</a>
                                    <a href="?delete_comment=${data.comment_id}&id=${form.querySelector('[name=doc_id]').value}" class="btn btn-sm btn-danger" onclick="return confirm('Bạn chắc chắn muốn xóa bình luận này?');">Xóa</a>
                                </div>
                                <div class="reply-box mt-2" id="reply-box-${data.comment_id}" style="display:none;"></div>
                                <div class="replies-list ms-4 mt-2" id="replies-list-${data.comment_id}" style="display:none;"></div>
                            </div>
                        `;
                            commentList.insertBefore(newComment, commentList.querySelector('.card'));
                        }
                        form.reset();
                    }
                });
        }
    });
</script>
<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
include 'includes/db.php';


$doc_id = (int)($_POST['doc_id'] ?? $_GET['id'] ?? 0);


// ===== XỬ LÝ XÓA/SỬA BÌNH LUẬN =====
if (isset($_GET['delete_comment']) && isset($_SESSION['user_id'])) {
    $comment_id = (int)$_GET['delete_comment'];
    $is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    // Chỉ admin hoặc chủ bình luận mới được xóa
    $stmt = $conn->prepare("SELECT user_id FROM comments WHERE comment_id=?");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch();
    if ($comment && ($is_admin || $comment['user_id'] == $_SESSION['user_id'])) {
        $delStmt = $conn->prepare("DELETE FROM comments WHERE comment_id=?");
        $delStmt->execute([$comment_id]);
    }
    header("Location: document_view.php?id=$doc_id");
    exit();
}

// Sửa bình luận
if (isset($_GET['edit_comment']) && isset($_SESSION['user_id'])) {
    $edit_id = (int)$_GET['edit_comment'];
    $stmt = $conn->prepare("SELECT * FROM comments WHERE comment_id=?");
    $stmt->execute([$edit_id]);
    $edit_comment = $stmt->fetch();
    $is_owner = $edit_comment && $edit_comment['user_id'] == $_SESSION['user_id'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_owner && isset($_POST['edit_content'])) {
        $new_content = trim($_POST['edit_content']);
        if ($new_content) {
            $upStmt = $conn->prepare("UPDATE comments SET content=? WHERE comment_id=?");
            $upStmt->execute([$new_content, $edit_id]);
        }
        header("Location: document_view.php?id=$doc_id");
        exit();
    }
}

// ===== XỬ LÝ BÌNH LUẬN MỚI =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['edit_comment'])) {
    // Bình luận
    if (isset($_POST['comment_content']) && isset($_SESSION['user_id'])) {
        $content = trim($_POST['comment_content']);
        if ($content) {
            $stmt = $conn->prepare("INSERT INTO comments (doc_id, user_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$doc_id, $_SESSION['user_id'], $content]);
        }
        // Thêm tag mới nếu có
        if (!empty($_POST['add_tag'])) {
            $tags_input = trim($_POST['add_tag']);
            if ($tags_input) {
                $tags_arr = array_filter(array_map('trim', explode(',', $tags_input)));
                foreach ($tags_arr as $new_tag) {
                    // Kiểm tra tag đã tồn tại chưa
                    $tagStmt = $conn->prepare("SELECT tag_id FROM tags WHERE tag_name = ? LIMIT 1");
                    $tagStmt->execute([$new_tag]);
                    $tag_id = $tagStmt->fetchColumn();
                    if (!$tag_id) {
                        // Thêm tag mới
                        $insertTagStmt = $conn->prepare("INSERT INTO tags (tag_name) VALUES (?)");
                        $insertTagStmt->execute([$new_tag]);
                        $tag_id = $conn->lastInsertId();
                    }
                    // Liên kết tag với tài liệu
                    $linkStmt = $conn->prepare("INSERT IGNORE INTO document_tags (doc_id, tag_id) VALUES (?, ?)");
                    $linkStmt->execute([$doc_id, $tag_id]);
                }
            }
        }
    }
    header("Location: document_view.php?id=$doc_id");
    exit();
}

// ===== LẤY DANH SÁCH BÌNH LUẬN =====

// Phân trang bình luận

$comments_per_page = 10;
$comment_page = isset($_GET['comment_page']) ? max(1, (int)$_GET['comment_page']) : 1;
$offset = ($comment_page - 1) * $comments_per_page;
$comment_sort = ($_GET['comment_sort'] ?? 'desc') === 'desc' ? 'DESC' : 'ASC';

$like_sort = ($_GET['like_sort'] ?? '') === 'desc' ? 'DESC' : (($_GET['like_sort'] ?? '') === 'asc' ? 'ASC' : '');
$dislike_sort = ($_GET['dislike_sort'] ?? '') === 'desc' ? 'DESC' : (($_GET['dislike_sort'] ?? '') === 'asc' ? 'ASC' : '');
$search_user = trim($_GET['search_user'] ?? '');

// Lấy tổng số bình luận
$countStmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE doc_id=? AND parent_comment_id IS NULL");
$countStmt->execute([$doc_id]);
$total_parent_comments = (int)$countStmt->fetchColumn();
$total_comment_pages = max(1, ceil($total_parent_comments / $comments_per_page));


// Lấy bình luận + số lượt like, reply dạng cây
$order_by = [];
if ($like_sort) $order_by[] = "like_count $like_sort";
if ($dislike_sort) $order_by[] = "dislike_count $dislike_sort";
$order_by[] = "latest_activity $comment_sort";
$order_sql = implode(", ", $order_by);

$where_sql = "c.doc_id=? AND c.parent_comment_id IS NULL";
$params = [$doc_id];
if ($search_user) {
    $where_sql .= " AND u.username LIKE ?";
    $params[] = "%$search_user%";
}

$stmt = $conn->prepare("
    SELECT c.*, u.username,
        (SELECT COUNT(*) FROM comment_reacts cr WHERE cr.comment_id = c.comment_id AND cr.react = 1) AS like_count,
        (SELECT COUNT(*) FROM comment_reacts cr WHERE cr.comment_id = c.comment_id AND cr.react = 0) AS dislike_count,
        GREATEST(
            UNIX_TIMESTAMP(c.created_at),
            IFNULL((SELECT MAX(UNIX_TIMESTAMP(created_at)) FROM comments r WHERE r.parent_comment_id = c.comment_id), 0)
        ) AS latest_activity
    FROM comments c
    JOIN users u ON c.user_id = u.user_id
    WHERE $where_sql
    ORDER BY $order_sql
    LIMIT $comments_per_page OFFSET $offset
");
$stmt->execute($params);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy replies cho mỗi comment
// Chỉ lấy reply cho comment gốc
$replyStmt = $conn->prepare("
    SELECT c.*, u.username,
        (SELECT COUNT(*) FROM comment_reacts cr WHERE cr.comment_id = c.comment_id AND cr.react = 1) AS like_count,
        (SELECT COUNT(*) FROM comment_reacts cr WHERE cr.comment_id = c.comment_id AND cr.react = 0) AS dislike_count
    FROM comments c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.doc_id=? AND c.parent_comment_id IS NOT NULL AND (SELECT parent_comment_id FROM comments WHERE comment_id=c.parent_comment_id) IS NULL
    ORDER BY c.created_at ASC
");
$replyStmt->execute([$doc_id]);
$all_replies = $replyStmt->fetchAll(PDO::FETCH_ASSOC);
$replies_by_comment = [];
foreach ($comments as $c) {
    $replies_by_comment[$c['comment_id']] = [];
}
foreach ($all_replies as $r) {
    $replies_by_comment[$r['parent_comment_id']][] = $r;
}
?>

<div class="container my-4">
    <?php if ($has_access && $doc['status_id'] != 2): ?>
        <div class="alert alert-warning border-warning">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <div>
                    <strong>LƯU Ý:</strong>
                    <span>
                        Tài liệu này chưa được duyệt - chỉ người tải lên và quản trị viên mới có thể xem tài liệu này.
                    </span>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="mb-0"><?= htmlspecialchars($doc['title'] ?? '') ?></h2>

        <div class="mb-3 d-flex flex-column align-items-end" style="min-height:40px;">
            <div class="d-flex align-items-center flex-wrap gap-2" style="justify-content: flex-end;">
                <strong class="me-2">Tag:</strong>
                <?php if ($doc_tags): ?>
                    <div id="all-tags" class="d-flex flex-wrap gap-1 align-items-center">
                        <?php foreach ($doc_tags as $tag): ?>
                            <span class="badge tag-badge px-2 py-1 mb-1">#<?= htmlspecialchars($tag) ?>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                    <a href="document_view.php?id=<?= $doc_id ?>&delete_tag=<?= urlencode($tag) ?>" class="text-danger ms-1" title="Xóa tag">&times;</a>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <button id="add-tag-btn" class="btn btn-success btn-sm ms-2 px-2 py-1" style="font-size:1em;">+</button>
                        <?php endif; ?>
                        </div>
                <?php else: ?>
                    <span class="text-muted no-tag-text">Chưa có tag</span>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <button id="add-tag-btn" class="btn btn-success btn-sm ms-2 px-2 py-1" style="font-size:1em;">+</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <form id="add-tag-form" method="post" class="mt-2 w-100 position-relative" autocomplete="off" style="display:none;max-width:300px;">
                <label class="form-label mb-1">Bổ sung tag cho tài liệu này (chọn nhiều, nhấn Enter hoặc dấu phẩy để thêm):</label>
                <div id="tags-container" class="d-flex flex-wrap gap-1 mb-2"></div>
                <input type="text" id="tags-input" class="form-control form-control-sm mb-1" autocomplete="off" placeholder="Nhập tag...">
                <div id="tags-suggestions" class="list-group position-absolute w-100" style="z-index:10; display:none;"></div>
                <input type="hidden" name="add_tag" id="tags-hidden">
                <input type="hidden" name="doc_id" value="<?= $doc_id ?>">
                <button type="submit" class="btn btn-primary btn-sm mt-2">Thêm tag</button>
            </form>
            <script>
                // Hiện form thêm tag khi bấm nút +
                document.addEventListener('DOMContentLoaded', function() {
                    const addTagBtn = document.getElementById('add-tag-btn');
                    const addTagForm = document.getElementById('add-tag-form');
                    if (addTagBtn && addTagForm) {
                        addTagBtn.onclick = function(e) {
                            e.preventDefault();
                            if (addTagForm.style.display === '' || addTagForm.style.display === 'block') {
                                addTagForm.style.display = 'none';
                            } else {
                                addTagForm.style.display = '';
                                addTagForm.querySelector('#tags-input').focus();
                            }
                        };
                    }
                });
                // Đảm bảo khi submit form, input hidden chứa danh sách tags đã chọn
                document.getElementById('add-tag-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    tagsHidden.value = selectedTags.join(',');
                    const formData = new FormData(this);
                    fetch('', {
                        method: 'POST',
                        body: formData
                    }).then(res => {
                        if (res.ok) {
                            location.reload();
                        }
                    });
                });
            </script>
            <script>
                <?php
                // Lấy danh sách tags từ DB
                $tagsList = $conn->query("SELECT tag_name FROM tags ORDER BY tag_name")->fetchAll(PDO::FETCH_COLUMN);
                ?>
                const allTags = <?php echo json_encode($tagsList); ?>;
                const tagsInput = document.getElementById('tags-input');
                const tagsContainer = document.getElementById('tags-container');
                const tagsHidden = document.getElementById('tags-hidden');
                const tagsSuggestions = document.getElementById('tags-suggestions');
                let selectedTags = [];

                function updateTagsUI() {
                    tagsContainer.innerHTML = '';
                    selectedTags.forEach(tag => {
                        const tagEl = document.createElement('span');
                        tagEl.className = 'badge bg-primary text-light px-2 py-1 mb-1';
                        tagEl.textContent = '#' + tag;
                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'btn-close btn-close-white btn-sm ms-1';
                        removeBtn.style.fontSize = '0.7em';
                        removeBtn.onclick = () => {
                            selectedTags = selectedTags.filter(t => t !== tag);
                            updateTagsUI();
                        };
                        tagEl.appendChild(removeBtn);
                        tagsContainer.appendChild(tagEl);
                    });
                    tagsHidden.value = selectedTags.join(',');
                }

                function showSuggestions(val) {
                    tagsSuggestions.innerHTML = '';
                    if (!val) {
                        tagsSuggestions.style.display = 'none';
                        return;
                    }
                    const filtered = allTags.filter(tag => tag.toLowerCase().includes(val.toLowerCase()) && !selectedTags.includes(tag));
                    if (filtered.length === 0) {
                        tagsSuggestions.style.display = 'none';
                        return;
                    }
                    filtered.forEach(tag => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action';
                        item.textContent = tag;
                        item.onclick = () => {
                            addTag(tag);
                            tagsSuggestions.style.display = 'none';
                            tagsInput.value = '';
                        };
                        tagsSuggestions.appendChild(item);
                    });
                    tagsSuggestions.style.display = '';
                }

                function addTag(tag) {
                    tag = tag.trim();
                    if (!tag) return;
                    // Cho phép thêm tag mới, không cần phải có trong allTags
                    if (!selectedTags.includes(tag)) {
                        selectedTags.push(tag);
                        updateTagsUI();
                    }
                }

                tagsInput.addEventListener('input', function() {
                    showSuggestions(this.value);
                });

                tagsInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ',') {
                        e.preventDefault();
                        let val = this.value.trim().replace(/,$/, '');
                        if (val) addTag(val);
                        this.value = '';
                        showSuggestions(this.value);
                    } else if (e.key === 'Escape') {
                        tagsSuggestions.style.display = 'none';
                    }
                });

                document.addEventListener('click', function(e) {
                    if (!tagsSuggestions.contains(e.target) && e.target !== tagsInput) {
                        tagsSuggestions.style.display = 'none';
                    }
                });

                // Nếu có giá trị cũ (ví dụ khi submit lỗi), khôi phục lại
                window.addEventListener('DOMContentLoaded', function() {
                    const oldTags = tagsHidden.value;
                    if (oldTags) {
                        selectedTags = oldTags.split(',').map(t => t.trim()).filter(t => t);
                        updateTagsUI();
                    }
                });
            </script>
        </div>
    </div>
    <p><strong>Môn học:</strong> <?= htmlspecialchars($doc['subject_name'] ?? '') ?></p>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <strong>Người đăng:</strong> <a href="profile.php?user=<?= urlencode($doc['username']) ?>" class="fw-bold text-primary"><?= htmlspecialchars($doc['username'] ?? '') ?></a>
        </div>
    </div>
    <p><strong>Mô tả:</strong> <?= nl2br(htmlspecialchars($doc['description'] ?? '')) ?></p>
    <?php if (isset($doc['summary']) && $doc['summary'] !== null && trim($doc['summary']) !== ''): ?>
    <details style="margin-bottom:10px">
        <summary style="cursor:pointer;font-weight:bold;color:#007bff">Tóm tắt văn bản</summary>
        <div style="padding:8px 0 0 16px;white-space:pre-line;"><?= nl2br(htmlspecialchars($doc['summary'])) ?></div>
    </details>
    <?php endif; ?>
    <p><strong>Đánh giá:</strong> <span id="review-summary-text"><?= $review_summary ?></span> (👍 <span id="like-count"><?= $doc['positive_count'] ?? 0 ?></span> | 👎 <span id="dislike-count"><?= $doc['negative_count'] ?? 0 ?></span>)</p>
    <p><strong>Lượt xem:</strong> <?= number_format($doc['views'] ?? 0) ?></p>
    <p><strong>Lượt tải:</strong> <?= $total_downloads ?></p>
    <p><strong>Ngày đăng:</strong> <?= date('d/m/Y H:i', strtotime($doc['upload_date'])) ?></p>
<?php if (!empty($doc['update_at'])): ?>
    <p><strong>Đã chỉnh sửa:</strong> <?= date('d/m/Y H:i', strtotime($doc['update_at'])) ?></p>
<?php endif; ?>
    <!-- Nút đánh giá AJAX -->
    <div class="mb-3">
        <?php if (isset($_SESSION['user_id'])): ?>
            <button id="like-btn" class="btn btn-success me-2<?= ($user_review_type === 'positive' ? ' active' : '') ?>">👍 Thích</button>
            <button id="dislike-btn" class="btn btn-danger<?= ($user_review_type === 'negative' ? ' active' : '') ?>">👎 Không thích</button>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const likeBtn = document.getElementById('like-btn');
            const dislikeBtn = document.getElementById('dislike-btn');
            const likeCount = document.getElementById('like-count');
            const dislikeCount = document.getElementById('dislike-count');
            const docId = <?= json_encode($doc['doc_id']) ?>;
            let userReviewType = <?= json_encode($user_review_type) ?>;

            function updateButtonState() {
                if (userReviewType === 'positive') {
                    likeBtn.classList.add('active');
                    dislikeBtn.classList.remove('active');
                } else if (userReviewType === 'negative') {
                    dislikeBtn.classList.add('active');
                    likeBtn.classList.remove('active');
                } else {
                    likeBtn.classList.remove('active');
                    dislikeBtn.classList.remove('active');
                }
            }

            function sendReview(type, undo = false) {
                let reviewType = type;
                if (undo) reviewType = 'none';
                fetch('action_doc_review.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: 'doc_id=' + encodeURIComponent(docId) + '&review_type=' + encodeURIComponent(reviewType)
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            likeCount.textContent = data.positive_count;
                            dislikeCount.textContent = data.negative_count;
                            userReviewType = (reviewType === 'none') ? '' : type;
                            updateButtonState();
                            // Tính lại review_summary
                            const total = data.positive_count + data.negative_count;
                            let summary = 'Chưa có đánh giá';
                            if (total > 0) {
                                const ratio = data.positive_count / total;
                                if (ratio >= 0.7) summary = 'Đánh giá tích cực';
                                else if (ratio >= 0.4) summary = 'Đánh giá trung bình';
                                else summary = 'Đánh giá tiêu cực';
                            }
                            document.getElementById('review-summary-text').textContent = summary;
                        }
                    });
            }
            if (likeBtn) likeBtn.onclick = function() {
                if (userReviewType === 'positive') {
                    // Undo like
                    sendReview('positive', true);
                } else {
                    sendReview('positive');
                }
            };
            if (dislikeBtn) dislikeBtn.onclick = function() {
                if (userReviewType === 'negative') {
                    // Undo dislike
                    sendReview('negative', true);
                } else {
                    sendReview('negative');
                }
            };
            updateButtonState();
        });
    </script>

    <!-- Universal Viewer -->
    <div class="file-viewer my-3" style="min-height:600px;">
        <?php if (in_array($ext, ['pdf'])): ?>
            <embed src="<?= htmlspecialchars($file) ?>" type="application/pdf" width="100%" height="600px" />
        <?php elseif (in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])): ?>
            <iframe src="https://view.officeapps.live.com/op/embed.aspx?src=<?= urlencode($file_url) ?>" width="100%" height="600px" frameborder="0"></iframe>
        <?php elseif (in_array($ext, ['py', 'js', 'cpp', 'c', 'java', 'html', 'css', 'ipynb'])): ?>
            <iframe src="code_viewer.php?file=<?= urlencode($file) ?>" width="100%" height="600px" frameborder="0"></iframe>
        <?php elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])): ?>
            <img src="<?= htmlspecialchars($file) ?>" class="img-fluid" alt="Image document" />
        <?php else: ?>
            <p>📄 File không thể xem trực tiếp. Bạn có thể tải xuống để mở.</p>
        <?php endif; ?>
    </div>

    <!-- Nút tải xuống -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="download.php?id=<?= $doc['doc_id'] ?? 0 ?>" class="btn btn-primary mb-3">📥 Tải xuống</a>
    <?php else: ?>
        <div class="alert alert-warning">⚠️ Hãy <a href="login.php">đăng nhập</a> hoặc <a href="register.php">tạo tài khoản</a> để tải và tương tác trên tài liệu này.</div>
    <?php endif; ?>

    <hr>


    <?php if (isset($_SESSION['user_id'])): ?>
        <?php if ($khoabinhluan): ?>
            <div class="alert alert-warning mb-3">⚠️ Tài khoản của bạn đã bị khóa bình luận: bạn không thể gửi, sửa hoặc tương tác với bình luận.</div>
        <?php else: ?>
            <?php if (isset($edit_comment) && $edit_comment && $edit_comment['user_id'] == $_SESSION['user_id']): ?>
                <form method="post" class="mb-4">
                    <input type="hidden" name="doc_id" value="<?= htmlspecialchars($doc['doc_id']) ?>">
                    <textarea name="edit_content" class="form-control mb-2" rows="3" required><?= htmlspecialchars($edit_comment['content']) ?></textarea>
                    <button class="btn btn-warning">Cập nhật bình luận</button>
                    <a href="document_view.php?id=<?= htmlspecialchars($doc['doc_id']) ?>" class="btn btn-secondary">Hủy</a>
                </form>
            <?php else: ?>
                <form method="post" class="mb-4">
                    <input type="hidden" name="doc_id" value="<?= htmlspecialchars($doc['doc_id']) ?>">
                    <textarea name="comment_content" class="form-control mb-2" rows="3" placeholder="Viết bình luận..." required></textarea>
                    <button class="btn btn-success">Gửi bình luận</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Hiển thị bình luận -->
  <?php if (!$comments): ?>
        <div class="alert alert-info">Chưa có bình luận nào.</div>
    <?php else: ?>
        <?php
        // Hàm tính khoảng thời gian đã qua
        function timeAgo($datetime)
        {
            $time = strtotime($datetime);
            $now = time();
            $diff = $now - $time;
            if ($diff < 3600) return ($diff < 0 || $diff < 60) ? '1 phút trước' : floor($diff / 60) . ' phút trước';
            if ($diff < 86400) return ($diff < 7200 ? '1 giờ trước' : floor($diff / 3600) . ' giờ trước');
            if ($diff < 31536000) return ($diff < 172800 ? '1 ngày trước' : floor($diff / 86400) . ' ngày trước');
            return (floor($diff / 31536000) < 2 ? '1 năm trước' : floor($diff / 31536000) . ' năm trước');
        }
        ?>
        <?php foreach ($comments as $c): ?>
            <div class="card mb-2 shadow-sm" id="comment-<?= (int)$c['comment_id'] ?>">
                <div class="card-body">
                    <strong><a href="profile.php?user=<?= urlencode($c['username']) ?>" class="text-decoration-none"><?= htmlspecialchars($c['username']) ?></a></strong>
                    <small class="comment-time">
                        <?= date("H:i", strtotime($c['created_at'])) ?> Ngày <?= date("d/m/Y", strtotime($c['created_at'])) ?> (<?= timeAgo($c['created_at']) ?>)
                        <?php if (!empty($c['edited_at'])): ?> <span class="text-warning ms-2">(Đã chỉnh sửa)</span> <?php endif; ?>
                    </small>

                    <div class="comment-content-area">
                        <?php
                        $is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $c['user_id'];
                        // Nếu người xem bị khóa bình luận thì không hiện form edit inline
                        if (!$khoabinhluan && isset($_GET['edit_comment']) && $_GET['edit_comment'] == $c['comment_id'] && $is_owner): ?>
                            <form class="edit-comment-form" method="post" style="margin-bottom:0;">
                                <input type="hidden" name="doc_id" value="<?= htmlspecialchars($doc['doc_id']) ?>">
                                <textarea name="edit_content" class="form-control mb-2" rows="3" required><?= htmlspecialchars($c['content']) ?></textarea>
                                <button class="btn btn-warning btn-sm">Cập nhật</button>
                                <a href="document_view.php?id=<?= htmlspecialchars($doc['doc_id']) ?>" class="btn btn-secondary btn-sm">Hủy</a>
                            </form>
                        <?php else: ?>
                            <p><?= nl2br(htmlspecialchars($c['content'])) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2 align-items-center">
                        <?php $disabledAttr = $khongtuongtac ? 'disabled' : ''; ?>
                        <button <?= $disabledAttr ?> class="btn btn-sm btn-outline-primary like-comment-btn" data-id="<?= (int)$c['comment_id'] ?>">👍 <span class="like-count"><?= (int)($c['like_count'] ?? 0) ?></span></button>
                        <button <?= $disabledAttr ?> class="btn btn-sm btn-outline-danger dislike-comment-btn" data-id="<?= (int)$c['comment_id'] ?>">👎 <span class="dislike-count"><?= (int)($c['dislike_count'] ?? 0) ?></span></button>
                        <button <?= $disabledAttr ?> class="btn btn-sm btn-outline-secondary reply-comment-btn" data-id="<?= (int)$c['comment_id'] ?>" <?= $khongtuongtac ? 'data-disabled="1"' : '' ?>>↩️ Phản hồi</button>

                        <?php
                        // Tìm reply mới nhất cho comment này
                        $latest_reply = null;
                        if (!empty($replies_by_comment[$c['comment_id']])) {
                            $latest_reply = array_reduce($replies_by_comment[$c['comment_id']], function ($a, $b) {
                                return (strtotime($a['created_at']) > strtotime($b['created_at'])) ? $a : $b;
                            }, $replies_by_comment[$c['comment_id']][0]);
                        }
                        if ($latest_reply): ?>
                            <span class="reply-info">Phản hồi mới nhất: <?= date("H:i", strtotime($latest_reply['created_at'])) ?> Ngày <?= date("d/m/Y", strtotime($latest_reply['created_at'])) ?> (<?= timeAgo($latest_reply['created_at']) ?>)</span>
                        <?php endif; ?>

                        <?php if (!$khongtuongtac && $is_owner): ?>
                            <a href="?edit_comment=<?= (int)$c['comment_id'] ?>&id=<?= (int)$doc['doc_id'] ?>#comment-<?= (int)$c['comment_id'] ?>" class="btn btn-sm btn-warning">Sửa</a>
                        <?php endif; ?>

                        <?php if (!$khongtuongtac && ($is_owner || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'))): ?>
                            <a href="?delete_comment=<?= (int)$c['comment_id'] ?>&id=<?= (int)$doc['doc_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn chắc chắn muốn xóa bình luận này?');">Xóa</a>
                        <?php endif; ?>
                    </div>

                    <div class="reply-box mt-2" id="reply-box-<?= (int)$c['comment_id'] ?>" style="display:none;"></div>

                    <?php $reply_count = !empty($replies_by_comment[$c['comment_id']]) ? count($replies_by_comment[$c['comment_id']]) : 0; ?>
                    <?php if ($reply_count > 0): ?>
                        <button class="btn btn-link show-replies-btn p-0 ms-2" data-id="<?= (int)$c['comment_id'] ?>">
                            <?= $reply_count ?> Phản hồi
                        </button>
                    <?php endif; ?>

                    <div class="replies-list ms-4 mt-2" id="replies-list-<?= (int)$c['comment_id'] ?>" style="display:none;">
                        <?php if ($reply_count > 0): ?>
                            <?php foreach ($replies_by_comment[$c['comment_id']] as $r): ?>
                                <div class="card mb-1 border-info">
                                    <div class="card-body py-2">
                                        <strong><a href="profile.php?user=<?= urlencode($r['username']) ?>" class="text-decoration-none"><?= htmlspecialchars($r['username']) ?></a></strong>
                                        <small class="comment-time">
                                            <?= date("H:i", strtotime($r['created_at'])) ?> Ngày <?= date("d/m/Y", strtotime($r['created_at'])) ?> (<?= timeAgo($r['created_at']) ?>)
                                            <?php if (!empty($r['edited_at'])): ?> <span class="text-warning ms-2">(Đã chỉnh sửa)</span> <?php endif; ?>
                                        </small>
                                        <p class="mb-1"><?= nl2br(htmlspecialchars($r['content'])) ?></p>
                                        <button <?= $disabledAttr ?> class="btn btn-sm btn-outline-primary like-comment-btn" data-id="<?= (int)$r['comment_id'] ?>">👍 <span class="like-count"><?= (int)($r['like_count'] ?? 0) ?></span></button>
                                        <button <?= $disabledAttr ?> class="btn btn-sm btn-outline-danger dislike-comment-btn" data-id="<?= (int)$r['comment_id'] ?>">👎 <span class="dislike-count"><?= (int)($r['dislike_count'] ?? 0) ?></span></button>
                                        <?php if (!$khongtuongtac && $is_owner): ?>
                                            <a href="?edit_comment=<?= (int)$c['comment_id'] ?>&id=<?= (int)$doc['doc_id'] ?>#comment-<?= (int)$c['comment_id'] ?>" class="btn btn-sm btn-warning">Sửa</a>
                                        <?php endif; ?>
                                        <?php if (!$khongtuongtac && ($is_owner || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'))): ?>
                                            <a href="?delete_comment=<?= (int)$c['comment_id'] ?>&id=<?= (int)$doc['doc_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn chắc chắn muốn xóa bình luận này?');">Xóa</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</div>
<!-- Kết thúc foreach comments -->
<!-- Phân trang bình luận -->
<?php if ($total_comment_pages > 1) { ?>
    <nav aria-label="Comment pagination">
        <ul class="pagination justify-content-center mt-3">
            <?php for ($i = 1; $i <= $total_comment_pages; $i++) { ?>
                <li class="page-item<?= $i == $comment_page ? ' active' : '' ?>">
                    <a class="page-link" href="?id=<?= $doc_id ?>&comment_page=<?= $i ?>#comments"><?= $i ?></a>
                </li>
            <?php } ?>
        </ul>
    </nav>
<?php } ?>
</div>

<div class="container mt-4 pt-5">
    <!-- Modal báo cáo bình luận -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reportModalLabel">Báo cáo bình luận</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="report-form">
                        <input type="hidden" name="comment_id" id="report-comment-id">
                        <div class="mb-3">
                            <label for="report-username" class="form-label">Người dùng</label>
                            <input type="text" class="form-control" id="report-username" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="report-reason" class="form-label">Lý do báo cáo</label>
                            <textarea class="form-control" id="report-reason" name="reason" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger">Gửi báo cáo</button>
                    </form>
                    <div id="report-success" class="alert alert-success mt-2" style="display:none;">Đã gửi báo cáo thành công!</div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .no-tag-text {
            color: #6c757d;
            transition: color 0.2s;
        }

        body.dark-mode .no-tag-text {
            color: #ffeb3b !important;
            text-shadow: 0 1px 4px #222;
            font-weight: bold;
        }

        .tag-badge {
            background: linear-gradient(90deg, #007bff 60%, #00c6ff 100%);
            color: #fff !important;
            font-weight: 500;
            border: none;
        }

        body.dark-mode .tag-badge {
            background: linear-gradient(90deg, #ffd700 60%, #ff9800 100%);
            color: #222 !important;
        }
    </style>
    <script>
        // Hiện/ẩn danh sách phản hồi khi bấm nút
        document.querySelectorAll('.show-replies-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const commentId = this.dataset.id;
                const repliesList = document.getElementById('replies-list-' + commentId);
                if (!repliesList) return;
                repliesList.style.display = (repliesList.style.display === 'none' || !repliesList.style.display) ? 'block' : 'none';
            });
        });
        // Xử lý nút reply cho cả comment gốc và reply
        document.querySelectorAll('.reply-comment-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Đóng tất cả các khung reply đang mở
                document.querySelectorAll('.reply-box').forEach(box => {
                    box.style.display = 'none';
                    box.innerHTML = '';
                });
                const commentId = this.dataset.id;
                const replyBox = document.getElementById('reply-box-' + commentId);
                if (!replyBox) return;
                let username = btn.dataset.username;
                let content = '';
                let placeholder = 'Nhập phản hồi...';
                if (username) {
                    content = `@${username} `;
                    placeholder = `@${username} ...`;
                }
                replyBox.style.display = '';
                replyBox.innerHTML = `
                    <form class="reply-form" method="post">
                        <input type="hidden" name="parent_comment_id" value="${commentId}">
                        <textarea name="reply_content" class="form-control mb-2" rows="2" placeholder="${placeholder}" required>${content}</textarea>
                        <button type="submit" class="btn btn-primary btn-sm">Gửi phản hồi</button>
                        <button type="button" class="btn btn-secondary btn-sm cancel-reply">Hủy</button>
                    </form>
                `;
                replyBox.querySelector('.cancel-reply').onclick = function() {
                    replyBox.style.display = 'none';
                    replyBox.innerHTML = '';
                };
                replyBox.querySelector('.reply-form').onsubmit = function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    fetch('action_reply_comment.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            }
                        });
                };
            });
        });
        document.addEventListener('DOMContentLoaded', function() {
            // Xem thêm/Ẩn bớt tags
            const showMore = document.getElementById('show-more-tags');
            const hideTags = document.getElementById('hide-tags');
            const mainTags = document.getElementById('main-tags');
            const allTags = document.getElementById('all-tags');
            if (showMore) {
                showMore.addEventListener('click', function(e) {
                    e.preventDefault();
                    mainTags.style.display = 'none';
                    allTags.style.display = '';
                });
            }
            if (hideTags) {
                hideTags.addEventListener('click', function(e) {
                    e.preventDefault();
                    allTags.style.display = 'none';
                    mainTags.style.display = '';
                });
            }
            // AJAX like/dislike comment
            document.querySelectorAll('.like-comment-btn, .dislike-comment-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const commentId = this.dataset.id;
                    const type = this.classList.contains('like-comment-btn') ? 'like' : 'dislike';
                    const commentCard = this.closest('.card');
                    const likeBtn = commentCard ? commentCard.querySelector('.like-comment-btn') : null;
                    const dislikeBtn = commentCard ? commentCard.querySelector('.dislike-comment-btn') : null;
                    fetch('action_review_comment.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'comment_id=' + encodeURIComponent(commentId) + '&type=' + encodeURIComponent(type)
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (likeBtn && dislikeBtn) {
                                likeBtn.querySelector('.like-count').textContent = data.like_count;
                                dislikeBtn.querySelector('.dislike-count').textContent = data.dislike_count;
                                if (data.type === 'like') {
                                    if (data.action === 'like') {
                                        likeBtn.classList.add('active');
                                        dislikeBtn.classList.remove('active');
                                    } else {
                                        likeBtn.classList.remove('active');
                                    }
                                } else if (data.type === 'dislike') {
                                    if (data.action === 'dislike') {
                                        dislikeBtn.classList.add('active');
                                        likeBtn.classList.remove('active');
                                    } else {
                                        dislikeBtn.classList.remove('active');
                                    }
                                }
                            }
                        });
                });
            });

            // Gợi ý tag
            const tagInput = document.querySelector('input[name="add_tag"]');
            const tagDatalist = document.getElementById('tags-list');
            tagInput.addEventListener('input', function() {
                const val = tagInput.value.trim();
                if (!val) return;
                fetch('suggest_tags.php?q=' + encodeURIComponent(val))
                    .then(res => res.json())
                    .then(list => {
                        tagDatalist.innerHTML = '';
                        list.forEach(tag => {
                            const opt = document.createElement('option');
                            opt.value = tag;
                            tagDatalist.appendChild(opt);
                        });
                    });
            });
        });
        // Sửa bình luận bằng AJAX, không reload trang, chỉ cập nhật nội dung tại chỗ
        document.querySelectorAll('.edit-comment-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const commentCard = form.closest('.card');
                const commentId = commentCard ? commentCard.id.replace('comment-', '') : null;
                const formData = new FormData(form);
                formData.append('comment_id', commentId);
                fetch('edit_comment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            // Cập nhật nội dung comment
                            commentCard.querySelector('.comment-content-area').innerHTML = `<p>${data.content}</p>`;
                            // Hiện thông báo đã chỉnh sửa
                            const timeEl = commentCard.querySelector('.comment-time');
                            if (timeEl && !timeEl.innerHTML.includes('Đã chỉnh sửa')) {
                                timeEl.innerHTML += '<span class="text-warning ms-2">(Đã chỉnh sửa)</span>';
                            }
                        } else {
                            alert(data.error || 'Lỗi!');
                        }
                    });
            });
        });
        // Sửa bình luận inline, không reload, không đổi URL
        document.querySelectorAll('.btn-warning.btn-sm').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const commentCard = btn.closest('.card');
                const commentId = commentCard ? commentCard.id.replace('comment-', '') : null;
                const contentArea = commentCard.querySelector('.comment-content-area');
                const oldContent = contentArea.querySelector('p') ? contentArea.querySelector('p').innerHTML : '';
                // Tạo form sửa inline
                contentArea.innerHTML = `
                <form class=\"edit-comment-form\" style=\"margin-bottom:0;\">
                    <textarea name=\"edit_content\" class=\"form-control mb-2\" rows=\"3\" required>${oldContent.replace(/<br\s*\/?>(\r?\n)?/g, '\n')}</textarea>
                    <button type=\"submit\" class=\"btn btn-warning btn-sm\">Cập nhật</button>
                    <button type=\"button\" class=\"btn btn-secondary btn-sm cancel-edit\">Hủy</button>
                </form>
            `;
                // Xử lý submit
                bindEditCommentForm(contentArea.querySelector('form'), contentArea, commentCard, oldContent);
            });
        });

        function bindEditCommentForm(form, contentArea, commentCard, oldContent) {
            form.addEventListener('submit', function(ev) {
                ev.preventDefault();
                const commentId = commentCard ? commentCard.id.replace('comment-', '') : null;
                const formData = new FormData();
                formData.append('comment_id', commentId);
                formData.append('edit_content', form.querySelector('textarea').value);
                fetch('edit_comment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            contentArea.innerHTML = `<p>${data.content}</p>`;
                            const timeEl = commentCard.querySelector('.comment-time');
                            if (timeEl && !timeEl.innerHTML.includes('Đã chỉnh sửa')) {
                                timeEl.innerHTML += '<span class="text-warning ms-2">(Đã chỉnh sửa)</span>';
                            }
                        } else {
                            alert(data.error || 'Lỗi!');
                        }
                    });
            });
            // Hủy sửa
            const cancelBtn = form.querySelector('.cancel-edit');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function() {
                    contentArea.innerHTML = `<p>${oldContent}</p>`;
                });
            }
        }
        // Event delegation cho form sửa comment
        document.addEventListener('submit', function(e) {
            if (e.target.classList.contains('edit-comment-form')) {
                e.preventDefault();
                const form = e.target;
                const commentCard = form.closest('.card');
                const contentArea = commentCard.querySelector('.comment-content-area');
                const commentId = commentCard ? commentCard.id.replace('comment-', '') : null;
                const formData = new FormData();
                formData.append('comment_id', commentId);
                formData.append('edit_content', form.querySelector('textarea').value);
                fetch('edit_comment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            contentArea.innerHTML = `<p>${data.content}</p>`;
                            const timeEl = commentCard.querySelector('.comment-time');
                            if (timeEl && !timeEl.innerHTML.includes('Đã chỉnh sửa')) {
                                timeEl.innerHTML += '<span class="text-warning ms-2">(Đã chỉnh sửa)</span>';
                            }
                        } else {
                            alert(data.error || 'Lỗi!');
                        }
                    });
            }
        });
        // Event delegation cho nút Hủy sửa
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('cancel-edit')) {
                const form = e.target.closest('.edit-comment-form');
                const contentArea = form.closest('.comment-content-area');
                const oldContent = form.querySelector('textarea').value;
                contentArea.innerHTML = `<p>${oldContent}</p>`;
            }
        });
    </script>
</div>
<?php include 'includes/footer.php'; ?>