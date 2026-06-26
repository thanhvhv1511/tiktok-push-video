<?php
require 'core/db.php';

$video_dir = getenv('VIDEO_DIR') ?: '/var/www/html/videos';
// ⚡ ĐÃ UPDATE: Trỏ tới thư mục kho thành phẩm đã phân loại theo mã SP
$processed_root_dir = getenv('PROCESSED_DIR') ?: '/var/www/html/push-video/processed'; 

// KIỂM TRA NGUY CƠ ĐĂNG TRÙNG
$stmt_check = $pdo->query("SELECT COUNT(*) as uncleaned_count FROM processed_videos WHERE status = 'pushed'");
$uncleaned_result = $stmt_check->fetch();
$has_uncleaned_danger = ((int)$uncleaned_result['uncleaned_count'] > 0);

$stmt = $pdo->query("SELECT product_id, name, units_sold FROM products ORDER BY created_at DESC");
$products = $stmt->fetchAll();

date_default_timezone_set('Asia/Ho_Chi_Minh');
$suggested_time = date('Y-m-d\TH:i', strtotime('+1 hour'));
$mac_base_path = getenv('MAC_BASE_PATH') ?: '/var/www/html';
$allowed_extensions = ['mp4', 'mov', 'mkv', 'avi'];

// ⚡ ĐÃ UPDATE: Quét thư mục con của từng sản phẩm trong PROCESSED_DIR
$ready_map = [];
foreach ($products as $p_row) {
    $pid = $p_row['product_id'];
    $ready_map[$pid] = [];
    $product_ready_dir = "$processed_root_dir/$pid";

    if (is_dir($product_ready_dir)) {
        foreach ($allowed_extensions as $ext) {
            $files = glob("$product_ready_dir/*.$ext");
            if ($files !== false) {
                foreach ($files as $file) {
                    $ready_map[$pid][] = $file;
                }
            }
        }
    }
}

$page_title = "Hệ Thống Auto Push Video";
$active_tab = "index";
require 'includes/header.php';
?>

<link rel="stylesheet" href="assets/style.css">

<?php if ($has_uncleaned_danger): ?>
<div class="alert alert-danger d-flex align-items-center border-0 shadow-sm p-4 mb-4 rounded-4 animate__animated animate__headShake" role="alert" style="background-color: #fef2f2; border-left: 5px solid #ef4444 !important;">
    <div class="text-danger me-3 bg-white p-2 rounded-3 shadow-sm border border-danger border-opacity-10">
        <i class="bi bi-exclamation-triangle-fill fs-3"></i>
    </div>
    <div class="w-100">
        <h5 class="alert-heading fw-bold mb-1 text-dark" style="color: #991b1b !important;">CẢNH BÁO NGUY CƠ ĐĂNG TRÙNG VIDEO!</h5>
        <p class="mb-0 text-secondary small">Hệ thống phát hiện có đợt lên lịch cũ đã <span class="badge bg-success font-monospace">Pushed</span> nhưng <span class="fw-bold text-danger">Chưa được xóa file vật lý (Cleaned)</span> khỏi ổ đĩa.</p>
    </div>
    <div class="ms-auto ps-3 text-nowrap">
        <a href="publish_history.php" class="btn btn-sm btn-danger fw-bold px-3 py-2 shadow-sm">
            <i class="bi bi-arrow-right-circle me-1"></i> Đi Dọn Kho Ngay
        </a>
    </div>
</div>
<?php endif; ?>

<div class="d-flex align-items-center mb-4">
    <div class="bg-white p-3 rounded-4 me-3 shadow-sm text-primary border" style="border-color: #e2e8f0 !important;">
        <i class="bi bi-robot fs-4"></i>
    </div>
    <div>
        <h2 class="fw-bold mb-1" style="color: #0f172a;">Auto Push Manager</h2>
        <p class="text-muted mb-0 font-monospace small"><?= htmlspecialchars($mac_base_path) ?></p>
    </div>
</div>

