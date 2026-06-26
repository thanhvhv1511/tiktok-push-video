/**
 * Hệ thống điều phối luồng Render, Quét Log Live & Điều tốc Tiến trình (Stop / Background)
 */
let logInterval = null;
let isFinished = false;

// ⚡ BỔ SUNG: Biến lưu mã sản phẩm đang được theo dõi trên Modal
let currentActivePid = ''; 

// Hứng các thành phần UI từ Modal mới
const terminal = document.getElementById('terminal');
const modalSpinner = document.getElementById('modal-spinner');
const modalStatusTitle = document.getElementById('modal-status-title');
const modalCloseBtn = document.getElementById('modal-close-btn');

// Bổ sung các nút điều khiển mới phục vụ việc Stop và Chạy nền
const modalStopBtn = document.getElementById('modal-stop-btn');
const modalBgBtn = document.getElementById('modal-bg-btn');

// Hàm kích hoạt luồng từ nút bấm Render ngoài danh sách
async function triggerProcess(productId) {
    const countInput = document.getElementById(`count_${productId}`);
    const processCount = parseInt(countInput.value);

    if (isNaN(processCount) || processCount <= 0) {
        showAppModal('Vui lòng nhập số lượng hợp lệ!', 'warning');
        return;
    }

    // ⚡ Gán mã sản phẩm hiện tại để hàm fetchLogs biết đường gọi đúng file
    currentActivePid = productId;
    isFinished = false;
    
    if (terminal) {
        terminal.value = `// Đang đẩy lệnh sản xuất cho mã ${productId} vào Hàng Đợi (Queue)...\n`;
    }
    
    // Khôi phục giao diện các nút điều khiển về trạng thái đang chạy live
    modalSpinner.className = "spinner-border spinner-border-sm text-warning me-2";
    modalSpinner.style.display = "inline-block";
    modalStatusTitle.className = "modal-title fw-bold text-dark";
    modalStatusTitle.innerText = `Đang thực thi quy trình xử lý video cho mã: ${productId}...`;
    
    if (modalStopBtn) modalStopBtn.disabled = false;
    if (modalBgBtn) modalBgBtn.disabled = false;
    if (modalCloseBtn) modalCloseBtn.disabled = true;

    // Ép gọi Modal hiển thị theo chuẩn Bootstrap 5
    const modalEl = document.getElementById('renderLogModal');
    let logModalInstance = bootstrap.Modal.getInstance(modalEl);
    if (!logModalInstance) {
        logModalInstance = new bootstrap.Modal(modalEl);
    }
    logModalInstance.show();

    // Chuẩn bị payload gửi sang file PHP xử lý kích hoạt
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('process_count', processCount);

    try {
        const response = await fetch('core/ajax_video_process.php', { method: 'POST', body: formData });
        const data = await response.json();

        if (data.error) {
            handleRenderError(data.error);
        } else {
            // Kích hoạt vòng lặp bốc log realtime (Cứ 1 giây lội đĩa 1 lần)
            if (logInterval) clearInterval(logInterval);
            logInterval = setInterval(fetchLogs, 1000);
        }
    } catch (error) {
        handleRenderError('Lỗi kết nối máy chủ Render hoặc Timeout hệ thống.');
    }
}

// Hàm đọc và nạp file log thời gian thực (Cơ chế đọc đè vô hiệu hóa kẹt cache)
function fetchLogs() {
    if (isFinished || !currentActivePid) return;

    // ⚡ ĐÃ UPDATE: Truyền tham số pid lên đường dẫn để PHP đọc đúng file log
    fetch(`read_log.php?pid=${currentActivePid}&t=${new Date().getTime()}`)
    .then(response => response.text())
    .then(text => {
        if (!text || text.trim() === "") return;

        if (terminal) {
            // Đè trực tiếp toàn bộ dữ liệu mới nhất vào Textarea (Không sợ lệch index)
            terminal.value = text;
            // Tự động cuộn thanh cuốn bám chặt vào đáy dòng log mới xuất hiện
            terminal.scrollTop = terminal.scrollHeight;
        }

        // 🎯 BẪY ĐIỂM DỪNG 1: Khớp câu chốt hạ độc quyền của Node.js ở cuối Bước 6
        if (text.includes('🏆 TIẾN TRÌNH HOÀN TẤT!')) {
            finishProcess(true, 'ĐÃ HOÀN THÀNH TOÀN BỘ TIẾN TRÌNH RENDER VÀ PHÂN PHỐI SẢN PHẨM!');
        }
        
        // 🎯 BẪY ĐIỂM DỪNG 2: Nhận diện hệ thống đã tiêu diệt tiến trình thành công từ lệnh Stop
        if (text.includes('🛑 [HỆ THỐNG] Đã hủy tiến trình vật lý thành công')) {
            finishProcess(false, 'TIẾN TRÌNH RENDER ĐÃ BỊ HỦY BỎ THEO LỆNH NGƯỜI DÙNG!');
        }

        // 🎯 BẪY LỖI: Phát hiện log có dấu hiệu sập luồng gãy xích giữa chừng
        if (text.includes('TIẾN TRÌNH BỊ LỖI') || text.includes('Lỗi khi chạy')) {
            finishProcess(false, 'TIẾN TRÌNH BỊ LỖI VÀ ĐÃ DỪNG LẠI!');
        }
    })
    .catch(err => console.error("Lỗi đọc log:", err));
}

