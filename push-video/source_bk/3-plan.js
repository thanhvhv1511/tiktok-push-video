const { chromium } = require('playwright');

(async () => {
    console.log('🔗 Đang kết nối vào Chrome qua port 9222...');
    let browser;
    try {
        browser = await chromium.connectOverCDP('http://localhost:9222');
        console.log('✅ Đã kết nối thành công với Chrome!');
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

    // --- CẤU HÌNH NGÀY GIỜ MUỐN LÊN LỊCH ---
    const TARGET_DAY = "14";     // Chọn ngày 14
    const TARGET_HOUR = "15";    // Chọn giờ 12
    const TARGET_MINUTE = "30";  // Chọn phút 00

    // -------------------------------------------------------------------------
    // EXECUTE: PHASE 3 - CẤU HÌNH LÊN LỊCH & ĐĂNG BÀI
    // -------------------------------------------------------------------------
    try {
        console.log('\n🗓️ BẮT ĐẦU PHASE 3: Cấu hình thời gian đăng...');
        
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(1000);

        // 1. TÍCH CHỌN Ô "LÊN LỊCH"
        console.log('🔘 Đang kích hoạt chế độ Lên lịch...');
        const isScheduled = await page.evaluate(() => {
            const scheduleInput = document.querySelector('input[type="radio"][value="schedule"]');
            if (scheduleInput) {
                scheduleInput.click();
                scheduleInput.dispatchEvent(new Event('change', { bubbles: true }));
                return true;
            }
            return false;
        });

        if (!isScheduled) {
            const scheduleLabel = page.locator('label').filter({ hasText: 'Lên lịch' }).first();
            await scheduleLabel.click({ force: true });
        }
        await page.waitForTimeout(1500); // Đợi UI xổ 2 ô ngày/giờ ra

        // 2. CHỌN NGÀY
        console.log(`📅 Đang mở lịch để chọn ngày ${TARGET_DAY}...`);
        
        // Tìm ô nhập ngày bằng cách tìm input có value chứa định dạng năm 202x
        const dateInput = page.locator('input[value^="202"]').first();
        await dateInput.waitFor({ state: 'visible', timeout: 5000 });
        await dateInput.click({ force: true });
        await page.waitForTimeout(1000); // Chờ lịch xổ ra

        // Cố gắng chọn ngày. Do các thư viện Datepicker (như react-day-picker) rất đa dạng
        // Chúng ta sẽ tìm thẻ chứa ngày có text chính xác là TARGET_DAY
        // Bỏ qua các ngày bị disable hoặc mờ đi (thường thuộc về tháng trước/sau)
        console.log(`   Đang tìm và click vào ngày ${TARGET_DAY}...`);
        const dayCell = page.locator(`.day-picker-day:not(.day-picker-disabled):has-text("${TARGET_DAY}")`).last();
        
        // Nếu dùng selector cụ thể không tìm thấy, thử dùng text
        if (await dayCell.count() === 0) {
            await page.getByText(TARGET_DAY, { exact: true }).filter({ hasNotClass: /disabled|outside/i }).last().click({ force: true });
        } else {
             await dayCell.click({ force: true });
        }
       
        console.log(`   ✅ Đã chốt ngày: ${TARGET_DAY}`);
        await page.waitForTimeout(1000);


        // 3. CHỌN GIỜ VÀ PHÚT
        console.log(`⏰ Đang mở danh sách chọn giờ ${TARGET_HOUR}:${TARGET_MINUTE}...`);
        // Tìm ô chọn thời gian.
        const timeInput = page.locator('.scheduled-picker .TUXTextInputCore-input').first();
        await timeInput.click({ force: true });
        await page.waitForTimeout(1000); // Chờ 2 cột giờ và phút xổ ra

        // Xử lý cột giờ (bên trái)
        console.log(`   Đang cuộn tìm giờ ${TARGET_HOUR}...`);
        const hourCell = page.locator(`.tiktok-timepicker-option-text.tiktok-timepicker-left:has-text("${TARGET_HOUR}")`).first();
        if (await hourCell.isVisible()) {
             await hourCell.scrollIntoViewIfNeeded();
             await hourCell.click({ force: true });
             console.log(`   ✅ Đã chọn giờ: ${TARGET_HOUR}`);
        } else {
            console.log('   ⚠️ Không tìm thấy ô chứa giờ này.');
        }

        // Xử lý cột phút (bên phải)
        console.log(`   Đang cuộn tìm phút ${TARGET_MINUTE}...`);
        const minuteCell = page.locator(`.tiktok-timepicker-option-text.tiktok-timepicker-right:has-text("${TARGET_MINUTE}")`).first();
        if (await minuteCell.isVisible()) {
             await minuteCell.scrollIntoViewIfNeeded();
             await minuteCell.click({ force: true });
             console.log(`   ✅ Đã chọn phút: ${TARGET_MINUTE}`);
        } else {
            console.log('   ⚠️ Không tìm thấy ô chứa phút này.');
        }

        // Click bên ngoài (ví dụ click lại vào ô ngày) để đóng popup chọn giờ
        await dateInput.click({ force: true });
        await page.waitForTimeout(1000);

        // 4. BẤM NÚT "LÊN LỊCH" CUỐI CÙNG
        console.log('🔴 Đang tìm nút Submit đỏ cuối cùng để tống vào hàng chờ...');
        const finalSubmitBtn = page.locator('button:has-text("Lên lịch"), button:has-text("Schedule")').last();
        await finalSubmitBtn.waitFor({ state: 'visible', timeout: 5000 });
        
        // await finalSubmitBtn.click(); // Mở comment dòng này khi muốn code tự bấm xác nhận
        
        console.log('   ✅ Đã định vị xong nút Lên lịch cuối. Sẵn sàng gộp 3 Phase!');

    } catch (e) {
        console.error(`❌ Lỗi tại Phase 3: ${e.message}`);
    }

    console.log('\n⏳ Giữ trạng thái kết nối điều khiển 15 giây để bạn check kết quả...');
    await page.waitForTimeout(15000);
    await browser.close();
})();