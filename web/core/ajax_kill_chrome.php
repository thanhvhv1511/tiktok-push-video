<?php
header('Content-Type: application/json');

// Lấy biến môi trường URL của FastAPI (Cái mà bạn đã khai báo trong file .env của Docker)
$backend_api_url = getenv('BACKEND_API_URL'); 

if (empty($backend_api_url)) {
    echo json_encode(["status" => "error", "message" => "Lỗi: Không tìm thấy biến môi trường BACKEND_API_URL."]);
    exit;
}

// Chuyển đuôi endpoint từ /run (hoặc gì đó) thành /kill-chrome
$target_url = preg_replace('#/[^/]*$#', '/kill-chrome', trim($backend_api_url));

try {
    // Bắn request API sang FastAPI ngoài Mac Host
    $ch = curl_init($target_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Timeout 3 giây
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        // Trả về cho JS trên UI biết là đã thành công
        echo json_encode(["status" => "success", "message" => "Đã dọn dẹp Chrome ngầm qua cầu nối FastAPI!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "FastAPI từ chối kết nối hoặc không phản hồi."]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Lỗi kết nối cURL: " . $e->getMessage()]);
}
?>