<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/send_mail.php';

// Database PDO in $conn from includes/db.php
$db = $conn;

$action = $_POST['action'] ?? null; // 'send', 'verify', 'reset'
$method = 'email';
$message = '';

// ensure session pending storage
if (!isset($_SESSION['pending_reset'])) {
    $_SESSION['pending_reset'] = [];
}

// SEND OTP
if ($action === 'send') {
    $email = trim(mb_strtolower($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Email không hợp lệ.';
    } else {
        // check account exists
        $stmt = $db->prepare('SELECT user_id FROM users WHERE LOWER(email) = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $message = 'Không tìm thấy tài khoản với email này.';
        } else {
            // generate OTP
            $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = time() + 600; // 10 minutes
            $otp_hash = hash('sha256', $otp);

            // regenerate session id
            session_regenerate_id(true);

            $_SESSION['pending_reset'] = [
                'method' => $method,
                'email' => $email,
                'otp_hash' => $otp_hash,
                'expiry' => $expiry,
                'verified' => false,
            ];

            // send email
            try {
                $subject = 'StudyShare - Đặt lại mật khẩu';
                $body = "Mã đặt lại mật khẩu của bạn là: $otp\nMã có hiệu lực trong 10 phút.";
                if (function_exists('gui_email')) {
                    gui_email($email, $subject, $body);
                }
                $message = 'OTP đã được gửi tới email của bạn.';
            } catch (Exception $e) {
                error_log('Mail send failed for reset OTP: ' . $e->getMessage());
                $message = 'Không thể gửi email. Vui lòng thử lại sau.';
            }
        }
    }
}

// VERIFY OTP
if ($action === 'verify') {
    $email = trim(mb_strtolower($_POST['email'] ?? ''));
    $input_otp = trim($_POST['otp'] ?? '');

    $pending = $_SESSION['pending_reset'] ?? null;
    if (!$pending || ($pending['email'] ?? '') !== $email) {
        $message = 'Không có mã OTP đang chờ. Vui lòng gửi mã OTP trước.';
    } elseif (time() > ($pending['expiry'] ?? 0)) {
        $message = 'Mã OTP đã hết hạn. Vui lòng gửi lại.';
        unset($_SESSION['pending_reset']);
    } else {
        $candidate_hash = hash('sha256', $input_otp);
        if (!empty($pending['otp_hash']) && hash_equals($pending['otp_hash'], $candidate_hash)) {
            // verified
            $_SESSION['pending_reset']['verified'] = true;
            // redirect to show password form
            header('Location: reset_password.php');
            exit;
        } else {
            $message = 'OTP không chính xác. Vui lòng thử lại.';
        }
    }
}

// RESET PASSWORD
if ($action === 'reset') {
    $pending = $_SESSION['pending_reset'] ?? null;
    if (!$pending || empty($pending['verified']) || ($pending['verified'] !== true)) {
        $message = 'Không có yêu cầu đặt lại mật khẩu hợp lệ. Vui lòng gửi mã OTP trước.';
    } else {
        $email = $pending['email'];
        $pw = $_POST['password'] ?? '';
        $pw2 = $_POST['password_confirm'] ?? '';

        // basic validations
        if ($pw !== $pw2) {
            $message = 'Mật khẩu và xác nhận mật khẩu không khớp.';
        } elseif (mb_strlen($pw) < 7 || !preg_match('/[A-Z]/', $pw) || !preg_match('/[a-z]/', $pw)) {
            $message = 'Mật khẩu phải dài hơn 6 ký tự và chứa ít nhất 1 chữ hoa và 1 chữ thường.';
        } else {
            // update password
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            try {
                $stmt = $db->prepare('UPDATE users SET password = ? WHERE LOWER(email) = ?');
                $stmt->execute([$hash, mb_strtolower($email)]);
                // clear session pending
                unset($_SESSION['pending_reset']);
                $_SESSION['flash_reset_success'] = 'Mật khẩu đã được đặt lại thành công. Vui lòng đăng nhập.';
                header('Location: login.php');
                exit;
            } catch (Exception $e) {
                error_log('Password reset DB error: ' . $e->getMessage());
                $message = 'Lỗi khi cập nhật mật khẩu. Vui lòng thử lại sau.';
            }
        }
    }
}

// Determine UI state
$pending = $_SESSION['pending_reset'] ?? null;
$show_password_form = !empty($pending) && !empty($pending['verified']) && $pending['verified'] === true;

?>
<div class="d-flex justify-content-center align-items-center" style="min-height:70vh;">
    <div class="card shadow-sm p-4" style="max-width: 420px; width: 100%;">
        <h3 class="card-title text-center mb-4">Đặt lại mật khẩu</h3>

        <?php if (!empty($message)): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!$show_password_form): ?>
            <form method="post" class="mb-3">
                <div class="mb-2">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($pending['email'] ?? '') ?>" required>
                </div>
                <div class="d-flex justify-content-center mb-2">
                    <button type="submit" name="action" value="send" class="btn btn-secondary">Gửi mã</button>
                </div>
            </form>

            <hr>

            <form method="post">
                <input type="hidden" name="email" value="<?= htmlspecialchars($pending['email'] ?? '') ?>">
                <div class="mb-2">
                    <label>Nhập mã OTP</label>
                    <input type="text" name="otp" class="form-control" required>
                </div>
                <div class="d-flex justify-content-center">
                    <button type="submit" name="action" value="verify" class="btn btn-success">Xác thực</button>
                </div>
            </form>
        <?php else: ?>
            <form method="post">
                <div class="mb-2">
                    <label>Mật khẩu mới</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label>Nhập lại mật khẩu</label>
                    <input type="password" name="password_confirm" class="form-control" required>
                </div>
                <div class="d-flex justify-content-center">
                    <button type="submit" name="action" value="reset" class="btn btn-primary">Đặt lại mật khẩu</button>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>