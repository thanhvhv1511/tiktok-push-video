const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const OUTPUT_DIR = path.resolve(__dirname, 'OUTPUT');
const CAPTION_FILE = path.resolve(__dirname, 'captions.txt');

function loadVideoConfig() {
    if (!fs.existsSync(CAPTION_FILE)) {
        return { caption: "Sản phẩm thời trang xu hướng #xuhuong #thoitrang", product: null };
    }
    const lines = fs.readFileSync(CAPTION_FILE, 'utf-8').split('\n').filter(l => l.trim() !== '');
    if (lines.length === 0) return { caption: "Sản phẩm thời trang xu hướng #xuhuong #thoitrang", product: null };
    
    const firstLine = lines[0];
    if (firstLine.includes('|')) {
        const [cap, prod] = firstLine.split('|');
        return { caption: cap.trim(), product: prod.trim() };
    }
    return { caption: firstLine.trim(), product: null };
}

(async () => {
    const config = loadVideoConfig();
    console.log(`📝 Dữ liệu cấu hình:\n  - Caption: ${config.caption}\n  - Sản phẩm: ${config.product || 'Không gắn'}`);

    console.log('🔗 Đang kết nối vào Chrome qua port 9222...');
    let browser;
    try {
        browser = await chromium.connectOverCDP('http://localhost:9222');
    } catch (error) {
        console.log('❌ Lỗi kết nối! Hãy kiểm tra port 9222.');
        return;
    }
    
    const context = browser.contexts()[0];
    const page = context.pages().find(p => p.url().includes('tiktok.com/tiktokstudio/upload'));

    if (!page) {
        console.log('❌ Không tìm thấy tab TikTok Studio Upload.');
        return;
    }

    console.log(`🎯 Đã ghim chặt tab: "${await page.title()}"`);

    try {
        // --- BƯỚC 1: NHẬP CAPTION ---
        console.log('⌨️  Đang xử lý Caption...');
        const editor = page.locator('div[contenteditable="true"]').first();
        await editor.waitFor({ state: 'visible', timeout: 15000 });
        await editor.click();
        await page.keyboard.press('Meta+A');
        await page.keyboard.press('Backspace');
        await editor.fill(config.caption);
        console.log('   ✅ Đã điền caption.');
        await page.waitForTimeout(1000);

        // --- BƯỚC 2: GẮN SẢN PHẨM ---
        if (config.product) {
            console.log(`🛒 Bắt đầu gắn link: "${config.product}"...`);
            
            await page.evaluate(() => window.scrollBy(0, 600));
            await page.waitForTimeout(1000);

            const addLinkBtn = page.locator('button:has-text("Thêm"), button:has-text("Add")').first();
            await addLinkBtn.waitFor({ state: 'visible', timeout: 15000 });
            await addLinkBtn.click();
            console.log('   ✅ Đã bấm nút Thêm liên kết.');
            await page.waitForTimeout(2000);

            console.log('🔘 Đang xử lý popup Loại liên kết...');
            const nextBtn = page.locator('button:has-text("Tiếp"), button:has-text("Next")').first();
            await nextBtn.waitFor({ state: 'visible', timeout: 10000 });
            await nextBtn.click();
            console.log('   ✅ Đã bấm nút "Tiếp" lần 1.');
            await page.waitForTimeout(3000);

            // --- BƯỚC MỚI: TÌM VÀ CLICK TRỰC TIẾP (BỎ QUA TÌM KIẾM) ---
            console.log(`🔍 Đang quét trực tiếp danh sách để tìm sản phẩm: "${config.product}"...`);
            
            // Định vị chính xác CÁI HÀNG (tr) có chứa text tên sản phẩm của bạn
            const targetRow = page.locator('.product-tb-row').filter({ hasText: config.product }).first();
            
            // Chờ cho đến khi cái hàng chứa sản phẩm này xuất hiện trên màn hình
            await targetRow.waitFor({ state: 'visible', timeout: 15000 });
            console.log('   ✅ Đã khóa mục tiêu! Đang chọc vào thẻ Radio của hàng này...');

            // Bơm JS vào đúng CÁI HÀNG ĐÓ để click vào input TUXRadioStandalone-input bên trong nó
            const isClicked = await targetRow.evaluate((row) => {
                const radioInput = row.querySelector('input.TUXRadioStandalone-input');
                if (radioInput) {
                    radioInput.click();
                    // Đánh thức React
                    radioInput.dispatchEvent(new Event('change', { bubbles: true }));
                    return true;
                }
                return false;
            });

            if (isClicked) {
                console.log('   ✅ Đã Check thành công vào ô Radio của sản phẩm mong muốn!');
            } else {
                console.log('   ⚠️ Fallback Check...');
                await targetRow.locator('input.TUXRadioStandalone-input').first().check({ force: true });
            }
            
            // Đợi 1.5s để nút Tiếp sáng lên
            await page.waitForTimeout(1500);

            // --- BẤM NÚT TIẾP ĐỂ XÁC NHẬN HOÀN TẤT ---
            console.log('🔴 Đang bấm nút Tiếp cuối cùng để đóng popup ghim link...');
            const confirmNextBtn = page.locator('button:has-text("Tiếp"), button:has-text("Next")').last();
            await confirmNextBtn.click({ force: true });
            
            // --- BƯỚC MỚI: BẤM NÚT "THÊM" Ở POPUP XÁC NHẬN TÊN SẢN PHẨM ---
            console.log('📝 Đang xử lý popup xác nhận tên sản phẩm hiển thị trên video...');
            await page.waitForTimeout(2000); // Đợi popup bung ra hoàn toàn

            const finalAddBtn = page.locator('button:has-text("Thêm"), button:has-text("Add")').last();
            await finalAddBtn.waitFor({ state: 'visible', timeout: 5000 });
            await finalAddBtn.click({ force: true });
            console.log('   ✅ Đã bấm nút Thêm cuối cùng.');

            console.log(`🎉 [XONG TOÀN BỘ PHASE 2]: Sản phẩm đã được đóng gói và ghim mượt mà vào bài đăng!`);
        }
    } catch (e) {
        console.error(`❌ Lỗi Phase 2: ${e.message}`);
    }

    console.log('⏳ Giữ kết nối 30 giây để kiểm tra...');
    await page.waitForTimeout(30000);
    await browser.close();
})();