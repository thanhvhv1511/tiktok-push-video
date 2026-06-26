# 1. Chạy API 
pm2 start python3 --name "auto-push-api" -- /Users/thanhvhv/personal/tool/site/core/app.py
pm2 start /Users/thanhvhv/personal/tool/site/core/worker.js --name "auto-render-worker"

### Lưu trạng thái để máy Mac khởi động lại nó tự chạy
pm2 save
### Nếu muốn kill hoặc xem log
pm2 logs auto-push-api
pm2 stop auto-push-api
pm2 reload auto-push-api
pm2 reload auto-render-worker

# 2. Vào browser
/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --remote-debugging-port=9222 --user-data-dir="/Users/thanhvhv/personal/tool/tiktok-browser" --profile-directory="thanhkx2000" --no-first-run https://www.tiktok.com
/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --remote-debugging-port=9222 --user-data-dir="/Users/thanhvhv/personal/tool/tiktok-browser" --profile-directory="thanha1k43" --no-first-run https://www.tiktok.com
/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --remote-debugging-port=9222 --user-data-dir="/Users/thanhvhv/personal/tool/tiktok-browser" --profile-directory="hoangvuhust2002" --no-first-run https://www.tiktok.com


# Kiến trúc 
## I. Core (Bộ máy trung tâm)

Đây là "trái tim" xử lý ngầm của hệ thống, tự động hóa 100% quy trình render và giao tiếp với Web UI. Cụm Core hoạt động với cơ chế 3 lớp chặt chẽ: **Nhạc trưởng** (`app.py`), **Gác cổng** (`worker.js`), và **Công nhân** (`run-pipeline.js`).

### 🔄 Sơ Đồ Luồng Hoạt Động (Workflow)

```text
[Web UI] 
   │ (Gửi yêu cầu Render / Đăng Video)
   ▼
[ 1. app.py (API Server) ] ─────────► (Điều phối tiến trình Bot / Ép đóng Web)
   │
   │ (Lưu Task vào Database)
   ▼
[ Database (render_queue) ]
   ▲
   │ (Quét 3s/lần)
[ 2. worker.js (Queue Worker) ] 
   │
   │ (Nhặt 1 Job -> Cắm cờ khóa luồng -> Gọi Script)
   ▼
[ 3. run-pipeline.js (Core Pipeline) ]
   │
   ├─► Bước 1: Gom file thô
   ├─► Bước 2: Cắt video (cut-video.sh)
   ├─► Bước 3: Chuẩn bị Input/Output
   ├─► Bước 4: Render (auto_render.py)
   ├─► Bước 5: Encode (encode.sh)
   └─► Bước 6: Lưu trữ & Xóa file thô
   │
   ▼
[ Thành phẩm cuối (Processed) ] & [ Thông báo Telegram ]
### 1. app.py 
Local API Server (Background Manager)
API trung gian kết nối Web UI với các tiến trình hệ thống cục bộ. Xử lý bất đồng bộ (không làm treo web) và tự động ghi log.
🔌 Các Endpoints chính:
POST /run: Khởi chạy Render Video (có khóa bảo vệ, chỉ cho phép 1 tiến trình chạy cùng lúc để tránh sập máy).
POST /stop: Nút dừng khẩn cấp, "giết" tận gốc toàn bộ tiến trình Render & FFmpeg.
POST /run-bot: Khởi chạy Bot upload TikTok (hỗ trợ chạy song song đa luồng nhiều kênh).
POST /kill-chrome: Dọn dẹp RAM, ép đóng toàn bộ Chrome bị kẹt ẩn dưới nền.

### 2. worker.js
Queue Worker (Trình Xử Lý Hàng Đợi)
Script chạy ngầm (polling) chuyên làm nhiệm vụ tuần tự nhặt Task từ Database ra để chạy Render. Đảm bảo hệ thống không bị quá tải phần cứng do chạy nhồi nhét quá nhiều video cùng lúc.
⚙️ Cơ chế hoạt động:
Quét DB định kỳ: Nhịp tim 3 giây/lần. Truy vấn bảng render_queue để tìm các task cũ nhất đang ở trạng thái pending.
Khóa Luồng An Toàn: Sử dụng cờ isWorking để đảm bảo chỉ xử lý 1 Job duy nhất tại một thời điểm. Task này hoàn tất (hoặc bị hủy) mới được phép nhận Task tiếp theo.
Điều phối Tiến trình: Gọi tiến trình con run-pipeline.js bằng lệnh spawn (không block luồng chính), tự động nhồi tham số động và tách riêng file log cho từng mã sản phẩm (process_{product_id}.log).
Đồng bộ Trạng thái: Lắng nghe Exit Code của tiến trình con để cập nhật trạng thái thực tế vào Database (running -> done hoặc error).

### 3. run-pipeline.js
Core Pipeline (Luồng Render Chính)
Script Node.js đóng vai trò "công nhân trực tiếp", tự động hóa toàn bộ vòng đời xử lý video từ file thô đến thành phẩm cuối cùng.
⚙️ Vòng đời thực thi 6 Bước:
Gom file thô: Nhặt đúng số lượng video thô của sản phẩm theo yêu cầu.
Cắt Video: Gọi cut-video.sh để cắt gọt thời lượng.
Chuẩn bị Thư mục: Reset luồng dữ liệu Input/Output.
Render: Gọi auto_render.py để xử lý hiệu ứng/âm thanh.
Encode: Gọi encode.sh để xuất định dạng chuẩn.
Lưu trữ & Dọn dẹp: Phân phối thành phẩm vào kho processed, đồng thời xóa file thô vật lý để giải phóng ổ cứng.
🛡️ Cơ chế bảo vệ & Tiện ích:
Watchdog (Chống Zombie): Liên tục giám sát cờ stop_render.flag. Nếu phát hiện, lập tức "tự sát" và chém bay mọi tiến trình con (Python, FFmpeg) đang chạy dở.
Báo cáo Telegram: Tự động bắn thông báo về điện thoại ngay khi xưởng hoàn tất mẻ render.