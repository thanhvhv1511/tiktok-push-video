// /Users/thanhvhv/personal/tool/site/test-node.js
const fs = require('fs');
const path = require('path');

// Cấu hình các đường dẫn hệ thống tuyệt đối mới nhất của bạn
const LOG_PATH = "/Users/thanhvhv/personal/tool/site/process.log";
const SCHEDULE_JSON = "/Users/thanhvhv/personal/tool/site/schedule.json";
const TARGET_DIR = "/Users/thanhvhv/personal/tool/site/videos-push";
const BASE_SOURCE_DIR = "/Users/thanhvhv/personal/tool/site/videos";

// Hàm ghi log live đẩy thẳng xuống ổ cứng để Web PHP đọc luôn
function logLive(message) {
    fs.appendFileSync(LOG_PATH, message + "\n");
    console.log(message);
}

// Khởi tạo file log mới
fs.writeFileSync(LOG_PATH, "--- 🚀 BẮT ĐẦU BƯỚC 1: SÀNG LỌC & GOM VIDEOS ---\n");

if (!fs.existsSync(SCHEDULE_JSON)) {
    logLive("❌ Lỗi: Không tìm thấy file lịch trình schedule.json!");
    process.exit(1);
}

try {
    const rawData = fs.readFileSync(SCHEDULE_JSON, 'utf8');
    const scheduleList = JSON.parse(rawData);

    logLive(`📂 Đã đọc lịch trình. Tìm thấy ${scheduleList.length} video cần xử lý.`);

    // Đảm bảo thư mục đích videos-push tồn tại độc lập
    if (!fs.existsSync(TARGET_DIR)) {
        fs.mkdirSync(TARGET_DIR, { recursive: true });
    }

    scheduleList.forEach((item, index) => {
        // 👉 BÍ KÍP: Lấy duy nhất tên file gốc (Ví dụ: nếu là "sp002/sp002-0010.mp4" thì chỉ lấy "sp002-0010.mp4")
        const pureVideoName = path.basename(item.file_name); 
        const productId = item.product_id;  // Ví dụ: sp002
        
        // Đường dẫn nguồn chuẩn: /site/videos/[productId]/[pureVideoName]
        const sourceFile = path.join(BASE_SOURCE_DIR, productId, pureVideoName);
        
        // Đường dẫn đích phẳng: /site/videos-push/[pureVideoName]
        const targetFile = path.join(TARGET_DIR, pureVideoName);

        logLive(`⏳ [${index + 1}/${scheduleList.length}] Đang gom video sản phẩm: ${productId} | File: ${pureVideoName}...`);

        if (fs.existsSync(sourceFile)) {
            fs.copyFileSync(sourceFile, targetFile);
            logLive(`   ✅ Thành công -> Đã chuyển ra /site/videos-push/${pureVideoName}`);
        } else {
            logLive(`   ❌ Thất bại -> Không tìm thấy file gốc tại: ${sourceFile}`);
        }
    });

    logLive("\n✅ HOÀN TẤT TIẾN TRÌNH GOM VIDEOS PHẲNG!");
    process.exit(0);

} catch (error) {
    logLive(`❌ Có lỗi hệ thống xảy ra: ${error.message}`);
    process.exit(1);
}