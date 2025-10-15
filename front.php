<?php
// Front controller riêng (file: front.php)
// - Mục đích: nhận tất cả request được rewrite từ .htaccess
// - Nếu request trỏ đến file/thư mục tồn tại thì include file đó (cho phép phục vụ tài nguyên tĩnh)
// - Nếu không tìm thấy route, trả HTTP 404 và include `!404.php`
// - Nếu là truy cập gốc '/', sẽ include `index.php` để hiển thị trang chủ

// Lấy đường dẫn yêu cầu (chỉ path, không bao gồm query string)
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$scriptName = '/' . basename(__FILE__);

// Nếu không phải truy cập gốc hoặc file front controller trực tiếp, xử lý routing
if ($path !== '/' && $path !== $scriptName) {
    // Bỏ dấu / đầu
    $target = ltrim($path, '/');

    // Ngăn chặn path traversal
    if (strpos($target, '..') !== false) {
        http_response_code(400);
        echo 'Yêu cầu không hợp lệ.';
        exit;
    }

    // Nếu file tồn tại trong thư mục hiện tại (document root), include và kết thúc
    $candidate = __DIR__ . DIRECTORY_SEPARATOR . $target;
    if ($target !== '' && file_exists($candidate)) {
        include $candidate;
        exit;
    }

    // Không tìm thấy -> trả 404 và include trang 404 tuỳ chỉnh
    http_response_code(404);
    if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . '!404.php')) {
        include __DIR__ . DIRECTORY_SEPARATOR . '!404.php';
    } else {
        echo '404 Not Found';
    }
    exit;
}

// Nếu tới đây: là truy cập gốc '/' hoặc truy cập trực tiếp file front.php
// -> Hiển thị trang chủ bằng cách include index.php
include __DIR__ . '/index.php';
exit;
