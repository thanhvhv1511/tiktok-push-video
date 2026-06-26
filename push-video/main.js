const { chromium } = require('playwright');
const { exec } = require('child_process');

const config = require('./config'); // Config vẫn nằm cùng cấp với main.js
// ⚡ Cập nhật đường dẫn trỏ vào folder utils
const { delay, loadTasks, updateTaskStatus, syncDatabaseStatus, checkCancelFromDB } = require('./utils/helpers');
const TikTok = require('./utils/tiktok'); 

console.log(`==================================================`);
console.log(`🤖 [Bot Kích Hoạt] Tài khoản: [${config.ACCOUNT_ARG}] (ID: ${config.ACCOUNT_ID_ARG})`);
console.log(`📦 [Mã Đợt Hàng] Batch ID: [${config.BATCH_ARG}]`);
console.log(`🔌 [Kết Nối CDP] Cổng Port: [${config.PORT_ARG}]`);
console.log(`==================================================\n`);

(async () => {
    const tasks = loadTasks().filter(t => t.status === 'PENDING');
    if (tasks.length === 0) return console.log(`✅ Sạch lịch!`);

    console.log(`🌐 Đang gọi Chrome Profile qua cổng ${config.PORT_ARG}...`);
    const chromeCmd = `"${config.CHROME_PATH}" --remote-debugging-port=${config.PORT_ARG} --user-data-dir="${config.CHROME_DATA_DIR}" --profile-directory="${config.ACCOUNT_ARG}" --no-first-run https://www.tiktok.com`;
    exec(chromeCmd, () => {});
    await delay(5000);

    let browser;
    try {
        browser = await chromium.connectOverCDP(`http://localhost:${config.PORT_ARG}`, { timeout: 15000 });
    } catch (e) {
        console.error(`❌ Lỗi kết nối CDP qua cổng ${config.PORT_ARG}! Chrome chưa mở kịp.`);
        tasks.forEach(task => {
            updateTaskStatus(task.fileName, 'CANCELLED');
            syncDatabaseStatus(task.videoId, 'CANCELLED', `Kẹt kết nối Chrome (Timeout).`);
        });
        await delay(3000); 
        process.exit(1);
    }

    const context = browser.contexts()[0];
    let page = context.pages().find(p => p.url().includes('tiktok.com'));
    if (!page) page = await context.newPage();

    // ==========================================
    // VÒNG LẶP XỬ LÝ 
    // ==========================================
    for (let i = 0; i < tasks.length; i++) {
        const task = tasks[i];
        
        console.log(`\n--------------------------------------------------`);
        console.log(`▶️ ĐANG XỬ LÝ [${i + 1}/${tasks.length}]: ${task.fileName}`);
        
        try {
            checkCancelFromDB(task.videoId);
            syncDatabaseStatus(task.videoId, 'PROCESSING');
            
            await page.goto('https://www.tiktok.com/tiktokstudio/upload', { waitUntil: 'domcontentloaded' });
            await page.waitForTimeout(4000);

            checkCancelFromDB(task.videoId);
            await TikTok.uploadVideo(page, task.path);

            checkCancelFromDB(task.videoId);
            await TikTok.inputCaption(page, task.caption);

            checkCancelFromDB(task.videoId);
            await TikTok.attachProduct(page, task.product);

            checkCancelFromDB(task.videoId);
            await TikTok.scheduleAndSubmit(page, task.schedule);
            
            console.log(`✅ [SUCCESS] Lên lịch thành công!`);
            updateTaskStatus(task.fileName, 'SUCCESS');
            syncDatabaseStatus(task.videoId, 'SUCCESS');
            await page.waitForTimeout(8000);

        } catch (error) {
            checkCancelFromDB(task.videoId); 
            console.error(`❌ [ERROR] LỖI MẠNG/UI: ${error.message}`);
            updateTaskStatus(task.fileName, 'ERROR');
            syncDatabaseStatus(task.videoId, 'ERROR', error.message);
        }
    }

    console.log(`\n🎉 HOÀN TẤT LUỒNG CHẠY! Đang đóng trình duyệt...`);
    process.exit(0);
})();