<?php
require 'core/db.php';

$video_dir = getenv('VIDEO_DIR') ?: '/var/www/html/videos';
$output_video_dir = getenv('PROCESSED_DIR') ?: '/var/www/html/push-video/processed'; 

$stmt_queue = $pdo->query("SELECT product_id, status FROM render_queue WHERE status IN ('pending', 'running')");
$active_queue = [];
while ($q_row = $stmt_queue->fetch()) {
    $active_queue[$q_row['product_id']] = $q_row['status']; 
}

$sql = "SELECT p.product_id, p.name FROM products p ORDER BY p.created_at DESC";
$stmt = $pdo->query($sql);
$products = $stmt->fetchAll();

$allowed_extensions = ['mp4', 'mov', 'mkv', 'avi'];
$processed_products = [];

foreach ($products as $row) {
    $pid = $row['product_id'];
    $unprocessed_count = 0;

    $matched_dirs = glob("$video_dir/$pid*", GLOB_ONLYDIR);
    if (!empty($matched_dirs)) {
        $target_dir = $matched_dirs[0];
        foreach ($allowed_extensions as $ext) {
            $files = glob("$target_dir/*.$ext");
            if ($files !== false) {
                $unprocessed_count += count($files);
            }
        }
    }

    $ready_count = 0;
    $product_ready_dir = "$output_video_dir/$pid"; 
    if (is_dir($product_ready_dir)) {
        foreach ($allowed_extensions as $ext) {
            $ready_files = glob("$product_ready_dir/*.$ext");
            if ($ready_files !== false) {
                $ready_count += count($ready_files);
            }
        }
    }

    $processed_products[] = [
        'product_id'        => $pid,
        'name'              => $row['name'],
        'unprocessed_count' => $unprocessed_count, 
        'ready_count'       => $ready_count,        
        'queue_status'      => $active_queue[$pid] ?? null 
    ];
}

$page_title = "Xưởng Xử Lý Video";
$active_tab = "pre_process"; 
require 'includes/header.php';
?>

<link rel="stylesheet" href="assets/style.css">
<link rel="stylesheet" href="assets/terminal.css">

<div class="d-flex align-items-center mb-4">
    <div class="bg-white p-3 rounded-4 me-3 shadow-sm text-primary border" style="border-color: #e2e8f0 !important;">
        <i class="bi bi-cpu fs-4"></i>
    </div>
    <div>
        <h2 class="fw-bold mb-1" style="color: #0f172a;">Xưởng Render Video</h2>
        <p class="text-muted mb-0 font-monospace small">Kích hoạt dây chuyền cắt, dựng, render hiệu ứng tự động</p>
    </div>
</div>

<div class="dashboard-card mb-5">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>Mã SP</th>
                    <th>Tên Sản Phẩm</th>
                    <th class="text-center">Tồn Kho Thô</th>
                    <th class="text-center">Kho Sẵn Sàng</th>
                    <th class="text-end">Hành động (Render)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($processed_products as $row): 
                    $pid = $row['product_id'];
                    $unprocessed_count = $row['unprocessed_count']; 
                    $ready_count = $row['ready_count']; 
                    $q_status = $row['queue_status'];
                    $default_val = $unprocessed_count > 0 ? min(5, $unprocessed_count) : 0;
                ?>
                <tr data-pid="<?= $pid ?>" data-max="<?= $unprocessed_count ?>">
                    <td class="fw-bold text-primary font-monospace"><?= $pid ?></td>
                    <td class="fw-medium"><?= htmlspecialchars($row['name']) ?></td>
                    <td class="text-center">
                        <span class="badge <?= $unprocessed_count > 0 ? 'bg-soft-secondary text-dark' : 'bg-soft-danger' ?>">
                            <?= $unprocessed_count ?> File thực tế
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge <?= $ready_count > 0 ? 'bg-soft-success' : 'bg-soft-danger' ?> fs-6">
                            <i class="bi bi-check-circle me-1"></i><?= $ready_count ?> Sẵn sàng
                        </span>
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end align-items-center gap-2 action-container">
                            
                            <?php if ($q_status === 'running'): ?>
                                <input type="number" class="form-control form-control-sm text-center" value="" disabled style="width: 65px;">
                                <button class="btn btn-sm btn-info fw-bold text-white shadow-none" onclick="reopenLogModal('<?= $pid ?>')" style="width: 105px;">
                                    <span class="spinner-border spinner-border-sm me-1" style="width: 0.8rem; height: 0.8rem;"></span> Xem Log
                                </button>
                            <?php elseif ($q_status === 'pending'): ?>
                                <input type="number" class="form-control form-control-sm text-center" value="" disabled style="width: 65px;">
                                <button class="btn btn-sm btn-secondary fw-bold" disabled style="width: 105px;">
                                    <i class="bi bi-hourglass-split me-1"></i> Đang chờ
                                </button>
                            <?php else: ?>
                                <?php if ($unprocessed_count > 0): ?>
                                    <button type="button" class="btn btn-sm btn-light border text-muted fw-bold px-2 py-1 shadow-none" style="font-size: 0.7rem;" onclick="document.getElementById('count_<?= $pid ?>').value = <?= $unprocessed_count ?>; return false;" title="Chọn tất cả">
                                        MAX
                                    </button>
                                <?php endif; ?>
                                <input type="number" id="count_<?= $pid ?>" class="form-control form-control-sm text-center" 
                                       value="<?= $default_val ?>" min="1" max="<?= $unprocessed_count ?>" style="width: 65px;"
                                       <?= ($unprocessed_count == 0) ? 'disabled' : '' ?>>
                                
                                <button class="btn btn-sm btn-primary fw-bold" onclick="triggerProcess('<?= $pid ?>')" id="btn_<?= $pid ?>" <?= $unprocessed_count == 0 ? 'disabled' : '' ?> style="width: 105px;">
                                    <i class="bi bi-play-fill"></i> Render
                                </button>
                            <?php endif; ?>

                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="renderLogModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm text-warning me-2" id="modal-spinner" role="status"></div>
                    <h5 class="modal-title fw-bold text-dark" id="modal-status-title">Khởi động luồng...</h5>
                </div>
            </div>
            <div class="modal-body p-3">
                <textarea class="terminal-box w-100" id="terminal" readonly style="height: 380px; resize: none; border: none; font-family: monospace;"></textarea>
                <div class="mt-2 text-muted font-monospace d-flex justify-content-between" style="font-size: 0.72rem;">
                    <div><i class="bi bi-info-circle me-1"></i>Hệ thống tự động cuộn bám đáy dòng log mới nhất.</div>
                    <div id="process-pid-badge" class="text-secondary font-monospace fw-bold"></div>
                </div>
            </div>
            <div class="modal-footer border-top-0 bg-white py-2 d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-danger btn-sm px-3 fw-bold shadow-none" id="modal-stop-btn" onclick="stopProcess()">
                        <i class="bi bi-stop-fill me-1"></i> Dừng tiến trình
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-warning btn-sm px-3 fw-bold text-white shadow-none" id="modal-bg-btn" onclick="runInBackground()">
                        <i class="bi bi-cloud-arrow-down-fill me-1"></i> Cho chạy nền
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm px-4 fw-bold shadow-none" id="modal-close-btn" disabled onclick="window.location.reload()">
                        Đóng & Tải lại trang
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/modals.php'; ?>
<script src="assets/script.js"></script>
<script src="assets/render_process.js"></script>

