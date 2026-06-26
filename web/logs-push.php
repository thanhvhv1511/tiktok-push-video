<?php
$page_title = "Giám Sát Hệ Thống Đa Kênh";
$active_tab = "logs"; 
require 'includes/header.php';
?>

<link rel="stylesheet" href="assets/style.css">

<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center">
        <div class="bg-dark p-3 rounded-4 me-3 shadow-sm text-white border">
            <i class="bi bi-activity fs-4"></i>
        </div>
        <div>
            <h2 class="fw-bold mb-1" style="color: #0f172a;">Dashboard Real-time</h2>
            <p class="text-muted mb-0 font-monospace small">
                <span class="spinner-grow spinner-grow-sm text-success me-1" role="status" aria-hidden="true" style="width: 0.8rem; height: 0.8rem;"></span>
                Hệ thống tự động đồng bộ siêu tốc 1s ngầm...
            </p>
        </div>
    </div>
    
    <button class="btn btn-danger fw-bold shadow-sm px-4 d-flex align-items-center transition-all" id="btn_kill_all" onclick="cancelAllBots()" style="height: 48px; border-radius: 0.5rem;">
        <i class="bi bi-browser-chrome me-2 fs-5"></i> Dọn Dẹp Chrome Kẹt
    </button>
</div>

<div class="row" id="realtime-dashboard-container">
    <div class="col-12 text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2 text-muted font-monospace">Đang nạp luồng nhật ký...</div>
    </div>
</div>

<style>
.rotate-icon {
    display: inline-block;
    animation: spin 2s linear infinite;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.table-responsive::-webkit-scrollbar, .card-body::-webkit-scrollbar {
    width: 6px;
}
.table-responsive::-webkit-scrollbar-track, .card-body::-webkit-scrollbar-track {
    background: #f1f5f9; 
}
.table-responsive::-webkit-scrollbar-thumb, .card-body::-webkit-scrollbar-thumb {
    background: #cbd5e1; 
    border-radius: 4px;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const container = document.getElementById('realtime-dashboard-container');

    function fetchDashboardData() {
        let progressScrollTop = 0;
        let logsScrollTop = 0;
        
        const progressCard = container.querySelector('.col-lg-5 .card-body');
        if (progressCard) progressScrollTop = progressCard.scrollTop;
        
        const logTable = container.querySelector('.table-responsive');
        if (logTable) logsScrollTop = logTable.scrollTop;

        fetch('core/ajax_push_data.php')
            .then(response => {
                if (!response.ok) throw new Error('Mạng lỗi');
                return response.text();
            })
            .then(html => {
                container.innerHTML = html;

                const newProgressCard = container.querySelector('.col-lg-5 .card-body');
                if (newProgressCard) newProgressCard.scrollTop = progressScrollTop;

                const newLogTable = container.querySelector('.table-responsive');
                if (newLogTable) newLogTable.scrollTop = logsScrollTop;
            })
            .catch(error => console.error('❌ Lỗi nạp realtime:', error));
    }

    fetchDashboardData();
    setInterval(fetchDashboardData, 1000);
});

// 1. HÀM DỌN DẸP CHROME (KILL CHROME)
function cancelAllBots() {
    if (!confirm("⚠️ Lệnh này sẽ ép đóng toàn bộ các trình duyệt Chrome đang chạy (kể cả những cửa sổ bạn đang dùng để lướt web nếu dùng chung Profile). Bạn chắc chắn chứ?")) return;

    const btn = document.getElementById('btn_kill_all');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Đang bắn hạ Chrome...';

    fetch('core/ajax_kill_chrome.php')
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            btn.classList.replace('btn-danger', 'btn-secondary');
            btn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Đã Dọn Sạch';
            
            // Tự động khôi phục nút sau 3 giây để sẵn sàng cho lần bấm sau
            setTimeout(() => {
                btn.classList.replace('btn-secondary', 'btn-danger');
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }, 3000);
        } else {
            alert('❌ Lỗi: ' + res.message);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }).catch(err => {
        alert('❌ Lỗi kết nối Server!');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
}

// 2. HÀM HỦY TỪNG JOB (BATCH) RIÊNG BIỆT
function cancelSingleBatch(batchId, btnId) {
    if (!confirm(`⚠️ Bạn có chắc muốn dừng khẩn cấp toàn bộ tiến trình upload của riêng Mã Đợt: ${batchId}?`)) return;

    const btn = document.getElementById(btnId);
    if (!btn) return;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const formData = new FormData();
    formData.append('batch_id', batchId);

    fetch('core/ajax_cancel.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            btn.className = "btn btn-sm btn-secondary font-monospace fw-bold";
            btn.innerHTML = "❌ ĐÃ HỦY";
        } else {
            alert('❌ Lỗi không thể hủy: ' + res.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-stop-fill"></i> HỦY JOB';
        }
    }).catch(err => {
        alert('❌ Lỗi kết nối Server!');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-stop-fill"></i> HỦY JOB';
        }
    });
}

// 3. HÀM HỦY RIÊNG TỪNG TÀI KHOẢN TRONG ĐỢT ĐANG CHẠY
function cancelAccountInBatch(batchId, accountId, accountName, btnId) {
    if (!confirm(`⚠️ Bạn có chắc muốn DỪNG RIÊNG kênh [${accountName}] trong Đợt [${batchId}] không?\nCác kênh khác vẫn sẽ chạy bình thường.`)) {
        return;
    }

    const btn = document.getElementById(btnId);
    if (!btn) return;
    
    const originalText = btn.innerHTML;
    btn.style.pointerEvents = 'none'; // Chống click đúp
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:0.6rem; height:0.6rem;"></span>';

    const formData = new FormData();
    formData.append('batch_id', batchId);
    formData.append('account_id', accountId); // Gửi thêm cả ID account lên

    fetch('core/ajax_cancel.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            btn.className = "text-muted font-monospace";
            btn.innerHTML = "❌ Đã dừng";
        } else {
            alert('❌ Không thể dừng: ' + res.message);
            btn.style.pointerEvents = 'auto';
            btn.innerHTML = originalText;
        }
    }).catch(err => {
        alert('❌ Lỗi kết nối Server!');
        btn.style.pointerEvents = 'auto';
        btn.innerHTML = originalText;
    });
}
</script>

<?php require 'includes/footer.php'; ?>