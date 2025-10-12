<?php
function callAIAPI($text) {
    $url = 'http://127.0.0.1:5000/process';
    
    $data = array(
        'text' => $text,
        'lang' => 'vi_VN'
    );
    
    // Khởi tạo cURL
    $ch = curl_init();
    
    // Cấu hình cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Accept: application/json'
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Timeout 60 giây
    
    // Thực hiện request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Kiểm tra lỗi
    if (curl_error($ch)) {
        curl_close($ch);
        return array(
            'success' => false,
            'error' => 'cURL Error: ' . curl_error($ch)
        );
    }
    
    curl_close($ch);
    
    // Kiểm tra HTTP status code
    if ($httpCode !== 200) {
        return array(
            'success' => false,
            'error' => 'HTTP Error: ' . $httpCode
        );
    }
    
    // Parse JSON response
    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return array(
            'success' => false,
            'error' => 'JSON Parse Error: ' . json_last_error_msg()
        );
    }
    
    return $result;
}

// Hàm trích xuất text từ PDF
function extractTextFromPDF($filePath) {
    // Cài đặt thư viện PDF parser: composer require smalot/pdfparser
    require_once 'vendor/autoload.php';
    
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();
        
        // Làm sạch text
        $text = preg_replace('/\s+/', ' ', $text); // Thay nhiều khoảng trắng bằng 1
        $text = trim($text);
        
        return $text;
    } catch (Exception $e) {
        return false;
    }
}

// Hàm trích xuất text từ file code (txt, php, js, v.v.)
function extractTextFromCodeFile($filePath) {
    $content = file_get_contents($filePath);
    if ($content === false) {
        return false;
    }
    
    // Làm sạch và giới hạn độ dài
    $content = strip_tags($content); // Loại bỏ HTML tags nếu có
    $content = preg_replace('/\s+/', ' ', $content);
    $content = trim($content);
    
    return $content;
}
?>
