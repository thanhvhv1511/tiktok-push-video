const fs = require('fs');
const path = require('path');
require('dotenv').config({ path: path.join(__dirname, '../.env') });
const { execSync, spawn } = require('child_process'); 
const TELE_TOKEN = process.env.TELE_BOT_TOKEN;
const TELE_CHAT_ID = process.env.TELE_CHAT_ID;

// 👉 BÍ KÍP DEVOPS: Ép nạp PATH hệ thống của Mac nếu bị Python làm rụng khi chạy ngầm
process.env.PATH = process.env.PATH || '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin';

const CORE_DIR = __dirname;
const SITE_DIR = path.dirname(CORE_DIR);

const LOG_PATH = process.env.LOG_FILE_PATH || path.join(SITE_DIR, "data", "process.log");
const CONFIG_PATH = process.env.CONFIG_JSON_PATH || path.join(SITE_DIR, "data", "config.json");
const PUSH_DIR = process.env.PUSH_DIR_PATH || path.join(SITE_DIR, "media", "videos-push");

const BASE_SOURCE_DIR = process.env.VIDEO_DIR || path.join(SITE_DIR, "media", "videos");
const PROCESSED_DIR = process.env.PROCESSED_DIR || path.join(SITE_DIR, "push-video", "processed");

const TOOL_DIR = process.env.TOOL_DIR_PATH || path.join(SITE_DIR, "edit-video");
const FINAL_PUSH_TARGET = process.env.FINAL_PUSH_PATH || path.join(SITE_DIR, "push-video", "videos");

// ⚡ BÍ KÍP DIỆT ZOMBIE: Biến lưu trữ tiến trình con đang chạy
let activeChildProcess = null;
const FLAG_FILE = path.join(SITE_DIR, "data", "stop_render.flag");

function logLive(message) {
    fs.appendFileSync(LOG_PATH, message + "\n");
    console.log(message);
}

function sendTelegramNotification(message) {
    // Kiểm tra nếu chưa cấu hình thì không gửi để tránh lỗi
    if (!TELE_TOKEN || !TELE_CHAT_ID) {
        logLive("⚠️ [TELEGRAM] Thiếu cấu hình BOT_TOKEN hoặc CHAT_ID trong .env, bỏ qua thông báo.");
        return;
    }

    const https = require('https');
    const encodedMsg = encodeURIComponent(message);
    const url = `https://api.telegram.org/bot${TELE_TOKEN}/sendMessage?chat_id=${TELE_CHAT_ID}&text=${encodedMsg}`;
    
    https.get(url, (res) => {
        logLive("📱 [TELEGRAM] Thông báo đã được gửi đi.");
    }).on('error', (e) => {
        logLive(`❌ [TELEGRAM] Gửi lỗi: ${e.message}`);
    });
}

setInterval(() => {
    if (fs.existsSync(FLAG_FILE)) {
        logLive("\n🛑 [WATCHDOG NODE.JS] Nhận được cờ HỦY từ Web! Đang tự sát và kéo theo toàn bộ tiến trình con...");
        
        // 1. Dọn dẹp cờ
        try { fs.unlinkSync(FLAG_FILE); } catch(e) {}
        
        // 2. Chém chết tiến trình con đang chạy dở (FFmpeg/Python)
        if (activeChildProcess) {
            try { activeChildProcess.kill('SIGKILL'); } catch(e) {}
        }
        
        // 3. Quét rác diện rộng đảm bảo không sót mống nào trên Mac
        try { execSync('pkill -9 -f auto_render.py', { stdio: 'ignore' }); } catch(e) {}
        try { execSync('killall -9 ffmpeg', { stdio: 'ignore' }); } catch(e) {}
        
        // 4. Khạc ra câu chốt này để Frontend của anh tự động đóng Modal (Khớp với bẫy trong file JS)
        logLive("🛑 [HỆ THỐNG] Đã hủy tiến trình vật lý thành công");
        
        // 5. Tự sát luồng chính
        setTimeout(() => process.exit(1), 500);
    }
}, 1000);


/**
 * ⚡ HÀM HELPER ĐIỀU PHỐI ĐỘC LẬP
 */
