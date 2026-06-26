const fs = require('fs');
const path = require('path');

// =========================================================================
// 🎛️ KHU VỰC CẤU HÌNH CỦA BẠN (CÀI ĐẶT TẠI ĐÂY)
// =========================================================================
const VIDEO_DIR = './';                // Quét từ thư mục hiện tại
const CSV_FILE = 'database.csv';
const OUTPUT_FILE = 'schedule_plan.csv';

const IS_RANDOM = false;                // false = đăng chuẩn theo thứ tự Priority, trùng Priority thì xếp theo tên file A-Z
const START_TIME_STR = "2026-06-14 08:00"; 
const INTERVAL_MINUTES = 30;           

// =========================================================================
// 🚀 LOGIC XỬ LÝ HỆ THỐNG
// =========================================================================

function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

if (!fs.existsSync(CSV_FILE)) {
    console.error(`❌ Không tìm thấy file gốc ${CSV_FILE}. Hãy tạo file trước!`);
    process.exit(1);
}

const dbContent = fs.readFileSync(CSV_FILE, 'utf-8').split(/\r?\n/);
const db = {};

dbContent.forEach((line, index) => {
    if (index === 0 || !line.trim()) return;
    
    // Regex thông minh: Tách dấu phẩy nhưng né dấu phẩy trong ngoặc kép ""
    const columns = line.match(/(".*?"|[^",]+)(?=\s*,|\s*$)/g);
    
    if (columns && columns.length >= 4) {
        const priority = parseInt(columns[0].replace(/^"|"$/g, '').trim(), 10); // Cột Priority (Số)
        const sku = columns[1].replace(/^"|"$/g, '').trim();                  // Cột SKU
        const name = columns[2].replace(/^"|"$/g, '').trim();                 // Cột Product_Name
        const caption = columns[3] ? columns[3].replace(/^"|"$/g, '').trim() : "";
        
        db[sku] = { priority, name, caption };
    }
});

// 2. Quét sâu toàn bộ file video .mp4 từ các thư mục con
let allVideos = [];
const items = fs.readdirSync(VIDEO_DIR);

items.forEach(item => {
    const itemPath = path.join(VIDEO_DIR, item);
    if (fs.statSync(itemPath).isDirectory()) {
        const vids = fs.readdirSync(itemPath).filter(f => f.toLowerCase().endsWith('.mp4'));
        vids.forEach(v => {
            allVideos.push({
                fileName: v,
                fullPath: path.join(itemPath, v),
                folderName: item
            });
        });
    }
});

if (allVideos.length === 0) {
    console.log('⚠️ Không tìm thấy bất kỳ file video .mp4 nào trong các thư mục con!');
    process.exit(0);
}

// 3. Áp dụng cờ RANDOM hoặc Sắp xếp theo cột Priority trong CSV
if (IS_RANDOM) {
    console.log('🎲 [CHẾ ĐỘ]: RANDOM - Đang trộn ngẫu nhiên toàn bộ danh sách video...');
    allVideos = shuffleArray(allVideos);
} else {
    console.log('📈 [CHẾ ĐỘ]: TUẦN TỰ - Sắp xếp theo cột Priority trong CSV, sau đó theo tên file (A-Z)...');
    allVideos.sort((a, b) => {
        // Trích xuất SKU từ tên file để dò tìm Priority
        const skuA = a.fileName.split('-')[0].trim();
        const skuB = b.fileName.split('-')[0].trim();
        
        // Lấy Priority từ DB, nếu không tìm thấy SKU thì mặc định đẩy xuống cuối cùng (Infinity)
        const priorityA = db[skuA] ? db[skuA].priority : Infinity;
        const priorityB = db[skuB] ? db[skuB].priority : Infinity;
        
        // Bước 1: So sánh theo Priority trong file CSV
        if (priorityA !== priorityB) {
            return priorityA - priorityB;
        }
        
        // Bước 2: Nếu cùng một sản phẩm (trùng Priority), sắp xếp theo tên file A-Z (0001 -> 0002)
        return a.fileName.localeCompare(b.fileName);
    });
}

// 4. Thiết lập mốc thời gian bắt đầu tính toán
const baseTime = new Date(START_TIME_STR.replace(/-/g, '/')); 
if (isNaN(baseTime.getTime())) {
    console.error('❌ Định dạng START_TIME_STR sai! Vui lòng để dạng "YYYY-MM-DD HH:mm"');
    process.exit(1);
}

// 5. Khởi tạo cấu trúc file CSV kế hoạch
let csvOutput = "Video_Path,Video_Name,Product_Name,Caption,Scheduled_Time,Status\n";

allVideos.forEach((vid, index) => {
    const sku = vid.fileName.split('-')[0].trim();
    const info = db[sku] || { name: "N/A (Không tìm thấy SKU)", caption: "N/A" };
    
    const scheduleTime = new Date(baseTime.getTime() + index * INTERVAL_MINUTES * 60 * 1000);
    
    const yyyy = scheduleTime.getFullYear();
    const mm = String(scheduleTime.getMonth() + 1).padStart(2, '0');
    const dd = String(scheduleTime.getDate()).padStart(2, '0');
    const hh = String(scheduleTime.getHours()).padStart(2, '0');
    const min = String(scheduleTime.getMinutes()).padStart(2, '0');
    const formattedTime = `${yyyy}-${mm}-${dd} ${hh}:${min}`;

    csvOutput += `"${vid.fullPath}","${vid.fileName}","${info.name}","${info.caption}","${formattedTime}",PENDING\n`;
});

// 6. Xuất file kế hoạch
fs.writeFileSync(OUTPUT_FILE, '\uFEFF' + csvOutput, 'utf-8');

console.log(`--------------------------------------------------------`);
console.log(`🎉 HOÀN THÀNH KẾ HOẠCH LÊN LỊCH THEO PRIORITY!`);
console.log(`📍 File kết quả: ${OUTPUT_FILE}`);
console.log(`📊 Tổng số video ghi nhận: ${allVideos.length} file.`);
console.log(`📅 Thời gian bắt đầu: ${START_TIME_STR} (Mỗi video cách nhau ${INTERVAL_MINUTES} phút)`);