<?php
// Hỗ trợ truyền lý do hiển thị message chi tiết hơn.
// - Khi include từ PHP, có thể đặt $reason = 'not_logged_in' hoặc 'insufficient_permissions'
// - Hoặc truyền qua query string ?reason=not_logged_in khi truy cập trực tiếp

http_response_code(403);

// Lấy reason từ biến nội bộ hoặc từ query string
$reason = $reason ?? ($_GET['reason'] ?? 'forbidden');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'includes/header.php';
?>
<body>
<div class="container my-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div>
                <div class="mb-4">
                    <img src="<?php echo $BASE_URL; ?>/assets/403.<?= rand(1,2) ?>.png" alt="Không cho!" style="max-width:180px;" class="mb-3">
                    <!--<div class="display-1 text-warning fw-bold">403</div>-->
                </div>
                <h2 class="h3 mb-3 text-warning">Không có quyền!</h2>
                <p class="mb-3">
                    <?php
                    switch ($reason) {
                        case 'chuadangnhap':
                            echo 'Bạn cần đăng nhập để thực hiện hành động này.';
                            break;
                        case 'camtailen':
                            echo 'Tài khoản của bạn đã bị khóa quyền tải lên.';
                            break;
                        default:
                            echo 'Bạn không có quyền truy cập tài nguyên này.';
                            break;
                    }
                    ?>
                </p>
                <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                    <a href="<?php echo $BASE_URL; ?>/" class="btn btn-primary">🏠 Về trang chủ</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>

<?php include 'includes/footer.php'; ?>