function runCommandLive(command, args, options) {
    return new Promise((resolve, reject) => {
        const extendedEnv = { ...process.env, ...options.env, PYTHONUNBUFFERED: "1" };
        
        const child = spawn(command, args, {
            cwd: options.cwd,
            env: extendedEnv
        });

        // ⚡ Nắm đầu tiến trình con gán vào biến toàn cục để Watchdog canh gác
        activeChildProcess = child;

        const handleLogStream = (data) => {
            const lines = data.toString().split('\n');
            lines.forEach(line => {
                if (line.trim()) logLive(line);
            });
        };

        child.stdout.on('data', handleLogStream);

        child.stderr.on('data', (data) => {
            const errorStr = data.toString().trim();
            if (errorStr.toLowerCase().includes('error') || errorStr.toLowerCase().includes('fatal')) {
                logLive(`⚠️ [HỆ THỐNG STDERR]: ${errorStr}`);
            }
        });

        child.on('close', (code) => {
            activeChildProcess = null; // Chạy xong thì nhả biến ra
            if (code === 0) {
                resolve();
            } else {
                reject(new Error(`Lệnh [${command} ${args.join(' ')}] sập với mã lỗi: ${code}`));
            }
        });

        child.on('error', (err) => {
            activeChildProcess = null;
            reject(err);
        });
    });
}

