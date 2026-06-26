<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : 'Auto Push Manager' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=1.0">
    <?php if (isset($extra_head)) echo $extra_head; ?>
</head>
<body class="py-4">

<div class="container-fluid px-4 px-lg-5 max-w-7xl mx-auto">
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded-4 shadow-sm border" style="border-color: #e2e8f0 !important;">
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'index' ? 'active shadow-sm' : 'text-secondary' ?> fw-bold px-4" href="index.php">
                <i class="bi bi-calendar-plus me-2"></i>Tạo Lịch Đăng
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'manage' ? 'active shadow-sm' : 'text-secondary' ?> fw-bold px-4" href="manage.php">
                <i class="bi bi-database-gear me-2"></i>Quản Lý Data
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'analytics' ? 'active shadow-sm' : 'text-secondary' ?> fw-bold px-4" href="analytics.php">
                <i class="bi bi-bar-chart-line me-2"></i>Phân Tích
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'sales_report' ? 'active shadow-sm' : 'text-secondary' ?> fw-bold px-4" href="sales_report.php">
                <i class="bi bi-journal-text me-2"></i>Báo Cáo Số Bán
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'pre_process' ? 'active shadow-sm' : 'text-secondary' ?> fw-bold px-4" href="pre_process.php">
                <i class="bi bi-cpu me-2"></i>Xưởng Render
            </a>   
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'publish_history' ? 'active shadow-sm' : 'text-secondary' ?> fw-bold px-4" href="publish_history.php">
                <i class="bi bi-clock-history me-2"></i>Lịch Sử Đăng
            </a>    
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'publish' ? 'active shadow-sm' : 'text-secondary' ?> fw-bold px-4" href="publish.php">
                <i class="bi bi-clock-history me-2"></i>Push
            </a>    
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'logs-push' ? 'active shadow-sm' : 'text-secondary' ?> fw-bold px-4" href="logs-push.php">
                <i class="bi bi-clock-history me-2"></i>Log Push
            </a>    
        </li>
    </ul>