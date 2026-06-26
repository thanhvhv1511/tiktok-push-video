<?php
if (php_sapi_name() !== 'cli') {
    exit("CLI only");
}

// Kết nối DB qua cổng 3307 của Mac Host
try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=thanhvhv;charset=utf8mb4", "root", "151120", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5
    ]);
} catch (PDOException $e) {
    echo "❌ [CLI DB CONNECTION ERROR]: " . $e->getMessage() . "\n";
    exit(1);
}

$video_id   = $argv[1] ?? null;
$account_id = $argv[2] ?? null;
$status     = $argv[3] ?? null;
$error_msg  = $argv[4] ?? null;

if (!$video_id || !$account_id || !$status) {
    echo "❌ [CLI Error] Thiếu tham số bắt buộc!\n";
    exit(1);
}

try {
    $pdo->beginTransaction();

    // ==============================================================
    // BƯỚC 1: Cập nhật bảng lịch sử chi tiết (Đã đổi thành pushed_at)
    // ==============================================================
    $stmt_hist = $pdo->prepare("
        INSERT INTO video_publish_history (video_id, account_id, status, error_message, pushed_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            status = VALUES(status), 
            error_message = VALUES(error_message),
            pushed_at = NOW()
    ");
    $stmt_hist->execute([$video_id, $account_id, $status, empty($error_msg) ? null : $error_msg]);

    // ==============================================================
    // BƯỚC 2: Cập nhật bảng tổng hợp nếu SUCCESS
    // ==============================================================
    if ($status === 'SUCCESS') {
        $stmt_vid = $pdo->prepare("SELECT product_id FROM processed_videos WHERE id = ?");
        $stmt_vid->execute([$video_id]);
        $video_info = $stmt_vid->fetch(PDO::FETCH_ASSOC);

        if ($video_info) {
            $product_id = $video_info['product_id'];
            
            // Check xem bảng video_publish_logs của ông có cột publish_date và videos_posted không nhé
            $stmt_log = $pdo->prepare("
                INSERT INTO video_publish_logs (product_id, account_id, publish_date, videos_posted)
                VALUES (?, ?, CURDATE(), 1)
                ON DUPLICATE KEY UPDATE videos_posted = videos_posted + 1
            ");
            $stmt_log->execute([$product_id, $account_id]);
        }
    }

    $pdo->commit();
    echo "⚙️ [CLI DB SUCCESS] Video ID [{$video_id}] -> Kênh [{$account_id}] -> Trạng thái: [{$status}]\n";
    exit(0);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ [CLI DB ERROR] Thất bại: " . $e->getMessage() . "\n";
    exit(1);
}