<?php
// 1. Cấu hình thông tin Bot của anh ở đây
$apiToken = "7810006090:AAFySZVifjegUcs6TXVXRi5yRDrm1YkorSU";
$chatId = "5730908881";

// 2. Nội dung muốn test
$message = "🚀 [TEST XƯỞNG RENDER] Xưởng đã xong việc, video đã nằm trong kho!";

// 3. Hàm gửi tin nhắn
function sendMessage($chatId, $message, $apiToken) {
    $url = "https://api.telegram.org/bot$apiToken/sendMessage?chat_id=$chatId&text=" . urlencode($message);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    return $error ? "Lỗi: " . $error : $result;
}

// 4. Chạy thử
echo "Đang gửi tin nhắn test...\n";
$response = sendMessage($chatId, $message, $apiToken);
echo "Kết quả trả về từ Telegram:\n" . $response;
?>