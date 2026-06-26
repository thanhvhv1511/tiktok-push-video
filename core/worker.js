require('dotenv').config({ path: __dirname + '/../.env' });
const mysql = require('mysql2/promise');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

// Cấu hình Database 
const dbConfig = {
    host: process.env.DB_HOST || '127.0.0.1',
    port: process.env.DB_PORT || 3307, 
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASS || '',
    database: process.env.DB_NAME || 'thanhvhv',
    waitForConnections: true,
    connectionLimit: 5,
    queueLimit: 0
};

// File log chung của riêng con Worker (để anh soi log hệ thống bằng pm2)
const LOG_FILE = process.env.LOG_FILE_PATH || path.join(__dirname, "../data/process.log");

function logLive(message) {
    const time = new Date().toISOString().replace(/T/, ' ').replace(/\..+/, '');
    const formattedMessage = `[${time}] ${message}\n`;
    fs.appendFileSync(LOG_FILE, formattedMessage);
    console.log(formattedMessage.trim());
}

let isWorking = false; // Cờ khóa: Chỉ cho phép cày 1 job tại 1 thời điểm

async function processQueue() {
    if (isWorking) return; // Nếu đang bận cày job khác thì bỏ qua nhịp quét này

    const pool = mysql.createPool(dbConfig);

    try {
        // 1. Quét tìm Job cũ nhất đang nằm chờ (pending)
        const [rows] = await pool.query("SELECT * FROM render_queue WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1");
        
        if (rows.length === 0) {
            await pool.end();
            return; // Không có việc thì thôi, ngủ tiếp
        }

        const job = rows[0];
        isWorking = true;
        logLive(`\n=================================================`);
        logLive(`🔥 [WORKER] PHÁT HIỆN TASK MỚI: #${job.task_id} (SP: ${job.product_id})`);
        
        // 2. Chuyển trạng thái sang Running
        await pool.query("UPDATE render_queue SET status = 'running', started_at = NOW() WHERE task_id = ?", [job.task_id]);

        // 3. Kích hoạt Pipeline (Gọi thằng Node.js cũ ra cày)
        logLive(`⚙️ Đang bàn giao cho run-pipeline.js xử lý...`);
        
        // ⚡ ĐÃ FIX: Khai báo đường dẫn log file động của riêng sản phẩm này
        const jobLogFile = path.join(__dirname, `../data/process_${job.product_id}.log`);

        // Truyền các biến môi trường cần thiết sang cho run-pipeline.js
        const envVars = {
            ...process.env,
            CURRENT_BATCH_PID: job.product_id,
            PROCESS_COUNT: job.process_count,
            LOG_FILE_PATH: jobLogFile // Nhét đường dẫn file động vào đây
        };

        const pipelineScript = path.join(__dirname, 'run-pipeline.js');

        // Ép chạy bằng lệnh spawn để luồng không bị block
        const child = spawn('node', [pipelineScript], { env: envVars });

        child.stdout.on('data', (data) => {
            // run-pipeline.js tự in log ra file rồi nên không cần in trùng ở đây nữa
        });

        child.stderr.on('data', (data) => {
            console.error(`[PIPELINE STDERR]: ${data}`);
        });

        child.on('close', async (code) => {
            if (code === 0) {
                logLive(`✅ [WORKER] Task #${job.task_id} hoàn tất xuất sắc!`);
                await pool.query("UPDATE render_queue SET status = 'done', completed_at = NOW() WHERE task_id = ?", [job.task_id]);
            } else {
                // Nếu bị người dùng bấm STOP hoặc lỗi, nó sẽ văng ra code khác 0
                logLive(`⚠️ [WORKER] Task #${job.task_id} kết thúc với trạng thái Hủy/Lỗi (Mã: ${code}).`);
                await pool.query("UPDATE render_queue SET status = 'error', completed_at = NOW() WHERE task_id = ?", [job.task_id]);
            }
            
            isWorking = false; // Giải phóng cờ, sẵn sàng nhận job mới
            await pool.end();
        });

    } catch (error) {
        logLive(`❌ [WORKER ERROR] Lỗi hệ thống quét Queue: ${error.message}`);
        isWorking = false;
        await pool.end();
    }
}

// 🐕 Bộ đếm nhịp tim: Cứ 3 giây lội vào DB kiểm tra việc 1 lần
logLive("🚀 [WORKER] Khởi động thành công! Đang lắng nghe Hàng Đợi (Queue)...");
setInterval(processQueue, 3000);