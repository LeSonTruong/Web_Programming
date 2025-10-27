<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/send_mail.php';

$user_id = $_SESSION['user_id'];
// includes/db.php defines $conn as a PDO instance
global $conn;
// ensure $conn exists
if (!isset($conn)) {
    die('Database connection not available.');
}
$db = $conn;

$method = $_POST['method'] ?? null; // require method to be provided via POST ('email' or 'phone')
$action = $_POST['action'] ?? null; // 'send' or 'verify'

// Enforce that method must be present in POST. If not, return error.
if ((is_null($method)) OR (!isset($_SESSION['user_id']))) {
    echo '<div class="container mt-4">Yêu cầu không hợp lệ.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
$value = '';
if ($method === 'email') {
    $value = $_POST['email'] ?? '';
} elseif ($method === 'phone') {
    $value = $_POST['phone'] ?? '';
}

// normalize inputs
$value = trim((string)$value);
if ($method === 'email') {
    // normalize email to lowercase for checks and use
    $value = mb_strtolower($value);
}

$message = '';

$stmt = $db->prepare('SELECT email, phone FROM users WHERE user_id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($value)) {
    if ($method === 'email') $value = $user['email'];
}

// ensure pending storage in session
if (!isset($_SESSION['pending_verify'])) {
    $_SESSION['pending_verify'] = [];
}

if ($action === 'send') {
    // Validate & Duplicate checks
    if ($method === 'email') {
        // validate email format after normalizing
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $message = 'Email không hợp lệ.';
        } else {
            // check duplicate (compare lowercased email)
            $check = $db->prepare('SELECT user_id, email_verified FROM users WHERE LOWER(email) = ? LIMIT 1');
            $check->execute([$value]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $ownerId = (int)$row['user_id'];
                $emailVerified = !empty($row['email_verified']);
                if ($ownerId === (int)$user_id) {
                    if ($emailVerified) {
                        $message = 'Bạn đang sử dụng email này rồi!';
                    }
                } else {
                    if ($emailVerified) {
                        $message = 'Email này đã được sử dụng bởi tài khoản khác.';
                    }
                }
            }
        }
    } elseif ($method === 'phone') {
        // phone must be exactly 10 digits
        if (!preg_match('/^\d{10}$/', $value)) {
            $message = 'Số điện thoại không hợp lệ. Vui lòng nhập đúng 10 chữ số.';
        } else {
            $check = $db->prepare('SELECT user_id FROM users WHERE phone = ? LIMIT 1');
            $check->execute([$value]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $ownerId = (int)$row['user_id'];
                if ($ownerId === (int)$user_id) {
                    $message = 'Bạn đang sử dụng số điện thoại này rồi!';
                } else {
                    $message = 'Số điện thoại này đã được sử dụng bởi tài khoản khác.';
                }
            }
        }
    }

    // if $message set by validation, skip sending/generating OTP
    if (!empty($message)) {
        // do nothing further in send branch
    } else {
        // generate 6-digit OTP
        $otp = str_pad(strval(random_int(100000, 999999)), 6, '0', STR_PAD_LEFT);
        $expiry = time() + 600; // 10p

        $otp_hash = hash('sha256', $otp);
        $hash_algo = 'sha256';

                // regenerate session id to mitigate fixation after creating OTP
                session_regenerate_id(true);

                $_SESSION['pending_verify'] = [
                    'method' => $method,
                    'value' => $value,
                    'otp_hash' => $otp_hash,
                    'hash_algo' => $hash_algo,
                    'expiry' => $expiry,
                ];

        if ($method === 'email') {
            try {
                $subject = 'StudyShare - Xác thực email';
                $body = "Mã xác thực của bạn là: $otp";
                if (function_exists('gui_email')) {
                    gui_email($value, $subject, $body);
                }
                $message = 'OTP đã được gửi tới email của bạn.';
            } catch (Exception $e) {
                // avoid exposing OTP in production; show generic error and log details
                error_log('Mail send failed for OTP: ' . $e->getMessage());
                $message = 'Không thể gửi email. Vui lòng thử lại sau. Nếu lỗi vẫn tiếp diễn, xin hãy liên hệ admin.';
            }
        } else {
            // For phone, we cannot send SMS here. Do not display OTP on the page in production.
            $message = 'OTP đã được tạo. (Hệ thống SMS chưa cấu hình; liên hệ quản trị để kiểm tra)';
            error_log('OTP generated for phone ' . $value . '. OTP=' . $otp);
        }
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
    } elseif (!empty($pending['otp_hash'])) {
        // compute candidate hash for comparison
        $candidate_hash = hash('sha256', $input_otp);

        if (hash_equals($pending['otp_hash'], $candidate_hash)) {
            // Verified - write to DB
            if ($method === 'email') {
                $update = $db->prepare('UPDATE users SET email = ?, email_verified = 1 WHERE user_id = ?');
                $update->execute([$value, $user_id]);
            } elseif ($method === 'phone') {
                $update = $db->prepare('UPDATE users SET phone = ? WHERE user_id = ?');
                $update->execute([$value, $user_id]);
            }
            unset($_SESSION['pending_verify']);
            $_SESSION['flash_verify_success'] = 'Xác thực thành công.';
            header('Location: settings_profile.php');
            exit;
        }
        $message = 'OTP không chính xác. Vui lòng thử lại.';
    } else {
        // no hash stored - invalid state
        $message = 'Không có mã OTP hợp lệ. Vui lòng gửi lại OTP.';
    }
}

?>
<div class="d-flex justify-content-center align-items-center" style="min-height:70vh;">
    <div class="card shadow-sm p-4" style="max-width: 400px; width: 100%;">
        <h3 class="card-title text-center mb-4">Xác thực <?= $method === 'phone' ? 'SĐT' : 'Email' ?></h3>
        <?php if (!empty($message)): ?>
            <div class="alert alert-info"><?= $message ?></div>
        <?php endif; ?>

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
                
                <div class="d-flex justify-content-center mb-2">
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
                <div class="d-flex justify-content-center">
                    <button type="submit" name="action" value="verify" class="btn btn-success">Xác thực</button>
                </div>
            </form>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
