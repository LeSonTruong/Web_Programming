<?php
include 'includes/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (fullname, username, email, password, role, created_at) 
                           VALUES (?, ?, ?, ?, 'user', NOW())");
    if ($stmt->execute([$fullname, $username, $email, $password])) {
        header("Location: login.php");
        exit();
    } else {
        $error = "Đăng ký thất bại! Vui lòng thử lại.";
    }
}

include 'includes/header.php';
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
                <input type="text" id="fullname" name="fullname" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="username" class="form-label">Tên đăng nhập</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Địa chỉ Email</label>
                <input type="email" id="email" name="email" class="form-control" required>
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