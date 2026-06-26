<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiến Trình Chạy Script</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=1.0">
    <style>
        .terminal-box {
            background-color: #0f172a;
            color: #38bdf8;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
            padding: 20px;
            border-radius: 12px;
            height: 500px;
            overflow-y: auto;
            box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.6);
        }
        .terminal-line { margin-bottom: 4px; white-space: pre-wrap; }
        .status-running { color: #f59e0b; }
    </style>
</head>
<body class="py-5">

<div class="container px-4 max-w-4xl mx-auto">
    <div class="dashboard-card p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-1">
                    <i class="bi bi-terminal text-primary me-2"></i>Tiến Trình Xử Lý Tự Động
                </h4>
                <p class="text-muted small mb-0" id="status-text">
                    <span class="spinner-border spinner-border-sm status-running me-2"></span>Hệ thống đang thực thi Quy trình render & push...
                </p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary fw-bold btn-sm"><i class="bi bi-house me-1"></i>Về Trang Chủ</a>
        </div>

        <div class="terminal-box" id="terminal">
            <div class="terminal-line text-muted">// Đang kết nối tới luồng hệ thống...</div>
        </div>
        
        <div class="mt-3 text-end text-muted small">
            <i class="bi bi-info-circle me-1"></i>Khung log tự động cuộn xuống khi có dữ liệu mới.
        </div>
    </div>
</div>

<script>
    const terminal = document.getElementById('terminal');
    const statusText = document.getElementById('status-text');
    let lastResponseLength = 0;
    let isFinished = false;

    function fetchLogs() {
        if (isFinished) return;

        fetch('/read_log.php')
        .then(response => response.text())
        .then(text => {
            if (text.length !== lastResponseLength) {
                terminal.innerHTML = ''; 
                
                const lines = text.split('\n');
                lines.forEach(line => {
                    if (line.trim() !== '') {
                        const div = document.createElement('div');
                        div.className = 'terminal-line';
                        
                        if (line.includes('✅') || line.includes('HOÀN TẤT')) {
                            div.style.color = '#10b981'; 
                        } else if (line.includes('❌') || line.includes('Lỗi') || line.includes('⚠️')) {
                            div.style.color = '#f43f5e'; 
                        } else if (line.includes('---')) {
                            div.style.color = '#e2e8f0'; 
                        }
                        
                        div.textContent = line;
                        terminal.appendChild(div);
                    }
                });

            if (text.includes('🏆 TIẾN TRÌNH HOÀN TẤT!')) {
                isFinished = true;
                statusText.innerHTML = '<span class="bi bi-check-circle-fill text-success me-2"></span><span class="fw-bold text-success">ĐÃ HOÀN THÀNH TOÀN BỘ TIẾN TRÌNH!</span>';
                clearInterval(logInterval); // Lúc này mới được phép dừng quét log
            }
                
                // 👉 Bẫy lỗi: Nếu file log báo có lỗi gãy tiến trình, chặn đứng spinner ngay lập tức
                if (text.includes('TIẾN TRÌNH BỊ LỖI') || text.includes('Lỗi khi chạy')) {
                    isFinished = true;
                    statusText.innerHTML = '<span class="bi bi-exclamation-triangle-fill text-danger me-2"></span><span class="fw-bold text-danger">TIẾN TRÌNH BỊ LỖI VÀ ĐÃ DỪNG LẠI!</span>';
                    clearInterval(logInterval);
                }

                terminal.scrollTop = terminal.scrollHeight;
                lastResponseLength = text.length;
            }
        })
        .catch(err => {
            console.error("Lỗi đọc log:", err);
        });
    }

    // 👉 Chỉnh lại tốc độ quét: Đều đặn 1 giây (1000ms) quét 1 lần để hệ thống thở mịn màng
    const logInterval = setInterval(fetchLogs, 1000);
</script>

</body>
</html>