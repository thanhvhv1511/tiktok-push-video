const fs = require('fs');
const path = require('path');

const csvPath = path.resolve(__dirname, 'database.csv');

// File mẫu chuẩn mã hóa UTF-8, các cột phân tách bằng dấu phẩy
const csvContent = '\uFEFF' + [
    'Priority,SKU,Product_Name,Caption',
    '1,sp001,( có túi ở lớp quần trong )Bộ tập gym yoga aerobic set chạy bộ đồ bơi áo cộ quần 2 lớp Sport Top Tập Thể Dục,Mẫu đồ tập gym yoga siêu tôn dáng ôm form cực chuẩn cho các nàng đây ạ #xuhuong #thoitrangnu',
    '2,sp002,Áo thun polo nam phối khóa,Áo thun polo nam phối khóa cổ hè cực mát chất thun co giãn thoải mái #polonam #thoitrangnam',
    '3,sp003,HL41-Set Đồ Nữ Cami Áo 2 Dây [ CÓ MÚT VÀ KHÔNG MÚT ] + Quần Short Kẻ Sọc Mềm \\,Thoáng Mát – Đồ Bộ Mặc Nhà,Set áo hai dây kẻ sọc mặc nhà hay đi dạo phố đều xinh xỉu up xỉu down #vayhe #setdonu'
].join('\n');

fs.writeFileSync(csvPath, csvContent, 'utf-8');
console.log('✅ Đã tạo file database.csv theo cấu trúc SKU mới!');