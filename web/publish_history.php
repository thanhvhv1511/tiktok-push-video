<?php
require 'core/db.php';

// Gom nhóm video thành từng đợt theo thời gian tạo (created_at)
// Hiển thị đầy đủ cả trạng thái 'used' (Đã xóa file) để giữ vững lịch sử đợt đăng ngoài UI chính
$sql = "SELECT 
            MIN(id) as sample_id,
            COUNT(*) as total_videos,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_count,
            SUM(CASE WHEN status = 'pushed' THEN 1 ELSE 0 END) as pushed_count,
            SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_count,
            MIN(publish_time) as start_time,
            MAX(publish_time) as end_time,
            created_at
        FROM processed_videos 
        WHERE status IN ('scheduled', 'pushed', 'used')
        GROUP BY created_at
        ORDER BY created_at DESC";

$stmt = $pdo->query($sql);
$batches = $stmt->fetchAll();

$page_title = "Lịch Sử Lên Lịch";
$active_tab = "publish_history";
require 'includes/header.php';
?>

<link rel="stylesheet" href="assets/style.css">

<div class="d-flex align-items-center mb-4">
    <div class="bg-white p-3 rounded-4 me-3 shadow-sm text-primary border" style="border-color: #e2e8f0 !important;">
        <i class="bi bi-layer-forward fs-4"></i>
    </div>
    <div>
        <h2 class="fw-bold mb-1" style="color: #0f172a;">Lịch Sử Lên Lịch</h2>
        <p class="text-muted mb-0 font-monospace small">Quản lý theo đợt lên lịch - Điều khiển trạng thái và dọn dẹp</p>
    </div>
</div>

<div class="dashboard-card mb-5">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Thời Gian Tạo Lịch</th>
                    <th>Khung Giờ Phát Sóng (Dự Kiến)</th>
                    <th class="text-center">Tổng Số Video</th>
                    <th class="text-center">Trạng Thái Đợt</th>
                    <th class="text-end" style="width: 320px;">Thao Tác Cả Đợt</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($batches)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2 text-black-50"></i>
                            Chưa có lượt lên lịch nào chờ xử lý.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($batches as $batch): 
                        $created_time = date('H:i:s d/m/Y', strtotime($batch['created_at']));
                        $start_time = date('H:i', strtotime($batch['start_time']));
                        $end_time = date('H:i d/m/Y', strtotime($batch['end_time']));
                        
                        $batch_id_md5 = md5($batch['created_at']);
                    ?>
                    <tr id="batch_row_<?= $batch_id_md5 ?>">
                        <td class="fw-bold text-dark"><i class="bi bi-calendar-event me-1 text-primary"></i> <?= $created_time ?></td>
                        <td class="text-muted small">
                            <span class="text-primary fw-medium"><?= $start_time ?></span> đến 
                            <span class="text-primary fw-medium"><?= $end_time ?></span>
                        </td>
                        <td class="text-center fw-bold text-secondary"><?= $batch['total_videos'] ?> Clip</td>
                        
                        <td class="text-center" id="status_container_<?= $batch_id_md5 ?>">
                            <?php if ($batch['scheduled_count'] > 0): ?>
                                <span class="badge bg-soft-warning text-dark border border-warning border-opacity-25">
                                    <i class="bi bi-hourglass-split me-1"></i> Scheduled
                                </span>
                            <?php elseif ($batch['pushed_count'] > 0): ?>
                                <span class="badge bg-soft-success border border-success border-opacity-25">
                                    <i class="bi bi-check-all me-1"></i> Pushed
                                </span>
                            <?php else: ?>
                                <span class="badge bg-soft-secondary text-muted border border-secondary border-opacity-25">
                                    <i class="bi bi-trash3 me-1"></i> Cleaned
                                </span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="text-end" id="action_container_<?= $batch_id_md5 ?>">
                            <div class="d-flex justify-content-end gap-1">
                                <button class="btn btn-sm btn-light border shadow-sm fw-medium" onclick="viewBatchDetails('<?= rawurlencode($batch['created_at']) ?>', '<?= $created_time ?>')">
                                    <i class="bi bi-eye"></i> Chi Tiết
                                </button>

                                <?php if ($batch['scheduled_count'] > 0): ?>
                                    <button class="btn btn-sm btn-outline-success fw-medium" onclick="updateBatchStatus('<?= rawurlencode($batch['created_at']) ?>', 'pushed', '<?= $batch_id_md5 ?>')">
                                        <i class="bi bi-check2-circle"></i> Done
                                    </button>
                                <?php elseif ($batch['pushed_count'] > 0): ?>
                                    <button class="btn btn-sm btn-danger fw-bold shadow-sm" onclick="deleteBatchFiles('<?= rawurlencode($batch['created_at']) ?>', '<?= $batch_id_md5 ?>')">
                                        <i class="bi bi-trash"></i> Xóa Video
                                    </button>
                                <?php else: ?>
                                    <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="batchDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-collection-play text-primary me-2"></i>Danh Sách Video Trong Đợt
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-striped align-middle mb-0 small">
                        <thead class="position-sticky top-0 bg-light" style="z-index: 10;">
                            <tr>
                                <th>Giờ Đăng</th>
                                <th>Mã SP</th>
                                <th>Tên File Video</th>
                            </tr>
                        </thead>
                        <tbody id="batch_video_list">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top-0 bg-light py-2">
                <span class="text-muted small me-auto" id="modal_batch_footer_info"></span>
                <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/modals.php'; ?>
