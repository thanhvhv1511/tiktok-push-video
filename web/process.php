<?php
require 'core/db.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

try {
    $pdo->exec("ALTER TABLE processed_videos ADD COLUMN batch_id VARCHAR(50) NULL AFTER id");
} catch (Exception $e) {} // Bỏ qua nếu cột đã tồn tại

$CONFIRM_PASSWORD   = getenv('CONFIRM_PASSWORD') ?: 'admin'; 
$push_video_dir          = getenv('PUSH_VIDEO_DIR') ?: '/var/www/html/push-video/videos';
// ⚡ ĐÃ ĐỒNG BỘ: Thư mục kho thành phẩm đã phân loại theo mã SP
$processed_root_dir = getenv('PROCESSED_DIR') ?: '/var/www/html/push-video/processed'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Truy cập không hợp lệ!");

$action = $_POST['action'] ?? '';
$global_sort_mode = $_POST['global_sort_mode'] ?? 'by_product';
$schedule = [];
$error_msg = '';

// ---------------------------------------------------------
// TRẠNG THÁI 1: XỬ LÝ PREVIEW & TẠO FILE CONFIG.JSON ĐA KÊNH
// ---------------------------------------------------------
if ($action === 'preview') {
    $selected_products = $_POST['selected_products'] ?? [];
    if (empty($selected_products)) die("<h2 style='color:red; text-align:center; margin-top:50px;'>❌ Bạn chưa chọn sản phẩm nào! <a href='index.php'>Quay lại</a></h2>");

    $global_start_time_str = $_POST['global_start_time'];
    $global_interval = (int)$_POST['global_interval'];
    $start_time = new DateTime($global_start_time_str);
    
    // Bốc cả cặp dữ liệu name và cdp_port không trùng lặp từ Database
    $stmt_accounts = $pdo->query("SELECT name, cdp_port FROM accounts ORDER BY account_id ASC");
    $accounts_db = $stmt_accounts->fetchAll(PDO::FETCH_ASSOC);
    
    $account_configs = [];
    if (!empty($accounts_db)) {
        foreach ($accounts_db as $acc) {
            $account_configs[] = [
                "name" => $acc['name'],
                "port" => (int)$acc['cdp_port']
            ];
        }
    } else {
        $account_configs = [
            ["name" => "Account_01", "port" => 9222],
            ["name" => "Account_02", "port" => 9223]
        ];
    }

    // ==================== ⚡ XUẤT CONFIG.JSON ĐẾN THƯ MỤC CHỨA CHUẨN ====================
    $base_dir = dirname(__FILE__); 
    
    $config_data = [
        "IS_RANDOM"        => ($global_sort_mode === 'random'),
        "START_TIME_STR"   => $start_time->format('Y-m-d H:i'),
        "INTERVAL_MINUTES" => $global_interval,
        "DATABASE_CSV"     => $base_dir . "/database.csv",
        "OUTPUT_PLAN_DIR"  => $base_dir . "/plans",
        "PUSH_VIDEO_DIR"   => $push_video_dir, 
        "ACCOUNTS"         => $account_configs 
    ];

    $json_string = json_encode($config_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    @file_put_contents($base_dir . '/config.json', $json_string);
    // ===================================================================================

    $global_start_time_str = $_POST['global_start_time'];
    $global_interval = (int)$_POST['global_interval'];
    $all_videos_to_post = [];

    foreach ($selected_products as $pid) {
        $mode = $_POST["mode_$pid"] ?? 'random';
        $selected_files = [];

        // ⚡ ĐÃ UPDATE: Quét file chuẩn ngay từ thư mục kho chứa phân loại sp ($processed_root_dir/$pid)
        $product_ready_dir = "$processed_root_dir/$pid";

        if ($mode === 'select') {
            $selected_files = $_POST["files_$pid"] ?? [];
        } else {
            $count = (int)($_POST["random_count_$pid"] ?? 0);
            if ($count > 0 && is_dir($product_ready_dir)) {
                $all_files = [];
                foreach (['mp4', 'mov', 'mkv', 'avi'] as $ext) {
                    $files = glob("$product_ready_dir/*.$ext");
                    if ($files !== false) $all_files = array_merge($all_files, $files);
                }
                
                if (!empty($all_files)) {
                    shuffle($all_files);
                    $selected_paths = array_slice($all_files, 0, $count);
                    // Giữ lại tên file thuần túy
                    $selected_files = array_map('basename', $selected_paths);
                }
            }
        }
        
        // Đóng gói mảng tạm kèm theo mã sản phẩm phục vụ cho khâu copy vật lý lúc sau
        foreach ($selected_files as $file) {
            $all_videos_to_post[] = [
                'product_id' => $pid, 
                'file_name'  => $file
            ];
        }
    }

    if ($global_sort_mode === 'random') shuffle($all_videos_to_post);
    else usort($all_videos_to_post, function($a, $b) { return strcmp($a['product_id'], $b['product_id']); });

    foreach ($all_videos_to_post as $video) {
        $schedule[] = [
            'product_id'   => $video['product_id'],
            'file_name'    => $video['file_name'],
            'publish_time' => $start_time->format('Y-m-d\TH:i') 
        ];
        $start_time->add(new DateInterval("PT{$global_interval}M"));
    }
}

// ---------------------------------------------------------
// TRẠNG THÁI 2: CHỐT LỊCH THEO ĐỢT (BATCH) & COPPY FILE
// ---------------------------------------------------------
if ($action === 'confirm') {
    $pass_input = $_POST['confirm_password'] ?? '';
    $post_pids = $_POST['edit_pid'] ?? [];
    $post_files = $_POST['edit_file'] ?? [];
    $post_times = $_POST['edit_time'] ?? [];

    for ($i = 0; $i < count($post_pids); $i++) {
        $schedule[] = [
            'product_id'   => $post_pids[$i], 
            'file_name'    => $post_files[$i], 
            'publish_time' => $post_times[$i]
        ];
    }

    if ($pass_input !== $CONFIRM_PASSWORD) {
        $error_msg = "❌ Mật khẩu xác nhận không đúng!";
    } else {
        usort($schedule, function($a, $b) { return strtotime($a['publish_time']) - strtotime($b['publish_time']); });

        // ⚡ 1. TẠO MÃ ĐỢT ĐỘC NHẤT & TẠO THƯ MỤC VẬT LÝ TRONG PUSH_VIDEO_DIR
        $batch_id = 'BATCH_' . date('Ymd_His');
        $batch_target_dir = "$push_video_dir/$batch_id";
        
        if (!is_dir($batch_target_dir)) {
            mkdir($batch_target_dir, 0777, true);
        }

        $pdo->beginTransaction();
        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO processed_videos (batch_id, product_id, original_filename, processed_filename, publish_time, status) 
                VALUES (?, ?, ?, ?, ?, 'scheduled')
            ");
            
            foreach ($schedule as $item) {
                $pub_time = date('Y-m-d H:i:s', strtotime($item['publish_time']));
                
                // ⚡ 2. VẬN HÀNH COPY: Copy file từ kho chứa sang thư mục BATCH mục tiêu
                $src_file = "$processed_root_dir/{$item['product_id']}/{$item['file_name']}";
                $dst_file = "$batch_target_dir/{$item['file_name']}";
                
                if (!file_exists($src_file)) {
                    throw new Exception("KHÔNG TÌM THẤY FILE NGUỒN: " . $src_file);
                }
                
                if (!copy($src_file, $dst_file)) {
                    throw new Exception("LỖI QUYỀN GHI: Không thể copy vào thư mục " . $batch_target_dir);
                }
                
                // Lưu thông tin đường dẫn tương đối (bao gồm cả thư mục Batch) vào Database
                $db_relative_path = "$batch_id/{$item['file_name']}";
                $stmtInsert->execute([$batch_id, $item['product_id'], $item['file_name'], $db_relative_path, $pub_time]);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            die("❌ Lỗi Ghi Hệ Thống / Copy File: " . $e->getMessage());
        }

        header("Location: publish.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview & Xác nhận Lịch</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">👀 XEM TRƯỚC VÀ CHỐT LỊCH ĐĂNG</h1>
            <a href="index.php" class="text-gray-500 hover:text-gray-800 underline">⬅ Hủy & Quay lại</a>
        </div>
        
        <?php if ($error_msg): ?>
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6 font-bold border border-red-300"><?= $error_msg ?></div>
        <?php endif; ?>

        <form action="process.php" method="POST">
            <input type="hidden" name="action" value="confirm">
            <input type="hidden" name="global_sort_mode" value="<?= htmlspecialchars($global_sort_mode) ?>">

            <div class="overflow-x-auto max-h-[600px] overflow-y-auto mb-6 border border-gray-200">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-800 text-white sticky top-0 z-10">
                        <tr>
                            <th class="py-3 px-4 text-center w-16">STT</th>
                            <th class="py-3 px-4 text-left">Chỉnh sửa Thời Gian</th>
                            <th class="py-3 px-4 text-left">Mã SP</th>
                            <th class="py-3 px-4 text-left">Đường Dẫn File Video</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($schedule)): ?>
                        <tr><td colspan="4" class="py-4 text-center text-red-500">Không có video nào!</td></tr>
                        <?php else: ?>
                            <?php foreach ($schedule as $index => $item): ?>
                            <tr class="hover:bg-gray-50 border-b">
                                <td class="py-3 px-4 text-center font-bold text-gray-500"><?= $index + 1 ?></td>
                                <td class="py-3 px-4">
                                    <input type="datetime-local" name="edit_time[]" value="<?= $item['publish_time'] ?>" class="border border-gray-300 p-2 rounded text-blue-600 font-bold focus:ring-2 focus:ring-blue-400">
                                </td>
                                <td class="py-3 px-4 text-gray-700 font-bold w-32">
                                    <?= $item['product_id'] ?>
                                    <input type="hidden" name="edit_pid[]" value="<?= $item['product_id'] ?>">
                                </td>
                                <td class="py-3 px-4 text-green-700 font-mono text-sm break-all">
                                    <?= $item['file_name'] ?>
                                    <input type="hidden" name="edit_file[]" value="<?= $item['file_name'] ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 p-6 rounded-lg flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-yellow-800 mb-2">🔒 Chốt Đợt Lên Lịch</h3>
                    <p class="text-sm text-yellow-700 mb-3">Lịch sẽ được gom thành 1 đợt, chuyển sang Trạm Đăng để bạn duyệt và bắn.</p>
                    <input type="password" name="confirm_password" placeholder="Nhập mật khẩu..." required class="border border-gray-300 p-2 rounded w-64 focus:ring-2 focus:ring-yellow-400 outline-none">
                </div>
                
                <button type="submit" class="bg-blue-600 text-white font-bold py-3 px-8 rounded hover:bg-blue-700 transition shadow-lg h-fit">
                    💾 GOM ĐỢT VÀ ĐI TỚI TRẠM ĐĂNG
                </button>
            </div>
        </form>
    </div>
</body>
</html>