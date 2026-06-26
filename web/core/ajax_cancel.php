<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$batch_id = $_POST['batch_id'] ?? '';
$account_id = $_POST['account_id'] ?? ''; 

if (empty($batch_id)) {
    echo json_encode(["status" => "error", "message" => "Thiếu mã đợt (batch_id)."]);
    exit;
}

// Đường dẫn chuẩn trỏ tới thư mục chứa các file kế hoạch CSV (web/plans)
$plans_dir = dirname(__DIR__) . '/plans';

try {
    $pdo->beginTransaction();

    if ($batch_id === 'ALL') {
        // 1. PANIC MODE: DỪNG TOÀN BỘ HỆ THỐNG
        exec("pkill -9 -f \"2-run-upload.js\"");
        
        // Dọn sạch toàn bộ file CSV kế hoạch để toàn bộ Bot tự sát ngay lập tức
        if (is_dir($plans_dir)) {
            $files = glob("$plans_dir/*.csv");
            foreach($files as $file) { if(is_file($file)) @unlink($file); }
        }
        
        $stmt_cancel = $pdo->prepare("
            UPDATE video_publish_history 
            SET status = 'CANCELLED', error_message = 'Người dùng chủ động dừng toàn bộ hệ thống (Panic Button)' 
            WHERE status IN ('PENDING', 'PROCESSING')
        ");
        $stmt_cancel->execute();
        $msg = "Đã dừng TOÀN BỘ Bot đang chạy ngầm trên hệ thống!";
        
    } elseif (!empty($account_id)) {
        // 2. ACCOUNT MODE: DỪNG RIÊNG 1 KÊNH TRONG ĐỢT
        $stmt_acc = $pdo->prepare("SELECT name FROM accounts WHERE account_id = ?");
        $stmt_acc->execute([$account_id]);
        $acc_name = $stmt_acc->fetchColumn();

        if ($acc_name) {
            // Hạ gục chính xác tiến trình Node chạy tài khoản này thuộc đợt này
            $cmd = "pkill -9 -f \"2-run-upload.js.*" . preg_quote($acc_name) . ".*" . preg_quote($batch_id) . "\"";
            exec($cmd);
            
            // Xóa file CSV riêng của tài khoản này để Bot kích hoạt checkCancelFromDB/checkCancel tự sát
            $safe_acc_name = preg_replace('/[^a-zA-Z0-9]/', '_', $acc_name);
            $csv_file = "$plans_dir/schedule_plan_{$safe_acc_name}_{$batch_id}.csv";
            if (file_exists($csv_file)) @unlink($csv_file);
        }

        $stmt_cancel = $pdo->prepare("
            UPDATE video_publish_history vph
            JOIN processed_videos pv ON vph.video_id = pv.id
            SET vph.status = 'CANCELLED', vph.error_message = 'Người dùng chủ động dừng riêng kênh này'
            WHERE pv.batch_id = ? AND vph.account_id = ? AND vph.status IN ('PENDING', 'PROCESSING')
        ");
        $stmt_cancel->execute([$batch_id, $account_id]);
        $msg = "Đã dừng thành công kênh: $acc_name!";
        
    } else {
        // 3. BATCH MODE: DỪNG TOÀN BỘ 1 ĐỢT ĐĂNG
        exec("pkill -9 -f \"2-run-upload.js.*" . escapeshellarg($batch_id) . "\"");
        
        // Xóa các file CSV của riêng đợt này
        if (is_dir($plans_dir)) {
            $files = glob("$plans_dir/*_{$batch_id}.csv");
            foreach($files as $file) { if(is_file($file)) @unlink($file); }
        }
        
        $stmt_cancel = $pdo->prepare("
            UPDATE video_publish_history vph
            JOIN processed_videos pv ON vph.video_id = pv.id
            SET vph.status = 'CANCELLED', vph.error_message = 'Người dùng chủ động dừng đợt đăng này'
            WHERE pv.batch_id = ? AND vph.status IN ('PENDING', 'PROCESSING')
        ");
        $stmt_cancel->execute([$batch_id]);
        $msg = "Đã dừng toàn bộ luồng Bot của đợt: $batch_id!";
    }

    $pdo->commit();
    echo json_encode(["status" => "success", "message" => $msg]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>