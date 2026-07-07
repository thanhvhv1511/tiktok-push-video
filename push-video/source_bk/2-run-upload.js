require('dotenv').config(); 
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { exec, execSync } = require('child_process');

// ⚡ LẤY CẤU HÌNH TỪ ENV
const CHROME_PATH = process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const CHROME_DATA_DIR = process.env.CHROME_DATA_DIR || path.join(__dirname, 'data-browser');
const PHP_PATH = process.env.PHP_PATH || 'php';

const ACCOUNT_ARG = process.argv[2];
const BATCH_ARG = process.argv[3];
const PORT_ARG = process.argv[4]; 
const ACCOUNT_ID_ARG = process.argv[5]; 

if (!ACCOUNT_ARG || !BATCH_ARG || !PORT_ARG || !ACCOUNT_ID_ARG) {
    console.error('❌ Thiếu tham số vận hành!');
    process.exit(1);
}

let OUTPUT_DIR = path.join(__dirname, '..', 'web', 'plans');
const CONFIG_PATH = path.join(__dirname, 'config.json');
if (fs.existsSync(CONFIG_PATH)) {
    const config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf-8'));
    OUTPUT_DIR = config.OUTPUT_PLAN_DIR || OUTPUT_DIR;
}

const safeAccName = ACCOUNT_ARG.replace(/[^a-zA-Z0-9]/g, '_');
const PLAN_FILE = path.join(OUTPUT_DIR, `schedule_plan_${safeAccName}_${BATCH_ARG}.csv`);

console.log(`==================================================`);
console.log(`🤖 [Bot Kích Hoạt] Tài khoản: [${ACCOUNT_ARG}] (ID: ${ACCOUNT_ID_ARG})`);
console.log(`📦 [Mã Đợt Hàng] Batch ID: [${BATCH_ARG}]`);
console.log(`🔌 [Kết Nối CDP] Cổng Port: [${PORT_ARG}]`);
console.log(`==================================================\n`);

function loadTasks() {
    if (!fs.existsSync(PLAN_FILE)) return [];
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
            } else updatedLines.push(line);
        } else updatedLines.push(line);
    }
    fs.writeFileSync(PLAN_FILE, '\uFEFF' + updatedLines.join('\n'), 'utf-8');
}

