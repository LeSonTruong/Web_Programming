<?php
include 'includes/db.php';
session_start();

// Nếu đã đăng nhập, chuyển hướng về trang chủ
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = trim($_POST['login']); // Email hoặc username
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=? OR username=? LIMIT 1");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Kiểm tra tài khoản có bị banned không
        if ($user['banned'] == 1) {
            $error = "Tài khoản của bạn đã bị khóa.";
        } else {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['display_name'] = $user['display_name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['display_name'] = $user['display_name'] ?? $user['username'];
            $_SESSION['avatar'] = $user['avatar'] ?? 'default.png';
            $_SESSION['role'] = $user['role'];

            header("Location: index.php");
            exit();
        }
    } else {
        $error = "Thông tin đăng nhập không chính xác!";
    }
}

// Chỉ include header sau khi xử lý redirect
include 'includes/header.php';
?>

<div class="d-flex justify-content-center align-items-center" style="min-height:70vh;">
    <div class="card shadow-sm p-4" style="max-width: 400px; width: 100%;">
        <h3 class="card-title text-center mb-4">Đăng nhập</h3>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label for="login" class="form-label">Email hoặc Tên đăng nhập</label>
                <input type="text" id="login" name="login" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Mật khẩu</label>
                <div class="input-group">
                    <input type="password" id="password" name="password" class="form-control" required>
                    <button type="button" class="btn btn-outline-secondary" id="togglePassword">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
        </form>

        <p class="mt-3 text-center">
            Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
        </p>
        <p class="text-center">
            <a href="reset_password.php">Quên mật khẩu?</a>
        </p>
    </div>
</div>

<script>
    // Nút con mắt: show/hide password
    const toggleBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    toggleBtn.addEventListener('click', () => {
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
        toggleBtn.textContent = type === 'password' ? '👁️' : '🙈';
    });
</script>

<?php include 'includes/footer.php'; ?>