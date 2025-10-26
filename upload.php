<?php
include 'includes/db.php';

session_start();

$stmt = $conn->prepare("SELECT upload_locked FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ====== KIỂM TRA QUYỀN ======
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $reason = 'chuadangnhap';
    include __DIR__ . '/!403.php';
    exit();
} elseif ($user && (int)($user['upload_locked'] ?? 0) === 1) {
    http_response_code(403);
    $reason = 'camtailen';
    include __DIR__ . '/!403.php';
    exit();
}

include 'includes/header.php';

// Hàm sinh summary đơn giản
function generateSummary($text)
{
    $text = strip_tags($text);
    return strlen($text) > 150 ? mb_substr($text, 0, 150) . "..." : $text;
}

// Lấy danh sách môn học
$subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách tags (đổi tên biến để không bị ghi đè khi POST)
$all_tags = $conn->query("SELECT tag_name FROM tags ORDER BY tag_name")->fetchAll(PDO::FETCH_COLUMN);

$error = $success = '';
$error_is_html = false; // flag để biết có hiển thị $error với HTML hay escape

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title        = trim($_POST['title']);
    $author_name  = trim($_POST['author_name']);
    $subject_name = trim($_POST['subject_name']);
    $department   = trim($_POST['department'] ?? '');
    $description  = trim($_POST['description']);
    $tags         = trim($_POST['tags']);
    $file         = $_FILES['document'];

    // Lấy status_id của 'pending'
    $stmt = $conn->prepare("SELECT status_id FROM statuses WHERE status_name='Pending' LIMIT 1");
    $stmt->execute();
    $status = $stmt->fetch(PDO::FETCH_ASSOC);
    $status_id = $status['status_id'] ?? 1;

    // Gom môn học gần giống
    $subject_id = null;
    foreach ($subjects as $sub) {
        if (strtolower($subject_name) === strtolower($sub['subject_name'])) {
            $subject_id = $sub['subject_id'];
            $subject_name = $sub['subject_name'];
            break;
        }
    }

    if (!$subject_id) {
        $stmt = $conn->prepare("INSERT INTO subjects (subject_name, department) VALUES (?, ?)");
        try {
            $stmt->execute([$subject_name, $department]);
            $subject_id = $conn->lastInsertId();
        } catch (PDOException $e) {
            // Escape message để an toàn
            $error = "❌ Lỗi khi tạo môn học: " . htmlspecialchars($e->getMessage());
        }
    }

    // Nếu chưa có lỗi, xử lý file upload
    if (!$error) {
        // Các định dạng được phép upload (PDF, ảnh, file code)
        // Danh sách định dạng được hỗ trợ hiển thị trực tiếp
        $allowed_types = [
            'pdf'
        ];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Nếu là file Office thì chặn và hướng dẫn người dùng convert trước
        if (in_array($ext, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'])) {
            $error = "❌ Định dạng <b>.$ext</b> không hỗ trợ xem trực tiếp. 
              Vui lòng <b>convert sang PDF</b> trước khi tải lên.";
        } elseif (!in_array($ext, $allowed_types)) {
            $error = "❌ Định dạng file không được hỗ trợ.";
        } elseif ($file['size'] > 20 * 1024 * 1024) {
            $error = "❌ File quá lớn, tối đa 20MB.";
        } else {
            // ... giữ nguyên phần xử lý upload cũ
        }

        // Các định dạng Office bị chặn — bắt người dùng convert sang PDF trước khi upload
        $blocked_office = ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Nếu file bị block (Office) -> thông báo hướng dẫn convert
        if (in_array($ext, $blocked_office)) {
            // Nội dung HTML hướng dẫn (server-side)
            $error = "<strong>❌ File .$ext không được hỗ trợ để xem trực tiếp.</strong><br>
                      👉 Vui lòng chuyển file sang <strong>PDF</strong> trước khi tải lên.<br><br>
                      <u>Cách chuyển nhanh:</u><br>
                      <ol>
                        <li><strong>Microsoft Office (Word/Excel/PowerPoint)</strong>: Mở file → <em>File → Save As → Chọn PDF</em> (hoặc <em>Export → Create PDF/XPS</em>).</li>
                        <li><strong>Google Docs / Sheets / Slides</strong>: Mở file trên Google Drive → <em>File → Download → PDF Document (.pdf)</em>.</li>
                        <li><strong>LibreOffice</strong>: Mở file → <em>File → Export as PDF</em>.</li>
                      </ol>
                      🔹 Nếu muốn, bạn có thể convert sẵn sang PDF trước khi upload để đảm bảo hiển thị chính xác.";
            $error_is_html = true;
        } elseif (!in_array($ext, $allowed_types)) {
            $error = "❌ Định dạng .$ext không được hỗ trợ. Vui lòng chọn file PDF, ảnh (jpg, png, gif) hoặc tệp code (.ipynb, .py, .js, ...).";
            $error_is_html = false;
        } elseif ($file['size'] > 20 * 1024 * 1024) {
            $error = "❌ File quá lớn, tối đa 20MB.";
            $error_is_html = false;
        } else {
            $filename = uniqid() . '.' . $ext;
            $file_path = 'uploads/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $summary = generateSummary($description);

                // Document type
                $doc_type = match ($ext) {
                    'jpg', 'jpeg', 'png', 'gif' => 'image',
                    'pdf' => 'pdf',
                    'ipynb', 'py', 'js', 'java', 'c', 'cpp', 'html', 'css', 'json', 'rb', 'go', 'ts' => 'code',
                    default => 'other',
                };

                $stmt = $conn->prepare("INSERT INTO documents
                (user_id, title, author_name, description, subject_id, file_path, file_size,
                 document_type, summary, status_id, upload_date, updated_at, views)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW(), 0)");
                try {
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $title,
                        $author_name,
                        $description,
                        $subject_id,
                        $file_path,
                        $file['size'],
                        $doc_type,
                        $summary,
                    ]);
                    $success = "✅ Tải lên thành công, chờ duyệt.";
                } catch (PDOException $e) {
                    $error = "❌ Lỗi khi lưu tài liệu: " . htmlspecialchars($e->getMessage());
                    $error_is_html = false;
                }
            } else {
                $error = "❌ Tải lên thất bại!";
                $error_is_html = false;
            }
        }
    }
}
?>

