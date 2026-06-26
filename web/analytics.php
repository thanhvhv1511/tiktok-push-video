<?php
require 'core/db.php';

$video_dir = getenv('VIDEO_DIR') ?: '/var/www/html/videos';

date_default_timezone_set('Asia/Ho_Chi_Minh');
$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// ---------------------------------------------------------
// ⏳ XỬ LÝ LỌC THỜI GIAN ĐỘNG (MẶC ĐỊNH 7 NGÀY GẦN NHẤT)
// ---------------------------------------------------------
$range = $_GET['range'] ?? '7_days'; 

switch ($range) {
    case 'yesterday':
        $filter_from = $yesterday;
        $filter_to   = $yesterday;
        break;
    case '30_days':
        $filter_from = date('Y-m-d', strtotime('-30 days'));
        $filter_to   = $today;
        break;
    case '7_days':
    case 'custom':
    default:
        if ($range === 'custom') {
            $filter_from = $_GET['from_date'] ?? date('Y-m-d', strtotime('-6 days'));
            $filter_to   = $_GET['to_date'] ?? $today;
        } else {
            $filter_from = date('Y-m-d', strtotime('-6 days'));
            $filter_to   = $today;
            $range       = '7_days';
        }
        break;
}

$accounts = $pdo->query("SELECT * FROM accounts ORDER BY account_id ASC")->fetchAll();
$filter_account = isset($_GET['account_id']) && $_GET['account_id'] !== 'all' ? (int)$_GET['account_id'] : 'all';

$period = new DatePeriod(
    new DateTime($filter_from),
    new DateInterval('P1D'),
    (new DateTime($filter_to))->modify('+1 day')
);
$timeline_days = [];
foreach ($period as $date) {
    $timeline_days[] = $date->format('Y-m-d');
}

// =========================================================
// 🧮 KHỐI TÍNH TOÁN DATA TỪ DATABASE (BỔ SUNG LOG TABLE)
// =========================================================
$all_products_raw = $pdo->query("SELECT product_id, name FROM products")->fetchAll();

$total_sold = 0;
$total_stock = 0;
$total_posted = 0; // Sẽ được tính từ bảng video_publish_logs
$product_data_mapped = [];

