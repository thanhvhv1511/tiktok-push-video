<?php
session_start();
require 'core/db.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Mặc định chọn ngày hôm qua nếu không có tham số trên URL
$selected_date = $_GET['date'] ?? $yesterday;

// Lấy danh sách tài khoản kênh bán
$accounts = $pdo->query("SELECT * FROM accounts ORDER BY account_id ASC")->fetchAll();
$first_account_id = !empty($accounts) ? $accounts[0]['account_id'] : 0;
$selected_account = isset($_GET['account_id']) ? (int)$_GET['account_id'] : $first_account_id;

// Hứng thông báo từ Session để hiển thị alert và xóa ngay sau khi load
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// ---------------------------------------------------------
// XỬ LÝ LƯU ĐỒNG LOẠT (BULK UPDATE) KHI SUBMIT
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_update') {
    $sale_date  = $_POST['sale_date'];
    $account_id = (int)$_POST['account_id'];
    
    $names    = $_POST['name'] ?? [];
    $item_ids = $_POST['item_id'] ?? [];
    $qtys     = $_POST['sale_qty'] ?? [];
    $captions = $_POST['caption'] ?? [];

    $pdo->beginTransaction();
    try {
        foreach ($names as $pid => $name) {
            $item_id  = $item_ids[$pid] ?? '';
            $sale_qty = (int)($qtys[$pid] ?? 0);
            $caption  = $captions[$pid] ?? '';

            // 1. Cập nhật thông tin gốc sản phẩm
            $stmt = $pdo->prepare("UPDATE products SET name = ?, item_id = ?, caption = ? WHERE product_id = ?");
            $stmt->execute([$name, $item_id, $caption, $pid]);

            // 2. 👉 Xử lý ghi nhận số đơn (Loại bỏ rác data = 0)
            if ($sale_qty > 0) {
                // Nếu có số bán > 0 -> Lưu hoặc cập nhật
                $stmtSales = $pdo->prepare("INSERT INTO product_sales (product_id, account_id, sale_date, quantity) VALUES (?, ?, ?, ?) 
                                            ON DUPLICATE KEY UPDATE quantity = ?");
                $stmtSales->execute([$pid, $account_id, $sale_date, $sale_qty, $sale_qty]);
            } else {
                // Nếu số bán = 0 -> Xóa luôn record của ngày đó nếu có (Trường hợp lỡ nhập sai trước đó)
                $stmtDelete = $pdo->prepare("DELETE FROM product_sales WHERE product_id = ? AND account_id = ? AND sale_date = ?");
                $stmtDelete->execute([$pid, $account_id, $sale_date]);
            }

            // 3. Tính lại tổng lũy kế toàn hệ thống của mã này
            $stmtSum = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM product_sales WHERE product_id = ?");
            $stmtSum->execute([$pid]);
            $total_accumulated_sold = (int)$stmtSum->fetchColumn();

            // 4. Đồng bộ số tổng ngược lại bảng products
            $stmtUpdateTotal = $pdo->prepare("UPDATE products SET units_sold = ? WHERE product_id = ?");
            $stmtUpdateTotal->execute([$total_accumulated_sold, $pid]);
        }

        $pdo->commit();
        $_SESSION['success_msg'] = "✅ Đã lưu cập nhật dữ liệu thành công!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "❌ Thất bại: " . $e->getMessage();
    }

    // Cơ chế PRG chặn đứng hoàn toàn lỗi lặp biểu mẫu khi F5 trang
    header("Location: manage.php?date=" . $sale_date . "&account_id=" . $account_id);
    exit;
}

// ---------------------------------------------------------
// QUERY LẤY DATA: Sắp xếp bảng theo units_sold DESC (Lũy kế giảm dần)
// ---------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT p.*, COALESCE(s.quantity, 0) as target_date_account_qty
    FROM products p
    LEFT JOIN product_sales s ON p.product_id = s.product_id AND s.account_id = :account_id AND s.sale_date = :selected_date
    ORDER BY p.units_sold DESC
");
$stmt->execute(['account_id' => $selected_account, 'selected_date' => $selected_date]);
$products = $stmt->fetchAll();

// Thiết lập các biến môi trường cấu trúc cho file Layout chung
$page_title = "Quản Lý Data Đa Tài Khoản";
$active_tab = "manage";
require 'includes/header.php';
?>

<script>
    function reloadWithFilters() {
        const date = document.getElementById('global_date_picker').value;
        const account = document.getElementById('global_account_picker').value;
        location.href = 'manage.php?date=' + date + '&account_id=' + account;
    }
</script>

