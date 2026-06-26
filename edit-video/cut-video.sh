#!/bin/bash

# 1. Kiểm tra xem người dùng có truyền tham số thư mục vào không
TARGET_DIR="${1:-.}"

if [ ! -d "$TARGET_DIR" ]; then
    echo -e "\e[31mError: Thư mục '$TARGET_DIR' không tồn tại!\e[0m"
    echo "Cách dùng: $0 /đường/dẫn/đến/thư/mục"
    exit 1
fi

echo -e "\e[34m=== Bắt đầu quét và xử lý video trong: $TARGET_DIR ===\e[0m"

# 2. Dùng `find` để quét tất cả file .mp4 trong thư mục cha và con
# -type f: chỉ lấy file
# -iname: tìm kiếm không phân biệt chữ hoa/thường (.mp4 hoặc .MP4)
find "$TARGET_DIR" -type f -iname "*.mp4" | while read -r f; do
    
    # 3. Lấy thời lượng của video (đơn vị: giây)
    duration=$(ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "$f" 2>/dev/null)
    
    # Bỏ qua nếu không lấy được thời lượng (file lỗi hoặc không phải video)
    if [ -z "$duration" ]; then
        continue
    fi

    # 4. Kiểm tra nếu thời lượng lớn hơn 9.75 giây
    if (( $(echo "$duration > 9.75" | bc -l) )); then
        echo -e "\e[32m[Xử lý]\e[0m $f ($duration s)"
        
        # Tạo tên file tạm nằm cùng thư mục với file gốc
        tmp_file="${f%.*}_tmp.mp4"
        
        # Chạy lệnh ffmpeg và ghi đè
        ffmpeg -y -ss 0.5 -i "$f" -c copy "$tmp_file" -loglevel error && \
        mv "$tmp_file" "$f"
    else
        echo -e "\e[90m[Bỏ qua]\e[0m $f ($duration s - <= 9.75s)"
    fi
done

echo -e "\e[34m=== Hoàn thành xử lý! ===\e[0m"
