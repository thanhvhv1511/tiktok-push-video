<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $product_id = $_POST['product_id'] ?? '';
    $process_count = (int)($_POST['process_count'] ?? 0);
    $log_file_path = "/var/www/html/data/process_{$product_id}.log";
    
    if (!$product_id || $process_count <= 0) {
        echo json_encode(['error' => 'Dữ liệu đầu vào không hợp lệ.']);
        exit;
    }

    try {
        // ========================================================
        // 1. CHỐNG SPAM: Kiểm tra kẹt xe trong Hàng đợi
        // ========================================================
        $stmt_check = $pdo->prepare("SELECT task_id FROM render_queue WHERE product_id = ? AND status IN ('pending', 'running')");
        $stmt_check->execute([$product_id]);
        
        if ($stmt_check->rowCount() > 0) {
             echo json_encode(['error' => 'Sản phẩm này hiện ĐANG TRONG HÀNG ĐỢI hoặc ĐANG RENDER rồi. Vui lòng chờ xong mới thao tác tiếp!']);
             exit;
        }

        // ========================================================
        // 2. NÉM VÀO HÀNG ĐỢI (QUEUE) THAY VÌ GỌI CHẠY TRỰC TIẾP
        // ========================================================
        $stmt = $pdo->prepare("INSERT INTO render_queue (product_id, process_count, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$product_id, $process_count]);
        $task_id = $pdo->lastInsertId();

        // ========================================================
        // 3. DỌN LOG ĐỂ CHUẨN BỊ CHO GIAO DIỆN HIỂN THỊ
        // ========================================================
        if (file_exists($log_file_path)) {
            unlink($log_file_path);
        }
        
        // Ghi dòng mở bài để Terminal trên Web nhảy số ngay lập tức
        file_put_contents($log_file_path, "⏳ [QUEUE] Task #$task_id (Mã SP: $product_id) đã được đưa vào hàng đợi. Đang chờ hệ thống cấp phát tài nguyên...\n");

        // Trả về JSON khớp với logic Frontend JS hiện tại
        echo json_encode([
            'success' => true,
            'task_id' => $task_id,
            'message' => "Đã xếp hàng đợi xử lý $process_count video cho mã $product_id!"
        ]);

    } catch (Exception $e) {
        echo json_encode(['error' => 'Lỗi Database: ' . $e->getMessage()]);
    }
    exit;
}