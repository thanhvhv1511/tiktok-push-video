<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$batch_id = $_POST['batch_id'] ?? '';
// Hứng chuỗi ID tài khoản (Ví dụ: "2,3")
$account_ids_raw = $_POST['account_ids'] ?? $_POST['account_id'] ?? '';

if (empty($batch_id) || empty($account_ids_raw)) {
    echo json_encode(["status" => "error", "message" => "Thiếu thông tin đợt hoặc tài khoản."]);
    exit;
}

// 1. Tách chuỗi thành mảng các ID tài khoản sạch
$account_ids = array_filter(array_map('intval', explode(',', $account_ids_raw)));

if (empty($account_ids)) {
    echo json_encode(["status" => "error", "message" => "Danh sách ID tài khoản không hợp lệ."]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 2. Lấy thông tin chi tiết video thuộc đợt (chỉ lấy 1 lần duy nhất)
    if ($batch_id === 'DOT_LE_TANG') {
        $query = "SELECT pv.id, pv.product_id, pv.processed_filename, pv.publish_time, p.name as product_name, p.caption 
                  FROM processed_videos pv 
                  JOIN products p ON pv.product_id = p.product_id 
                  WHERE pv.batch_id IS NULL ORDER BY pv.publish_time ASC";
        $stmt_get = $pdo->prepare($query);
        $stmt_get->execute();
    } else {
        $query = "SELECT pv.id, pv.product_id, pv.processed_filename, pv.publish_time, p.name as product_name, p.caption 
                  FROM processed_videos pv 
                  JOIN products p ON pv.product_id = p.product_id 
                  WHERE pv.batch_id = ? ORDER BY pv.publish_time ASC";
        $stmt_get = $pdo->prepare($query);
        $stmt_get->execute([$batch_id]);
    }
    
    $videos = $stmt_get->fetchAll(PDO::FETCH_ASSOC);

    if (empty($videos)) {
        throw new Exception("Không tìm thấy video nào cho đợt này!");
    }

    // Chuẩn bị sẵn câu lệnh SQL
    $stmt_acc = $pdo->prepare("SELECT name, cdp_port FROM accounts WHERE account_id = ?");
    
    // Ghi nhận trạng thái lên lịch trình đăng (Lịch sử chi tiết của Video)
    $stmt_hist = $pdo->prepare("
        INSERT INTO video_publish_history (video_id, account_id, status, error_message) 
        VALUES (?, ?, 'PENDING', NULL) 
        ON DUPLICATE KEY UPDATE status = 'PENDING', error_message = NULL
    ");
    
    // Đã xóa $stmt_log: Không đếm trước khi Bot chạy thực tế. Đếm ở callback.

    $mac_base_path = getenv('MAC_BASE_PATH') ?: '/Users/thanhvhv/personal/tool/site';
    $base_dir = dirname(__DIR__); 
    $plans_dir = $base_dir . '/plans';

    if (!is_dir($plans_dir)) {
        mkdir($plans_dir, 0777, true);
    }

    // Mảng lưu thông tin các bot cần kích hoạt sau khi commit DB thành công
    $activated_bots = [];

    // ==============================================================
    // 3. VÒNG LẶP LỚN: Duyệt qua từng tài khoản hợp lệ
    // ==============================================================
    foreach ($account_ids as $account_id) {
        
        $stmt_acc->execute([$account_id]);
        $account = $stmt_acc->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            continue; // Bỏ qua nếu không tìm thấy tài khoản trong DB
        }

        $acc_name = $account['name'];
        $cdp_port = $account['cdp_port'];
        $safe_acc_name = preg_replace('/[^a-zA-Z0-9]/', '_', $acc_name);

        // ⚡ VÒNG LẶP NHỎ 1: Ghi log database ứng với từng ID số đơn lẻ
        foreach ($videos as $vid) {
            $stmt_hist->execute([$vid['id'], $account_id]);
            // Logic đếm +1 đã được dời đi.
        }

        // ⚡ VÒNG LẶP NHỎ 2: Sinh file CSV kế hoạch riêng cho từng kênh
        $csv_filename = $plans_dir . "/schedule_plan_{$safe_acc_name}_{$batch_id}.csv";
        $fp = fopen($csv_filename, 'w');
        fputs($fp, "\xEF\xBB\xBF"); // BOM UTF-8
        
        fputcsv($fp, ['Video_Path', 'Video_Name', 'Product_Name', 'Caption', 'Scheduled_Time', 'Status', 'Video_ID']);

        foreach ($videos as $vid) {
            $absolute_video_path = rtrim($mac_base_path, '/') . '/push-video/videos/' . ltrim($vid['processed_filename'], '/');
            $video_name = basename($vid['processed_filename']);
            $time_formatted = $vid['publish_time'] ? date('Y/m/d H:i', strtotime($vid['publish_time'])) : date('Y/m/d H:i');

            fputcsv($fp, [
                $absolute_video_path,
                $video_name,
                $vid['product_name'],
                $vid['caption'],
                $time_formatted,
                'PENDING',
                $vid['id']
            ]);
        }
        fclose($fp);

        // Đút vào hàng đợi kích hoạt bot
        $activated_bots[] = [
            'acc_name' => $acc_name,
            'cdp_port' => $cdp_port,
            'account_id' => $account_id
        ];
    }

    // Commit toàn bộ dữ liệu sạch vào Database
    $pdo->commit();

    // ==============================================================
    // 4. KÍCH HOẠT DÀN BOT ĐA KÊNH CHẠY SONG SONG
    // ==============================================================
    $backend_api_url = getenv('BACKEND_API_URL');

    foreach ($activated_bots as $bot) {
        if (!empty($backend_api_url)) {
            // CÁCH 1: Gọi FastAPI qua Docker
            $target_url = preg_replace('#/run$#', '/run-bot', trim($backend_api_url));
            $payload = json_encode([
                'account'    => $bot['acc_name'],
                'batch_id'   => $batch_id,
                'port'       => (string)$bot['cdp_port'],
                'account_id' => (string)$bot['account_id']
            ]);

            $ch = curl_init($target_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2); 
            curl_exec($ch);
            curl_close($ch);
            
        } else {
            // CÁCH 2: Chạy trực tiếp qua lệnh exec của Mac
            $custom_bin_path = getenv('CUSTOM_BIN_PATH') ?: '/usr/local/bin:/opt/homebrew/bin';
            $node_path = getenv('NODE_PATH') ?: 'node';
            
            $node_script_path = escapeshellarg(rtrim($mac_base_path, '/') . '/push-video/2-run-upload.js');
            $cmd_acc_name     = escapeshellarg($bot['acc_name']);
            $cmd_batch_id     = escapeshellarg($batch_id); 
            $cmd_port         = escapeshellarg($bot['cdp_port']);
            $cmd_account_id   = escapeshellarg($bot['account_id']); 
            
            $cmd = "export PATH=\$PATH:{$custom_bin_path} && {$node_path} {$node_script_path} {$cmd_acc_name} {$cmd_batch_id} {$cmd_port} {$cmd_account_id} > /dev/null 2>&1 &";
            exec($cmd);
        }
    }

    echo json_encode(["status" => "success", "message" => "Đã tạo cấu trúc Multi-Plan và kích nổ dàn Bot đa kênh!"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>