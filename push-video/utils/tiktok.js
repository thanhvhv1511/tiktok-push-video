// Các hàm tương tác trực tiếp với DOM của TikTok
class TikTokAutomator {
    static async uploadVideo(page, filePath) {
        console.log('🔘 [Phase 1] Đang ném file video...');
        const fileInput = page.locator('input[type="file"]');
        await fileInput.waitFor({ state: 'attached', timeout: 15000 });
        await fileInput.setInputFiles(filePath);
        await page.waitForTimeout(5000);
    }

    static async inputCaption(page, caption) {
        if (!caption || caption.trim() === '') return;
        console.log('⌨️  [Phase 2] Đang nhập Caption...');
        
        const editor = page.locator('div[contenteditable="true"]').first();
        await editor.waitFor({ state: 'visible', timeout: 15000 });
        
        let isSuccess = false;
        for (let attempt = 1; attempt <= 3; attempt++) {
            await editor.click();
            await page.keyboard.press('Control+A'); 
            await page.keyboard.press('Meta+A'); 
            await page.keyboard.press('Backspace');
            await page.waitForTimeout(500);
            await editor.fill(caption);
            await page.waitForTimeout(1500);
            const currentText = await editor.textContent();
            if (currentText && currentText.trim().length > 0) { 
                isSuccess = true; 
                break; 
            }
        }
        if (!isSuccess) throw new Error('Không thể điền mô tả (Timeout).');
    }

    static async attachProduct(page, product) {
        if (!product || product === 'N/A' || product.trim() === '') return;
        console.log(`🛒 [Phase 2.5] Gắn sản phẩm: "${product}"...`);
        
        await page.evaluate(() => window.scrollBy(0, 600));
        await page.locator('button:has-text("Thêm"), button:has-text("Add")').first().click();
        await page.waitForTimeout(2000);
        await page.locator('button:has-text("Tiếp"), button:has-text("Next")').first().click();
        await page.waitForTimeout(3000);

        let isFound = false, currentPage = 1;
        while (!isFound && currentPage <= 20) {
            const targetRow = page.locator('.product-tb-row').filter({ hasText: product }).first();
            if (await targetRow.isVisible()) {
                await targetRow.evaluate((row) => {
                    const radio = row.querySelector('input.TUXRadioStandalone-input');
                    if (radio) { radio.click(); radio.dispatchEvent(new Event('change', { bubbles: true })); }
                });
                isFound = true; 
                break; 
            }
            const nextBtn = page.locator('div[class*="pagination"], ul[class*="pagination"]').locator('button, li').last(); 
            const isDisabled = await nextBtn.evaluate(node => node.hasAttribute('disabled') || node.classList.contains('disabled')).catch(() => true); 
            if (isDisabled) break; 
            
            await nextBtn.click();
            await page.waitForTimeout(2500); 
            currentPage++;
        }

        if (isFound) {
            await page.locator('button:has-text("Tiếp"), button:has-text("Next")').last().click({ force: true });
            await page.waitForTimeout(2000);
            await page.locator('button:has-text("Thêm"), button:has-text("Add")').last().click({ force: true });
        }
    }

    static async scheduleAndSubmit(page, scheduleString) {
        console.log(`🗓️ [Phase 3] Lên lịch...`);
        const scheduleDate = new Date(scheduleString);
        const targetDay = String(scheduleDate.getDate());
        const targetHour = String(scheduleDate.getHours()).padStart(2, '0');
        const targetMinute = String(scheduleDate.getMinutes()).padStart(2, '0');

        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(1000);

        const isScheduled = await page.evaluate(() => {
            const radio = document.querySelector('input[type="radio"][value="schedule"]');
            if (radio) { radio.click(); radio.dispatchEvent(new Event('change', { bubbles: true })); return true; }
            return false;
        });
        
        if (!isScheduled) {
            await page.locator('label').filter({ hasText: 'Lên lịch' }).first().click({ force: true });
        }
        await page.waitForTimeout(1500);

        // Chọn ngày
        const dateInput = page.locator('input[value^="202"]').first();
        await dateInput.click({ force: true });
        await page.waitForTimeout(1000);

        const dayCell = page.locator(`.day-picker-day:not(.day-picker-disabled):has-text("${targetDay}")`).last();
        if (await dayCell.count() === 0) {
            await page.getByText(targetDay, { exact: true }).filter({ hasNotClass: /disabled|outside/i }).last().click({ force: true });
        } else {
            await dayCell.click({ force: true });
        }
        await page.waitForTimeout(1000);

        // Chọn giờ
        const timeInput = page.locator('.scheduled-picker .TUXTextInputCore-input').first();
        await timeInput.click({ force: true });
        await page.waitForTimeout(1000);

        const hourCell = page.locator(`.tiktok-timepicker-option-text.tiktok-timepicker-left:has-text("${targetHour}")`).first();
        if (await hourCell.isVisible()) { await hourCell.scrollIntoViewIfNeeded(); await hourCell.click({ force: true }); }
        
        const minuteCell = page.locator(`.tiktok-timepicker-option-text.tiktok-timepicker-right:has-text("${targetMinute}")`).first();
        if (await minuteCell.isVisible()) { await minuteCell.scrollIntoViewIfNeeded(); await minuteCell.click({ force: true }); }

        await dateInput.click({ force: true });
        await page.waitForTimeout(1500);

        // Submit
        const finalSubmitBtn = page.locator('button:has-text("Lên lịch"), button:has-text("Schedule")').last();
        await finalSubmitBtn.click(); 
    }
}

module.exports = TikTokAutomator;