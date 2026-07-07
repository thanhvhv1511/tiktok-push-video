const fs = require('fs');
const path = require('path');
const { exec, execSync } = require('child_process');
const config = require('./config');

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

function loadTasks() {
    if (!fs.existsSync(config.PLAN_FILE)) return [];
    const content = fs.readFileSync(config.PLAN_FILE, 'utf-8').split(/\r?\n/);
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
    if (!fs.existsSync(config.PLAN_FILE)) return;
    const lines = fs.readFileSync(config.PLAN_FILE, 'utf-8').split(/\r?\n/);
    const updatedLines = [lines[0]];
    for (let i = 1; i < lines.length; i++) {
        const line = lines[i];
        if (!line.trim()) continue;
        const cols = line.match(/(".*?"|[^",]+)(?=\s*,|\s*$)/g);
        if (cols && cols.length >= 7) {
            if (cols[1].replace(/"/g, '') === fileName) {
                cols[5] = `"${newStatus}"`;
                updatedLines.push(cols.join(','));
            } else updatedLines.push(line);
        } else updatedLines.push(line);
    }
    fs.writeFileSync(config.PLAN_FILE, '\uFEFF' + updatedLines.join('\n'), 'utf-8');
}

function syncDatabaseStatus(videoId, status, errorMsg = '') {
    const phpScript = path.join(config.PHP_DIR, 'cli_update_status.php');
    const safeErrorMsg = errorMsg ? errorMsg.replace(/"/g, '\\"').replace(/\n/g, ' ') : '';
    const cmd = `export PATH=$PATH:/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin && "${config.PHP_PATH}" "${phpScript}" ${videoId} ${config.ACCOUNT_ID_ARG} ${status} "${safeErrorMsg}"`;
    exec(cmd, (err) => { if (err) console.log(`❌ [Lỗi Đồng Bộ DB] ${err.message}`); });
}

function checkCancelFromDB(videoId, port) {
    try {
        const phpScript = path.join(config.PHP_DIR, 'cli_check_status.php');
        const cmd = `export PATH=$PATH:/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin && "${config.PHP_PATH}" "${phpScript}" ${videoId} ${config.ACCOUNT_ID_ARG}`;
        const result = execSync(cmd, { encoding: 'utf-8' }).trim();
        
        if (result === 'CANCELLED') {
            console.log(`\n🛑 [DỪNG KHẨN CẤP] Phát hiện lệnh HỦY từ Web! Tiêu diệt Bot ngay lập tức.`);
            process.exit(1); 
        } 
        else if (result === 'EXIT') {
            console.log(`\n🧹 [DỌN DẸP] Phát hiện lệnh EXIT! Đang ép đóng Chrome tại port ${port}...`);
            try {
                // Ép đóng Chrome theo port truyền vào, stdio: 'ignore' để ẩn log rác của terminal
                execSync(`pkill -9 -f "remote-debugging-port=${port}"`, { stdio: 'ignore' });
            } catch (err) {
                // Bỏ qua nếu tiến trình Chrome không tồn tại (đã tự đóng trước đó)
            }
            // Tắt luôn Bot để Playwright không văng lỗi mất kết nối CDP
            process.exit(1);
        }
    } catch (e) {
        // Bỏ qua lỗi thực thi nhỏ của PHP script
    }
}

module.exports = { delay, loadTasks, updateTaskStatus, syncDatabaseStatus, checkCancelFromDB };