<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// Only admins
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    $reason = '';
    include __DIR__ . '/!403.php';
    exit();
}

include 'includes/header.php';

// get user id from GET or POST
$edit_user_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if ($edit_user_id <= 0) {
    echo '<div class="container my-5"><div class="alert alert-danger">Không có user được chọn để chỉnh sửa.</div></div>';
    include 'includes/footer.php';
    exit();
}

// load user + profile
$stmt = $conn->prepare("SELECT users.*, 
                user_profile.show_email, user_profile.show_phone, user_profile.show_birthday, 
                user_profile.show_gender, user_profile.show_facebook, 
                user_profile.birthday, user_profile.gender, user_profile.facebook
            FROM users
            LEFT JOIN user_profile ON users.user_id = user_profile.user_id
            WHERE users.user_id=? LIMIT 1");
$stmt->execute([$edit_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo '<div class="container my-5"><div class="alert alert-danger">Không tìm thấy user.</div></div>';
    include 'includes/footer.php';
    exit();
}

$error = '';
$success = '';

// Handle POST save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    // collect fields
    $display_name = trim($_POST['display_name'] ?? $user['display_name']);
    $email = trim(mb_strtolower($_POST['email'] ?? $user['email']));
    $phone = trim($_POST['phone'] ?? $user['phone']);

    // extended profile
    $birthday = $_POST['birthday'] ?? $user['birthday'] ?? null;
    if ($birthday === '') $birthday = null;
    $gender = isset($_POST['gender']) ? (int)$_POST['gender'] : ($user['gender'] ?? 0);
    $facebook = trim($_POST['facebook'] ?? $user['facebook'] ?? '');
    $show_email = !empty($_POST['show_email']) ? 1 : 0;
    $show_phone = !empty($_POST['show_phone']) ? 1 : 0;
    $show_birthday = !empty($_POST['show_birthday']) ? 1 : 0;
    $show_gender = !empty($_POST['show_gender']) ? 1 : 0;
    $show_facebook = !empty($_POST['show_facebook']) ? 1 : 0;

    // validations
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    }
    if (!$error && $phone !== '' && !preg_match('/^\d{10}$/', $phone)) {
        $error = 'Số điện thoại phải đúng 10 chữ số.';
    }

    // check duplicate email (other user)
    if (!$error) {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE LOWER(email)=? AND user_id<>? LIMIT 1');
        $stmt->execute([mb_strtolower($email), $edit_user_id]);
        if ($stmt->fetch()) {
            $error = 'Email này đã được sử dụng bởi tài khoản khác.';
        }
    }

    if (!$error) {
        // process avatar upload if present
        if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif'];
            if (!in_array($ext, $allowed)) {
                $error = 'Định dạng ảnh không hợp lệ.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'Ảnh quá lớn (tối đa 5MB).';
            } else {
                $newName = 'avatar_' . $edit_user_id . '.' . $ext;
                $path = 'uploads/avatars/' . $newName;
                if (!move_uploaded_file($file['tmp_name'], $path)) {
                    $error = 'Không lưu được ảnh đại diện.';
                } else {
                    // update avatar later
                    $avatar_to_set = $newName;
                }
            }
        }
    }

    if (!$error) {
        try {
            // update users table: display_name, email, email_verified (set 1 if changed), phone
            $params = [];
            $update_parts = [];

            $update_parts[] = 'display_name = ?'; $params[] = $display_name;

            // if email changed, set verified to 1 as requested
            if (mb_strtolower($email) !== mb_strtolower($user['email'] ?? '')) {
                $update_parts[] = 'email = ?'; $params[] = $email;
                $update_parts[] = 'email_verified = 1';
            }

            // phone
            if ($phone !== ($user['phone'] ?? '')) { $update_parts[] = 'phone = ?'; $params[] = $phone; }

            // upload_locked is no longer editable from this form

            // avatar
            if (isset($avatar_to_set)) { $update_parts[] = 'avatar = ?'; $params[] = $avatar_to_set; }

            if (!empty($update_parts)) {
                $params[] = $edit_user_id;
                $sql = 'UPDATE users SET ' . implode(', ', $update_parts) . ' WHERE user_id = ?';
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            }

            // update user_profile
            $up_stmt = $conn->prepare('REPLACE INTO user_profile (user_id, birthday, gender, facebook, show_email, show_phone, show_birthday, show_gender, show_facebook) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $up_stmt->execute([$edit_user_id, $birthday, $gender, $facebook, $show_email, $show_phone, $show_birthday, $show_gender, $show_facebook]);

            $success = 'Thông tin người dùng đã được cập nhật.';

            // if admin edited their own profile, sync session avatar/display_name
            if ($edit_user_id === ($_SESSION['user_id'] ?? 0)) {
                if (!empty($avatar_to_set)) $_SESSION['avatar'] = $avatar_to_set;
                $_SESSION['display_name'] = $display_name;
            }

            // reload user data
            $stmt = $conn->prepare("SELECT users.*, user_profile.show_email, user_profile.show_phone, user_profile.show_birthday, user_profile.show_gender, user_profile.show_facebook, user_profile.birthday, user_profile.gender, user_profile.facebook FROM users LEFT JOIN user_profile ON users.user_id = user_profile.user_id WHERE users.user_id=? LIMIT 1");
            $stmt->execute([$edit_user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log('Admin edit user error: ' . $e->getMessage());
            $error = 'Lỗi khi lưu dữ liệu. Vui lòng thử lại.';
        }
    }
}

?>
<div class="container mt-4">
    <h2>Admin — Chỉnh sửa người dùng</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= htmlspecialchars($edit_user_id) ?>">

        <div class="card p-3 mb-3">
            <h4>Ảnh đại diện</h4>
            <img src="uploads/avatars/<?= htmlspecialchars($user['avatar'] ?? 'default.png') ?>" width="100" class="rounded mb-2">
            <input type="file" name="avatar" class="form-control mb-2">
        </div>

        <div class="card p-3 mb-3">
            <h4>Thông tin chính</h4>
            <div class="mb-2">
                <label class="form-label">Tên hiển thị</label>
                <input type="text" name="display_name" class="form-control" value="<?= htmlspecialchars($user['display_name'] ?? '') ?>" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Username (không thể sửa)</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
            </div>
            <div class="mb-2">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
            </div>
            <div class="mb-2">
                <label class="form-label">Số điện thoại</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
            </div>
        </div>

        <div class="card p-3 mb-3">
            <h4>Thông tin mở rộng</h4>
            <div class="mb-2">
                <label class="form-label">Ngày sinh</label>
                <input type="date" name="birthday" class="form-control" value="<?= htmlspecialchars($user['birthday'] ?? '') ?>">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="show_birthday" name="show_birthday" value="1" <?= !empty($user['show_birthday']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="show_birthday">Hiển thị ngày sinh</label>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Giới tính</label>
                <select class="form-select" name="gender">
                    <option value="0" <?= ($user['gender'] == 0) ? 'selected' : '' ?>>Nam</option>
                    <option value="1" <?= ($user['gender'] == 1) ? 'selected' : '' ?>>Nữ</option>
                    <option value="2" <?= ($user['gender'] == 2) ? 'selected' : '' ?>>Khác</option>
                </select>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="show_gender" name="show_gender" value="1" <?= !empty($user['show_gender']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="show_gender">Hiển thị giới tính</label>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Facebook</label>
                <input type="url" name="facebook" class="form-control" value="<?= htmlspecialchars($user['facebook'] ?? '') ?>">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="show_facebook" name="show_facebook" value="1" <?= !empty($user['show_facebook']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="show_facebook">Hiển thị Facebook</label>
                </div>
            </div>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="show_email" name="show_email" value="1" <?= !empty($user['show_email']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="show_email">Hiển thị email</label>
            </div>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="show_phone" name="show_phone" value="1" <?= !empty($user['show_phone']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="show_phone">Hiển thị số điện thoại</label>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" name="save_user" class="btn btn-primary">Lưu</button>
            <a href="user.php" class="btn btn-secondary">Hủy</a>
        </div>
    </form>
</div>

<?php include 'includes/footer.php';
