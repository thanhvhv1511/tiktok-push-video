<?php
require 'core/db.php';

// 1. Lấy danh sách tài khoản
$stmt_acc = $pdo->query("SELECT account_id, name FROM accounts ORDER BY account_id ASC");
$accounts = $stmt_acc->fetchAll();

// 2. Lấy thông tin lịch sử kèm trạng thái chi tiết của từng kênh
$sql = "SELECT pv.id as video_id, pv.batch_id, pv.product_id, pv.processed_filename, pv.publish_time, p.name as product_name,
        (SELECT GROUP_CONCAT(CONCAT(vph.account_id, ':', vph.status) SEPARATOR ',') 
         FROM video_publish_history vph 
         WHERE vph.video_id = pv.id) as history_raw
        FROM processed_videos pv
        JOIN products p ON pv.product_id = p.product_id
        WHERE pv.status = 'scheduled'
        ORDER BY pv.created_at DESC, pv.publish_time ASC";

$stmt_vid = $pdo->query($sql);
$all_videos = $stmt_vid->fetchAll();

// 3. Gom nhóm dữ liệu theo Batch và bóc tách trạng thái chi tiết
$batches = [];
$batch_acc_statuses = []; 

foreach ($all_videos as $vid) {
    $b_id = $vid['batch_id'] ?: 'DOT_LE_TANG'; 
    if (!isset($batches[$b_id])) {
        $batches[$b_id] = [];
        $batch_acc_statuses[$b_id] = [];
    }
    
    $vid['statuses'] = [];
    if (!empty($vid['history_raw'])) {
        $pairs = explode(',', $vid['history_raw']);
        foreach ($pairs as $pair) {
            $parts = explode(':', $pair);
            if (count($parts) === 2) {
                $acc_id = (int)$parts[0];
                $status = $parts[1];
                $vid['statuses'][$acc_id] = $status;
                $batch_acc_statuses[$b_id][$acc_id][] = $status;
            }
        }
    }
    $batches[$b_id][] = $vid;
}

$page_title = "Trạm Đăng Video";
$active_tab = "publish"; 
require 'includes/header.php';
?>

<link rel="stylesheet" href="assets/style.css">

<div class="d-flex align-items-center mb-4">
    <div class="bg-white p-3 rounded-4 me-3 shadow-sm text-success border" style="border-color: #e2e8f0 !important;">
        <i class="bi bi-rocket-takeoff-fill fs-4"></i>
    </div>
    <div>
        <h2 class="fw-bold mb-1" style="color: #0f172a;">Trạm Đăng Video (Multi-Push)</h2>
        <p class="text-muted mb-0 font-monospace small">Đăng video đa kênh. Hãy tích chọn những kênh muốn đẩy hàng loạt rồi nhấn Push All.</p>
    </div>
</div>

<?php if (empty($batches)): ?>
    <div class="dashboard-card text-center py-5 border shadow-sm bg-white rounded">
        <i class="bi bi-inbox fs-1 d-block mb-3 text-secondary"></i> 
        <h5 class="text-muted font-monospace">Hiện không có video nào nằm chờ đăng!</h5>
    </div>