function syncDatabaseStatus(videoId, status, errorMsg = '') {
    const phpScript = path.join(__dirname, '..', 'web', 'core', 'cli_update_status.php');
    const safeErrorMsg = errorMsg ? errorMsg.replace(/"/g, '\\"').replace(/\n/g, ' ') : '';
    const cmd = `export PATH=$PATH:/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin && "${PHP_PATH}" "${phpScript}" ${videoId} ${ACCOUNT_ID_ARG} ${status} "${safeErrorMsg}"`;
    exec(cmd, (err) => { if (err) console.log(`❌ [Lỗi Đồng Bộ DB]`); });
}

// ======================================================================
// ⚡ HÀM TỬ THẦN TRUY VẤN DATABASE (DB-DRIVEN KILL SWITCH)
// ======================================================================
function checkCancelFromDB(videoId) {
    try {
        const phpScript = path.join(__dirname, '..', 'web', 'core', 'cli_check_status.php');
        const cmd = `export PATH=$PATH:/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin && "${PHP_PATH}" "${phpScript}" ${videoId} ${ACCOUNT_ID_ARG}`;
        const result = execSync(cmd, { encoding: 'utf-8' }).trim();
        
        if (result === 'CANCELLED') {
            console.log(`\n🛑 [DỪNG KHẨN CẤP] Phát hiện lệnh HỦY từ Web! Tiêu diệt Bot ngay lập tức.`);
            process.exit(1); 
        }
    } catch (e) {
        // Bỏ qua lỗi thực thi nhỏ để không làm sập bot oan
    }
}

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

(async () => {
    const tasks = loadTasks().filter(t => t.status === 'PENDING');
    if (tasks.length === 0) return console.log(`✅ Sạch lịch!`);

    console.log(`🌐 Đang gọi Chrome Profile qua cổng ${PORT_ARG}...`);
    const chromeCmd = `"${CHROME_PATH}" --remote-debugging-port=${PORT_ARG} --user-data-dir="${CHROME_DATA_DIR}" --profile-directory="${ACCOUNT_ARG}" --no-first-run https://www.tiktok.com`;
    
    exec(chromeCmd, (err) => {});
    await delay(5000);

    let browser;
    try {
        // GIỮ NGUYÊN LOCALHOST NHƯ BẠN YÊU CẦU
        browser = await chromium.connectOverCDP(`http://localhost:${PORT_ARG}`, { timeout: 15000 });
    } catch (e) {
        console.error(`❌ Lỗi kết nối CDP qua cổng ${PORT_ARG}! Chrome chưa mở kịp.`);
        for (let i = 0; i < tasks.length; i++) {
            updateTaskStatus(tasks[i].fileName, 'CANCELLED');
            syncDatabaseStatus(tasks[i].videoId, 'CANCELLED', `Kẹt kết nối Chrome (Timeout).`);
        }
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
        
        checkCancelFromDB(task.videoId);

        console.log(`\n--------------------------------------------------`);
        console.log(`▶️ ĐANG XỬ LÝ [${i + 1}/${tasks.length}]: ${task.fileName}`);
        
        try {
            syncDatabaseStatus(task.videoId, 'PROCESSING');
            
            await page.goto('https://www.tiktok.com/tiktokstudio/upload?from=creator_center&tab=video', { waitUntil: 'domcontentloaded' });
            await page.waitForTimeout(4000);

            checkCancelFromDB(task.videoId);

            console.log('🔘 [Phase 1] Đang ném file video...');
            const fileInput = page.locator('input[type="file"]');
            await fileInput.waitFor({ state: 'attached', timeout: 15000 });
            await fileInput.setInputFiles(task.path);
            await page.waitForTimeout(5000); 

            checkCancelFromDB(task.videoId);

            console.log('⌨️  [Phase 2] Đang nhập Caption...');
            if (task.caption && task.caption.trim() !== '') {
                const editor = page.locator('div[contenteditable="true"]').first();
                await editor.waitFor({ state: 'visible', timeout: 15000 });
                let isCaptionSuccess = false;
                for (let attempt = 1; attempt <= 3; attempt++) {
                    await editor.click();
                    await page.keyboard.press('Control+A'); 
                    await page.keyboard.press('Meta+A'); 
                    await page.keyboard.press('Backspace');
                    await page.waitForTimeout(500);
                    await editor.fill(task.caption);
                    await page.waitForTimeout(1500);
                    const currentText = await editor.textContent();
                    if (currentText && currentText.trim().length > 0) { isCaptionSuccess = true; break; }
                }
                if (!isCaptionSuccess) throw new Error('Không thể điền mô tả (Timeout).');
            }

            checkCancelFromDB(task.videoId);

            if (task.product && task.product !== 'N/A' && task.product.trim() !== '') {
                console.log(`🛒 [Phase 2.5] Gắn sản phẩm: "${task.product}"...`);
                await page.evaluate(() => window.scrollBy(0, 600));
                await page.locator('button:has-text("Thêm"), button:has-text("Add")').first().click();
                await page.waitForTimeout(2000);
                await page.locator('button:has-text("Tiếp"), button:has-text("Next")').first().click();
                await page.waitForTimeout(3000);

                let isProductFound = false, currentPage = 1;
                while (!isProductFound && currentPage <= 20) {
                    const targetRow = page.locator('.product-tb-row').filter({ hasText: task.product }).first();
                    if (await targetRow.isVisible()) {
                        await targetRow.evaluate((row) => {
                            const radio = row.querySelector('input.TUXRadioStandalone-input');
                            if (radio) { radio.click(); radio.dispatchEvent(new Event('change', { bubbles: true })); }
                        });
                        isProductFound = true; break; 
                    }
                    const nextPaginationBtn = page.locator('div[class*="pagination"], ul[class*="pagination"]').locator('button, li').last(); 
                    const isDisabled = await nextPaginationBtn.evaluate(node => node.hasAttribute('disabled') || node.classList.contains('disabled')).catch(() => true); 
                    if (isDisabled) break; 
                    await nextPaginationBtn.click();
                    await page.waitForTimeout(2500); 
                    currentPage++;
                }

                if (isProductFound) {
                    await page.locator('button:has-text("Tiếp"), button:has-text("Next")').last().click({ force: true });
                    await page.waitForTimeout(2000);
                    await page.locator('button:has-text("Thêm"), button:has-text("Add")').last().click({ force: true });
                }
            }

            checkCancelFromDB(task.videoId);

            console.log(`🗓️ [Phase 3] Lên lịch...`);
            const scheduleDate = new Date(task.schedule);
            const TARGET_DAY = String(scheduleDate.getDate());
            const TARGET_HOUR = String(scheduleDate.getHours()).padStart(2, '0');
            const TARGET_MINUTE = String(scheduleDate.getMinutes()).padStart(2, '0');

            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            await page.waitForTimeout(1000);

            const isScheduled = await page.evaluate(() => {
                const scheduleInput = document.querySelector('input[type="radio"][value="schedule"]');
                if (scheduleInput) { scheduleInput.click(); scheduleInput.dispatchEvent(new Event('change', { bubbles: true })); return true; }
                return false;
            });
            if (!isScheduled) await page.locator('label').filter({ hasText: 'Lên lịch' }).first().click({ force: true });
            await page.waitForTimeout(1500);

            const dateInput = page.locator('input[value^="202"]').first();
            await dateInput.click({ force: true });
            await page.waitForTimeout(1000);

            const dayCell = page.locator(`.day-picker-day:not(.day-picker-disabled):has-text("${TARGET_DAY}")`).last();
            if (await dayCell.count() === 0) {
                await page.getByText(TARGET_DAY, { exact: true }).filter({ hasNotClass: /disabled|outside/i }).last().click({ force: true });
            } else await dayCell.click({ force: true });
            await page.waitForTimeout(1000);

            const timeInput = page.locator('.scheduled-picker .TUXTextInputCore-input').first();
            await timeInput.click({ force: true });
            await page.waitForTimeout(1000);

            const hourCell = page.locator(`.tiktok-timepicker-option-text.tiktok-timepicker-left:has-text("${TARGET_HOUR}")`).first();
            if (await hourCell.isVisible()) { await hourCell.scrollIntoViewIfNeeded(); await hourCell.click({ force: true }); }
            const minuteCell = page.locator(`.tiktok-timepicker-option-text.tiktok-timepicker-right:has-text("${TARGET_MINUTE}")`).first();
            if (await minuteCell.isVisible()) { await minuteCell.scrollIntoViewIfNeeded(); await minuteCell.click({ force: true }); }

            await dateInput.click({ force: true });
            await page.waitForTimeout(1500);

            // CHỐT CUỐI CÙNG TRƯỚC KHI ẤN ĐĂNG
            checkCancelFromDB(task.videoId);

            const finalSubmitBtn = page.locator('button:has-text("Lên lịch"), button:has-text("Schedule")').last();
            await finalSubmitBtn.click(); 
            
            console.log(`✅ [SUCCESS] Lên lịch thành công!`);
            updateTaskStatus(task.fileName, 'SUCCESS');
            syncDatabaseStatus(task.videoId, 'SUCCESS');
            await page.waitForTimeout(8000);

        } catch (error) {
            // ⚡ Bảo vệ đúp: Lỡ bạn đóng Chrome thủ công gây ra lỗi catch, nó sẽ hỏi lại DB xem có phải do Hủy không
            try {
                const phpScript = path.join(__dirname, '..', 'web', 'core', 'cli_check_status.php');
                const cmd = `export PATH=$PATH:/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin && "${PHP_PATH}" "${phpScript}" ${task.videoId} ${ACCOUNT_ID_ARG}`;
                const result = execSync(cmd, { encoding: 'utf-8' }).trim();
                if (result === 'CANCELLED') process.exit(1);
            } catch (e) {}

            console.error(`❌ [ERROR] LỖI MẠNG/UI: ${error.message}`);
            updateTaskStatus(task.fileName, 'ERROR');
            syncDatabaseStatus(task.videoId, 'ERROR', error.message);
        }
    }

    console.log(`\n🎉 HOÀN TẤT LUỒNG CHẠY! Đang đóng trình duyệt...`);
    process.exit(0);
})();