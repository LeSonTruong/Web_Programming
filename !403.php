<?php
// Trả về mã lỗi HTTP 404
http_response_code(404);

session_start();
include 'includes/header.php';
?>
<body>
<div class="container my-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="error-page">
                <div class="error-icon mb-4">
                    <img src="assets/404.2.png" alt="Yanfei thinking brr" style="max-width:180px;" class="mb-3">
                    <div class="display-1 text-danger fw-bold">403</div>
                </div>
                
                <!-- Thông báo lỗi -->
                <h2 class="h3 mb-3 text-danger">Bạn không có quyền truy cập trang này!</h2>
                <p class="mb-4">
                    Xin lỗi, bạn không có quyền truy cập vào trang này. 
                    Vui lòng kiểm tra lại quyền truy cập của bạn hoặc quay về trang chủ.
                </p>
                
                <!-- Nút điều hướng -->
                <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                    <button onclick="history.back()" class="btn btn-outline-primary">
                        ← Quay lại trang trước
                    </button>
                    <a href="index.php" class="btn btn-primary">
                        🏠 Về trang chủ
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
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
</style>

<?php include 'includes/footer.php'; ?>