foreach ($all_products_raw as $p) {
    $pid = $p['product_id'];
    
    // 1. Quét ổ đĩa đếm file tồn kho thực tế
    $stock_count = 0;
    $matched_dirs = glob("$video_dir/$pid*", GLOB_ONLYDIR);
    if (!empty($matched_dirs)) {
        foreach (['mp4', 'mov', 'mkv', 'avi'] as $ext) {
            $files = glob($matched_dirs[0]."/*.$ext");
            if ($files) $stock_count += count($files);
        }
    }
    $total_stock  += $stock_count;

    // Chuẩn bị tham số lọc
    $acc_condition = ($filter_account !== 'all') ? "AND account_id = :account_id" : "";
    $params = ['product_id' => $pid, 'from_date' => $filter_from, 'to_date' => $filter_to];
    if ($filter_account !== 'all') $params['account_id'] = $filter_account;

    // 2. Lấy số ĐƠN nổ trong kỳ lọc
    $stmtSold = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM product_sales 
                               WHERE product_id = :product_id AND sale_date BETWEEN :from_date AND :to_date $acc_condition");
    $stmtSold->execute($params);
    $p_period_sold = (int)$stmtSold->fetchColumn();
    $total_sold += $p_period_sold;

    // 3. 👉 LẤY SỐ VIDEO ĐÃ ĐĂNG TỪ BẢNG video_publish_logs (Thay vì trừ trừ như trước)
    $stmtPosted = $pdo->prepare("SELECT COALESCE(SUM(videos_posted), 0) FROM video_publish_logs 
                                 WHERE product_id = :product_id AND publish_date BETWEEN :from_date AND :to_date $acc_condition");
    $stmtPosted->execute($params);
    $p_period_posted = (int)$stmtPosted->fetchColumn();
    $total_posted += $p_period_posted;

    $product_data_mapped[$pid] = [
        'name'            => $p['name'],
        'stock'           => $stock_count,
        'posted'          => $p_period_posted,
        'sold'            => $p_period_sold,
        'conversion_rate' => ($p_period_posted > 0) ? round(($p_period_sold / $p_period_posted) * 100, 2) : 0
    ];
}

// Tính tỉ lệ chuyển đổi chung của toàn hệ thống trong kỳ
$global_conversion_rate = ($total_posted > 0) ? round(($total_sold / $total_posted) * 100, 2) : 0;

// Sắp xếp tìm Top 5 bán chạy
uasort($product_data_mapped, function($a, $b) { return $b['sold'] - $a['sold']; });

$top_5_pie_labels = [];
$top_5_pie_sales  = [];
$rank_counter = 0;
foreach ($product_data_mapped as $pid => $data) {
    if ($rank_counter < 5 && $data['sold'] > 0) {
        $top_5_pie_labels[] = $pid;
        $top_5_pie_sales[]  = $data['sold'];
    }
    $rank_counter++;
}

// =========================================================
// 📈 ĐỔ DATA TIMELINE (GỘP TỪ 2 BẢNG SALES & LOGS)
// =========================================================
$daily_posted_videos = [];
$daily_sold_orders   = [];

foreach ($timeline_days as $day) {
    $acc_cond = ($filter_account !== 'all') ? "AND account_id = ?" : "";
    
    // Đơn nổ theo ngày
    $stmtDs = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM product_sales WHERE sale_date = ? $acc_cond");
    if ($filter_account !== 'all') { $stmtDs->execute([$day, $filter_account]); } else { $stmtDs->execute([$day]); }
    $daily_sold_orders[] = (int)$stmtDs->fetchColumn();

    // Video đăng theo ngày (Từ bảng Log)
    $stmtDp = $pdo->prepare("SELECT COALESCE(SUM(videos_posted), 0) FROM video_publish_logs WHERE publish_date = ? $acc_cond");
    if ($filter_account !== 'all') { $stmtDp->execute([$day, $filter_account]); } else { $stmtDp->execute([$day]); }
    $daily_posted_videos[] = (int)$stmtDp->fetchColumn(); 
}

// =========================================================
// 📊 DATA TIMELINE CHI TIẾT TỪNG SẢN PHẨM
// =========================================================
$product_line_datasets = [];
$top_products_keys = array_slice(array_keys($product_data_mapped), 0, 5);

foreach ($top_products_keys as $pid) {
    if ($product_data_mapped[$pid]['sold'] <= 0 && $product_data_mapped[$pid]['posted'] <= 0) continue;
    
    $p_orders = [];
    $p_videos = [];
    $p_rates  = [];

    foreach ($timeline_days as $day) {
        $acc_cond = ($filter_account !== 'all') ? "AND account_id = ?" : "";
        
        // Đơn theo ngày của SP
        $stmtPLine = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM product_sales WHERE product_id = ? AND sale_date = ? $acc_cond");
        if ($filter_account !== 'all') { $stmtPLine->execute([$pid, $day, $filter_account]); } else { $stmtPLine->execute([$pid, $day]); }
        $d_sold = (int)$stmtPLine->fetchColumn();
        $p_orders[] = $d_sold;

        // Video theo ngày của SP (Từ bảng Log)
        $stmtPVid = $pdo->prepare("SELECT COALESCE(SUM(videos_posted), 0) FROM video_publish_logs WHERE product_id = ? AND publish_date = ? $acc_cond");
        if ($filter_account !== 'all') { $stmtPVid->execute([$pid, $day, $filter_account]); } else { $stmtPVid->execute([$pid, $day]); }
        $d_posted = (int)$stmtPVid->fetchColumn();
        $p_videos[] = $d_posted;

        // CR theo ngày
        $p_rates[] = $d_posted > 0 ? round(($d_sold / $d_posted) * 100, 1) : 0;
    }
    
    $product_line_datasets[] = [
        'label'            => $pid,
        'data'             => $p_orders, 
        'video_history'    => $p_videos, 
        'rate_history'     => $p_rates,  
        'borderWidth'      => 2,
        'fill'             => false,
        'tension'          => 0.2
    ];
}

$timeline_labels_dm = array_map(function($d) { return date('d/m', strtotime($d)); }, $timeline_days);

$page_title = "Trung Tâm Phân Tích Dữ Liệu";
$active_tab = "analytics";
$extra_head = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
require 'includes/header.php';
?>

<div class="dashboard-card mb-4 p-4 bg-white border shadow-sm rounded-4">
    <form method="GET" action="analytics.php" class="row g-3 align-items-end">
        <input type="hidden" name="range" id="range_hidden_input" value="<?= htmlspecialchars($range) ?>">
        <div class="col-12 mb-2">
            <label class="form-label small fw-bold text-dark mb-2"><i class="bi bi-clock-history me-1 text-primary"></i>Chọn nhanh thời gian:</label>
            <div class="btn-group" role="group">
                <a href="analytics.php?range=yesterday&account_id=<?= $filter_account ?>" class="btn btn-sm <?= $range === 'yesterday' ? 'btn-primary' : 'btn-outline-secondary' ?> fw-semibold px-3">Hôm qua</a>
                <a href="analytics.php?range=7_days&account_id=<?= $filter_account ?>" class="btn btn-sm <?= $range === '7_days' ? 'btn-primary' : 'btn-outline-secondary' ?> fw-semibold px-3">7 ngày qua</a>
                <a href="analytics.php?range=30_days&account_id=<?= $filter_account ?>" class="btn btn-sm <?= $range === '30_days' ? 'btn-primary' : 'btn-outline-secondary' ?> fw-semibold px-3">30 ngày qua</a>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <label class="form-label small fw-semibold text-muted">Từ ngày</label>
            <input type="date" name="from_date" class="form-control form-control-sm" value="<?= $filter_from ?>" onchange="document.getElementById('range_hidden_input').value='custom'">
        </div>
        <div class="col-md-2 col-sm-6">
            <label class="form-label small fw-semibold text-muted">Đến ngày</label>
            <input type="date" name="to_date" class="form-control form-control-sm" value="<?= $filter_to ?>" onchange="document.getElementById('range_hidden_input').value='custom'">
        </div>
        <div class="col-md-4 col-sm-12">
            <label class="form-label small fw-semibold text-muted">Tài khoản kênh</label>
            <select name="account_id" class="form-select form-select-sm fw-bold">
                <option value="all" <?= $filter_account === 'all' ? 'selected' : '' ?>>📊 Hệ thống chung (All Shop)</option>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?= $acc['account_id'] ?>" <?= $filter_account === (int)$acc['account_id'] ? 'selected' : '' ?>><?= htmlspecialchars($acc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 col-sm-12 text-md-end">
            <button type="submit" class="btn btn-dark btn-sm px-4 fw-bold shadow-sm w-100 w-md-auto"><i class="bi bi-filter me-1"></i>LỌC SỐ LIỆU</button>
        </div>
    </form>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="dashboard-card p-4 border-bottom border-primary border-4 h-100 bg-white shadow-sm">
            <div class="text-muted fw-semibold small mb-1">TỔNG ĐƠN PHÁT SINH</div>
            <h2 class="fw-bold text-dark mb-0"><?= number_format($total_sold) ?> <span class="fs-6 text-muted fw-normal">đơn</span></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-card p-4 border-bottom border-warning border-4 h-100 bg-white shadow-sm">
            <div class="text-muted fw-semibold small mb-1">TỈ LỆ CHUYỂN ĐỔI CHUNG</div>
            <h2 class="fw-bold text-dark mb-0"><?= $global_conversion_rate ?>%</h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-card p-4 border-bottom border-success border-4 h-100 bg-white shadow-sm">
            <div class="text-muted fw-semibold small mb-1">TỔNG VIDEO ĐÃ ĐĂNG (LOG)</div>
            <h2 class="fw-bold text-dark mb-0"><?= number_format($total_posted) ?> <span class="fs-6 text-muted fw-normal">đã đăng</span></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-card p-4 border-bottom border-info border-4 h-100 bg-white shadow-sm">
            <div class="text-muted fw-semibold small mb-1">VIDEO CÒN TRONG KHO (FILE)</div>
            <h2 class="fw-bold text-info mb-0"><?= number_format($total_stock) ?> <span class="fs-6 text-muted fw-normal">file tồn</span></h2>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="dashboard-card p-4 bg-white border shadow-sm rounded-4 h-100 d-flex flex-column align-items-center">
            <h5 class="fw-bold text-dark mb-4 w-100"><i class="bi bi-pie-chart-fill text-success me-2"></i>Top 5 Sản Phẩm Chạy Nhất</h5>
            <div style="width: 215px;" class="my-auto">
                <?php if (empty($top_5_pie_sales)): ?>
                    <span class="text-muted small d-block text-center py-5">Chưa có đơn hàng kỳ này</span>
                <?php else: ?>
                    <canvas id="top5PieChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="dashboard-card bg-white border shadow-sm rounded-4 h-100 overflow-hidden">
            <div class="p-3 bg-light border-bottom"><h6 class="fw-bold text-dark mb-0">Thứ Hạng & Chuyển Đổi Trong Kỳ</h6></div>
            <div class="table-responsive" style="max-height: 290px; overflow-y: auto;">
                <table class="table mb-0 table-hover align-middle">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="ps-3" style="width: 60px;">Hạng</th>
                            <th style="width: 90px;">Mã SP</th>
                            <th>Tên Sản Phẩm</th>
                            <th class="text-center" style="width: 110px;">Đơn Kỳ</th>
                            <th class="text-center" style="width: 110px;">Video Kỳ</th>
                            <th class="text-center" style="width: 110px;">Chuyển Đổi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($product_data_mapped as $pid => $data): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-secondary"><?= $rank ?></td>
                            <td class="font-monospace fw-bold text-primary"><?= $pid ?></td>
                            <td class="text-dark small fw-medium text-truncate" style="max-width: 200px;"><?= htmlspecialchars($data['name']) ?></td>
                            <td class="text-center fw-bold text-danger bg-light"><?= number_format($data['sold']) ?></td>
                            <td class="text-center fw-bold text-dark bg-light"><?= number_format($data['posted']) ?></td>
                            <td class="text-center fw-bold text-primary bg-light"><?= $data['conversion_rate'] ?>%</td>
                        </tr>
                        <?php $rank++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="dashboard-card p-4 bg-white border shadow-sm rounded-4">
            <h5 class="fw-bold text-dark mb-2"><i class="bi bi-graph-up text-danger me-2"></i>Xu Hướng Đơn Hàng Theo Từng Sản Phẩm</h5>
            <canvas id="productSalesTimelineChart" height="40"></canvas>
        </div>
    </div>
    <div class="col-12">
        <div class="dashboard-card p-4 bg-white border shadow-sm rounded-4">
            <h5 class="fw-bold text-dark mb-2"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Xu Hướng Tỉ Lệ Chuyển Đổi Theo Từng Sản Phẩm (%)</h5>
            <canvas id="productRatesTimelineChart" height="40"></canvas>
        </div>
    </div>
    <div class="col-12 mb-5">
        <div class="dashboard-card p-4 bg-white border shadow-sm rounded-4">
            <h5 class="fw-bold text-dark mb-2"><i class="bi bi-calendar-range text-primary me-2"></i>Tương Quan Đăng Video & Tổng Đơn Hàng</h5>
            <canvas id="dailyCombinedChart" height="40"></canvas>
        </div>
    </div>
</div>

<script>
    const colorPalette = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ecc94b', '#48bb78'];

    // 1. Pie Chart
    <?php if (!empty($top_5_pie_sales)): ?>
    new Chart(document.getElementById('top5PieChart').getContext('2d'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($top_5_pie_labels) ?>,
            datasets: [{ data: <?= json_encode($top_5_pie_sales) ?>, backgroundColor: colorPalette.slice(0, 5), borderWidth: 1 }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } }
    });
    <?php endif; ?>

    const prodTimelineRaw = <?= json_encode($product_line_datasets) ?>;

    // 2. Timeline Sản Phẩm (Orders)
    new Chart(document.getElementById('productSalesTimelineChart').getContext('2d'), {
        type: 'line',
        data: { labels: <?= json_encode($timeline_labels_dm) ?>, datasets: prodTimelineRaw.map((set, idx) => ({ ...set, borderColor: colorPalette[idx % colorPalette.length], backgroundColor: colorPalette[idx % colorPalette.length] })) },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 10, font: { size: 11, weight: 'bold' } } },
                tooltip: { callbacks: { label: (c) => [`📦 Mã SP: ${c.dataset.label}`, `🛒 Số đơn: ${c.dataset.data[c.dataIndex]}`, `🎬 Video đã đăng: ${c.dataset.video_history[c.dataIndex]}`, `🎯 CR: ${c.dataset.rate_history[c.dataIndex]}%`] } }
            },
            scales: { y: { beginAtZero: true, grid: { borderDash: [4, 4] } } }
        }
    });

    // 3. Timeline Tỉ Lệ Chuyển Đổi (%)
    new Chart(document.getElementById('productRatesTimelineChart').getContext('2d'), {
        type: 'line',
        data: { labels: <?= json_encode($timeline_labels_dm) ?>, datasets: prodTimelineRaw.map((set, idx) => ({ label: set.label, data: set.rate_history, borderColor: colorPalette[idx % colorPalette.length], backgroundColor: colorPalette[idx % colorPalette.length], borderWidth: 2, fill: false, tension: 0.2 })) },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top', labels: { boxWidth: 10, font: { size: 11, weight: 'bold' } } } },
            scales: { y: { beginAtZero: true, title: { display: true, text: 'CR (%)' }, grid: { borderDash: [4, 4] } } }
        }
    });

    // 4. Mixed Chart (Video vs Orders)
    new Chart(document.getElementById('dailyCombinedChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($timeline_labels_dm) ?>,
            datasets: [
                { label: 'Số video (Log)', data: <?= json_encode($daily_posted_videos) ?>, backgroundColor: '#cbd5e1', yAxisID: 'yV', borderRadius: 4, order: 2 },
                { label: 'Tổng đơn', data: <?= json_encode($daily_sold_orders) ?>, borderColor: '#ef4444', backgroundColor: '#ef4444', borderWidth: 3, type: 'line', yAxisID: 'yS', tension: 0.2, order: 1 }
            ]
        },
        options: {
            responsive: true,
            scales: {
                yV: { type: 'linear', position: 'left', title: { display: true, text: 'Clips' }, beginAtZero: true },
                yS: { type: 'linear', position: 'right', title: { display: true, text: 'Đơn' }, beginAtZero: true, grid: { drawOnChartArea: false } }
            }
        }
    });
</script>

<?php require 'includes/footer.php'; ?>