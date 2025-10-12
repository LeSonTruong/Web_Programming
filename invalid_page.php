<?php
// Trả về mã lỗi HTTP 404
http_response_code(404);

session_start();
include 'includes/header.php';
?>

<div class="container my-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="error-page">
                <!-- Icon lỗi 404 -->
                <div class="error-icon mb-4">
                    <h1 class="display-1 text-danger fw-bold">404</h1>
                    <div class="fs-1 mb-3">🚫</div>
                </div>
                
                <!-- Thông báo lỗi -->
                <h2 class="h3 mb-3 text-danger">Trang không tồn tại!</h2>
                <p class="text-muted mb-4">
                    Xin lỗi, trang bạn đang tìm kiếm không tồn tại hoặc đã bị xóa. 
                    Vui lòng kiểm tra lại đường dẫn hoặc quay về trang chủ.
                </p>
                
                <!-- Nút điều hướng -->
                <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                    <button onclick="history.back()" class="btn btn-outline-primary">
                        ← Quay lại trang trước
                    </button>
                    <a href="index.php" class="btn btn-primary">
                        🏠 Về trang chủ
                    </a>
                    <a href="documents.php" class="btn btn-outline-secondary">
                        📚 Xem tài liệu
                    </a>
                </div>
                
                <!-- Thông tin hỗ trợ -->
                <div class="mt-5 pt-4 border-top">
                    <p class="text-muted small">
                        Nếu bạn cho rằng đây là lỗi hệ thống, vui lòng 
                        <a href="mailto:support@example.com" class="text-decoration-none">liên hệ với chúng tôi</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.error-page {
    padding: 2rem 0;
}

.error-icon h1 {
    font-size: 8rem;
    line-height: 1;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
}

@media (max-width: 576px) {
    .error-icon h1 {
        font-size: 6rem;
    }
}

/* Dark mode support */
body.dark-mode .error-icon h1 {
    color: #ff6b6b !important;
}

body.dark-mode .text-danger {
    color: #ff6b6b !important;
}
</style>

<?php include 'includes/footer.php'; ?>