<script>
setInterval(() => {
    fetch('core/ajax_queue_status.php?t=' + new Date().getTime())
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            const activeQueue = res.data;
            document.querySelectorAll('tr[data-pid]').forEach(tr => {
                const pid = tr.getAttribute('data-pid');
                const maxCount = parseInt(tr.getAttribute('data-max'));
                const container = tr.querySelector('.action-container');
                const qStatus = activeQueue[pid] || null;
                
                let html = '';
                if (qStatus === 'running') {
                    html = `
                        <input type="number" class="form-control form-control-sm text-center" value="" disabled style="width: 65px;">
                        <button class="btn btn-sm btn-info fw-bold text-white shadow-none" onclick="reopenLogModal('${pid}')" style="width: 105px;">
                            <span class="spinner-border spinner-border-sm me-1" style="width: 0.8rem; height: 0.8rem;"></span> Xem Log
                        </button>
                    `;
                } else if (qStatus === 'pending') {
                    html = `
                        <input type="number" class="form-control form-control-sm text-center" value="" disabled style="width: 65px;">
                        <button class="btn btn-sm btn-secondary fw-bold" disabled style="width: 105px;">
                            <i class="bi bi-hourglass-split me-1"></i> Đang chờ
                        </button>
                    `;
                } else {
                    let defaultVal = maxCount > 0 ? Math.min(5, maxCount) : 0;
                    if (maxCount > 0) {
                        html = `
                            <button type="button" class="btn btn-sm btn-light border text-muted fw-bold px-2 py-1 shadow-none" style="font-size: 0.7rem;" onclick="document.getElementById('count_${pid}').value = ${maxCount}; return false;" title="Chọn tất cả">MAX</button>
                            <input type="number" id="count_${pid}" class="form-control form-control-sm text-center" value="${defaultVal}" min="1" max="${maxCount}" style="width: 65px;">
                            <button class="btn btn-sm btn-primary fw-bold" onclick="triggerProcess('${pid}')" id="btn_${pid}" style="width: 105px;">
                                <i class="bi bi-play-fill"></i> Render
                            </button>
                        `;
                    } else {
                        html = `
                            <input type="number" class="form-control form-control-sm text-center" value="0" disabled style="width: 65px;">
                            <button class="btn btn-sm btn-primary fw-bold" disabled style="width: 105px;">
                                <i class="bi bi-play-fill"></i> Render
                            </button>
                        `;
                    }
                }
                
                // Chỉ vẽ lại UI nếu trạng thái thay đổi để tránh giật lag nút bấm
                if (container.innerHTML.trim() !== html.trim()) {
                    container.innerHTML = html;
                }
            });
        }
    });
}, 3000);
</script>

<?php require 'includes/footer.php'; ?>

