#!/bin/bash

# 1. Xác định thư mục mục tiêu (Mặc định là thư mục hiện tại ".")
TARGET_DIR="${1:-.}"

if [ ! -d "$TARGET_DIR" ]; then
    echo "❌ Error: Thư mục '$TARGET_DIR' không tồn tại!"
    echo "Cách dùng: $0 /đường/dẫn/đến/thư/mục"
    exit 1
fi

echo "=== Bắt đầu encode video trong thư mục: $TARGET_DIR ==="

shopt -s nullglob

# 2. Quét qua toàn bộ file .mp4
for f in "$TARGET_DIR"/*.mp4; do
    
    # Bỏ qua nếu file này chính là file đã được fix trước đó
    if [[ "$f" == *"_fix.mp4" ]]; then
        continue
    fi

    # CHỮ THUẦN TÚY KHÔNG CHỨA RÁC TERMINAL
    echo "[Đang xử lý] $f"
    
    # Tạo tên file đầu ra có hậu tố _fix.mp4
    out_file="${f%.mp4}_fix.mp4"
    
    # ⚡ ĐÃ UPDATE: Thêm -nostats và ép -loglevel quiet lên đầu để triệt tiêu hoàn toàn khoảng lặng treo log ngoài UI
    ffmpeg -y -nostats -loglevel quiet -i "$f" \
      -c:v libx264 -b:v 6M -maxrate 8M -bufsize 16M \
      -profile:v high -pix_fmt yuv420p \
      -c:a copy "$out_file"
      
    # 👉 Kiểm tra nếu ffmpeg tạo file _fix thành công (dung lượng > 0) thì mới xóa file gốc
    if [ -s "$out_file" ]; then
        rm -f "$f"
        echo "✅ Đã xuất & Xóa file cũ: $out_file"
    else
        echo "❌ Lỗi: Không thể encode file $f"
    fi
    echo "------------------------------------------------"
done

shopt -u nullglob
echo "=== Hoàn thành toàn bộ tiến trình! ==="