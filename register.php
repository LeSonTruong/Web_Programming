<?php
include 'includes/header.php';
include 'includes/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password_raw = trim($_POST['password']);

    // ✅ 1. Kiểm tra dữ liệu rỗng
    if (empty($fullname) || empty($username) || empty($email) || empty($password_raw)) {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    }
    // ✅ 2. Kiểm tra định dạng email
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Địa chỉ email không hợp lệ!";
    } else {
        // ✅ 3. Kiểm tra username / email đã tồn tại chưa
        $check = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
        $check->execute([$email, $username]);
        if ($check->fetchColumn() > 0) {
            $error = "Tên đăng nhập hoặc email đã tồn tại!";
        } else {
            // ✅ 4. Hash mật khẩu và insert
            $password = password_hash($password_raw, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (fullname, username, email, password, role, created_at) 
                                   VALUES (?, ?, ?, ?, 'user', NOW())");
            if ($stmt->execute([$fullname, $username, $email, $password])) {
                header("Location: login.php");
                exit();
            } else {
                $error = "Đăng ký thất bại! Vui lòng thử lại.";
            }
        }
    }
}

?>

<div class="d-flex justify-content-center align-items-center" style="min-height:70vh;">
    <div class="card shadow-sm p-4" style="max-width: 450px; width: 100%;">
        <h3 class="card-title text-center mb-4">Đăng ký tài khoản</h3>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label for="fullname" class="form-label">Họ và tên</label>
                <input type="text" id="fullname" name="fullname" class="form-control" value="<?= htmlspecialchars($fullname ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label for="username" class="form-label">Tên đăng nhập</label>
                <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($username ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Địa chỉ Email</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Mật khẩu</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-success w-100">Đăng ký</button>
        </form>

        <p class="mt-3 text-center">
            Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a>
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>