<?php
// code_viewer.php
include 'includes/viewer_header.php';

// Lấy file từ query string
$file = $_GET['file'] ?? '';

// Chỉ cho phép mở các file code an toàn
$allowed_extensions = ['php', 'py', 'js', 'cpp', 'c', 'java', 'ipynb', 'txt'];
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

if (!in_array($ext, $allowed_extensions)) {
    echo "<div class='container my-5 alert alert-danger'>❌ File không hợp lệ hoặc không thể xem trực tiếp!</div>";
    include 'includes/footer.php';
    exit;
}

// Kiểm tra file tồn tại trên server
$full_path = __DIR__ . '/' . $file;
if (!file_exists($full_path)) {
    echo "<div class='container my-5 alert alert-danger'>❌ File không tồn tại!</div>";
    include 'includes/footer.php';
    exit;
}

// Lấy nội dung file
$code_content = htmlspecialchars(file_get_contents($full_path));
?>

<pre><code class="language-<?= $ext ?>"><?= $code_content ?></code></pre>

<!-- Prism.js CSS & JS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-python.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-java.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-c.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-cpp.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup.min.js"></script>