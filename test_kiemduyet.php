<?php
// File test_kiemduyet.php - Test kiểm duyệt và tóm tắt tài liệu với AI
ini_set('memory_limit', '512M');

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
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Connection timeout 10 giây
    
    // Thực hiện request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Kiểm tra lỗi
    if (curl_error($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return array(
            'success' => false,
            'error' => 'cURL Error: ' . $error,
            'http_code' => 0
        );
    }
    
    curl_close($ch);
    
    // Kiểm tra HTTP status code
    if ($httpCode !== 200) {
        return array(
            'success' => false,
            'error' => 'HTTP Error: ' . $httpCode,
            'http_code' => $httpCode,
            'response_body' => $response
        );
    }
    
    // Parse JSON response
    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return array(
            'success' => false,
            'error' => 'JSON Parse Error: ' . json_last_error_msg(),
            'raw_response' => $response
        );
    }
    
    return $result;
}

// Hàm trích xuất text từ PDF
function extractTextFromPDF($filePath) {
    // Sử dụng thư viện smalot/pdfparser
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
        return "Error extracting PDF: " . $e->getMessage();
    }
}

// Hàm trích xuất text từ file code
function extractTextFromCodeFile($filePath) {
    try {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return "Error reading file";
        }
        
        // Làm sạch và giới hạn độ dài
        $content = strip_tags($content); // Loại bỏ HTML tags nếu có
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        return $content;
    } catch (Exception $e) {
        return "Error reading code file: " . $e->getMessage();
    }
}

// Xử lý upload và test
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['document'])) {
    $uploadedFile = $_FILES['document'];
    $fileName = $uploadedFile['name'];
    $fileTmpName = $uploadedFile['tmp_name'];
    $fileSize = $uploadedFile['size'];
    $fileError = $uploadedFile['error'];
    
    // Thông tin về file upload
    $uploadInfo = array(
        'original_filename' => $fileName,
        'file_size' => $fileSize,
        'upload_error' => $fileError,
        'timestamp' => date('Y-m-d H:i:s')
    );
    
    // Kiểm tra lỗi upload
    if ($fileError !== UPLOAD_ERR_OK) {
        $result = array(
            'upload_info' => $uploadInfo,
            'extraction_result' => null,
            'ai_result' => null,
            'error' => 'Upload error code: ' . $fileError
        );
        goto output_json;
    }
    
    // Kiểm tra định dạng file
    $allowedExtensions = ['pdf', 'txt', 'php', 'js', 'html', 'css', 'py', 'java', 'cpp', 'c', 'json', 'xml'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        $result = array(
            'upload_info' => $uploadInfo,
            'extraction_result' => null,
            'ai_result' => null,
            'error' => 'File không được hỗ trợ. Chỉ chấp nhận: ' . implode(', ', $allowedExtensions)
        );
        goto output_json;
    }
    
    // Tạo thư mục uploads nếu chưa có
    $uploadDir = 'uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Di chuyển file upload đến thư mục đích
    $targetFile = $uploadDir . uniqid() . '_' . basename($fileName);
    
    if (!move_uploaded_file($fileTmpName, $targetFile)) {
        $result = array(
            'upload_info' => $uploadInfo,
            'extraction_result' => null,
            'ai_result' => null,
            'error' => 'Không thể lưu file'
        );
        goto output_json;
    }
    
    // Trích xuất text từ file
    $documentText = '';
    $extractionInfo = array(
        'file_extension' => $fileExtension,
        'extraction_method' => '',
        'text_length' => 0,
        'extraction_time' => microtime(true)
    );
    
    if ($fileExtension === 'pdf') {
        $extractionInfo['extraction_method'] = 'PDF Parser';
        $documentText = extractTextFromPDF($targetFile);
    } else {
        $extractionInfo['extraction_method'] = 'File Get Contents';
        $documentText = extractTextFromCodeFile($targetFile);
    }
    
    $extractionInfo['extraction_time'] = round(microtime(true) - $extractionInfo['extraction_time'], 3);
    $extractionInfo['text_length'] = strlen($documentText);
    
    // Giới hạn độ dài text để tránh quá tải AI (giữ nguyên để test)
    $originalLength = strlen($documentText);
    if (strlen($documentText) > 10000) {
        $documentText = substr($documentText, 0, 10000) . '... [truncated]';
        $extractionInfo['truncated'] = true;
        $extractionInfo['original_length'] = $originalLength;
        $extractionInfo['truncated_length'] = strlen($documentText);
    } else {
        $extractionInfo['truncated'] = false;
    }
    
    // Preview text (100 ký tự đầu)
    $extractionInfo['text_preview'] = substr($documentText, 0, 25000) . (strlen($documentText) > 25000 ? '...' : '');
    
    // Gọi AI API
    $aiStartTime = microtime(true);
    $aiResult = callAIAPI($documentText);
    $aiProcessTime = round(microtime(true) - $aiStartTime, 3);
    
    // Thêm thông tin thời gian xử lý
    if (is_array($aiResult)) {
        $aiResult['processing_time_seconds'] = $aiProcessTime;
    }
    
    // Kết quả cuối cùng
    $result = array(
        'upload_info' => $uploadInfo,
        'extraction_result' => $extractionInfo,
        'ai_result' => $aiResult,
        'total_processing_time' => round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3)
    );
    
    // Dọn dẹp file tạm
    if (file_exists($targetFile)) {
        unlink($targetFile);
    }
    
    output_json:
    // Output JSON result
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Kiểm Duyệt AI</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px dashed #ddd;
            border-radius: 5px;
            background: #fafafa;
        }
        input[type="file"]:hover {
            border-color: #007bff;
        }
        button {
            background: #007bff;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        button:hover {
            background: #0056b3;
        }
        .info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border-left: 4px solid #007bff;
        }
        .warning {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border-left: 4px solid #ffc107;
        }
        .code-info {
            font-family: monospace;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 Test Kiểm Duyệt AI</h1>
        
        <div class="info">
            <h3>Hướng dẫn sử dụng:</h3>
            <ul>
                <li>Chọn file PDF hoặc file code (txt, php, js, html, css, py, java, cpp, c, json, xml)</li>
                <li>Nhấn "Test AI" để upload và xử lý</li>
                <li>Kết quả sẽ hiển thị dưới dạng JSON chi tiết</li>
                <li>Đảm bảo AI service đang chạy tại http://127.0.0.1:5000</li>
            </ul>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="document">📁 Chọn file để test:</label>
                <input type="file" 
                       name="document" 
                       id="document" 
                       accept=".pdf,.txt,.php,.js,.html,.css,.py,.java,.cpp,.c,.json,.xml" 
                       required>
            </div>
            
            <button type="submit">🚀 Test AI Kiểm Duyệt & Tóm Tắt</button>
        </form>
        
        <div class="warning">
            <h4>⚠️ Lưu ý:</h4>
            <ul>
                <li>File sẽ được xóa sau khi xử lý xong</li>
                <li>Text dài hơn 10,000 ký tự sẽ bị cắt ngắn</li>
                <li>Kết quả JSON sẽ chứa đầy đủ thông tin debug</li>
            </ul>
        </div>
        
        <div class="info code-info">
            <strong>API Endpoint:</strong> http://127.0.0.1:5000/process<br>
            <strong>Supported Files:</strong> PDF, TXT, PHP, JS, HTML, CSS, PY, JAVA, CPP, C, JSON, XML<br>
            <strong>Max Text Length:</strong> 10,000 characters
        </div>
    </div>
</body>
</html>