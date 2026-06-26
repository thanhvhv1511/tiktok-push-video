const { chromium } = require('playwright');

(async () => {
    console.log('🔗 Đang kết nối tới Chrome debug (port 9222)...');
    let browser;
    try {
        browser = await chromium.connectOverCDP('http://localhost:9222');
    } catch (e) {
        return console.log('❌ Thất bại: Hãy chắc chắn bạn đã mở Chrome với cờ --remote-debugging-port=9222');
    }

    const context = browser.contexts()[0];
    const page = context.pages().find(p => p.url().includes('tiktok.com'));

    if (!page) {
        return console.log('❌ Thất bại: Không tìm thấy tab nào đang mở trang TikTok!');
    }

    console.log('📌 Đã kết nối thành công. Tiến hành dập công tắc Content...');
    console.log('------------------------------------------------------------');

    try {
        // 1. Nhắm trực tiếp vào khối cha chuẩn của Content check lite
        const contentRow = page.locator('.jsx-2629471817').filter({ hasText: 'Content check lite' });
        
        // 2. Định vị cái span thumb thật dựa theo danh sách element của bạn
        const thumbBtn = contentRow.locator('.Switch__thumb');

        // Chờ nó xuất hiện thực tế trên màn hình
        await thumbBtn.waitFor({ state: 'visible', timeout: 5000 });

        // 3. Đọc thuộc tính class để xem nó đang bật (true) hay tắt (false)
        const classList = await thumbBtn.getAttribute('class');
        
        if (classList && classList.includes('Switch__thumb--checked-true')) {
            console.log('📱 Trạng thái UI: Công tắc đang BẬT (Màu xanh). Đang click vào span thumb...');
            
            // Click trực tiếp vào span thumb
            await thumbBtn.click({ force: true });
            
            console.log('✅ Kết quả: Đã click gạt công tắc thành công!');
        } else {
            console.log('⏭️ Trạng thái UI: Công tắc đã TẮT sẵn từ trước, không cần gạt.');
        }
    } catch (error) {
        console.error('❌ Lỗi xử lý click:', error.message);
    }

    console.log('------------------------------------------------------------');
    console.log('🎉 Đã test xong! Bạn kiểm tra lại trình duyệt xem nút gạt đã tắt chưa.');
})();