<?php else: ?>

    <?php foreach ($batches as $batch_id => $videos): ?>
    <div class="dashboard-card mb-4 border-primary shadow-sm" id="batch_card_<?= $batch_id ?>" style="padding: 0; overflow: hidden; border-radius: 0.75rem;">
        <div class="p-3 bg-white border-bottom">
            <div class="row g-3">
                
                <div class="col-lg-6 d-flex flex-column gap-2">
                    <div class="d-flex align-items-center mb-1">
                        <h5 class="fw-bold text-primary mb-0 me-3">
                            <i class="bi bi-collection-play-fill me-2"></i>Mã Đợt: <?= $batch_id ?>
                        </h5>
                        <span class="badge bg-secondary rounded-pill px-3 py-1 fs-6 shadow-sm"><?= count($videos) ?> Video</span>
                    </div>
                    
                    <div class="p-3 rounded bg-light border border-dashed flex-grow-1">
                        <div class="small fw-bold text-muted mb-2"><i class="bi bi-list-check me-1"></i>Tình trạng đẩy kênh đợt này:</div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($accounts as $acc): ?>
                                <?php 
                                    $acc_id = (int)$acc['account_id'];
                                    $acc_name = trim($acc['name']);
                                    
                                    $badge_class = 'border-secondary text-secondary bg-white';
                                    $icon_class = 'bi-circle text-muted';
                                    $text_suffix = '';

                                    if (isset($batch_acc_statuses[$batch_id][$acc_id])) {
                                        $list_statuses = $batch_acc_statuses[$batch_id][$acc_id];
                                        
                                        if (in_array('ERROR', $list_statuses)) {
                                            $badge_class = 'border-danger text-danger bg-danger bg-opacity-10 fw-bold';
                                            $icon_class = 'bi-exclamation-circle-fill';
                                            $text_suffix = ' (Có lỗi)';
                                        } elseif (in_array('PROCESSING', $list_statuses)) {
                                            $badge_class = 'border-warning text-warning bg-warning bg-opacity-10 fw-bold animate-pulse';
                                            $icon_class = 'bi-gear-fill rotate-icon';
                                            $text_suffix = ' (Đang chạy)';
                                        } elseif (in_array('SUCCESS', $list_statuses)) {
                                            $success_count = count(array_filter($list_statuses, function($s) { return $s === 'SUCCESS'; }));
                                            $total_count = count($videos);
                                            
                                            if ($success_count === $total_count) {
                                                $badge_class = 'border-success text-success bg-success bg-opacity-10 fw-bold';
                                                $icon_class = 'bi-check-circle-fill';
                                                $text_suffix = ' (Xong)';
                                            } else {
                                                $badge_class = 'border-primary text-primary bg-primary bg-opacity-10 fw-bold';
                                                $icon_class = 'bi-play-circle-fill';
                                                $text_suffix = " ({$success_count}/{$total_count})";
                                            }
                                        }
                                    }
                                ?>
                                <span class="badge border <?= $badge_class ?> p-2 d-flex align-items-center shadow-sm" style="font-size: 0.8rem; font-weight: 500;">
                                    <i class="bi <?= $icon_class ?> me-1"></i> 
                                    <?= htmlspecialchars($acc_name) . $text_suffix ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 d-flex flex-column gap-2">
                    <div class="p-3 border rounded shadow-sm bg-white flex-grow-1" style="max-height: 120px; overflow-y: auto;">
                        <div class="small fw-bold text-muted mb-2 border-bottom pb-1"><i class="bi bi-check2-all me-1"></i>Chọn các kênh muốn đẩy:</div>
                        <div class="row g-2">
                            <?php foreach ($accounts as $acc): ?>
                                <div class="col-6">
                                    <div class="form-check custom-checkbox">
                                        <input class="form-check-input batch-acc-checkbox-<?= $batch_id ?>" type="checkbox" value="<?= $acc['account_id'] ?>" id="chk_<?= $batch_id ?>_<?= $acc['account_id'] ?>">
                                        <label class="form-check-label small text-dark fw-semibold text-truncate d-block cursor-pointer" for="chk_<?= $batch_id ?>_<?= $acc['account_id'] ?>" title="<?= htmlspecialchars($acc['name']) ?>">
                                            <?= htmlspecialchars($acc['name']) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2 w-100">
                        <button class="btn btn-primary fw-bold px-3 shadow-sm flex-grow-1 d-flex align-items-center justify-content-center transition-all" id="btn_<?= $batch_id ?>" onclick="publishBatch('<?= $batch_id ?>')" style="height: 44px; font-size: 0.95rem;">
                            <i class="bi bi-send-fill me-2"></i> Push All Lên Kênh Đã Chọn
                        </button>
                        <button class="btn btn-warning fw-bold px-3 shadow-sm flex-grow-1 d-flex align-items-center justify-content-center transition-all" id="btn_retry_<?= $batch_id ?>" onclick="retryBatch('<?= $batch_id ?>')" style="height: 44px; font-size: 0.95rem; color: #854d0e; background-color: #fef08a; border-color: #fde047;">
                            <i class="bi bi-arrow-clockwise me-2 fw-bold"></i> Đăng Lại (Chỉ Lỗi)
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <div class="p-3 bg-light" style="max-height: 480px; overflow-y: auto;">
            <div class="row g-2">
                <?php foreach ($videos as $index => $vid): ?>
                <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 0.5rem;">
                        <div class="card-body p-2 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary font-monospace border border-primary border-opacity-25" style="font-size: 0.75rem;">
                                    <?= $vid['product_id'] ?>
                                </span>
                                <span class="text-muted fw-bold font-monospace" style="font-size: 0.7rem;">#<?= $index + 1 ?></span>
                            </div>
                            
                            <div class="text-truncate fw-bold text-dark mb-2" title="<?= $vid['processed_filename'] ?>" style="font-size: 0.85rem;">
                                <i class="bi bi-film text-secondary me-1"></i><?= basename($vid['processed_filename']) ?>
                            </div>

                            <div class="d-flex gap-1 mb-2 flex-wrap">
                                <?php foreach($vid['statuses'] as $a_id => $st): 
                                    $dot_color = 'bg-secondary';
                                    if ($st === 'SUCCESS') $dot_color = 'bg-success';
                                    if ($st === 'PROCESSING') $dot_color = 'bg-warning';
                                    if ($st === 'ERROR') $dot_color = 'bg-danger';
                                ?>
                                    <span class="badge <?= $dot_color ?> rounded-circle p-1 shadow-sm" style="width: 8px; height: 8px;" title="Acc ID <?= $a_id ?>: <?= $st ?>"></span>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-auto pt-2 text-center border-top">
                                <span class="text-dark font-monospace" style="font-size: 0.8rem; background-color: #fffbeb; padding: 2px 6px; border-radius: 4px; border: 1px solid #fde68a;">
                                    <i class="bi bi-clock-history text-warning me-1"></i>
                                    <?= $vid['publish_time'] ? date('H:i d/m', strtotime($vid['publish_time'])) : '...' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<script src="assets/publish.js"></script>

<?php require 'includes/footer.php'; ?>