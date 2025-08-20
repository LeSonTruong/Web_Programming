<?php
include 'includes/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = trim($_POST['login']); // có thể là email hoặc username
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? OR username=? LIMIT 1");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        header("Location: index.php");
        exit();
    } else {
        $error = "Tên đăng nhập/Email hoặc mật khẩu không đúng!";
    }
}

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
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
        </form>

        <p class="mt-3 text-center">
            Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>