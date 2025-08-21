<?php
include 'includes/header.php';
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp_input = trim($_POST['otp']);

    // Kiểm tra session OTP
    if (isset($_SESSION['email_otp'], $_SESSION['otp_expire'], $_SESSION['pending_email'])) {
        if (time() > $_SESSION['otp_expire']) {
            $message = "OTP đã hết hạn, vui lòng thử lại!";
            $message_type = 'alert-danger';
            // Xóa OTP cũ
            unset($_SESSION['email_otp'], $_SESSION['otp_expire'], $_SESSION['pending_email']);
        } elseif ($otp_input === (string)$_SESSION['email_otp']) {
            // Cập nhật email mới
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
            $stmt->execute([$_SESSION['pending_email'], $_SESSION['user_id']]);

            $message = "Đổi email thành công!";
            $message_type = 'alert-success';

            // Đồng bộ session avatar/email nếu cần
            unset($_SESSION['email_otp'], $_SESSION['otp_expire'], $_SESSION['pending_email']);
        } else {
            $message = "OTP không chính xác!";
            $message_type = 'alert-danger';
        }
    } else {
        $message = "Không có yêu cầu đổi email nào!";
        $message_type = 'alert-danger';
    }
}
?>

<div class="container mt-4">
    <h3>Xác nhận OTP</h3>

    <?php if ($message): ?>
        <div class="alert <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post">
        <label>Nhập mã OTP:</label>
        <input type="text" name="otp" class="form-control mb-2" required>
        <button type="submit" class="btn btn-primary">Xác nhận</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>