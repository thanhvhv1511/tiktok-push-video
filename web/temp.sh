#!/bin/bash

echo "🚀 Bắt đầu quy hoạch lại cấu hình thư mục dự án..."

# 1. Tạo các thư mục cấu trúc mới nếu chưa có
mkdir -p assets includes core storage

echo "📁 Đang tạo cấu trúc thư mục mới..."

# 2. Di chuyển các tài nguyên tĩnh (CSS, JS)
[ -f "style.css" ] && mv style.css assets/
[ -f "script.js" ] && mv script.js assets/

# 3. Di chuyển các thành phần layout dùng chung
[ -f "header.php" ] && mv header.php includes/
[ -f "footer.php" ] && mv footer.php includes/
[ -f "modals.php" ] && mv modals.php includes/

# 4. Di chuyển lõi xử lý logic & cấu hình database
[ -f "db.php" ] && mv db.php core/
[ -f "ajax_memory.php" ] && mv ajax_memory.php core/
[ -f "process.php" ] && mv process.php core/

# 5. Di chuyển dữ liệu lưu trữ
[ -d "data" ] && mv data storage/
[ -f "schedule.json" ] && mv schedule.json storage/
# Lưu ý: Thư mục videos đang mount docker thì giữ nguyên hoặc chuyển tùy cấu hình docker của bạn.
# Ở đây tạm thời giữ nguyên videos ở root để tránh lệch volume docker mount.

echo "🔄 Đang cập nhật lại đường dẫn (Path) bên trong các file PHP/JS..."

# Sửa đường dẫn kết nối DB trong các file lõi và file giao diện chính
# Chuyển require 'db.php' hoặc include 'db.php' sang vị trí mới
sed -i '' "s|require 'db.php'|require 'core/db.php'|g" *.php core/*.php 2>/dev/null

# Sửa đường dẫn gọi header, footer, modals trong các file giao diện
sed -i '' "s|require 'header.php'|require 'includes/header.php'|g" *.php 2>/dev/null
sed -i '' "s|require 'footer.php'|require 'includes/footer.php'|g" *.php 2>/dev/null
sed -i '' "s|require 'modals.php'|require 'includes/modals.php'|g" *.php 2>/dev/null

# Sửa đường dẫn gọi file css, js trong index.php và includes/header.php/footer.php
sed -i '' 's|href="style.css"|href="assets/style.css"|g' index.php includes/*.php 2>/dev/null
sed -i '' 's|src="script.js"|src="assets/script.js"|g' index.php includes/*.php 2>/dev/null

# Sửa endpoint gọi API ajax_memory.php trong file assets/script.js
sed -i '' "s|'ajax_memory.php'|'core/ajax_memory.php'|g" assets/script.js 2>/dev/null

echo "✨ Hoàn thành dọn dẹp! Thư mục của bạn bây giờ đã sạch sẽ gọn gàng."
ls -lh
