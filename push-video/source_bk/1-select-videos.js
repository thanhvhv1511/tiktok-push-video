const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

// Cấu hình thư mục chứa video thành phẩm của bạn
const OUTPUT_DIR = path.resolve(__dirname, 'OUTPUT');

(async () => {
    // Quét danh sách video trong thư mục OUTPUT
    if (!fs.existsSync(OUTPUT_DIR)) {
        console.log(`❌ Không tìm thấy thư mục: ${OUTPUT_DIR}`);
        return;
    }
    
    const videos = fs.readdirSync(OUTPUT_DIR).filter(f => f.endsWith('.mp4') || f.endsWith('.mov') || f.endsWith('.mkv'));
    if (videos.length === 0) {
        console.log('❌ Không tìm thấy video nào trong thư mục OUTPUT để test!');
        return;
    }

    const testVideoPath = path.join(OUTPUT_DIR, videos[0]);
    console.log(`🎯 Phase 1: Thử nghiệm upload video đầu tiên: ${videos[0]}`);

    console.log('Đang kết nối vào Chrome via CDP port 9222...');
    let browser;
    
    try {
        browser = await chromium.connectOverCDP('http://localhost:9222');
        console.log('✅ Đã kết nối thành công với Chrome!');
    } catch (error) {
        console.log('❌ Lỗi kết nối! Hãy chắc chắn bạn đã mở Chrome với cờ --remote-debugging-port=9222.');
        return;
    }
    
    const context = browser.contexts()[0];
    const pages = context.pages();
    
    // Tìm tab TikTok Studio Upload đang mở sẵn
    let page = pages.find(p => p.url().includes('tiktok.com/tiktokstudio/upload') || p.url().includes('tiktok.com/creator-center'));

    if (!page && pages.length > 0) {
        page = pages[0];
        console.log('🌐 Không tìm thấy tab mở sẵn, tự động chuyển hướng tab hiện tại sang TikTok...');
        await page.goto('https://www.tiktok.com/tiktokstudio/upload?from=creator_center&tab=video', { waitUntil: 'domcontentloaded' });
    } else if (page) {
        console.log(`🎯 Đã nhắm trúng tab TikTok: "${await page.title()}"`);
        if (!page.url().includes('upload')) {
            await page.goto('https://www.tiktok.com/tiktokstudio/upload?from=creator_center&tab=video', { waitUntil: 'domcontentloaded' });
        }
    }

    if (!page) {
        console.log('❌ Không tìm thấy tab hợp lệ nào đang mở.');
        return;
    }

    await page.waitForTimeout(3000);

    // -------------------------------------------------------------------------
    // EXECUTE: PHASE 1 - UPLOAD VIDEO (FIXED: ELEMENT NOT VISIBLE)
    // -------------------------------------------------------------------------
    console.log('🔘 Đang định vị ô input ẩn và ném file video vào thẳng UI...');
    try {
        // Định vị ô input file của TikTok (Chấp mọi loại ẩn giấu bằng CSS của UI)
        const fileInput = page.locator('input[type="file"]');
        
        // Đợi ô input này gắn kết vào cấu trúc DOM (Attached)
        await fileInput.waitFor({ state: 'attached', timeout: 15000 });
        
        // Nạp thẳng đường dẫn file vào mà không cần click giả lập mở Finder
        await fileInput.setInputFiles(testVideoPath);
        
        console.log('📊 [PHASE 1 THÀNH CÔNG] Đã đẩy file vào hệ thống! Bạn nhìn sang Chrome xem video đã bắt đầu load (%) chưa nhé.');
        
    } catch (e) {
        console.error(`❌ Thất bại khi đẩy file video: ${e.message}`);
    }

    // Treo kết nối 30 giây để bạn check thực tế trên giao diện Chrome
    console.log('⏳ Giữ kết nối điều khiển trong 30 giây...');
    await page.waitForTimeout(30000);

    // Ngắt kết nối CDP điều khiển (Không làm tắt tab Chrome của bạn)
    await browser.close();
    console.log('🔌 Đã ngắt tiến trình tự động hóa ẩn.');
})();