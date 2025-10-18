<?php
include 'includes/header.php';
require_once "includes/db.php";

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/send_mail.php';

if (!isset($_SESSION['user_id'])) {
    echo '<div class="container my-5">
            <div class="alert alert-warning text-center">
                ⚠️ Tạo tài khoản hoặc đăng nhập đi bạn ÊYYYYY!
            </div>
          </div>';
    include 'includes/footer.php';
    exit();
}

$user_id = $_SESSION['user_id'];

// Lấy thông tin user
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Đồng bộ avatar & display_name cho header
$_SESSION['avatar'] = $user['avatar'] ?? 'default.png';
$_SESSION['display_name'] = $user['display_name'] ?? $user['username'];

$error = $success = '';
$show_email_verify = false;
$show_phone_verify = false;

// ===== Đổi Display Name =====
if (isset($_POST['change_display'])) {
    $new_display = trim($_POST['display_name']);
    $last_change = $user['last_display_change'];
    if ($last_change && strtotime($last_change) > strtotime('-30 days')) {
        $error = "Bạn chỉ có thể đổi tên hiển thị sau 30 ngày!";
    } else {
        $stmt = $conn->prepare("UPDATE users SET display_name=?, last_display_change=NOW() WHERE user_id=?");
        $stmt->execute([$new_display, $user_id]);
        $success = "Tên hiển thị đã được cập nhật!";
        $_SESSION['display_name'] = $new_display;
    }
}

// ===== Đổi Email + OTP =====
if (isset($_POST['change_email'])) {
    $new_email = trim($_POST['email']);
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ!";
    } else {
        $otp = rand(100000, 999999);
        $expiry = date("Y-m-d H:i:s", time() + 600); // 10 phút

        // Cập nhật OTP vào DB
        $stmt = $conn->prepare("UPDATE users SET email_verification_code=?, email_verified=0, email=? WHERE user_id=?");
        $stmt->execute([$otp, $new_email, $user_id]);

        if (function_exists('sendMail') && sendMail($new_email, "Xác nhận email", "Mã OTP của bạn là: <b>$otp</b>")) {
            $success = "OTP đã được gửi tới $new_email. Nhập OTP để xác nhận.";
            $show_email_verify = true;
        } else {
            $error = "Không thể gửi email. Vui lòng thử lại.";
        }
    }
}

// ===== Xác thực Email =====
if (isset($_POST['verify_email_otp'])) {
    $input_otp = trim($_POST['email_otp']);
    if ($input_otp == $user['email_verification_code']) {
        $stmt = $conn->prepare("UPDATE users SET email_verified=1, email_verification_code=NULL WHERE user_id=?");
        $stmt->execute([$user_id]);
        $success = "Email đã được xác thực!";
    } else {
        $error = "OTP không đúng hoặc đã hết hạn!";
        $show_email_verify = true;
    }
}

// ===== Đổi Số điện thoại + OTP =====
if (isset($_POST['send_phone_otp'])) {
    $phone = trim($_POST['phone']);
    if (!preg_match('/^\+?\d{9,15}$/', $phone)) {
        $error = "Số điện thoại không hợp lệ!";
    } else {
        $otp = rand(100000, 999999);
        $expiry = date("Y-m-d H:i:s", time() + 600);

        $stmt = $conn->prepare("UPDATE users SET phone=?, otp_code=?, otp_expiry=? WHERE user_id=?");
        $stmt->execute([$phone, $otp, $expiry, $user_id]);

        $success = "[Test] OTP đã được gửi tới số điện thoại $phone";
        $show_phone_verify = true;
    }
}

// ===== Xác thực OTP Số điện thoại =====
if (isset($_POST['verify_phone_otp'])) {
    $input_otp = trim($_POST['phone_otp']);
    if ($input_otp == $user['otp_code'] && strtotime($user['otp_expiry']) > time()) {
        $stmt = $conn->prepare("UPDATE users SET otp_code=NULL, otp_expiry=NULL WHERE user_id=?");
        $stmt->execute([$user_id]);
        $success = "Số điện thoại đã được xác thực!";
    } else {
        $error = "OTP không đúng hoặc đã hết hạn!";
        $show_phone_verify = true;
    }
}

// ===== Đổi Mật khẩu =====
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
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
        $stmt->execute([$hashed, $user_id]);
        $success = "Mật khẩu đã được thay đổi!";
    }
}

// ===== Đổi Avatar =====
if (isset($_POST['change_avatar']) && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file['error'] === 0 && in_array($ext, $allowed)) {
        $newName = "avatar_" . $user_id . "." . $ext;
        $path = "uploads/avatars/" . $newName;
        move_uploaded_file($file['tmp_name'], $path);

        $stmt = $conn->prepare("UPDATE users SET avatar=? WHERE user_id=?");
        $stmt->execute([$newName, $user_id]);
        $success = "Ảnh đại diện đã được cập nhật!";
        $_SESSION['avatar'] = $newName;
    } else {
        $error = "File không hợp lệ!";
    }
}

