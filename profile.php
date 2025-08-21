<?php
include 'includes/header.php';
require_once "includes/db.php";
require 'includes/send_mail.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Lấy thông tin user
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Đồng bộ avatar & display_name cho header
$_SESSION['avatar'] = $user['avatar'] ?? 'default.png';
$_SESSION['display_name'] = $user['display_name'] ?? $user['username'];

$error = $success = '';
$show_verify_link = false;

if (isset($_POST['change_display'])) {
    $new_display = trim($_POST['display_name']);
    $last_change = $user['last_display_change'];

    if ($last_change && strtotime($last_change) > strtotime('-30 days')) {
        $error = "Bạn chỉ có thể đổi tên hiển thị sau 30 ngày!";
    } else {
        $stmt = $conn->prepare("UPDATE users SET display_name = ?, last_display_change = NOW() WHERE user_id = ?");
        $stmt->execute([$new_display, $user_id]);
        $success = "Tên hiển thị đã được cập nhật!";

        // ✅ Cập nhật session ngay lập tức
        $_SESSION['display_name'] = $new_display;
    }
}

// 2️⃣ Đổi Email qua OTP
if (isset($_POST['change_email'])) {
    $new_email = trim($_POST['email']);
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ!";
    } else {
        // Sinh OTP
        $otp = rand(100000, 999999);
        $_SESSION['pending_email'] = $new_email;
        $_SESSION['email_otp'] = $otp;
        $_SESSION['otp_expire'] = time() + 600; // 10 phút

        // Gửi email xác nhận
        if (sendMail($new_email, "Xác nhận thay đổi email", "Mã OTP của bạn là: <b>$otp</b>")) {
            $success = "OTP đã được gửi tới $new_email. Vui lòng nhập mã OTP để xác nhận.";
            $show_verify_link = true;
        } else {
            $error = "Không thể gửi email. Vui lòng thử lại.";
        }
    }
}

// 3️⃣ Đổi mật khẩu
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (!password_verify($current, $user['password'])) {
        $error = "Mật khẩu hiện tại không đúng!";
    } elseif ($new !== $confirm) {
        $error = "Mật khẩu mới không khớp!";
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->execute([$hashed, $user_id]);
        $success = "Mật khẩu đã được thay đổi!";
    }
}

// 4️⃣ Đổi Avatar
if (isset($_POST['change_avatar']) && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] === 0 && in_array($ext, $allowed)) {
        $newName = "avatar_" . $user_id . "." . $ext;
        $path = "uploads/avatars/" . $newName;
        move_uploaded_file($file['tmp_name'], $path);

        $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE user_id = ?");
        $stmt->execute([$newName, $user_id]);
        $success = "Ảnh đại diện đã được cập nhật!";
        $_SESSION['avatar'] = $newName; // Cập nhật ngay cho header
    } else {
        $error = "File không hợp lệ!";
    }
}

// Lấy lại dữ liệu sau cập nhật
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2>Quản lý tài khoản</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php if ($show_verify_link): ?>
            <a href="verify_email.php" class="btn btn-warning mt-2">Nhập OTP tại đây</a>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Avatar -->
    <div class="card p-3 mb-3">
        <h4>Ảnh đại diện</h4>
        <img src="uploads/avatars/<?= htmlspecialchars($user['avatar'] ?? 'default.png') ?>" width="100" class="rounded mb-2">
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="avatar" class="form-control mb-2" required>
            <button type="submit" name="change_avatar" class="btn btn-primary">Cập nhật ảnh</button>
        </form>
    </div>

    <!-- Display Name -->
    <div class="card p-3 mb-3">
        <h4>Display Name</h4>
        <form method="POST">
            <input type="text" name="display_name" class="form-control mb-2"
                value="<?= htmlspecialchars($user['display_name'] ?? '') ?>" required>
            <button type="submit" name="change_display" class="btn btn-primary">Đổi tên hiển thị</button>
        </form>
    </div>

    <!-- Email -->
    <div class="card p-3 mb-3">
        <h4>Email</h4>
        <form method="POST">
            <input type="email" name="email" class="form-control mb-2"
                value="<?= htmlspecialchars($user['email']) ?>" required>
            <button type="submit" name="change_email" class="btn btn-primary">Gửi OTP xác nhận Email</button>
        </form>
    </div>

    <!-- Mật khẩu -->
    <div class="card p-3 mb-3">
        <h4>Đổi mật khẩu</h4>
        <form method="POST">
            <input type="password" name="current_password" class="form-control mb-2" placeholder="Mật khẩu hiện tại" required>
            <input type="password" name="new_password" class="form-control mb-2" placeholder="Mật khẩu mới" required>
            <input type="password" name="confirm_password" class="form-control mb-2" placeholder="Xác nhận mật khẩu mới" required>
            <button type="submit" name="change_password" class="btn btn-primary">Đổi mật khẩu</button>
        </form>
    </div>
</div>

<?php include "includes/footer.php"; ?>