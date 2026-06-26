const { chromium } = require('playwright');

async function main() {
    // Hardcode trực tiếp tên sản phẩm bạn muốn test ở đây
    const productName = "1730533234178689632"; 

    console.log('🔗 Đang kết nối vào Chrome qua port 9222...');
    let browser;
    try {
        browser = await chromium.connectOverCDP('http://localhost:9222');
    } catch (error) {
        console.log('❌ Lỗi kết nối! Hãy kiểm tra port 9222.');
        return; 
    }

    try {
        const contexts = browser.contexts();
        if (contexts.length === 0) throw new Error("Không tìm thấy browser context nào.");
        
        const context = contexts[0];
        const pages = context.pages();
        if (pages.length === 0) throw new Error("Không tìm thấy tab nào đang mở.");
        
        const page = pages[0]; 
        console.log(`✅ Đã kết nối thành công tới tab: "${await page.title()}"`);

        // =================================================================
        // LOGIC TEST TÌM VÀ CHỌN SẢN PHẨM (CÓ LẬT TRANG)
        // =================================================================
        if (productName && productName !== 'N/A' && productName !== '') {
            console.log(`🛒 [Phase 2] Đang gắn sản phẩm: "${productName}"...`);
            
            await page.evaluate(() => window.scrollBy(0, 600));
            
            const addLinkBtn = page.locator('button:has-text("Thêm"), button:has-text("Add")').first();
            await addLinkBtn.click();
            await page.waitForTimeout(2000);

            const nextBtn = page.locator('button:has-text("Tiếp"), button:has-text("Next")').first();
            await nextBtn.click();
            await page.waitForTimeout(3000);

            let isProductFound = false;
            let maxPages = 20; 
            let currentPage = 1;

            while (!isProductFound && currentPage <= maxPages) {
                console.log(`🔎 Đang quét tìm sản phẩm ở trang ${currentPage}...`);

                const targetRow = page.locator('.product-tb-row').filter({ hasText: productName }).first();

                if (await targetRow.isVisible()) {
                    console.log(`✅ Đã tìm thấy "${productName}" ở trang ${currentPage}!`);
                    
                    await targetRow.evaluate((row) => {
                        const radio = row.querySelector('input.TUXRadioStandalone-input');
                        if (radio) { 
                            radio.click(); 
                            radio.dispatchEvent(new Event('change', { bubbles: true })); 
                        }
                    });
                    
                    isProductFound = true;
                    await page.waitForTimeout(1500);
                    break; 
                }

                const nextPaginationBtn = page.locator('div[class*="pagination"], ul[class*="pagination"]').locator('button, li').last(); 
                
                const isDisabled = await nextPaginationBtn.evaluate((node) => {
                    return node.hasAttribute('disabled') || 
                           node.classList.contains('disabled') || 
                           node.getAttribute('aria-disabled') === 'true';
                }).catch(() => true); 

                if (isDisabled) {
                    console.log(`❌ Đã đến trang cuối nhưng không tìm thấy sản phẩm "${productName}".`);
                    break; 
                }

                await nextPaginationBtn.click();
                console.log(`⏩ Đang chuyển sang trang ${currentPage + 1}...`);
                await page.waitForTimeout(2500); 
                currentPage++;
            }

            if (isProductFound) {
                const confirmNextBtn = page.locator('button:has-text("Tiếp"), button:has-text("Next")').last();
                await confirmNextBtn.click({ force: true });
                
                await page.waitForTimeout(2000);
                const finalAddBtn = page.locator('button:has-text("Thêm"), button:has-text("Add")').last();
                await finalAddBtn.click({ force: true });
                console.log(`🎉 Đã gắn link sản phẩm thành công!`);
            } else {
                console.log(`⚠️ Bỏ qua bước gắn link vì không tìm thấy sản phẩm.`);
            }
        }

    } catch (error) {
        console.error("❌ Đã xảy ra lỗi trong quá trình chạy:", error);
    } finally {
        console.log('🏁 Tool đã hoàn tất luồng gắn sản phẩm.');
    }
}

main();