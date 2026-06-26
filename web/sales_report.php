<?php
require 'core/db.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// ---------------------------------------------------------
// XỬ LÝ LOGIC CHỌN NHANH KHOẢNG THỜI GIAN (QUICK SELECT)
// ---------------------------------------------------------
$range = $_GET['range'] ?? 'this_month'; // Mặc định mở ra xem Tháng này

switch ($range) {
    case 'yesterday':
        $filter_from = $yesterday;
        $filter_to   = $yesterday;
        break;
    case '7_days':
        $filter_from = date('Y-m-d', strtotime('-7 days'));
        $filter_to   = $today;
        break;
    case '30_days':
        $filter_from = date('Y-m-d', strtotime('-30 days'));
        $filter_to   = $today;
        break;
    case 'this_month':
        $filter_from = date('Y-m-01');
        $filter_to   = $today;
        break;
    case 'custom':
    default:
        $filter_from = $_GET['from_date'] ?? date('Y-m-01');
        $filter_to   = $_GET['to_date'] ?? $today;
        $range       = 'custom';
        break;
}

$filter_account = $_GET['account_id'] ?? 'all';
$filter_product = $_GET['product_id'] ?? 'all';
$filter_sort    = $_GET['sort'] ?? 'qty'; // Mặc định xếp theo số đơn

$accounts = $pdo->query("SELECT * FROM accounts ORDER BY account_id ASC")->fetchAll();
$products_filter = $pdo->query("SELECT product_id, name FROM products ORDER BY product_id ASC")->fetchAll();

// =========================================================
// XÂY DỰNG ĐIỀU KIỆN LỌC (BỎ QTY = 0)
// =========================================================
$where_clauses = [
    "s.sale_date BETWEEN :from_date AND :to_date",
    "s.quantity > 0" 
];
$params = ['from_date' => $filter_from, 'to_date' => $filter_to];

if ($filter_account !== 'all') {
    $where_clauses[] = "s.account_id = :account_id";
    $params['account_id'] = (int)$filter_account;
}
if ($filter_product !== 'all') {
    $where_clauses[] = "s.product_id = :product_id";
    $params['product_id'] = $filter_product;
}
$where_str = implode(" AND ", $where_clauses);

// Sắp xếp động: Xếp theo tổng đơn tích lũy (total_quantity) hoặc mã sản phẩm/ngày
$order_clause = ($filter_sort === 'qty') ? "total_quantity DESC" : "p.product_id ASC";

// ---------------------------------------------------------
// 👉 LOGIC MỚI: GROUP BY THEO SẢN PHẨM (BỎ QUAN TÂM NGÀY CHI TIẾT)
// ---------------------------------------------------------
$show_account_column = true;
if ($filter_account === 'all' && $filter_product === 'all') {
    $show_account_column = false;
}

if (!$show_account_column) {
    // Xem TẤT CẢ Tài Khoản + TẤT CẢ Sản Phẩm: Gộp phẳng hoàn toàn theo từng sản phẩm
    $log_sql = "
        SELECT p.product_id, p.name as product_name, SUM(s.quantity) as total_quantity
        FROM product_sales s
        JOIN products p ON s.product_id = p.product_id
        WHERE $where_str
        GROUP BY p.product_id
        ORDER BY $order_clause
    ";
} else {
    // Lọc cụ thể: Gom nhóm theo từng Shop + từng Sản phẩm để tính tổng đơn trong kỳ
    $log_sql = "
        SELECT a.name as account_name, p.product_id, p.name as product_name, SUM(s.quantity) as total_quantity
        FROM product_sales s
        JOIN accounts a ON s.account_id = a.account_id
        JOIN products p ON s.product_id = p.product_id
        WHERE $where_str
        GROUP BY s.account_id, p.product_id
        ORDER BY $order_clause
    ";
}

$stmtLog = $pdo->prepare($log_sql);
$stmtLog->execute($params);
$sales_logs = $stmtLog->fetchAll();

$grand_total_units = 0;
foreach ($sales_logs as $log) {
    $grand_total_units += (int)$log['total_quantity'];
}

$page_title = "Báo Cáo Doanh Số Tích Lũy - Auto Push";
$active_tab = "sales_report";
require 'includes/header.php';
?>

<script>
    function setTimeRange(rangeType) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('range', rangeType);
        urlParams.delete('from_date');
        urlParams.delete('to_date');
        window.location.search = urlParams.toString();
    }
</script>