// 🟩 NÚT CHẠY NỀN: Ẩn giao diện lập tức, tắt bộ lặp AJAX để nhường tài nguyên cho máy cày việc khác
function runInBackground() {
    if (logInterval) {
        clearInterval(logInterval);
    }
    // Ẩn Modal log ra khỏi màn hình
    const modalEl = document.getElementById('renderLogModal');
    const logModalInstance = bootstrap.Modal.getInstance(modalEl);
    if (logModalInstance) {
        logModalInstance.hide();
    }
    
}

// 🟥 NÚT STOP ĐÃ NÂNG CẤP: Dùng UI xịn để hỏi & Ép giải phóng luồng ngay lập tức
function stopProcess() {
    showConfirmModal(
        "⚠️ Anh có chắc chắn muốn HỦY và DỪNG NGAY tiến trình này không? (Toàn bộ video đang xử lý dở sẽ bị dừng lập tức)",
        function() {
            // Đổi trạng thái nút bấm tạm thời để chống người dùng click lặp lại
            if (modalStopBtn) {
                modalStopBtn.disabled = true;
                modalStopBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang dừng...';
            }
            if (modalBgBtn) modalBgBtn.disabled = true;
            if (modalStatusTitle) {
                modalStatusTitle.className = "modal-title fw-bold text-danger";
                modalStatusTitle.innerText = "ĐANG TIÊU DIỆT TIẾN TRÌNH RENDER...";
            }

            // Bắn request sang file PHP trung gian để gõ lệnh kill, Đính kèm mã SP để sau này nâng cấp
            const formData = new FormData();
            formData.append('product_id', currentActivePid);

            fetch('stop_process.php', { method: 'POST', body: formData })
            .then(response => {
                if(!response.ok) throw new Error("HTTP error");
                return response.json();
            })
            .then(res => {
                if (res.status === 'success') {
                    // ⚡ ÉP GIẢI PHÓNG UI LẬP TỨC: Backend đã kill xong là cho xong luôn, không đợi bốc log
                    finishProcess(false, 'TIẾN TRÌNH RENDER ĐÃ BỊ HỦY BỎ THEO LỆNH NGƯỜI DÙNG!');
                    showAppModal('🛑 Đã phát lệnh tiêu diệt sạch cụm tiến trình render thành công!', 'success');
                } else {
                    showAppModal('⚠️ Thông báo hệ thống: ' + res.message, 'warning');
                    if (modalStopBtn) {
                        modalStopBtn.disabled = false;
                        modalStopBtn.innerHTML = '<i class="bi bi-stop-fill me-1"></i> Dừng tiến trình';
                    }
                    if (modalBgBtn) modalBgBtn.disabled = false;
                }
            })
            .catch(err => {
                // ⚡ DỰ PHÒNG: Backend trả lỗi mạng nhưng lệnh kill vẫn chạy, ép giải phóng UI luôn
                finishProcess(false, 'TIẾN TRÌNH RENDER ĐÃ BỊ HỦY BỎ THEO LỆNH NGƯỜI DÙNG!');
                showAppModal('🛑 Lệnh hủy diện rộng đã được đẩy lên hệ thống. Đang ngắt tiến trình con...', 'success');
            });
        }
    );
}

// Xử lý khi kết thúc tiến trình hoàn tất hoặc dừng hẳn (Hạ màn thu quân)
function finishProcess(isSuccess, message) {
    isFinished = true;
    clearInterval(logInterval);
    
    if (modalSpinner) {
        modalSpinner.className = isSuccess ? "bi bi-check-circle-fill text-success me-2 fs-5" : "bi bi-exclamation-triangle-fill text-danger me-2 fs-5";
    }
    if (modalStatusTitle) {
        modalStatusTitle.className = isSuccess ? "modal-title fw-bold text-success" : "modal-title fw-bold text-danger";
        modalStatusTitle.innerText = message;
    }
    
    // Vô hiệu hóa nút thao tác, mở khóa nút Đóng & Tải lại trang để cập nhật số lượng tồn kho lên bảng
    if (modalStopBtn) {
        modalStopBtn.disabled = true;
        modalStopBtn.innerHTML = '<i class="bi bi-stop-fill me-1"></i> Dừng tiến trình';
    }
    if (modalBgBtn) modalBgBtn.disabled = true;
    if (modalCloseBtn) modalCloseBtn.disabled = false;
}

// Xử lý báo lỗi kết nối cục bộ ban đầu
function handleRenderError(errorText) {
    isFinished = true;
    if (logInterval) clearInterval(logInterval);
    
    if (modalSpinner) modalSpinner.className = "bi bi-exclamation-triangle-fill text-danger me-2 fs-5";
    if (modalStatusTitle) {
        modalStatusTitle.className = "modal-title fw-bold text-danger";
        modalStatusTitle.innerText = errorText;
    }
    
    if (modalStopBtn) modalStopBtn.disabled = true;
    if (modalBgBtn) modalBgBtn.disabled = true;
    if (modalCloseBtn) modalCloseBtn.disabled = false;
}

