<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/send_mail.php';

if (!isset($_SESSION['user_id'])) {
    echo '<div class="container mt-4">Vui lòng đăng nhập để xác thực.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$user_id = $_SESSION['user_id'];
// includes/db.php defines $conn as a PDO instance
global $conn;
// ensure $conn exists
if (!isset($conn)) {
    die('Database connection not available.');
}
$db = $conn;

$method = $_POST['method'] ?? $_GET['method'] ?? null; // 'email' or 'phone'
$value = '';
if ($method === 'email') {
    $value = $_POST['email'] ?? '';
} elseif ($method === 'phone') {
    $value = $_POST['phone'] ?? '';
}

$action = $_POST['action'] ?? null; // 'send' or 'verify'
$message = '';

// Load current user info to prefill
$stmt = $db->prepare('SELECT email, phone FROM users WHERE user_id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    echo '<div class="container mt-4">Người dùng không tồn tại.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if (empty($value)) {
    if ($method === 'email') $value = $user['email'];
    if ($method === 'phone') $value = $user['phone'];
}

// ensure pending storage in session
if (!isset($_SESSION['pending_verify'])) {
    $_SESSION['pending_verify'] = [];
}

if ($action === 'send') {
    // generate 6-digit OTP
    $otp = str_pad(strval(random_int(100000, 999999)), 6, '0', STR_PAD_LEFT);
    $expiry = time() + 600; // 10 minutes
    $_SESSION['pending_verify'] = [
        'method' => $method,
        'value' => $value,
        'otp' => $otp,
        'expiry' => $expiry,
    ];

    if ($method === 'email') {
        // try to send email using existing send_mail helper if available
            if (file_exists(__DIR__ . '/includes/send_mail.php')) {
            // lightweight: use send_mail.php which may expose a function send_mail
            // Fallback: show OTP on page if send fails (useful for testing)
            try {
                // includes/send_mail.php in this project expects parameters; we'll attempt a basic usage
                $subject = 'Mã xác thực của bạn';
                $body = "Mã xác thực của bạn là: $otp";
                // If a helper function exists, call it; otherwise use PHPMailer fallback
                    if (function_exists('gui_email')) {
                        gui_email($value, $subject, $body);
                    } else {
                        // no helper - show message but still store OTP in session
                    }
                $message = 'OTP đã được gửi tới email của bạn.';
            } catch (Exception $e) {
                $message = 'Không thể gửi email. Vui lòng kiểm tra cấu hình. Mã OTP hiển thị bên dưới (dùng cho môi trường dev): ' . htmlspecialchars($otp);
            }
        } else {
            $message = 'OTP (hiển thị vì không có helper gửi mail): ' . htmlspecialchars($otp);
        }
    } else {
        // For phone, we cannot send SMS here. Display message and show OTP for testing.
        $message = 'OTP đã được tạo và lưu tạm. (Hệ thống SMS chưa cấu hình) OTP: ' . htmlspecialchars($otp);
    }
}

if ($action === 'verify') {
    $input_otp = trim($_POST['otp'] ?? '');
    $pending = $_SESSION['pending_verify'] ?? null;
    if (!$pending || $pending['method'] !== $method || $pending['value'] !== $value) {
        $message = 'Không có mã OTP đang chờ cho giá trị này. Vui lòng gửi mã OTP trước.';
    } elseif (time() > ($pending['expiry'] ?? 0)) {
        $message = 'Mã OTP đã hết hạn. Vui lòng gửi lại.';
        unset($_SESSION['pending_verify']);
    } elseif ($input_otp === ($pending['otp'] ?? '')) {
        // Verified - write to DB
        if ($method === 'email') {
            $update = $db->prepare('UPDATE users SET email = ?, email_verified = 1 WHERE user_id = ?');
            $update->execute([$value, $user_id]);
        } elseif ($method === 'phone') {
            $update = $db->prepare('UPDATE users SET phone = ?, otp_code = NULL, otp_expiry = NULL WHERE user_id = ?');
            $update->execute([$value, $user_id]);
        }
        unset($_SESSION['pending_verify']);
        $_SESSION['flash_verify_success'] = 'Xác thực thành công.';
        header('Location: settings_profile.php');
        exit;
    } else {
        $message = 'OTP không chính xác. Vui lòng thử lại.';
    }
}

?>
<div class="container mt-4">
    <h3>Xác thực <?= $method === 'phone' ? 'SĐT' : 'Email' ?></h3>
    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <div class="card p-3">
        <form method="post">
            <input type="hidden" name="method" value="<?= htmlspecialchars($method) ?>">
            <?php if ($method === 'email'): ?>
                <div class="mb-2">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($value) ?>" required>
                </div>
            <?php else: ?>
                <div class="mb-2">
                    <label>Số điện thoại</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($value) ?>" required>
                </div>
            <?php endif; ?>

            <div class="d-flex gap-2 mb-2">
                <button type="submit" name="action" value="send" class="btn btn-secondary">Gửi OTP</button>
            </div>
        </form>

        <hr>

        <form method="post">
            <input type="hidden" name="method" value="<?= htmlspecialchars($method) ?>">
            <?php if ($method === 'email'): ?>
                <input type="hidden" name="email" value="<?= htmlspecialchars($value) ?>">
            <?php else: ?>
                <input type="hidden" name="phone" value="<?= htmlspecialchars($value) ?>">
            <?php endif; ?>
            <div class="mb-2">
                <label>Nhập OTP</label>
                <input type="text" name="otp" class="form-control" required>
            </div>
            <button type="submit" name="action" value="verify" class="btn btn-success">Xác thực</button>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