<script src="assets/script.js"></script>

<script>
    // 1. Xem danh sách video con trong đợt
    async function viewBatchDetails(encodedCreatedAt, friendlyTitle) {
        const rawCreatedAt = decodeURIComponent(encodedCreatedAt);
        
        const formData = new FormData();
        formData.append('action', 'get_batch_details');
        formData.append('created_at', rawCreatedAt);

        try {
            const res = await fetch('core/ajax_history.php', { method: 'POST', body: formData });
            const videos = await res.json();
            
            const tbody = document.getElementById('batch_video_list');
            tbody.innerHTML = '';

            videos.forEach(v => {
                const pTime = new Date(v.publish_time).toLocaleTimeString('vi-VN', {hour: '2-digit', minute:'2-digit'});
                tbody.innerHTML += `
                    <tr>
                        <td class="fw-bold text-dark font-monospace">${pTime}</td>
                        <td class="font-monospace fw-bold text-primary">${v.product_id}</td>
                        <td class="text-muted font-monospace text-break">${v.processed_filename}</td>
                    </tr>
                `;
            });

            document.getElementById('modal_batch_footer_info').innerText = `Tổng số: ${videos.length} video (Tạo lúc: ${friendlyTitle})`;
            
            const myModal = new bootstrap.Modal(document.getElementById('batchDetailsModal'));
            myModal.show();
        } catch (error) {
            showAppModal('Không thể tải chi tiết danh sách video.', 'error');
        }
    }

    // 2. Click nút Done để chuyển đợt sang trạng thái Pushed
    async function updateBatchStatus(encodedCreatedAt, newStatus, rowMd5) {
        const rawCreatedAt = decodeURIComponent(encodedCreatedAt);
        const formData = new FormData();
        formData.append('action', 'update_batch_status');
        formData.append('created_at', rawCreatedAt);
        formData.append('status', newStatus);

        try {
            const res = await fetch('core/ajax_history.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                // Thay đổi trạng thái UI nóng sang Pushed và đổi nút hành động sang Xóa Video
                document.getElementById(`status_container_${rowMd5}`).innerHTML = `
                    <span class="badge bg-soft-success border border-success border-opacity-25">
                        <i class="bi bi-check-all me-1"></i> Pushed
                    </span>
                `;
                document.getElementById(`action_container_${rowMd5}`).innerHTML = `
                    <div class="d-flex justify-content-end gap-1">
                        <button class="btn btn-sm btn-light border shadow-sm fw-medium" onclick="viewBatchDetails('${encodedCreatedAt}', '')">
                            <i class="bi bi-eye"></i> Chi Tiết
                        </button>
                        <button class="btn btn-sm btn-danger fw-bold shadow-sm" onclick="deleteBatchFiles('${encodedCreatedAt}', '${rowMd5}')">
                            <i class="bi bi-trash"></i> Xóa Video
                        </button>
                    </div>
                `;
            } else {
                showAppModal(data.error || 'Cập nhật trạng thái đợt thất bại.', 'error');
            }
        } catch (error) {
            showAppModal('Lỗi kết nối máy chủ.', 'error');
        }
    }

    // 3. Xóa file vật lý khỏi folder (Chuyển sang trạng thái Cleaned, giữ dòng lịch sử ngoài UI)
    function deleteBatchFiles(encodedCreatedAt, rowMd5) {
        showAppModal('Hệ thống sẽ xóa vĩnh viễn TOÀN BỘ file video vật lý thuộc đợt này khỏi folder. Bạn chắc chắn chứ?', 'confirm', async () => {
            const rawCreatedAt = decodeURIComponent(encodedCreatedAt);
            const formData = new FormData();
            formData.append('action', 'delete_batch_files');
            formData.append('created_at', rawCreatedAt);

            try {
                const res = await fetch('core/ajax_history.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    showAppModal('Đã dọn dẹp sạch toàn bộ file video vật lý khỏi folder!', 'success');
                    
                    // Cập nhật UI sang trạng thái Cleaned (Xóa file xong nhưng dòng lịch sử vẫn nằm im ở bảng ngoài)
                    document.getElementById(`status_container_${rowMd5}`).innerHTML = `
                        <span class="badge bg-soft-secondary text-muted border border-secondary border-opacity-25">
                            <i class="bi bi-trash3 me-1"></i> Cleaned
                        </span>
                    `;
                    // Ẩn nút Xóa Video, chỉ giữ lại nút Chi Tiết xem lịch sử
                    document.getElementById(`action_container_${rowMd5}`).innerHTML = `
                        <div class="d-flex justify-content-end gap-1">
                            <button class="btn btn-sm btn-light border shadow-sm fw-medium" onclick="viewBatchDetails('${encodedCreatedAt}', '')">
                                <i class="bi bi-eye"></i> Chi Tiết
                            </button>
                        </div>
                    `;
                } else {
                    showAppModal(data.error || 'Có lỗi xảy ra trong quá trình xóa file.', 'error');
                }
            } catch (error) {
                showAppModal('Lỗi hệ thống khi thực thi lệnh dọn dẹp.', 'error');
            }
        });
    }
</script>

<?php require 'includes/footer.php'; ?>