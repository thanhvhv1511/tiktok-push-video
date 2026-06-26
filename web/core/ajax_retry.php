<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$batch_id = $_POST['batch_id'] ?? '';
$account_ids_raw = $_POST['account_ids'] ?? '';

if (empty($batch_id) || empty($account_ids_raw)) {
    echo json_encode(["status" => "error", "message" => "Thiếu thông tin đợt hoặc tài khoản."]);
    exit;
}

$account_ids = array_filter(array_map('intval', explode(',', $account_ids_raw)));

if (empty($account_ids)) {
    echo json_encode(["status" => "error", "message" => "Danh sách ID tài khoản không hợp lệ."]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. UPDATE NHỮNG VIDEO LỖI, CHƯA CHẠY, BỊ HỦY HOẶC "BỊ KẸT CHẠY DỞ" (PROCESSING) VỀ LẠI PENDING
    // ⚡ Đã bổ sung 'PROCESSING' vào đây để giải cứu các video bị kẹt bóng ma
    $stmt_reset = $pdo->prepare("
        UPDATE video_publish_history vph
        JOIN processed_videos pv ON vph.video_id = pv.id
        SET vph.status = 'PENDING', vph.error_message = NULL
        WHERE pv.batch_id = ? AND vph.account_id = ? 
        AND vph.status IN ('ERROR', 'CANCELLED', 'PENDING', 'PROCESSING')
    ");

    $stmt_acc = $pdo->prepare("SELECT name, cdp_port FROM accounts WHERE account_id = ?");
    
    // Lấy thông tin video để sinh lại file CSV
    $stmt_get_vids = $pdo->prepare("
        SELECT pv.id, pv.product_id, pv.processed_filename, pv.publish_time, p.name as product_name, p.caption, vph.status
        FROM processed_videos pv 
        JOIN products p ON pv.product_id = p.product_id 
        JOIN video_publish_history vph ON vph.video_id = pv.id
        WHERE pv.batch_id = ? AND vph.account_id = ? AND vph.status = 'PENDING'
        ORDER BY pv.publish_time ASC
    ");

    $mac_base_path = getenv('MAC_BASE_PATH') ?: '/Users/thanhvhv/personal/tool/site';
    $base_dir = dirname(__DIR__); 
    $plans_dir = $base_dir . '/plans';
    if (!is_dir($plans_dir)) mkdir($plans_dir, 0777, true);

    $activated_bots = [];

    // ==============================================================
    // 2. VÒNG LẶP LỚN: Xử lý trạng thái và sinh file CSV cho Từng Kênh
    // ==============================================================
    foreach ($account_ids as $account_id) {
        // Reset trạng thái database
        $stmt_reset->execute([$batch_id, $account_id]);

        $stmt_acc->execute([$account_id]);
        $account = $stmt_acc->fetch(PDO::FETCH_ASSOC);
        if (!$account) continue;

        $acc_name = $account['name'];
        $cdp_port = $account['cdp_port'];
        $safe_acc_name = preg_replace('/[^a-zA-Z0-9]/', '_', $acc_name);

        // Lấy danh sách những video CẦN ĐĂNG LẠI (PENDING)
        $stmt_get_vids->execute([$batch_id, $account_id]);
        $pending_videos = $stmt_get_vids->fetchAll(PDO::FETCH_ASSOC);

        if (count($pending_videos) > 0) {
            // Ghi đè file CSV mới tinh, CHỈ CHỨA NHỮNG VIDEO PENDING
            $csv_filename = $plans_dir . "/schedule_plan_{$safe_acc_name}_{$batch_id}.csv";
            $fp = fopen($csv_filename, 'w');
            fputs($fp, "\xEF\xBB\xBF"); 
            fputcsv($fp, ['Video_Path', 'Video_Name', 'Product_Name', 'Caption', 'Scheduled_Time', 'Status', 'Video_ID']);

            foreach ($pending_videos as $vid) {
                $absolute_video_path = rtrim($mac_base_path, '/') . '/push-video/videos/' . ltrim($vid['processed_filename'], '/');
                $time_formatted = $vid['publish_time'] ? date('Y/m/d H:i', strtotime($vid['publish_time'])) : date('Y/m/d H:i');

                fputcsv($fp, [
                    $absolute_video_path, 
                    basename($vid['processed_filename']), 
                    $vid['product_name'], 
                    $vid['caption'], 
                    $time_formatted, 
                    'PENDING', 
                    $vid['id']
                ]);
            }
            fclose($fp);

            $activated_bots[] = [
                'acc_name' => $acc_name, 
                'cdp_port' => $cdp_port, 
                'account_id' => $account_id
            ];
        }
    }

    $pdo->commit();

    if (empty($activated_bots)) {
        echo json_encode(["status" => "error", "message" => "Tất cả video đã được đăng thành công, không có video lỗi hoặc bị kẹt nào để chạy lại!"]);
        exit;
    }

    // ==============================================================
    // 3. KÍCH NỔ LẠI DÀN BOT ĐA KÊNH
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
            
            $cmd = "export PATH=\$PATH:{$custom_bin_path} && {$node_path} {$node_script_path} " . 
                   escapeshellarg($bot['acc_name']) . " " . 
                   escapeshellarg($batch_id) . " " . 
                   escapeshellarg($bot['cdp_port']) . " " . 
                   escapeshellarg($bot['account_id']) . " > /dev/null 2>&1 &";
            exec($cmd);
        }
    }

    echo json_encode(["status" => "success", "message" => "Đã gửi lệnh chạy lại các video (bao gồm cả các video đang kẹt)!"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>