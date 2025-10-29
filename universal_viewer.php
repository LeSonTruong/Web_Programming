<?php
include 'includes/viewer_header.php'; // header đơn giản, hỗ trợ dark mode
include 'includes/db.php';

$doc_id = $_GET['id'] ?? 0;

// Lấy thông tin tài liệu
$stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    echo "<div class='alert alert-danger'>❌ Không tìm thấy tài liệu!</div>";
    include 'includes/footer.php';
    exit;
}

$file       = $doc['file_path'];
$ext        = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$title      = htmlspecialchars($doc['title']);
$description = nl2br(htmlspecialchars($doc['description']));
$file_url   = 'https://studyshare.banhgao.net/' . ltrim($file, '/'); // đổi sang domain thực tế

// Kiểm tra xem có bản convert PDF không
$converted_pdf = 'uploads/converted/' . pathinfo($file, PATHINFO_FILENAME) . '.pdf';
$converted_pdf_url = 'https://studyshare.banhgao.net/' . ltrim($converted_pdf, '/');
$has_converted_pdf = file_exists($converted_pdf);
?>

<div class="container my-4">
    <h2><?= $title ?></h2>
    <?php if (!empty($description)): ?>
        <p><strong>Mô tả:</strong> <?= $description ?></p>
    <?php endif; ?>

    <div class="file-viewer my-3" style="min-height: 600px;">
        <?php
        // Ưu tiên hiển thị file đã convert sang PDF
        if ($has_converted_pdf):
        ?>
            <embed src="<?= htmlspecialchars($converted_pdf) ?>" type="application/pdf" width="100%" height="600px" />
        <?php
        // PDF gốc
        elseif ($ext === 'pdf'):
        ?>
            <embed src="<?= htmlspecialchars($file) ?>" type="application/pdf" width="100%" height="600px" />
        <?php
        // Office files (Word, Excel, PowerPoint)
        elseif (in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])):
        ?>
            <!-- Thử Office Online Viewer -->
            <iframe id="officeViewer"
                src="https://view.officeapps.live.com/op/embed.aspx?src=<?= urlencode($file_url) ?>"
                width="100%" height="600px" frameborder="0"></iframe>

            <script>
                // Nếu Office Viewer lỗi -> fallback sang Google Docs Viewer
                document.getElementById('officeViewer').addEventListener('error', function() {
                    this.src = "https://docs.google.com/viewer?url=<?= urlencode($file_url) ?>&embedded=true";
                });
            </script>
        <?php
        // Images
        elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])):
        ?>
            <img src="<?= htmlspecialchars($file) ?>" class="img-fluid" alt="Document Image" />
        <?php
        // Code files
        elseif (in_array($ext, ['php', 'js', 'py', 'java', 'cpp', 'c', 'html', 'css', 'ipynb'])):
        ?>
            <iframe src="code_viewer.php?file=<?= urlencode($file) ?>" width="100%" height="600px" frameborder="0"></iframe>
        <?php
        // Khác
        else:
        ?>
            <p>📄 File này không thể xem trực tiếp. Vui lòng tải xuống để mở.</p>
            <a href="download.php?id=<?= $doc['doc_id'] ?>" class="btn btn-primary">📥 Tải xuống</a>
        <?php endif; ?>
    </div>
</div>