<div class="container mt-5" style="max-width: 700px;">
    <div class="card shadow-lg">
        <div class="card-body">
            <h2 class="card-title text-center mb-4">📤 Tải tài liệu lên</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php
                    if ($error_is_html) {
                        // Nếu thông báo có HTML (hướng dẫn convert), in thẳng để giữ format
                        echo $error;
                    } else {
                        // Các lỗi khác in escape an toàn
                        echo htmlspecialchars($error);
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" id="upload-form">
                <div class="mb-3">
                    <label class="form-label">📌 Tiêu đề</label>
                    <input type="text" name="title" class="form-control" required value="<?= isset($title) ? htmlspecialchars($title) : '' ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">✍️ Tác giả</label>
                    <input type="text" name="author_name" class="form-control" required value="<?= isset($author_name) ? htmlspecialchars($author_name) : '' ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">📚 Môn học</label>
                    <input type="text" name="subject_name" list="subjects-list" class="form-control" required value="<?= isset($subject_name) ? htmlspecialchars($subject_name) : '' ?>">
                    <datalist id="subjects-list">
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= htmlspecialchars($sub['subject_name']) ?>">
                            <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="mb-3">
                    <label class="form-label">🏫 Khoa (tùy chọn)</label>
                    <input type="text" name="department" class="form-control" value="<?= isset($department) ? htmlspecialchars($department) : '' ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">📝 Mô tả</label>
                    <textarea name="description" class="form-control" rows="3"><?= isset($description) ? htmlspecialchars($description) : '' ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">🏷️ Tags (chọn nhiều, nhấn Enter hoặc dấu phẩy để thêm)</label>
                    <div id="tags-container" class="d-flex flex-wrap gap-1 mb-2"></div>
                    <input type="text" id="tags-input" class="form-control" autocomplete="off" placeholder="Nhập tag...">
                    <div id="tags-suggestions" class="list-group position-absolute w-100" style="z-index:10; display:none;"></div>
                    <input type="hidden" name="tags" id="tags-hidden" value="<?= isset($tags) ? htmlspecialchars($tags) : '' ?>">
                </div>

                <script>
                    const allTags = <?php echo json_encode($all_tags); ?>;
                    const tagsInput = document.getElementById('tags-input');
                    const tagsContainer = document.getElementById('tags-container');
                    const tagsHidden = document.getElementById('tags-hidden');
                    const tagsSuggestions = document.getElementById('tags-suggestions');
                    let selectedTags = [];

                    function updateTagsUI() {
                        tagsContainer.innerHTML = '';
                        selectedTags.forEach(tag => {
                            const tagEl = document.createElement('span');
                            tagEl.className = 'badge bg-primary text-light px-2 py-1 mb-1 d-inline-flex align-items-center';
                            tagEl.style.gap = '4px';
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
                            tagsSuggestions.style.display = 'none';
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

                <div class="mb-3">
                    <label class="form-label">📂 File tài liệu</label>
                    <input type="file" name="document" class="form-control" id="document-input" required>
                    <div id="file-warning-area" class="mt-2"></div>
                    <small class="text-muted d-block mt-2">
                        🔹 Chỉ cho phép tải lên file <strong>PDF</strong>.<br>
                        ❌ Nếu bạn có file Word/Excel/PowerPoint (.docx, .pptx, .xlsx, ...) — <strong>vui lòng chuyển sang PDF</strong> trước khi upload.
                    </small>

                    <!-- Hướng dẫn ngắn (có thể mở rộng sang trang full hướng dẫn nếu cần) -->
                    <details class="mt-2">
                        <summary style="cursor:pointer">Hướng dẫn nhanh: Cách convert sang PDF</summary>
                        <div class="mt-2">
                            <ul>
                                <li><strong>Microsoft Office</strong>: File → Save As → chọn PDF hoặc Export → Create PDF/XPS.</li>
                                <li><strong>Google Docs / Sheets / Slides</strong>: File → Download → PDF Document (.pdf).</li>
                                <li><strong>LibreOffice</strong>: File → Export as PDF.</li>
                                <li><strong>Nếu dùng điện thoại</strong>: Mở file trong app Office/Google Drive → Export/Share → Save as PDF.</li>
                            </ul>
                        </div>
                    </details>
                </div>

                <button type="submit" class="btn btn-success w-100" id="submit-btn">🚀 Tải lên</button>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        const blockedOffice = <?php echo json_encode(['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx']); ?>;
        const fileInput = document.getElementById('document-input');
        const submitBtn = document.getElementById('submit-btn');
        const fileWarningArea = document.getElementById('file-warning-area');
        const form = document.getElementById('upload-form');

        function showWarning(html) {
            fileWarningArea.innerHTML = '<div class="alert alert-warning">' + html + '</div>';
        }

        function clearWarning() {
            fileWarningArea.innerHTML = '';
        }

        fileInput.addEventListener('change', function() {
            const f = this.files[0];
            if (!f) {
                clearWarning();
                submitBtn.disabled = false;
                return;
            }
            const name = f.name || '';
            const ext = name.split('.').pop().toLowerCase();
            if (blockedOffice.includes(ext)) {
                showWarning(
                    '<strong>Không hỗ trợ upload file <em>.' + ext + '</em>.</strong> ' +
                    'Vui lòng chuyển file sang <strong>PDF</strong> trước khi tải lên.<br>' +
                    'Xem hướng dẫn nhanh ở bên dưới hoặc convert bằng Word/Google Docs.'
                );
                submitBtn.disabled = true;
            } else {
                clearWarning();
                submitBtn.disabled = false;
            }
        });

        // Nếu cố tình submit bằng mã (bypass), server vẫn kiểm tra; nhưng chặn ngay trên client để UX tốt hơn
        form.addEventListener('submit', function(e) {
            const f = fileInput.files[0];
            if (f) {
                const name = f.name || '';
                const ext = name.split('.').pop().toLowerCase();
                if (blockedOffice.includes(ext)) {
                    e.preventDefault();
                    showWarning('<strong>Vui lòng chuyển file Office sang PDF trước khi tải lên.</strong>');
                    submitBtn.disabled = true;
                    return false;
                }
            }
        });
    })();
</script>

<?php include 'includes/footer.php'; ?>