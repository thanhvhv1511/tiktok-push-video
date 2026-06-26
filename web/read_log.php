<?php
// /Users/thanhvhv/personal/tool/site/web/read_log.php

// ⚡ BÍ KÍP CHỐNG TREO: Ép trình duyệt và Web Server xóa sạch bộ đệm cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ⚡ Xóa bộ đệm trạng thái file của hệ điều hành Mac trước khi đọc dữ liệu mới
clearstatcache();

// 1. Hứng mã sản phẩm (pid) từ hàm fetchLogs() của file JS gửi lên
$pid = $_GET['pid'] ?? '';

if (empty($pid)) {
    echo "⚠️ Lỗi hệ thống: Không nhận diện được mã luồng Render.";
    exit;
}

// 2. Bảo mật cơ bản: Dùng basename() để làm sạch chuỗi, chống lỗi Path Traversal
$safe_pid = basename($pid);

// 3. Trỏ tới thư mục data/ nằm ngang hàng với thư mục web/ (hoặc theo cấu trúc của anh)
$log_file = __DIR__ . "/../data/process_{$safe_pid}.log";

if (file_exists($log_file)) {
    echo file_get_contents($log_file);
} else {
    echo "⏳ Đang chuẩn bị phân luồng dữ liệu cho mã {$safe_pid}, anh đợi chút nhé...";
}
?>