<div class="dashboard-card mb-4 p-4 bg-white border shadow-sm rounded-4">
    <form method="GET" action="sales_report.php" id="filterForm">
        <input type="hidden" name="range" id="range_hidden_input" value="<?= htmlspecialchars($range) ?>">

        <div class="mb-4">
            <label class="form-label small fw-bold text-dark mb-2"><i class="bi bi-speedometer2 me-1 text-primary"></i>Chọn nhanh mốc thời gian:</label>
            <div class="btn-group flex-wrap" role="group">
                <button type="button" onclick="setTimeRange('yesterday')" class="btn btn-sm <?= $range === 'yesterday' ? 'btn-primary shadow-sm' : 'btn-outline-secondary' ?> fw-semibold px-3">Hôm qua</button>
                <button type="button" onclick="setTimeRange('7_days')" class="btn btn-sm <?= $range === '7_days' ? 'btn-primary shadow-sm' : 'btn-outline-secondary' ?> fw-semibold px-3">7 ngày qua</button>
                <button type="button" onclick="setTimeRange('30_days')" class="btn btn-sm <?= $range === '30_days' ? 'btn-primary shadow-sm' : 'btn-outline-secondary' ?> fw-semibold px-3">30 ngày qua</button>
                <button type="button" onclick="setTimeRange('this_month')" class="btn btn-sm <?= $range === 'this_month' ? 'btn-primary shadow-sm' : 'btn-outline-secondary' ?> fw-semibold px-3">Tháng này</button>
            </div>
        </div>

        <hr class="text-muted opacity-25 my-3">

        <div class="row g-3 align-items-end">
            <div class="col-md-2 col-sm-6" onchange="document.getElementById('range_hidden_input').value = 'custom';">
                <label class="form-label small fw-semibold text-muted">Từ ngày</label>
                <input type="date" name="from_date" class="form-control form-control-sm fw-medium" value="<?= $filter_from ?>">
            </div>
            <div class="col-md-2 col-sm-6" onchange="document.getElementById('range_hidden_input').value = 'custom';">
                <label class="form-label small fw-semibold text-muted">Đến ngày</label>
                <input type="date" name="to_date" class="form-control form-control-sm fw-medium" value="<?= $filter_to ?>">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label small fw-semibold text-muted">Tài khoản kênh</label>
                <select name="account_id" class="form-select form-select-sm fw-medium">
                    <option value="all" <?= $filter_account === 'all' ? 'selected' : '' ?>>🌐 Tất cả tài khoản</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc['account_id'] ?>" <?= (string)$filter_account === (string)$acc['account_id'] ? 'selected' : '' ?>>
                            🛍️ <?= htmlspecialchars($acc['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label small fw-semibold text-muted">Sản phẩm</label>
                <select name="product_id" class="form-select form-select-sm fw-medium">
                    <option value="all" <?= $filter_product === 'all' ? 'selected' : '' ?>>📦 Tất cả sản phẩm</option>
                    <?php foreach ($products_filter as $p): ?>
                        <option value="<?= $p['product_id'] ?>" <?= $filter_product === $p['product_id'] ? 'selected' : '' ?>>
                            [<?= $p['product_id'] ?>] <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 col-sm-12">
                <label class="form-label small fw-semibold text-muted">Thứ tự sắp xếp</label>
                <select name="sort" class="form-select form-select-sm fw-bold text-primary">
                    <option value="qty" <?= $filter_sort === 'qty' ? 'selected' : '' ?>>Theo Tổng số đơn</option>
                    <option value="date" <?= $filter_sort === 'date' ? 'selected' : '' ?>>Theo Mã sản phẩm</option>
                </select>
            </div>
            
            <div class="col-12 text-end mt-4">
                <button type="submit" class="btn btn-dark btn-sm px-4 fw-bold shadow-sm"><i class="bi bi-filter me-1"></i>ÁP DỤNG BỘ LỌC</button>
            </div>
        </div>
    </form>
</div>

<div class="dashboard-card border shadow-sm rounded-4 overflow-hidden mb-5">
    <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>Tổng hợp số bán theo khoảng thời gian</h6>
        <span class="badge bg-soft-primary text-primary font-monospace small">Kỳ báo cáo: <?= date('d/m/Y', strtotime($filter_from)) ?> - <?= date('d/m/Y', strtotime($filter_to)) ?></span>
    </div>
    <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
        <table class="table mb-0 table-hover align-middle">
            <thead class="table-dark sticky-top">
                <tr>
                    <?php if ($show_account_column): ?>
                        <th class="ps-3" style="width: 250px;">Tài Khoản Kênh</th>
                    <?php endif; ?>
                    <th class="<?= !$show_account_column ? 'ps-3' : '' ?>" style="width: 120px;">Mã SP</th>
                    <th>Tên Sản Phẩm</th>
                    <th class="text-center bg-dark text-white" style="width: 180px;">Tổng Đơn Trong Kỳ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales_logs)): ?>
                <tr>
                    <td colspan="<?= $show_account_column ? 4 : 3 ?>" class="text-center py-5 text-muted fw-medium">
                        <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                        Không có dữ liệu bán hàng phát sinh!
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($sales_logs as $log): ?>
                    <tr>
                        <?php if ($show_account_column): ?>
                            <td class="ps-3 fw-medium text-dark"><i class="bi bi-shop me-1 text-muted"></i><?= htmlspecialchars($log['account_name']) ?></td>
                        <?php endif; ?>
                        
                        <td class="<?= !$show_account_column ? 'ps-3' : '' ?> font-monospace fw-bold text-primary"><?= $log['product_id'] ?></td>
                        <td class="text-dark fw-medium small"><?= htmlspecialchars($log['product_name']) ?></td>
                        <td class="text-center fw-bold text-danger fs-6 bg-light font-monospace"><?= number_format($log['total_quantity']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            
            <?php if (!empty($sales_logs)): ?>
            <tfoot class="table-light border-top border-dark border-2 fw-bold">
                <tr>
                    <td colspan="<?= $show_account_column ? 3 : 2 ?>" class="text-end text-dark py-3 pe-4 fs-6">
                        <i class="bi bi-calculator me-1"></i>TỔNG SỐ ĐƠN TOÀN HỆ THỐNG GIAI ĐOẠN NÀY:
                    </td>
                    <td class="text-center text-danger fs-5 bg-dark text-white py-2 font-monospace">
                        <?= number_format($grand_total_units) ?>
                    </td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require 'includes/footer.php'; ?>