/**
 * ====================================================
 * TRẠM ĐĂNG (PUBLISH) JAVASCRIPT LOGIC
 * ====================================================
 */

// Khôi phục vị trí cuộn sau khi reload trang
document.addEventListener("DOMContentLoaded", function() {
    const scrollPos = sessionStorage.getItem('publish_scroll_pos');
    if (scrollPos) {
        window.scrollTo(0, parseInt(scrollPos, 10));
        sessionStorage.removeItem('publish_scroll_pos');
    }
});

// Hàm Push All gốc
function publishBatch(batchId) {
    const checkboxes = document.querySelectorAll(`.batch-acc-checkbox-${batchId}:checked`);
    if (checkboxes.length === 0) {
        alert("⚠️ Vui lòng tích chọn ít nhất 1 tài khoản kênh bán!");
        return;
    }

    const accountIds = Array.from(checkboxes).map(cb => cb.value);
    const btn = document.getElementById(`btn_${batchId}`);
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Đang nổ máy...';

    const formData = new FormData();
    formData.append('batch_id', batchId);
    formData.append('account_ids', accountIds.join(','));

    fetch('core/ajax_publish.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');
            btn.innerHTML = '<i class="bi bi-robot me-2"></i> Đang Chạy...';
            
            sessionStorage.setItem('publish_scroll_pos', window.scrollY);
            setTimeout(() => window.location.reload(), 1200);
        } else {
            alert('❌ Lỗi khởi động: ' + res.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-fill me-2"></i> Push All';
        }
    }).catch(err => {
        alert('❌ Lỗi kết nối API!');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill me-2"></i> Push All';
    });
}

// Hàm Đăng Lại (Chỉ chạy video lỗi)
function retryBatch(batchId) {
    const checkboxes = document.querySelectorAll(`.batch-acc-checkbox-${batchId}:checked`);
    if (checkboxes.length === 0) {
        alert("⚠️ Vui lòng tích chọn ít nhất 1 tài khoản kênh bán để đăng lại!");
        return;
    }

    if (!confirm("Hệ thống sẽ BỎ QUA các video đã lên thành công và chỉ chạy lại các video bị LỖI/CHƯA CHẠY. Bạn chắc chắn chứ?")) {
        return;
    }

    const accountIds = Array.from(checkboxes).map(cb => cb.value);
    const btn = document.getElementById(`btn_retry_${batchId}`);
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Đang Khởi Động...';

    const formData = new FormData();
    formData.append('batch_id', batchId);
    formData.append('account_ids', accountIds.join(','));

    fetch('core/ajax_retry.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            btn.classList.replace('btn-warning', 'btn-success');
            btn.style.color = '#fff';
            btn.innerHTML = '<i class="bi bi-robot me-2"></i> Đang Đăng Lại...';
            
            sessionStorage.setItem('publish_scroll_pos', window.scrollY);
            setTimeout(() => window.location.reload(), 1200);
        } else {
            alert('❌ Không thể đăng lại: ' + res.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-2 fw-bold"></i> Đăng Lại (Chỉ Lỗi)';
        }
    }).catch(err => {
        alert('❌ Lỗi kết nối Server!');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-clockwise me-2 fw-bold"></i> Đăng Lại (Chỉ Lỗi)';
    });
}