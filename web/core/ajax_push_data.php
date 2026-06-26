<?php
require_once __DIR__ . '/db.php';

// 1. LẤY DỮ LIỆU THỐNG KÊ TIẾN ĐỘ (PROGRESS)
$sql_stats = "
    SELECT 
        IFNULL(pv.batch_id, 'DOT_LE_TANG') AS batch_id,
        a.account_id,
        a.name AS account_name,
        COUNT(vph.id) AS total_videos,
        SUM(CASE WHEN vph.status = 'SUCCESS' THEN 1 ELSE 0 END) AS success_count,
        SUM(CASE WHEN vph.status = 'ERROR' THEN 1 ELSE 0 END) AS error_count,
        SUM(CASE WHEN vph.status = 'PROCESSING' THEN 1 ELSE 0 END) AS processing_count,
        SUM(CASE WHEN vph.status = 'PENDING' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN vph.status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled_count
    FROM video_publish_history vph
    JOIN processed_videos pv ON vph.video_id = pv.id
    JOIN accounts a ON vph.account_id = a.account_id
    GROUP BY pv.batch_id, a.account_id, a.name
    ORDER BY pv.batch_id DESC, a.name ASC
";
$stmt_stats = $pdo->query($sql_stats);
$stats_raw = $stmt_stats->fetchAll();

$batches_stats = [];
foreach ($stats_raw as $row) {
    $batches_stats[$row['batch_id']][] = $row;
}

// 2. LẤY DỮ LIỆU LIVE FEED LOGS
$sql_logs = "
    SELECT vph.pushed_at, a.name as account_name, vph.status, vph.error_message, pv.processed_filename, pv.publish_time, p.name as product_name
    FROM video_publish_history vph
    JOIN processed_videos pv ON vph.video_id = pv.id
    JOIN accounts a ON vph.account_id = a.account_id
    JOIN products p ON pv.product_id = p.product_id
    ORDER BY vph.pushed_at DESC LIMIT 100
";
$stmt_logs = $pdo->query($sql_logs);
$live_logs = $stmt_logs->fetchAll();
?>

<div class="col-lg-5 mb-4">
    <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-header bg-white border-bottom p-3">
            <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-bar-chart-steps me-2"></i>Tiến độ đa kênh</h5>
        </div>
        <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
            <?php if (empty($batches_stats)): ?>
                <div class="p-4 text-center text-muted font-monospace">Chưa có dữ liệu đẩy kênh nào!</div>
            <?php else: ?>
                <?php foreach ($batches_stats as $batch_id => $accounts_data): ?>
                    <div class="p-3 border-bottom bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="fw-bold text-dark font-monospace small">📦 MÃ ĐỢT: <?= $batch_id ?></div>
                            <?php if ($batch_id !== 'DOT_LE_TANG'): ?>
                                <?php $safe_btn_id = 'btn_cancel_log_' . preg_replace('/[^a-zA-Z0-9]/', '_', $batch_id); ?>
                                <button class="btn btn-sm btn-outline-danger fw-bold font-monospace shadow-sm" 
                                        id="<?= $safe_btn_id ?>" style="font-size: 0.7rem; padding: 2px 8px;"
                                        onclick="cancelSingleBatch('<?= $batch_id ?>', '<?= $safe_btn_id ?>')">
                                    <i class="bi bi-stop-fill"></i> HỦY JOB
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php foreach ($accounts_data as $stat): 
                            $total = $stat['total_videos'];
                            $success = $stat['success_count'];
                            $error = $stat['error_count'];
                            $processing = $stat['processing_count'];
                            $cancelled = $stat['cancelled_count'] ?? 0;
                            
                            $percent_success = ($total > 0) ? round(($success / $total) * 100) : 0;
                            $percent_error = ($total > 0) ? round(($error / $total) * 100) : 0;
                            $percent_cancelled = ($total > 0) ? round(($cancelled / $total) * 100) : 0;
                            
                            $overall_status = "Đang chờ"; $status_color = "text-secondary"; $icon = "bi-circle";
                            
                            if ($error > 0) {
                                $overall_status = "Báo động lỗi!"; $status_color = "text-danger fw-bold"; $icon = "bi-exclamation-triangle-fill text-danger";
                            } elseif ($cancelled > 0 && $success + $error + $cancelled == $total) {
                                $overall_status = "Đã hủy"; $status_color = "text-muted fw-bold"; $icon = "bi-slash-circle-fill text-secondary";
                            } elseif ($success == $total) {
                                $overall_status = "Hoàn thành"; $status_color = "text-success fw-bold"; $icon = "bi-check-circle-fill text-success";
                            } elseif ($processing > 0 || $success > 0) {
                                $overall_status = "Đang chạy"; $status_color = "text-primary fw-bold"; $icon = "bi-gear-fill text-primary rotate-icon";
                            }
                        ?>
                            <div class="mb-3 bg-white p-2 rounded border border-light shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold small text-dark"><i class="bi bi-person-badge me-1 text-muted"></i><?= htmlspecialchars($stat['account_name']) ?></span>
                                        
                                        <?php if ($batch_id !== 'DOT_LE_TANG' && ($processing > 0 || $stat['pending_count'] > 0)): ?>
                                            <?php $safe_acc_btn_id = 'btn_cancel_acc_' . $stat['account_id'] . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $batch_id); ?>
                                            <a href="javascript:void(0)" class="text-danger border border-danger border-opacity-20 px-1 rounded font-monospace" 
                                               id="<?= $safe_acc_btn_id ?>" style="font-size: 0.65rem; text-decoration: none;"
                                               onclick="cancelAccountInBatch('<?= $batch_id ?>', '<?= $stat['account_id'] ?>', '<?= htmlspecialchars($stat['account_name']) ?>', '<?= $safe_acc_btn_id ?>')">
                                                <i class="bi bi-x-octagon-fill"></i> Dừng kênh
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <span class="small <?= $status_color ?>"><i class="bi <?= $icon ?> me-1"></i><?= $overall_status ?></span>
                                </div>
                                
                                <div class="progress" style="height: 12px; border-radius: 6px; background-color: #e2e8f0;">
                                    <div class="progress-bar bg-success progress-bar-striped <?= ($overall_status == 'Đang chạy') ? 'progress-bar-animated' : '' ?>" role="progressbar" style="width: <?= $percent_success ?>%"></div>
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $percent_error ?>%"></div>
                                    <div class="progress-bar bg-secondary" role="progressbar" style="width: <?= $percent_cancelled ?>%"></div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-1">
                                    <span class="small font-monospace text-muted" style="font-size: 0.75rem;">
                                        <span class="text-success fw-bold"><?= $success ?></span> OK 
                                        <span class="mx-1">|</span> <span class="text-danger fw-bold"><?= $error ?></span> Lỗi
                                        <?php if ($cancelled > 0): ?><span class="mx-1">|</span> <span class="text-secondary fw-bold"><?= $cancelled ?></span> Hủy<?php endif; ?>
                                    </span>
                                    <span class="small font-monospace fw-bold" style="font-size: 0.75rem; color: #475569;"><?= $percent_success ?>%</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="col-lg-7 mb-4">
    <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-terminal me-2"></i>Luồng Nhật Ký (Live Feed)</h5>
            <span class="badge bg-light text-dark border font-monospace">100 bản ghi mới nhất</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                <table class="table table-hover table-borderless mb-0 align-middle">
                    <thead class="bg-light sticky-top" style="z-index: 1;">
                        <tr>
                            <th class="py-3 px-3 text-muted small font-monospace">THỜI GIAN</th>
                            <th class="py-3 text-muted small font-monospace">KÊNH ĐĂNG</th>
                            <th class="py-3 text-muted small font-monospace">CHI TIẾT VIDEO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($live_logs)): ?>
                            <tr><td colspan="3" class="text-center py-5 text-muted font-monospace">Nhật ký trống!</td></tr>
                        <?php else: ?>
                            <?php foreach ($live_logs as $log): 
                                $bg_class = '';
                                if ($log['status'] == 'ERROR') $bg_class = 'bg-danger bg-opacity-10';
                                if ($log['status'] == 'SUCCESS') $bg_class = 'bg-success bg-opacity-10';
                                if ($log['status'] == 'PROCESSING') $bg_class = 'bg-warning bg-opacity-10';
                                if ($log['status'] == 'CANCELLED') $bg_class = 'bg-secondary bg-opacity-10';
                            ?>
                            <tr class="border-bottom <?= $bg_class ?>">
                                <td class="px-3"><span class="font-monospace text-muted" style="font-size: 0.8rem;"><?= date('H:i:s', strtotime($log['pushed_at'])) ?></span></td>
                                <td><span class="fw-bold text-dark" style="font-size: 0.85rem;"><i class="bi bi-shop me-1 text-primary"></i><?= htmlspecialchars($log['account_name']) ?></span></td>
                                <td>
                                    <?php if ($log['status'] == 'ERROR'): ?>
                                        <div class="text-danger fw-bold" style="font-size: 0.85rem;"><i class="bi bi-x-circle-fill me-1"></i> Lỗi ở Video: <span class="font-monospace"><?= basename($log['processed_filename']) ?></span></div>
                                        <div class="text-muted font-monospace mt-1" style="font-size: 0.75rem;">👉 Lý do: <?= htmlspecialchars($log['error_message'] ?: 'Không xác định') ?></div>
                                    <?php elseif ($log['status'] == 'CANCELLED'): ?>
                                        <div class="text-muted fw-bold" style="font-size: 0.85rem; color: #64748b !important;"><i class="bi bi-slash-circle me-1"></i> Đã Hủy Video: <span class="font-monospace"><?= basename($log['processed_filename']) ?></span></div>
                                        <div class="text-muted font-monospace mt-1" style="font-size: 0.75rem;">👉 Trạng thái: <?= htmlspecialchars($log['error_message'] ?: 'Bị dừng khẩn cấp') ?></div>
                                    <?php elseif ($log['status'] == 'SUCCESS'): ?>
                                        <div class="text-success fw-bold" style="font-size: 0.85rem;"><i class="bi bi-check-circle-fill me-1"></i> Đã lên lịch xong Video: <span class="font-monospace"><?= basename($log['processed_filename']) ?></span></div>
                                        <div class="text-muted font-monospace mt-1" style="font-size: 0.75rem;">Lịch đăng: <?= date('H:i d/m/Y', strtotime($log['publish_time'])) ?> | <?= htmlspecialchars($log['product_name']) ?></div>
                                    <?php elseif ($log['status'] == 'PROCESSING'): ?>
                                        <div class="text-warning fw-bold" style="font-size: 0.85rem;"><i class="bi bi-arrow-repeat rotate-icon me-1"></i> Bot đang xử lý Video: <span class="font-monospace text-dark"><?= basename($log['processed_filename']) ?></span></div>
                                    <?php else: ?>
                                        <div class="text-secondary fw-bold" style="font-size: 0.85rem;"><i class="bi bi-hourglass me-1"></i> Đang chờ chạy: <span class="font-monospace"><?= basename($log['processed_filename']) ?></span></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>