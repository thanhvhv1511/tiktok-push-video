#!/bin/bash

# Dừng lại nếu có bất kỳ bước nào gặp lỗi (quan trọng để đảm bảo an toàn)
set -e

# --- CẤU HÌNH ĐƯỜNG DẪN ---
VIDEO_DIR="/Users/thanhvhv/personal/tt/videos"
TOOL_DIR="/Users/thanhvhv/personal/tool/edit-video"
PUSH_DIR="${VIDEO_DIR}/video-push"
FINAL_PUSH_TARGET="/Users/thanhvhv/personal/tool/push-video/videos"

echo -e "\e[34m🚀 BẮT ĐẦU QUY TRÌNH XỬ LÝ TỰ ĐỘNG\e[0m"

# 1. Cut video
echo "--- 1. Đang cắt video..."
./cut-video.sh "$VIDEO_DIR" 

# 2. Chuẩn bị cho edit (Copy file)
echo "--- 3. Đang copy file vào thư mục edit..."
rm -rf "${TOOL_DIR}/INPUT/"*
rm -rf "${TOOL_DIR}/OUTPUT/"*
cp -r "${PUSH_DIR}/"* "${TOOL_DIR}/INPUT/"

# 3. Chạy Python edit
echo "--- 3. Đang chạy render Python..."
cd "$TOOL_DIR"
python3 auto_render.py

# 4. Xử lý encode cuối cùng
echo "--- 4. Đang chạy encode output..."
"${TOOL_DIR}/encode.sh" "${TOOL_DIR}/OUTPUT"

# 5. Copy video thành phẩm chuẩn bị push
echo "--- 5. Đang copy video đã encode sang thư mục push..."
# Xóa các file cũ trong thư mục push để tránh nhầm lẫn (tùy chọn, an toàn)
rm -rf "${FINAL_PUSH_TARGET}/"*
# Copy toàn bộ file từ thư mục OUTPUT đã encode xong
cp -r "${TOOL_DIR}/OUTPUT/"* "$FINAL_PUSH_TARGET/"

echo -e "\e[32m✅ HOÀN TẤT TOÀN BỘ QUY TRÌNH!\e[0m"