<div class="mb-4">
    <div class="d-flex align-items-center mb-2">
        <i class="bi bi-lightning-charge-fill text-warning me-2"></i>
        <span class="fw-bold text-dark fs-6">Ghim Nhanh:</span>
    </div>
    <div id="pinned_memories_container" class="d-flex flex-wrap gap-2"></div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 bg-white p-3 rounded-3 shadow-sm border" style="border-color: #e2e8f0 !important;">
    <div class="d-flex flex-wrap gap-4 align-items-center mb-2 mb-md-0">
        <div class="input-group" style="max-width: 250px;">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-camera-video"></i></span>
            <input type="number" id="default_random_count" class="form-control" placeholder="Mặc định số video/SP" min="1">
        </div>
        <div class="form-check form-switch fs-5 mb-0 d-flex align-items-center">
            <input class="form-check-input shadow-none mt-0 me-2" type="checkbox" id="selectAll" onchange="toggleSelectAll()" style="cursor: pointer;">
            <label class="form-check-label fs-6 fw-bold text-dark" for="selectAll" style="cursor: pointer;">Chọn Tất Cả SP</label>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary fw-medium" data-bs-toggle="modal" data-bs-target="#memoryModal">
            <i class="bi bi-cloud-arrow-up me-1"></i> Bộ nhớ Đám Mây
        </button>
    </div>
</div>