<?php if (!empty($success_msg)): ?><div class="alert alert-success fw-bold shadow-sm border-0"><i class="bi bi-check-circle me-2"></i><?= $success_msg ?></div><?php endif; ?>
<?php if (!empty($error_msg)): ?><div class="alert alert-danger fw-bold shadow-sm border-0"><i class="bi bi-exclamation-triangle me-2"></i><?= $error_msg ?></div><?php endif; ?>

<div class="dashboard-card mb-4 p-4 bg-white border shadow-sm rounded-4">
    <div class="row align-items-center g-3">
        <div class="col-lg-4">
            <h5 class="fw-bold text-dark mb-1"><i class="bi bi-person-gear text-primary me-2"></i>Nhập Liệu Hệ Thống Đa Kênh</h5>
            <p class="text-muted small mb-0">Bảng tính tự động sắp xếp theo tổng lũy kế doanh số giảm dần</p>
        </div>
        
        <div class="col-lg-8 text-lg-end">
            <div class="d-inline-flex flex-wrap align-items-center gap-2 bg-light p-2 rounded-3 border">
                <span class="small fw-semibold text-secondary ps-2">Tài khoản:</span>
                <select id="global_account_picker" class="form-select form-select-sm fw-bold text-dark" style="width: 220px;" onchange="reloadWithFilters()">
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc['account_id'] ?>" <?= $selected_account === (int)$acc['account_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($acc['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="vertical-divider d-none d-sm-block mx-1" style="border-left: 1px solid #cbd5e1; height: 24px;"></div>

                <span class="small fw-semibold text-secondary d-none d-sm-inline">Ngày nhập:</span>
                <a href="manage.php?date=<?= $today ?>&account_id=<?= $selected_account ?>" class="btn btn-sm <?= $selected_date === $today ? 'btn-primary' : 'btn-outline-secondary' ?> fw-medium">Hôm nay</a>
                <a href="manage.php?date=<?= $yesterday ?>&account_id=<?= $selected_account ?>" class="btn btn-sm <?= $selected_date === $yesterday ? 'btn-primary' : 'btn-outline-secondary' ?> fw-medium">Hôm qua</a>
                <input type="date" id="global_date_picker" class="form-control form-control-sm text-center fw-bold text-primary" style="width: 140px;" value="<?= $selected_date ?>" onchange="reloadWithFilters()">
            </div>
        </div>
    </div>
</div>

<form action="manage.php" method="POST">
    <input type="hidden" name="action" value="bulk_update">
    <input type="hidden" name="sale_date" value="<?= $selected_date ?>">
    <input type="hidden" name="account_id" value="<?= $selected_account ?>">

    <div class="dashboard-card border shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="table-responsive">
            <table class="table mb-0 table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 80px;" class="ps-3">Mã SP</th>
                        <th style="width: 160px;">Item ID (TikTok)</th>
                        <th style="width: 250px;">Tên Sản Phẩm</th>
                        <th style="width: 130px;" class="text-center bg-primary text-white">Đơn Kênh Ngày Chọn</th>
                        <th style="width: 120px;" class="text-center bg-secondary text-white">🔥 Tổng Lũy Kế (All Shop)</th>
                        <th>Caption sản phẩm</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $row): $pid = $row['product_id']; ?>
                    <tr>
                        <td class="fw-bold text-primary font-monospace ps-3"><?= $pid ?></td>
                        <td><input type="text" name="item_id[<?= $pid ?>]" class="form-control form-control-sm font-monospace text-secondary" value="<?= htmlspecialchars($row['item_id']) ?>"></td>
                        <td><input type="text" name="name[<?= $pid ?>]" class="form-control form-control-sm fw-medium" value="<?= htmlspecialchars($row['name']) ?>" required></td>
                        
                        <td class="bg-soft-primary">
                            <input type="number" name="sale_qty[<?= $pid ?>]" class="form-control form-control-sm text-center fw-bold text-danger fs-6 border-danger shadow-none" value="<?= $row['target_date_account_qty'] ?>" min="0" required>
                        </td>
                        
                        <td class="text-center fw-bold text-dark font-monospace bg-soft-secondary fs-6">
                            <?= number_format($row['units_sold']) ?>
                        </td>
                        <td><input type="text" name="caption[<?= $pid ?>]" class="form-control form-control-sm text-muted small" value="<?= htmlspecialchars($row['caption']) ?>"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="p-4 bg-light border-top d-flex justify-content-between align-items-center">
            <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Hệ thống sẽ không lưu các dòng có số lượng bằng 0 để tối ưu Database.</span>
            <button type="submit" class="btn btn-primary fw-bold px-5 shadow"><i class="bi bi-cloud-arrow-up-fill me-2"></i>LƯU TOÀN BỘ DỮ LIỆU</button>
        </div>
    </div>
</form>

<?php require 'includes/footer.php'; ?>