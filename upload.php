<?php
include 'includes/header.php';
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $file = $_FILES['document'];

    $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_types)) {
        $error = "Chỉ cho phép file PDF, DOC, DOCX, PPT, PPTX.";
    } elseif ($file['size'] > 20 * 1024 * 1024) {
        $error = "File quá lớn, chỉ cho phép tối đa 20MB.";
    } else {
        $filename = uniqid() . '.' . $ext; // Tên file an toàn
        $file_path = 'uploads/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $stmt = $pdo->prepare("INSERT INTO documents (user_id, title, description, subject, file_path, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$_SESSION['user_id'], $title, $description, $subject, $file_path]);
            $success = "Tải lên thành công, chờ admin duyệt.";
        } else {
            $error = "Tải lên thất bại!";
        }
    }
}
?>

<h2>Tải tài liệu lên</h2>

<?php if (isset($error)) echo "<p style='color:red'>$error</p>"; ?>
<?php if (isset($success)) echo "<p style='color:green'>$success</p>"; ?>

<form method="post" enctype="multipart/form-data">
    <label>Tiêu đề:</label>
    <input type="text" name="title" required>

    <label>Môn học:</label>
    <input type="text" name="subject" required>

    <label>Mô tả:</label>
    <textarea name="description"></textarea>

    <label>File:</label>
    <input type="file" name="document" required>

    <button type="submit">Tải lên</button>
</form>

<?php include 'includes/footer.php'; ?>