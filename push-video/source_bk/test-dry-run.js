require('dotenv').config(); 
const fs = require('fs');
const path = require('path');
const { exec } = require('child_process');

// ⚡ LẤY CẤU HÌNH TỪ ENV
const PHP_PATH = process.env.PHP_PATH || 'php';

// Nhận tham số từ PHP
const ACCOUNT_ARG = process.argv[2];
const BATCH_ARG = process.argv[3];
const PORT_ARG = process.argv[4]; 
const ACCOUNT_ID_ARG = process.argv[5]; 

if (!ACCOUNT_ARG || !BATCH_ARG || !PORT_ARG || !ACCOUNT_ID_ARG) {
    console.error('❌ Thiếu tham số vận hành!');
    process.exit(1);
}

// ⚡ ĐƯỜNG DẪN CHUẨN: Trỏ từ site/push-video sang site/web/plans
let OUTPUT_DIR = path.join(__dirname, '..', 'web', 'plans');

const CONFIG_PATH = path.join(__dirname, 'config.json');
if (fs.existsSync(CONFIG_PATH)) {
    const config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf-8'));
    OUTPUT_DIR = config.OUTPUT_PLAN_DIR || OUTPUT_DIR;
}

const safeAccName = ACCOUNT_ARG.replace(/[^a-zA-Z0-9]/g, '_');
const PLAN_FILE = path.join(OUTPUT_DIR, `schedule_plan_${safeAccName}_${BATCH_ARG}.csv`);

console.log(`==================================================`);
console.log(`🧪 [DRY RUN MODE] BẬT CHẾ ĐỘ GIẢ LẬP KHÔNG MỞ CHROME`);
console.log(`==================================================`);
console.log(`🤖 Tài khoản: [${ACCOUNT_ARG}]`);
console.log(`📦 Batch ID: [${BATCH_ARG}]`);
console.log(`📂 Thư mục chứa Plan: ${OUTPUT_DIR}`);
console.log(`📂 File Plan: ${PLAN_FILE}\n`);

