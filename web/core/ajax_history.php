<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $created_at = $_POST['created_at'] ?? '';

    try {
        if ($action === 'get_batch_details') {
            if (!$created_at) {
                echo json_encode(['error' => 'Dữ liệu đợt không hợp lệ.']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT product_id, processed_filename, publish_time FROM processed_videos WHERE created_at = ? AND status IN ('scheduled', 'pushed', 'used') ORDER BY publish_time ASC");
            $stmt->execute([$created_at]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }

        if ($action === 'update_batch_status') {
            $new_status = $_POST['status'] ?? '';
            if ($new_status !== 'pushed' || !$created_at) {
                echo json_encode(['error' => 'Thông tin đợt hoặc trạng thái không hợp lệ.']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE processed_videos SET status = ? WHERE created_at = ? AND status = 'scheduled'");
            $stmt->execute([$new_status, $created_at]);
            echo json_encode(['success' => true]);
            exit;
        }

        // =========================================================
        // ⚡ TRẢ VỀ DANH SÁCH FILE ĐỂ XEM TRƯỚC (ĐÃ FIX ĐƯỜNG DẪN)
        // =========================================================
        if ($action === 'preview_delete_batch') {
            if (empty($created_at)) {
                echo json_encode(['paths' => []]);
                exit;
            }
            $processed_dir = rtrim(getenv('PROCESSED_DIR') ?: '/var/www/html/push-video/processed', '/');
            $stmt = $pdo->prepare("SELECT product_id, processed_filename FROM processed_videos WHERE created_at = ?");
            $stmt->execute([$created_at]);
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $debug_paths = [];
            foreach ($videos as $vid) {
                $product_id = trim($vid['product_id']);
                // Dùng basename để lọc sạch sành sanh mọi path thừa (BATCH_xxx), chỉ lấy tên file gốc
                $clean_filename = basename($vid['processed_filename']); 
                
                if (!empty($product_id)) {
                    $absolute_file_path = $processed_dir . '/' . $product_id . '/' . $clean_filename;
                } else {
                    $absolute_file_path = $processed_dir . '/' . $clean_filename;
                }

                $status = file_exists($absolute_file_path) ? "✅ CÓ" : "❌ KHÔNG TÌM THẤY";
                $debug_paths[] = "<b>{$status}</b>: {$absolute_file_path}";
            }
            echo json_encode(['paths' => $debug_paths]);
            exit;
        }

        // =========================================================
        // ⚡ XÓA FILE THẬT (ĐÃ FIX ĐƯỜNG DẪN)
        // =========================================================
        if ($action === 'delete_batch_files') {
            if (empty($created_at)) {
                echo json_encode(['success' => false, 'error' => 'Thiếu thông tin thời gian đợt.']);
                exit;
            }

            try {
                $processed_dir = rtrim(getenv('PROCESSED_DIR') ?: '/var/www/html/push-video/processed', '/');
                $stmt = $pdo->prepare("SELECT id, product_id, processed_filename FROM processed_videos WHERE created_at = ?");
                $stmt->execute([$created_at]);
                $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $deleted_count = 0;

                foreach ($videos as $vid) {
                    $product_id = trim($vid['product_id']);
                    // Tương tự, dùng basename để chặt đứt đường dẫn rác
                    $clean_filename = basename($vid['processed_filename']);

                    if (!empty($product_id)) {
                        $absolute_file_path = $processed_dir . '/' . $product_id . '/' . $clean_filename;
                    } else {
                        $absolute_file_path = $processed_dir . '/' . $clean_filename;
                    }

                    if (file_exists($absolute_file_path)) {
                        @chmod($absolute_file_path, 0777); 
                        if (@unlink($absolute_file_path)) {
                            $deleted_count++;
                        }
                    }

                    $stmt_update = $pdo->prepare("UPDATE processed_videos SET status = 'used' WHERE id = ?");
                    $stmt_update->execute([$vid['id']]);
                }

                echo json_encode([
                    'success' => true, 
                    'message' => "Đã dọn dẹp xong! Xóa thành công $deleted_count file video vật lý."
                ]);

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['error' => 'Lỗi xử lý Server: ' . $e->getMessage()]);
    }
    exit;
}
?>