// 🚀 KHỞI ĐỘNG LUỒNG CHẠY BẤT ĐỒNG BỘ CHÍNH
async function main() {
    logLive("⚙️ [NODE.JS DEBUG] Đang lội vào ổ đĩa quét thư mục gốc: " + BASE_SOURCE_DIR);

    try {
        const currentProductId = process.env.CURRENT_BATCH_PID;
        if (!currentProductId) throw new Error("Không nhận diện được mã sản phẩm cần render thật!");

        const processCount = process.env.PROCESS_COUNT ? parseInt(process.env.PROCESS_COUNT, 10) : 0;

        // BƯỚC 1: QUÉT VÀ GOM TỆP
        logLive(`--- 📦 BƯỚC 1: QUÉT VÀ GOM VIDEOS THÔ CỦA SẢN PHẨM ${currentProductId} ---`);
        const matchedDirs = fs.readdirSync(BASE_SOURCE_DIR).filter(f => f.startsWith(currentProductId) && fs.statSync(path.join(BASE_SOURCE_DIR, f)).isDirectory());
        if (matchedDirs.length === 0) throw new Error(`Không tìm thấy folder thô cho sản phẩm: ${currentProductId}`);
        
        const productRawFolder = path.join(BASE_SOURCE_DIR, matchedDirs[0]);
        let rawFiles = fs.readdirSync(productRawFolder)
            .filter(file => ['.mp4', '.mov', '.mkv', '.avi'].includes(path.extname(file).toLowerCase()))
            .sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));

        logLive(`📂 Tổng số video thô thực tế tìm thấy trên đĩa: ${rawFiles.length} file.`);

        if (processCount > 0 && rawFiles.length > processCount) {
            logLive(`⚠️ [HỆ THỐNG] Nhận lệnh khống chế: Chỉ lấy ${processCount} file đầu tiên theo thứ tự từ A-Z.`);
            rawFiles = rawFiles.slice(0, processCount);
        }

        try {
            if (fs.existsSync(PUSH_DIR)) execSync(`rm -rf "${PUSH_DIR}"/*`, { stdio: 'ignore', env: process.env });
        } catch (err) {}

        if (!fs.existsSync(PUSH_DIR)) fs.mkdirSync(PUSH_DIR, { recursive: true });

        const rawFilesToCleanup = [];
        rawFiles.forEach((file, index) => {
            const sourceFile = path.join(productRawFolder, file);
            const targetFile = path.join(PUSH_DIR, file); 
            logLive(`⏳ [${index + 1}/${rawFiles.length}] Đang nạp vào thớt: ${file}...`);
            fs.copyFileSync(sourceFile, targetFile);
            rawFilesToCleanup.push(sourceFile);
        });
        logLive(`✅ Đã gom thành công ${rawFiles.length} file thô vào xưởng.\n`);

        // BƯỚC 2: CẮT VIDEO
        logLive("--- ✂️ BƯỚC 2: ĐANG CẮT VIDEO ---");
        try {
            await runCommandLive('./cut-video.sh', [PUSH_DIR], { cwd: TOOL_DIR });
            logLive("✅ Cắt video hoàn tất.\n");
        } catch (cutError) { throw cutError; }

        // BƯỚC 3: CHUẨN BỊ THƯ MỤC
        logLive("--- 📂 BƯỚC 3: CHUẨN BỊ THƯ MỤC RENDER (INPUT/OUTPUT) ---");
        const inputDir = path.join(TOOL_DIR, "INPUT");
        const outputDir = path.join(TOOL_DIR, "OUTPUT");
        
        try {
            if (fs.existsSync(inputDir)) execSync(`rm -rf "${inputDir}"/*`, { stdio: 'ignore', env: process.env });
            if (fs.existsSync(outputDir)) execSync(`rm -rf "${outputDir}"/*`, { stdio: 'ignore', env: process.env });
        } catch (rmError) {}

        if (!fs.existsSync(inputDir)) fs.mkdirSync(inputDir, { recursive: true });
        if (!fs.existsSync(outputDir)) fs.mkdirSync(outputDir, { recursive: true });

        const filesToRender = fs.readdirSync(PUSH_DIR);
        filesToRender.forEach(file => {
            fs.copyFileSync(path.join(PUSH_DIR, file), path.join(inputDir, file));
        });
        logLive(`✅ Đã đồng bộ ${filesToRender.length} file mới vào thư mục INPUT.\n`);

        // BƯỚC 4: RENDER PYTHON
        logLive("--- 🐍 BƯỚC 4: ĐANG CHẠY RENDER PYTHON ---");
        try {
            await runCommandLive('python3', ['auto_render.py'], { cwd: TOOL_DIR });
            logLive("✅ Render Python hoàn tất.\n");
        } catch (pyError) { throw pyError; }

        // BƯỚC 5: ENCODE
        logLive("--- 🎬 BƯỚC 5: ĐANG CHẠY ENCODE OUTPUT ---");
        try {
            await runCommandLive('./encode.sh', [outputDir], { cwd: TOOL_DIR });
            logLive("✅ Encode output hoàn tất.\n");
        } catch (encError) { throw encError; }

        // ==========================================
        // BƯỚC 6: PHÂN PHỐI THÀNH PHẨM (UPDATE: GỘP VÀO PROCESSED_DIR THEO MÃ SP)
        // ==========================================
        logLive("--- 🚀 BƯỚC 6: ĐANG ĐẨY THÀNH PHẨM SANG THƯ MỤC LƯU TRỮ ---");
        
        // Tạo thư mục gốc lưu trữ chung nếu chưa có
        if (!fs.existsSync(PROCESSED_DIR)) {
            fs.mkdirSync(PROCESSED_DIR, { recursive: true });
        }

        const finalFiles = fs.readdirSync(outputDir);
        
        // Logic: Tạo thư mục con theo Mã SP ngay trong PROCESSED_DIR
        const productProcessedDir = path.join(PROCESSED_DIR, currentProductId);
        if (!fs.existsSync(productProcessedDir)) {
            fs.mkdirSync(productProcessedDir, { recursive: true });
        }

        await Promise.all(finalFiles.map(async (file) => {
            const finalSourcePath = path.join(outputDir, file);
            // Copy trực tiếp vào thư mục mã SP bên trong PROCESSED_DIR
            await fs.promises.copyFile(finalSourcePath, path.join(productProcessedDir, file));
        }));
        
        logLive(`✅ Thành công! Đã đẩy ${finalFiles.length} video vào thư mục: ${productProcessedDir}\n`);

        logLive("🗑️ Đang tiến hành xóa tệp thô vật lý trên ổ đĩa để giải phóng kho lưu trữ...");
        await Promise.all(rawFilesToCleanup.map(async (filePath) => {
            if (fs.existsSync(filePath)) {
                await fs.promises.unlink(filePath);
                logLive(`   -> Đã dọn dẹp file thô gốc: ${path.basename(filePath)}`);
            }
        }));

        logLive("\n🏆 TIẾN TRÌNH HOÀN TẤT!");
        sendTelegramNotification(`🚀 [RENDER] Xong việc!\nSản phẩm: ${currentProductId}\nĐã render xong ${finalFiles.length} video.`);
        setTimeout(() => process.exit(0), 500);

    } catch (error) {
        logLive(`\n❌ TIẾN TRÌNH BỊ LỖI VÀ DỪNG LẠI: ${error.message}`);
        process.exit(1);
    }
}

main();