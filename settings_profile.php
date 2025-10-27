<?php
session_start();
require_once "includes/db.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $reason = 'chuadangnhap';
    include __DIR__ . '/!403.php';
    exit();
}

include 'includes/header.php';

require_once __DIR__ . '/vendor/autoload.php';

$user_id = $_SESSION['user_id'];

// Lấy thông tin user (bao gồm profile mở rộng lưu ở user_profile)
$stmt = $conn->prepare("SELECT users.*, 
                user_profile.show_email, user_profile.show_phone, user_profile.show_birthday, 
                user_profile.show_gender, user_profile.show_facebook, 
                user_profile.birthday, user_profile.gender, user_profile.facebook
            FROM users
            LEFT JOIN user_profile ON users.user_id = user_profile.user_id
            WHERE users.user_id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Đồng bộ avatar & display_name cho header chỉ khi đang chỉnh sửa tài khoản chính mình
if ($user['user_id'] == ($_SESSION['user_id'] ?? null)) {
    $_SESSION['avatar'] = $user['avatar'] ?? 'default.png';
    $_SESSION['display_name'] = $user['display_name'] ?? $user['username'];
}

$error = $success = '';

// ===== Đổi Display Name =====
if (isset($_POST['change_display'])) {
    $new_display = trim($_POST['display_name']);
    $last_change = $user['last_name_change'];
    if ($last_change && strtotime($last_change) > strtotime('-30 days')) {
        $error = "Bạn chỉ có thể đổi tên hiển thị sau 30 ngày!";
    } else {
        $stmt = $conn->prepare("UPDATE users SET display_name=?, last_name_change=NOW() WHERE user_id=?");
        $stmt->execute([$new_display, $user_id]);
        $success = "Tên hiển thị đã được cập nhật!";
        $_SESSION['display_name'] = $new_display;
    }
}

// ===== Đổi Mật khẩu =====
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    // Kiểm tra mật khẩu mạnh
    $password_error = '';
    if (
        strlen($new) < 6 ||
        !preg_match('/[A-Z]/', $new) ||
        !preg_match('/[a-z]/', $new)
    ) {
        $password_error = "Mật khẩu phải có ít nhất 6 ký tự, 1 chữ hoa và 1 chữ thường.";
    }

    if (!password_verify($current, $user['password'])) {
        $error = "Mật khẩu hiện tại không đúng!";
    } elseif ($new !== $confirm) {
        $error = "Mật khẩu mới không khớp!";
    } elseif ($password_error) {
        $error = $password_error;
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
        $maxSize = 5 * 1024 * 1024; // 5 MB
        if ($file['size'] > $maxSize) {
            $error = "Giới hạn kích cỡ ảnh là 5MB!  ";
        } else {
            $newName = "avatar_" . $user_id . "." . $ext;
            $path = "uploads/avatars/" . $newName;
            move_uploaded_file($file['tmp_name'], $path);

            $stmt = $conn->prepare("UPDATE users SET avatar=? WHERE user_id=?");
            $stmt->execute([$newName, $user_id]);
            $success = "Ảnh đại diện đã được cập nhật!";
            $_SESSION['avatar'] = $newName;
        }
    } else {
        $error = "File không hợp lệ!";
    }
}

// ===== Lưu thông tin cá nhân mở rộng =====
if (isset($_POST['update_extended'])) {
    $birthday = $_POST['birthday'] ?? null;
    if (empty($birthday)) {
        $birthday = null;
    }
    $show_birthday = !empty($_POST['show_birthday']) ? 1 : 0;
    $gender = isset($_POST['gender']) ? (int)$_POST['gender'] : 0;
    $show_gender = !empty($_POST['show_gender']) ? 1 : 0;
    $facebook = $_POST['facebook'] ?? '';
    $show_facebook = !empty($_POST['show_facebook']) ? 1 : 0;
    $show_email = !empty($_POST['show_email']) ? 1 : 0;
    $show_phone = !empty($_POST['show_phone']) ? 1 : 0;

    // Validate birthday: must not be a future date
    if (!is_null($birthday)) {
        $ts = strtotime($birthday);
        if ($ts === false) {
            $error = "Ngày sinh không hợp lệ.";
        } else {
            $today = strtotime('today');
            if ($ts > $today) {
                $error = "Nhà du hành thời gian không được phép sử dụng trang web này.";
            }
        }
    }

    if (empty($error)) {
        $stmt = $conn->prepare("UPDATE user_profile SET birthday=?, show_birthday=?, gender=?, show_gender=?, facebook=?, show_facebook=?, show_email=?, show_phone=? WHERE user_id=?");
        $stmt->execute([$birthday, $show_birthday, $gender, $show_gender, $facebook, $show_facebook, $show_email, $show_phone, $user_id]);
        $success = "Thông tin cá nhân đã được cập nhật!";
    }
}

// Lấy lại dữ liệu user sau cập nhật (với profile mở rộng)
$stmt = $conn->prepare("SELECT users.*, 
                user_profile.show_email, user_profile.show_phone, user_profile.show_birthday, 
                user_profile.show_gender, user_profile.show_facebook, 
                user_profile.birthday, user_profile.gender, user_profile.facebook
            FROM users
            LEFT JOIN user_profile ON users.user_id = user_profile.user_id
            WHERE users.user_id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2>Quản lý tài khoản</h2>

    <?php if (!empty($_SESSION['flash_verify_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_verify_success']) ?></div>
        <?php unset($_SESSION['flash_verify_success']); ?>
    <?php endif; ?>

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
        <form method="POST" action="verify.php">
            <input type="hidden" name="method" value="email">
            <button type="submit" class="btn btn-primary">Xác thực/Đổi Email</button>
        </form>
    </div>

    <!-- Phone -->
    <div class="card p-3 mb-3">
        <h4>Số điện thoại</h4>
        <p><strong>Số điện thoại:</strong> <?= htmlspecialchars($user['phone'] ?: 'Chưa cập nhật') ?>
            <?php if ($user['phone']): ?>
                <span class="badge bg-success">Đã xác thực</span>
            <?php endif; ?>
        </p>
            <form method="POST" action="verify.php">
                <input type="hidden" name="method" value="phone">
                <button type="submit" class="btn btn-primary">Xác thực/Đổi SĐT</button>
            </form>
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
                    <option value="0" <?= ($user['gender'] == 0) ? 'selected' : '' ?>>Nam</option>
                    <option value="1" <?= ($user['gender'] == 1) ? 'selected' : '' ?>>Nữ</option>
                    <option value="2" <?= ($user['gender'] == 2) ? 'selected' : '' ?>>Khác</option>
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