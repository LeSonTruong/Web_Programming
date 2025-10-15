<?php
http_response_code(404);

session_start();

include 'includes/header.php';
?>
<body>
<div class="container my-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div>
                <!-- Icon lỗi 404 -->
                <div class="mb-4">
                    <img src="<?php echo $BASE_URL; ?>/assets/404.<?= rand(1,3) ?>.png" alt="Yanfei thinking brr" style="max-width:180px;" class="mb-3">
                    <div class="display-1 text-danger fw-bold">404</div>
                </div>
                
                <!-- Thông báo lỗi -->
                <h2 class="h3 mb-3 text-danger">Trang không tồn tại!</h2>
                <p class="mb-4">
                    Xin lỗi, trang bạn đang tìm kiếm không tồn tại hoặc đã bị xóa. 
                    Vui lòng kiểm tra lại đường dẫn hoặc quay về trang chủ.
                </p>
                
                <!-- Nút điều hướng -->
                <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                    <button onclick="history.back()" class="btn btn-outline-primary">
                        ← Quay lại trang trước
                    </button>
                    <a href="<?php echo $BASE_URL; ?>/index.php" class="btn btn-primary">
                        🏠 Về trang chủ
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>

<?php include 'includes/footer.php'; ?>