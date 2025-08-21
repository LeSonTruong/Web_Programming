<?php
include 'includes/header.php';
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code_input = trim($_POST['code']);

    $stmt = $conn->prepare("SELECT email_verification_code, email_verified FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $message = "Người dùng không tồn tại!";
        $message_type = 'alert-danger';
    } elseif ($user['email_verified']) {
        $message = "Email đã được xác thực!";
        $message_type = 'alert-success';
    } elseif ($code_input == $user['email_verification_code']) {
        $stmt = $conn->prepare("UPDATE users SET email_verified = 1, email_verification_code = NULL WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);

        $message = "Xác thực email thành công!";
        $message_type = 'alert-success';
    } else {
        $message = "Mã xác thực không đúng!";
        $message_type = 'alert-danger';
    }
}
?>

<div class="container mt-4">
    <h3>Xác thực Email</h3>

    <?php if ($message): ?>
        <div class="alert <?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post">
        <label>Nhập mã xác thực:</label>
        <input type="text" name="code" class="form-control mb-2" required>
        <button type="submit" class="btn btn-primary">Xác nhận</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>