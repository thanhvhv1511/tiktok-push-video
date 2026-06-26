<?php
// File này chỉ dùng để Bot Node.js gọi vào từ macOS Host để hỏi thăm trạng thái
if (php_sapi_name() !== 'cli') {
    exit("CLI only");
}

// 1. Kết nối DB qua cổng 3307 của Mac Host (Độc lập với cấu hình web Docker)
try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=thanhvhv;charset=utf8mb4", "root", "151120", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5
    ]);
} catch (PDOException $e) {
    // Nếu lỗi kết nối, in ra chữ ERROR để Bot nhận diện chứ không in log dài dòng gây sập execSync
    echo "ERROR"; 
    exit(1);
}

// 2. Nhận tham số từ dòng lệnh (Node.js truyền sang)
$video_id = $argv[1] ?? 0;
$account_id = $argv[2] ?? 0;

if ($video_id && $account_id) {
    try {
        $stmt = $pdo->prepare("SELECT status FROM video_publish_history WHERE video_id = ? AND account_id = ?");
        $stmt->execute([$video_id, $account_id]);
        
        $status = $stmt->fetchColumn();
        
        // 3. In ra đúng trạng thái hiện tại (PENDING, PROCESSING, CANCELLED, ERROR)
        if ($status) {
            echo $status;
        } else {
            echo "NOT_FOUND";
        }
    } catch (Exception $e) {
        echo "ERROR";
    }
} else {
    echo "MISSING_PARAMS";
}
?>