<form action="process.php" method="POST">
    <input type="hidden" name="action" value="preview">
    <div class="dashboard-card mb-5">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 60px;">Chọn</th>
                        <th>Mã SP</th>
                        <th>Tên Sản Phẩm</th>
                        <th class="text-center">Tồn Kho Thô (Đĩa)</th>
                        <th class="text-center">Sẵn Sàng Đăng</th>
                        <th class="text-center">Đã Bán</th>
                        <th class="text-center">Tỉ Lệ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $row): 
                        $pid = $row['product_id'];
                        $ready_files = $ready_map[$pid] ?? [];
                        $ready_count = count($ready_files);

                        $stock_count = 0;
                        $matched_dirs = glob("$video_dir/$pid*", GLOB_ONLYDIR);
                        if (!empty($matched_dirs)) {
                            $target_dir = $matched_dirs[0];
                            foreach ($allowed_extensions as $ext) {
                                $files = glob("$target_dir/*.$ext");
                                if ($files !== false) $stock_count += count($files);
                            }
                        }

                        $stmt_posted = $pdo->prepare("SELECT COUNT(*) FROM processed_videos WHERE product_id = ? AND status IN ('pushed', 'used')");
                        $stmt_posted->execute([$pid]);
                        $posted_count = (int)$stmt_posted->fetchColumn();
                        $conversion_rate = $posted_count > 0 ? round(($row['units_sold'] / $posted_count) * 100, 2) : 0;
                    ?>
                    <tr>
                        <td class="text-center">
                            <?php if ($ready_count > 0): ?>
                                <input class="form-check-input shadow-none" type="checkbox" id="check_<?= $pid ?>" name="selected_products[]" value="<?= $pid ?>" onchange="toggleProductSelect('<?= $pid ?>')">
                            <?php else: ?>
                                <span class="badge bg-soft-secondary text-muted border">HẾT</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold text-primary font-monospace"><?= $pid ?></td>
                        <td class="fw-medium"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="text-center">
                            <span class="badge <?= $stock_count > 0 ? 'bg-soft-secondary text-dark' : 'bg-soft-danger' ?>">
                                <?= $stock_count ?> File thô
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= $ready_count > 0 ? 'bg-soft-success' : 'bg-soft-danger' ?> fs-6">
                                <i class="bi bi-check-circle me-1"></i><?= $ready_count ?> Có sẵn
                            </span>
                        </td>
                        <td class="text-center fw-bold" style="color: #ef4444;"><?= number_format($row['units_sold']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-soft-info"><?= $conversion_rate ?>%</span>
                        </td>
                    </tr>
                    <tr id="config_<?= $pid ?>" class="config-row d-none">
                        <td colspan="7" class="px-4 py-4 border-bottom">
                            <div class="row w-100 m-0">
                                <div class="col-lg-6 col-md-8 px-0">
                                    <h6 class="fw-bold mb-3" style="color: #475569;"><i class="bi bi-sliders me-2"></i>Thiết lập nguồn video cho <?= $pid ?></h6>
                                    <div class="mb-3 p-2 bg-white rounded-3 border d-inline-block">
                                        <div class="form-check form-check-inline mb-0">
                                            <input class="form-check-input" type="radio" name="mode_<?= $pid ?>" id="mode_random_<?= $pid ?>" value="random" checked onchange="toggleMode('<?= $pid ?>')">
                                            <label class="form-check-label small fw-medium" for="mode_random_<?= $pid ?>">Lấy Random</label>
                                        </div>
                                        <div class="form-check form-check-inline mb-0">
                                            <input class="form-check-input" type="radio" name="mode_<?= $pid ?>" id="mode_select_<?= $pid ?>" value="select" onchange="toggleMode('<?= $pid ?>')">
                                            <label class="form-check-label small fw-medium" for="mode_select_<?= $pid ?>">Chọn thủ công</label>
                                        </div>
                                    </div>
                                    <div id="random_input_<?= $pid ?>">
                                        <div class="input-group shadow-sm" style="max-width: 300px;">
                                            <span class="input-group-text"><i class="bi bi-shuffle"></i></span>
                                            <input type="number" name="random_count_<?= $pid ?>" class="form-control border-start-0 ps-0" placeholder="Số lượng..." min="1" max="<?= $ready_count ?>">
                                            <span class="input-group-text font-monospace small">/ <?= $ready_count ?></span>
                                        </div>
                                    </div>
                                    <div id="select_input_<?= $pid ?>" class="d-none">
                                        <div class="file-list shadow-sm">
                                            <?php if(empty($ready_files)): ?>
                                                <span class="text-danger small"><i class="bi bi-exclamation-circle me-1"></i>Kho video sẵn sàng trống.</span>
                                            <?php else: ?>
                                                <?php foreach($ready_files as $file): ?>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input shadow-none" type="checkbox" name="files_<?= $pid ?>[]" value="<?= htmlspecialchars(basename($file)) ?>" id="file_<?= md5($file) ?>">
                                                        <label class="form-check-label small font-monospace text-dark" for="file_<?= md5($file) ?>">
                                                            <i class="bi bi-file-earmark-play me-1 text-success"></i><?= htmlspecialchars(basename($file)) ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="dashboard-card border border-primary border-opacity-10 p-4 p-md-5" style="background: linear-gradient(145deg, #ffffff, #f8fafc);">
        <h5 class="fw-bold mb-4" style="color: #0f172a;"><i class="bi bi-calendar2-check text-primary me-2"></i>Cấu Hình Lịch Đăng Chung</h5>
        <div class="row g-4 align-items-end">
            <div class="col-md-4 col-lg-3">
                <label class="form-label fw-semibold text-muted small mb-2">Video đầu tiên lên sóng lúc:</label>
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-clock"></i></span>
                    <input type="datetime-local" name="global_start_time" required class="form-control border-start-0 ps-0 fw-medium" value="<?= $suggested_time ?>">
                </div>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label fw-semibold text-muted small mb-2">Khoảng cách mỗi video:</label>
                <div class="input-group shadow-sm">
                    <input type="number" name="global_interval" value="45" required class="form-control border-end-0 fw-medium text-center">
                    <span class="input-group-text bg-white text-muted small fw-medium">Phút</span>
                </div>
            </div>
            <div class="col-md-5 col-lg-4">
                <label class="form-label fw-semibold text-muted small mb-2">Chế độ đăng:</label>
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-sort-down"></i></span>
                    <select name="global_sort_mode" class="form-select border-start-0 ps-0 fw-medium">
                        <option value="by_product" selected>Theo Sản Phẩm</option>
                        <option value="random">Random</option>
                    </select>
                </div>
            </div>
            <div class="col-md-12 col-lg-3 text-lg-end mt-4 mt-lg-0">
                <button type="submit" class="btn btn-primary btn-lg w-100 shadow"><i class="bi bi-magic me-2"></i>TẠO LỊCH PREVIEW</button>
            </div>
        </div>
    </div>
</form>

<?php require 'includes/modals.php'; ?>
<script src="assets/script.js"></script>
<?php require 'includes/footer.php'; ?>