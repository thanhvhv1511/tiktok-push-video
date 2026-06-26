<?php
header('Content-Type: application/json');

// Gọi file kết nối Database để kiểm tra Hàng đợi
require_once __DIR__ . '/core/db.php'; // Đảm bảo đường dẫn này trỏ đúng file db.php của anh

$product_id = $_POST['product_id'] ?? '';

if (empty($product_id)) {
    echo json_encode(["status" => "error", "message" => "Thiếu mã sản phẩm, không thể hủy!"]);
    exit;
}

try {
    // 1. Tìm xem tiến trình của mã SP này đang nằm ở đâu trong Hàng đợi
    $stmt = $pdo->prepare("SELECT task_id, status FROM render_queue WHERE product_id = ? AND status IN ('pending', 'running')");
    $stmt->execute([$product_id]);
    $task = $stmt->fetch();

    if (!$task) {
        echo json_encode(["status" => "error", "message" => "Không tìm thấy tiến trình nào đang chạy hoặc chờ cho mã SP này."]);
        exit;
    }

    $task_id = $task['task_id'];

    // 2. KỊCH BẢN A: Task ĐANG NẰM CHỜ (Pending) -> Chỉ cần gạch tên khỏi DB
    if ($task['status'] === 'pending') {
        $upd_stmt = $pdo->prepare("UPDATE render_queue SET status = 'error', completed_at = NOW() WHERE task_id = ?");
        $upd_stmt->execute([$task_id]);
        
        echo json_encode(["status" => "success", "message" => "Đã rút lệnh sản xuất khỏi hàng đợi thành công!"]);
        exit;
    }

    // 3. KỊCH BẢN B: Task ĐANG CHẠY VẬT LÝ (Running) -> Đánh dấu DB & Cắm cờ vật lý
    if ($task['status'] === 'running') {
        // Cập nhật DB
        $upd_stmt = $pdo->prepare("UPDATE render_queue SET status = 'error', completed_at = NOW() WHERE task_id = ?");
        $upd_stmt->execute([$task_id]);

        // Cắm cờ vật lý xuống đĩa cứng cho Watchdog của Node.js tự sát
        // Tùy theo vị trí file stop_process.php, anh trỏ cho đúng vào thư mục data
        $flag_file = __DIR__ . '/data/stop_render.flag'; 
        file_put_contents($flag_file, "STOP");

        echo json_encode(["status" => "success", "message" => "Đã phát lệnh tiêu diệt tiến trình đang chạy!"]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Lỗi Database: " . $e->getMessage()]);
}
?>