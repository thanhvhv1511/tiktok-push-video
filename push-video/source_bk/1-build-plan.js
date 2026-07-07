const fs = require('fs');
const path = require('path');

// --- ĐƯỜNG DẪN ĐẾN FILE CẤU HÌNH CỦA FRONTEND ---
const CONFIG_PATH = '/Users/thanhvhv/personal/tool/site/push-video/config.json';

// BẪY LỖI 1: Kiểm tra xem Frontend đã tạo file cấu hình chưa
if (!fs.existsSync(CONFIG_PATH)) {
    console.error(`❌ Error: Không tìm thấy file cấu hình hệ thống tại: ${CONFIG_PATH}`);
    process.exit(1);
}

// Đọc cấu hình động từ file JSON công cộng
const config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf-8'));

// ⚡ ĐÃ UPDATE: Đồng bộ key cấu hình tường minh mới
const VIDEO_DIR = config.PUSH_VIDEO_DIR || './videos';
const CSV_FILE = config.DATABASE_CSV || 'database.csv';
const OUTPUT_DIR = config.OUTPUT_PLAN_DIR || __dirname; 
const IS_RANDOM = config.IS_RANDOM ?? false;
const START_TIME_STR = config.START_TIME_STR; 
const INTERVAL_MINUTES = parseInt(config.INTERVAL_MINUTES, 10) || 45;

// MẢNG TÀI KHOẢN ĐĂNG VIDEO (Bây giờ nhận mảng Object chứa name và port)
const ACCOUNTS = config.ACCOUNTS || [{ "name": "Account_01", "port": 9222 }];

function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

function buildPlan() {
    // BẪY LỖI 2: Kiểm tra file database dữ liệu sản phẩm
    if (!fs.existsSync(CSV_FILE)) {
        throw new Error(`Không tìm thấy file dữ liệu gốc ${CSV_FILE}! Hãy kiểm tra lại.`);
    }

    // BẪY LỖI 3: Kiểm tra định dạng thời gian từ Frontend truyền qua
    const baseTime = new Date(START_TIME_STR.replace(/-/g, '/'));
    if (isNaN(baseTime.getTime())) {
        throw new Error(`Định dạng thời gian START_TIME_STR ("${START_TIME_STR}") do Frontend truyền sang bị SAI! Vui lòng nhập đúng dạng YYYY-MM-DD HH:mm`);
    }

    // Tự động tạo thư mục plans nếu chưa có để tránh lỗi ENOENT khi ghi file
    if (!fs.existsSync(OUTPUT_DIR)) {
        fs.mkdirSync(OUTPUT_DIR, { recursive: true });
    }

    const dbContent = fs.readFileSync(CSV_FILE, 'utf-8').split(/\r?\n/);
    const db = {};

    dbContent.forEach((line, index) => {
        if (index === 0 || !line.trim()) return;
        const columns = line.match(/(".*?"|[^",]+)(?=\s*,|\s*$)/g);
        if (columns && columns.length >= 4) {
            const priority = parseInt(columns[0].replace(/^"|"$/g, '').trim(), 10);
            const sku = columns[1].replace(/^"|"$/g, '').trim();
            const name = columns[2].replace(/^"|"$/g, '').trim();
            const caption = columns[3] ? columns[3].replace(/^"|"$/g, '').trim() : "";
            db[sku] = { priority, name, caption };
        }
    });

    let allVideos = [];
    if (!fs.existsSync(VIDEO_DIR)) {
        throw new Error(`Thư mục chứa video gốc không tồn tại: ${VIDEO_DIR}`);
    }

    const items = fs.readdirSync(VIDEO_DIR);
    items.forEach(item => {
        const itemPath = path.join(VIDEO_DIR, item);
        const stats = fs.statSync(itemPath);

        if (stats.isFile() && item.toLowerCase().match(/\.(mp4|mov|mkv)$/)) {
            allVideos.push({ fileName: item, fullPath: path.resolve(itemPath) });
        } 
        else if (stats.isDirectory()) {
            const vids = fs.readdirSync(itemPath).filter(f => f.toLowerCase().match(/\.(mp4|mov|mkv)$/));
            vids.forEach(v => {
                allVideos.push({ fileName: v, fullPath: path.resolve(itemPath, v) });
            });
        }
    });

    if (allVideos.length === 0) {
        throw new Error(`Thư mục ${VIDEO_DIR} trống. Không tìm thấy bất kỳ video sản phẩm nào để lên lịch!`);
    }

    if (IS_RANDOM) {
        allVideos = shuffleArray(allVideos);
    } else {
        allVideos.sort((a, b) => {
            const skuA = a.fileName.split('-')[0].trim();
            const skuB = b.fileName.split('-')[0].trim();
            const pA = db[skuA] ? db[skuA].priority : Infinity;
            const pB = db[skuB] ? db[skuB].priority : Infinity;
            if (pA !== pB) return pA - pB;
            return a.fileName.localeCompare(b.fileName);
        });
    }

    // Duyệt qua từng Account để xuất các file CSV độc lập
    ACCOUNTS.forEach(acc => {
        // ⚡ ĐÃ SỬA: Lấy accName từ thuộc tính .name của Object acc (phòng hờ nếu config cũ truyền chuỗi thì vẫn chạy được)
        const accName = typeof acc === 'object' ? acc.name : acc;

        // Giữ nguyên cấu trúc 6 cột ban đầu để tool Python/Bot đọc mượt mà
        let csvOutput = "Video_Path,Video_Name,Product_Name,Caption,Scheduled_Time,Status\n";

        allVideos.forEach((vid, index) => {
            const sku = vid.fileName.split('-')[0].trim();
            const info = db[sku] || { name: "", caption: "Sản phẩm thời trang #xuhuong" };
            const scheduleTime = new Date(baseTime.getTime() + index * INTERVAL_MINUTES * 60 * 1000);
            
            const yyyy = scheduleTime.getFullYear();
            const mm = String(scheduleTime.getMonth() + 1).padStart(2, '0');
            const dd = String(scheduleTime.getDate()).padStart(2, '0');
            const hh = String(scheduleTime.getHours()).padStart(2, '0');
            const min = String(scheduleTime.getMinutes()).padStart(2, '0');
            
            csvOutput += `"${vid.fullPath}","${vid.fileName}","${info.name}","${info.caption}","${yyyy}/${mm}/${dd} ${hh}:${min}",PENDING\n`;
        });

        // ⚡ ĐÃ SỬA: Dùng accName (chuỗi tên sạch) để tạo tên file phân loại
        const safeAccName = accName.replace(/[^a-zA-Z0-9]/g, '_');
        const finalOutputFile = path.join(OUTPUT_DIR, `schedule_plan_${safeAccName}.csv`);

        fs.writeFileSync(finalOutputFile, '\uFEFF' + csvOutput, 'utf-8');
        console.log(`🎉 Đã tạo Plan riêng cho [${accName}]: ${finalOutputFile}`);
    });
}

try {
    buildPlan();
} catch (error) {
    console.error(`❌ TIẾN TRÌNH LẬP LỊCH BỊ LỖI: ${error.message}`);
    process.exit(1); 
}