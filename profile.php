<?php
include 'includes/header.php';
require_once "includes/db.php";


// Nếu có user_id trên URL thì lấy user đó, nếu không thì lấy user đang đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo '<div class="container my-5">
            <div class="alert alert-warning text-center">
                ⚠️ Vui lòng đăng nhập để xem trang cá nhân!
            </div>
          </div>';
    include 'includes/footer.php';
    exit();
}

// Xác định kiểu truy vấn: user_id, username, hoặc chính mình

if (isset($_GET['user_id'])) {
    $view_user_id = intval($_GET['user_id']);
    $query = "SELECT * FROM users WHERE user_id=?";
    $param = [$view_user_id];
} elseif (isset($_GET['user'])) {
    $view_user_id = null; // sẽ lấy sau khi truy vấn
    $query = "SELECT * FROM users WHERE username=?";
    $param = [$_GET['user']];
} else {
    $view_user_id = $_SESSION['user_id'];
    $query = "SELECT * FROM users WHERE user_id=?";
    $param = [$view_user_id];
}

$stmt = $conn->prepare($query);
$stmt->execute($param);

$user = $stmt->fetch(PDO::FETCH_ASSOC);
if ($view_user_id === null && $user) {
    $view_user_id = $user['user_id'];
}

// Nếu không tìm thấy user thì báo lỗi
if (!$user) {
    echo '<div class="container my-5">
            <div class="alert alert-danger text-center">
                Không tìm thấy người dùng này!
            </div>
          </div>';
    include 'includes/footer.php';
    exit();
}

// Chỉ đồng bộ avatar & display_name cho header nếu là chính mình
if ($user['user_id'] == $_SESSION['user_id']) {
    $_SESSION['avatar'] = $user['avatar'] ?? 'default.png';
    $_SESSION['display_name'] = $user['display_name'] ?? $user['username'];
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow p-4 mb-4">
                <div class="d-flex align-items-center gap-4 mb-3">
                    <img src="uploads/avatars/<?= htmlspecialchars($user['avatar'] ?? 'default.png') ?>" width="120" height="120" class="rounded-circle border">
                    <div>
                        <h3 class="mb-1 fw-bold"><?= htmlspecialchars($user['display_name'] ?? $user['username']) ?></h3>
                        <div class="text-muted">@<?= htmlspecialchars($user['username']) ?></div>
                    </div>
                </div>
                <hr>
                <?php
                // Đảm bảo so sánh đúng user đang xem với user đăng nhập
                $is_owner = ($user['user_id'] == $_SESSION['user_id']);
                $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

                // Quyền hiển thị từng trường
                $can_show_email = ($is_owner || $is_admin || !empty($user['show_email']));
                $can_show_phone = ($is_owner || $is_admin || !empty($user['show_phone']));
                $can_show_birthday = ($is_owner || $is_admin || !empty($user['show_birthday']));
                $can_show_gender = ($is_owner || $is_admin || !empty($user['show_gender']));
                $can_show_facebook = ($is_owner || $is_admin || !empty($user['show_facebook']));
                ?>
                <div class="mb-3">
                    <?php if ($is_owner): ?>
                        <span class="badge bg-primary">Trang cá nhân của bạn</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Trang cá nhân của <?= htmlspecialchars($user['display_name'] ?? $user['username']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <strong>Email:</strong>
                    <?php if ($can_show_email): ?>
                        <?= htmlspecialchars($user['email']) ?>
                        <?php if ($user['email_verified']): ?>
                            <span class="badge bg-success ms-2">Đã xác thực</span>
                        <?php else: ?>
                            <span class="badge bg-warning ms-2">Chưa xác thực</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-secondary">(Ẩn)</span>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <strong>Số điện thoại:</strong>
                    <?php if ($can_show_phone): ?>
                        <?= htmlspecialchars($user['phone'] ?: 'Chưa cập nhật') ?>
                        <?php if (!empty($user['phone']) && empty($user['otp_code'])): ?>
                            <span class="badge bg-success ms-2">Đã xác thực</span>
                        <?php elseif (!empty($user['phone'])): ?>
                            <span class="badge bg-warning ms-2">Chưa xác thực</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">(Ẩn)</span>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <strong>Ngày sinh:</strong>
                    <?php if ($can_show_birthday): ?>
                        <?= !empty($user['birthday']) ? date('d/m/Y', strtotime($user['birthday'])) : '<span class="text-secondary">Chưa cập nhật</span>' ?>
                    <?php else: ?>
                        <span class="text-light">(Ẩn)</span>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <strong>Giới tính:</strong>
                    <?php if ($can_show_gender): ?>
                        <?php
                        if ($user['gender'] === 'male') echo 'Nam';
                        elseif ($user['gender'] === 'female') echo 'Nữ';
                        elseif ($user['gender'] === 'other') echo 'Khác';
                        else echo '<span class="text-secondary">Chưa cập nhật</span>';
                        ?>
                    <?php else: ?>
                        <span class="text-secondary">(Ẩn)</span>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <strong>Facebook:</strong>
                    <?php if ($can_show_facebook): ?>
                        <?php if (!empty($user['facebook'])): ?>
                            <a href="<?= htmlspecialchars($user['facebook']) ?>" target="_blank" rel="noopener" class="text-primary">Facebook cá nhân</a>
                        <?php else: ?>
                            <span class="text-secondary">Chưa liên kết</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">(Ẩn)</span>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <strong>Ngày tạo tài khoản:</strong> <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include "includes/footer.php"; ?>