// =========================================================================
// ⚡ TÍCH HỢP MỚI: BIẾN APP_MODAL CÓ SẴN THÀNH POPUP THÔNG BÁO CHUYÊN NGHIỆP
// =========================================================================
function showAppModal(message, type = 'info') {
    const modalEl = document.getElementById('appModal');
    const modalIcon = document.getElementById('appModalIcon');
    const modalMessage = document.getElementById('appModalMessage');
    const modalActions = document.getElementById('appModalActions');

    if (!modalEl || !modalMessage) return;

    let iconHTML = '';
    if (type === 'success') {
        iconHTML = '<i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>';
    } else if (type === 'danger') {
        iconHTML = '<i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 3rem;"></i>';
    } else if (type === 'warning') {
        iconHTML = '<i class="bi bi-exclamation-circle-fill text-warning" style="font-size: 3rem;"></i>';
    } else {
        iconHTML = '<i class="bi bi-info-circle-fill text-primary" style="font-size: 3rem;"></i>';
    }

    modalIcon.innerHTML = iconHTML;
    modalMessage.innerText = message;
    modalActions.innerHTML = '<button type="button" class="btn btn-sm btn-secondary font-monospace px-4 fw-bold shadow-none" data-bs-dismiss="modal">ĐỒNG Ý</button>';

    let appModalInstance = bootstrap.Modal.getInstance(modalEl);
    if (!appModalInstance) {
        appModalInstance = new bootstrap.Modal(modalEl);
    }
    appModalInstance.show();
}

// =========================================================================
// ⚡ TÍCH HỢP MỚI: BIẾN THỂ CONFIRM MODAL (VIẾT BẰNG VANILLA JS THUẦN)
// =========================================================================
function showConfirmModal(message, onConfirmCallback) {
    const modalEl = document.getElementById('appModal');
    const modalIcon = document.getElementById('appModalIcon');
    const modalMessage = document.getElementById('appModalMessage');
    const modalActions = document.getElementById('appModalActions');

    if (!modalEl || !modalMessage) return;

    modalIcon.innerHTML = '<i class="bi bi-question-circle-fill text-warning" style="font-size: 3rem;"></i>';
    modalMessage.innerText = message;

    modalActions.innerHTML = `
        <button type="button" class="btn btn-sm btn-light font-monospace px-3 fw-bold border" data-bs-dismiss="modal">QUAY LẠI</button>
        <button type="button" class="btn btn-sm btn-danger font-monospace px-3 fw-bold shadow-none" id="appModalConfirmBtn">HỦY TIẾN TRÌNH</button>
    `;

    // Gán sự kiện onclick thuần Vanilla JS để an toàn tuyệt đối
    const confirmBtn = document.getElementById('appModalConfirmBtn');
    if (confirmBtn) {
        confirmBtn.onclick = function() {
            let appModalInstance = bootstrap.Modal.getInstance(modalEl);
            if (appModalInstance) appModalInstance.hide();
            
            if (typeof onConfirmCallback === 'function') {
                onConfirmCallback();
            }
        };
    }

    let appModalInstance = bootstrap.Modal.getInstance(modalEl);
    if (!appModalInstance) {
        appModalInstance = new bootstrap.Modal(modalEl);
    }
    appModalInstance.show();
}

// =========================================================================
// ⚡ MỞ LẠI MODAL ĐỂ XEM LOG CỦA TIẾN TRÌNH ĐANG CHẠY NỀN
// =========================================================================
function reopenLogModal(productId) {
    currentActivePid = productId;
    isFinished = false;

    // Reset lại giao diện Modal
    if (terminal) terminal.value = `// Đang móc nối lại vào luồng Log của tiến trình ${productId}...\n`;
    if (modalSpinner) {
        modalSpinner.className = "spinner-border spinner-border-sm text-info me-2";
        modalSpinner.style.display = "inline-block";
    }
    if (modalStatusTitle) {
        modalStatusTitle.className = "modal-title fw-bold text-dark";
        modalStatusTitle.innerText = `Đang theo dõi trực tiếp mã: ${productId}`;
    }
    
    // Mở khóa các nút
    if (modalStopBtn) modalStopBtn.disabled = false;
    if (modalBgBtn) modalBgBtn.disabled = false;
    if (modalCloseBtn) modalCloseBtn.disabled = true;

    // Hiển thị Modal
    const modalEl = document.getElementById('renderLogModal');
    let logModalInstance = bootstrap.Modal.getInstance(modalEl);
    if (!logModalInstance) logModalInstance = new bootstrap.Modal(modalEl);
    logModalInstance.show();

    // Bắt đầu cắm ống hút log lại
    if (logInterval) clearInterval(logInterval);
    logInterval = setInterval(fetchLogs, 1000);
}