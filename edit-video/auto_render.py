import os
import subprocess
import re
import sys

# Cấu hình thư mục mặc định
INPUT_DIR = "INPUT"
OUTPUT_DIR = "OUTPUT"
EFFECTS_DIR = "EFFECTS" 
VIDEO_EXTENSIONS = ('.mp4', '.mov', '.mkv', '.avi')

def log_realtime(message):
    """
    ⚡ HÀM IN LOG SIÊU TỐC: Ép chữ xuống đĩa ngay lập tức để Node.js hốt lên UI luôn
    """
    print(message, flush=True)

def auto_render_run():
    if not os.path.exists(INPUT_DIR):
        log_realtime(f"❌ Lỗi: Không tìm thấy thư mục '{INPUT_DIR}'!")
        return

    if not os.path.exists(EFFECTS_DIR):
        log_realtime(f"❌ Lỗi: Không tìm thấy thư mục '{EFFECTS_DIR}' chứa các file text effect!")
        return

    if not os.path.exists(OUTPUT_DIR):
        os.makedirs(OUTPUT_DIR)

    # Lấy danh sách video đầu vào và sắp xếp thứ tự chuẩn
    input_videos = sorted([f for f in os.listdir(INPUT_DIR) if f.lower().endswith(VIDEO_EXTENSIONS)])
    
    if not input_videos:
        log_realtime("❌ Không tìm thấy video mới nào trong thư mục INPUT!")
        return

    log_realtime(f"🚀 Tìm thấy {len(input_videos)} video mới. Bắt đầu ghép Text Effect tự động...")

    for index, video_name in enumerate(input_videos, start=1):
        input_video_path = os.path.join(INPUT_DIR, video_name)
        # ⚡ ÉP GIỮ NGUYÊN TÊN FILE: Đảm bảo đồng bộ tuyệt đối tên tệp tin từ INPUT sang OUTPUT
        output_video_path = os.path.join(OUTPUT_DIR, video_name)

        prefix = re.split(r'[-_.]', video_name)[0]
        
        matching_effects = [
            f for f in os.listdir(EFFECTS_DIR) 
            if f.startswith(prefix) and f.lower().endswith(VIDEO_EXTENSIONS)
        ]

        if not matching_effects:
            log_realtime(f" ⚠️ [{index}/{len(input_videos)}] BỎ QUA: {video_name}")
            log_realtime(f"    ↳ Lỗi: Không tìm thấy file text effect nào cho tiền tố '{prefix}' trong thư mục {EFFECTS_DIR}!")
            continue

        effect_filename = matching_effects[0]
        text_effect_path = os.path.join(EFFECTS_DIR, effect_filename)

        log_realtime(f" ⏳ [{index}/{len(input_videos)}] Đang xử lý: {video_name}")
        log_realtime(f"    ↳ Khớp với Text Effect: {effect_filename}...")

        # -------------------------------------------------------------------------
        # LỆNH FFMPEG SẠCH SẼ - ĐÃ TRIỆT TIÊU RÁC LOG FRAME=...
        # -------------------------------------------------------------------------
        cmd = [
            'ffmpeg', '-y',
            '-i', input_video_path,
            '-i', text_effect_path,
            '-filter_complex', 
            '[0:v]setpts=20/19*PTS[slowed]; '
            '[slowed]split=2[v1][v2]; '
            '[v2]hflip[v2_mirrored]; '
            '[v1][v2_mirrored]concat=n=2:v=1:a=0[bg_concat]; '
            # 1. Dùng sws_flags=lanczos để chống mờ khi phóng to + unsharp để tăng độ nét chi tiết
            '[bg_concat]scale=1440:2560:sws_flags=lanczos,unsharp=3:3:0.4:3:3:0.4,setsar=1[vid_scaled]; '
            '[1:v]scale=1440:2560:sws_flags=lanczos,colorkey=0x00FF00:0.3:0.1[chroma_out]; '
            '[vid_scaled][chroma_out]overlay=x=(W-w)/2:y=(H-h)/2:shortest=1[video_out]',
            '-map', '[video_out]',
            '-map', '1:a',
            '-c:v', 'libx264',
            '-preset', 'slow',     # Giữ nguyên slow (hoặc veryslow) để nén block ảnh thông minh
            '-crf', '17',          # 2. Giảm từ 18 xuống 17 để tăng dung lượng bitrate, giảm nén vỡ hạt
            '-tune', 'film',       # 3. Tối ưu hóa pixel cho video có chi tiết đời thực / sản phẩm
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            '-b:a', '320k',
            output_video_path
        ]

        try:
            # ⚡ NÉM SẠCH LOG TIẾN ĐỘ VÀO SỌT RÁC: Chỉ giữ lại log thông báo của Python
            subprocess.run(
                cmd, 
                stdout=subprocess.DEVNULL, 
                stderr=subprocess.DEVNULL, 
                check=True
            )
            log_realtime(f"    ✅ Thành công!")
        except subprocess.CalledProcessError:
            log_realtime(f"    ❌ Lỗi trong quá trình render FFmpeg!")

if __name__ == "__main__":
    auto_render_run()