// Lấy lại dữ liệu user sau cập nhật
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2>Quản lý tài khoản</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
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
        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?>
            <?php if ($user['email_verified']): ?>
                <span class="badge bg-success">Đã xác thực</span>
            <?php else: ?>
                <span class="badge bg-warning">Chưa xác thực</span>
            <?php endif; ?>
        </p>
        <form method="POST">
            <input type="email" name="email" class="form-control mb-2" value="<?= htmlspecialchars($user['email']) ?>" required>
            <button type="submit" name="change_email" class="btn btn-primary">Gửi OTP xác nhận Email</button>
        </form>

        <?php if ($show_email_verify): ?>
            <form method="POST" class="mt-2">
                <input type="text" name="email_otp" class="form-control mb-2" placeholder="Nhập OTP" required>
                <button type="submit" name="verify_email_otp" class="btn btn-success">Xác thực Email</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Phone -->
    <div class="card p-3 mb-3">
        <h4>Số điện thoại</h4>
        <p><strong>Số điện thoại:</strong> <?= htmlspecialchars($user['phone'] ?: 'Chưa cập nhật') ?>
            <?php if (!empty($user['phone']) && empty($user['otp_code'])): ?>
                <span class="badge bg-success">Đã xác thực</span>
            <?php elseif (!empty($user['phone'])): ?>
                <span class="badge bg-warning">Chưa xác thực</span>
            <?php endif; ?>
        </p>
        <form method="POST">
            <input type="text" name="phone" class="form-control mb-2" placeholder="Nhập số điện thoại" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
            <button type="submit" name="send_phone_otp" class="btn btn-primary mb-2">Gửi OTP</button>
        </form>

        <?php if ($show_phone_verify || !empty($user['otp_code'])): ?>
            <form method="POST">
                <input type="text" name="phone_otp" class="form-control mb-2" placeholder="Nhập OTP" required>
                <button type="submit" name="verify_phone_otp" class="btn btn-success">Xác thực OTP</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Password -->
    <div class="card p-3 mb-3">
        <h4>Đổi mật khẩu</h4>
        <form method="POST">
            <input type="password" name="current_password" class="form-control mb-2" placeholder="Mật khẩu hiện tại" required>
            <input type="password" name="new_password" class="form-control mb-2" placeholder="Mật khẩu mới" required>
            <input type="password" name="confirm_password" class="form-control mb-2" placeholder="Xác nhận mật khẩu mới" required>
            <button type="submit" name="change_password" class="btn btn-primary">Đổi mật khẩu</button>
        </form>
    </div>

    <!-- Thông tin cá nhân mở rộng -->
    <div class="card p-3 mb-3">
        <h4>Thông tin cá nhân mở rộng</h4>
        <form method="POST">
            <div class="mb-3">
                <label for="birthday" class="form-label">Ngày sinh</label>
                <input type="date" class="form-control" id="birthday" name="birthday" value="<?= htmlspecialchars($user['birthday'] ?? '') ?>">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="show_birthday" name="show_birthday" value="1" <?= !empty($user['show_birthday']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="show_birthday">Hiển thị ngày sinh trên trang cá nhân</label>
                </div>
            </div>
            <div class="mb-3">
                <label for="gender" class="form-label">Giới tính</label>
                <select class="form-select" id="gender" name="gender">
                    <option value="" <?= empty($user['gender']) ? 'selected' : '' ?>>Chưa chọn</option>
                    <option value="male" <?= ($user['gender'] === 'male') ? 'selected' : '' ?>>Nam</option>
                    <option value="female" <?= ($user['gender'] === 'female') ? 'selected' : '' ?>>Nữ</option>
                    <option value="other" <?= ($user['gender'] === 'other') ? 'selected' : '' ?>>Khác</option>
                </select>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="show_gender" name="show_gender" value="1" <?= !empty($user['show_gender']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="show_gender">Hiển thị giới tính trên trang cá nhân</label>
                </div>
            </div>
            <div class="mb-3">
                <label for="facebook" class="form-label">Liên kết Facebook</label>
                <input type="url" class="form-control" id="facebook" name="facebook" placeholder="https://facebook.com/yourprofile" value="<?= htmlspecialchars($user['facebook'] ?? '') ?>">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="show_facebook" name="show_facebook" value="1" <?= !empty($user['show_facebook']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="show_facebook">Hiển thị Facebook trên trang cá nhân</label>
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="show_email" name="show_email" value="1" <?= !empty($user['show_email']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="show_email">Hiển thị email trên trang cá nhân</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="show_phone" name="show_phone" value="1" <?= !empty($user['show_phone']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="show_phone">Hiển thị số điện thoại trên trang cá nhân</label>
                </div>
            </div>
            <button type="submit" name="update_extended" class="btn btn-success">Lưu thông tin mở rộng</button>
        </form>
    </div>
</div>


<?php include "includes/footer.php"; ?>