function loadTasks() {
    if (!fs.existsSync(PLAN_FILE)) {
        console.error(`❌ Không tìm thấy file kế hoạch CSV tại: ${PLAN_FILE}`);
        return [];
    }
    const content = fs.readFileSync(PLAN_FILE, 'utf-8').split(/\r?\n/);
    const tasks = [];
    content.forEach((line, i) => {
        if (i === 0 || !line.trim()) return;
        const cols = line.match(/(".*?"|[^",]+)(?=\s*,|\s*$)/g);
        if (cols && cols.length >= 7) {
            tasks.push({
                path: cols[0].replace(/"/g, ''),
                fileName: cols[1].replace(/"/g, ''),
                product: cols[2].replace(/"/g, ''),
                caption: cols[3].replace(/"/g, ''),
                schedule: cols[4].replace(/"/g, ''),
                status: cols[5].replace(/"/g, ''),
                videoId: cols[6].replace(/"/g, '')
            });
        }
    });
    return tasks;
}

function updateTaskStatus(fileName, newStatus) {
    if (!fs.existsSync(PLAN_FILE)) return;
    const lines = fs.readFileSync(PLAN_FILE, 'utf-8').split(/\r?\n/);
    const updatedLines = [];
    updatedLines.push(lines[0]);

    for (let i = 1; i < lines.length; i++) {
        const line = lines[i];
        if (!line.trim()) continue;
        const cols = line.match(/(".*?"|[^",]+)(?=\s*,|\s*$)/g);
        if (cols && cols.length >= 7) {
            const currentFileName = cols[1].replace(/"/g, '');
            if (currentFileName === fileName) {
                cols[5] = `"${newStatus}"`;
                updatedLines.push(cols.join(','));
            } else {
                updatedLines.push(line);
            }
        } else {
            updatedLines.push(line);
        }
    }
    fs.writeFileSync(PLAN_FILE, '\uFEFF' + updatedLines.join('\n'), 'utf-8');
}

function syncDatabaseStatus(videoId, status, errorMsg = '') {
    const phpScript = path.join(__dirname, '..', 'web' ,'core', 'cli_update_status.php');
    const safeErrorMsg = errorMsg.replace(/"/g, '\\"').replace(/\n/g, ' ');
    
    const cmd = `export PATH=$PATH:/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin && "${PHP_PATH}" "${phpScript}" ${videoId} ${ACCOUNT_ID_ARG} ${status} "${safeErrorMsg}"`;
    
    // ⚡ ÉP IN LOG ĐỂ THEO DÕI BIẾN CỐ
    console.log(`🚀 [Exec CMD]: ${cmd}`); 

    exec(cmd, (err, stdout, stderr) => {
        if (err) {
            console.log(`❌ [Lỗi Đồng Bộ DB - Hệ Thống]: ${err.message}`);
            return;
        }
        if (stderr) {
            console.log(`❌ [Lỗi Đồng Bộ DB - Thực Thi]: ${stderr}`);
            return;
        }
        // Hiển thị kết quả mà file PHP in ra (Ví dụ: "⚙️ [CLI DB SUCCESS]...")
        console.log(`✨ [PHP Output]: ${stdout.trim()}`); 
    });
}

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

(async () => {
    const tasks = loadTasks().filter(t => t.status === 'PENDING');
    if (tasks.length === 0) return console.log(`✅ Sạch lịch! Không có video nào ở trạng thái PENDING để chạy.`);

    console.log(`🌐 [Giả lập] Đang kết nối mạng và chuẩn bị dữ liệu cho ${tasks.length} video...`);
    await delay(2000); 

    // VÒNG LẶP XỬ LÝ GIẢ LẬP
    for (let i = 0; i < tasks.length; i++) {
        const task = tasks[i];
        console.log(`\n--------------------------------------------------`);
        console.log(`▶️ ĐANG XỬ LÝ [${i + 1}/${tasks.length}]: ${task.fileName}`);
        
        try {
            // ⚡ BÁO PROCESSING VỀ DB ĐỂ HIỂN THỊ ICON QUAY QUAY TRÊN WEB
            syncDatabaseStatus(task.videoId, 'PROCESSING');
            
            // Giả lập thời gian vào trang TikTok Studio
            console.log('🔘 [Phase 1] (Giả lập) Đang ném file video...');
            await delay(3000); 

            // Giả lập nhập Caption
            console.log('⌨️  [Phase 2] (Giả lập) Đang nhập Caption...');
            if (task.caption && task.caption.trim() !== '') {
                await delay(2000); 
            }

            // Giả lập gắn link sản phẩm
            if (task.product && task.product !== 'N/A' && task.product.trim() !== '') {
                console.log(`🛒 [Phase 2.5] (Giả lập) Đang gắn sản phẩm: "${task.product}"...`);
                await delay(3000); 
            }

            // Giả lập cấu hình thời gian
            console.log(`🗓️ [Phase 3] (Giả lập) Đang cấu hình ngày giờ: ${task.schedule}...`);
            await delay(2000);

            // ==========================================
            // 🎲 GIẢ LẬP LỖI RANDOM (Tỷ lệ 10% sẽ báo lỗi)
            // ==========================================
            const isError = Math.random() < 0.1; 
            if (isError) {
                throw new Error('Giả lập Lỗi Mạng: Không thể click nút Lên Lịch (Timeout 15000ms)');
            }

            console.log(`✅ [SUCCESS] (Giả lập) Lên lịch thành công!`);
            updateTaskStatus(task.fileName, 'SUCCESS');
            syncDatabaseStatus(task.videoId, 'SUCCESS');
            
            await delay(2000);

        } catch (error) {
            console.error(`❌ [ERROR] (Giả lập): ${error.message}`);
            updateTaskStatus(task.fileName, 'ERROR');
            syncDatabaseStatus(task.videoId, 'ERROR', error.message);
            await delay(2000);
        }
    }

    console.log(`\n🎉 HOÀN TẤT LUỒNG CHẠY GIẢ LẬP CỦA [${ACCOUNT_ARG}]!`);
    process.exit(0);
})();