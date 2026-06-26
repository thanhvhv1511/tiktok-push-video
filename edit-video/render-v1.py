import os
import subprocess
import re

# Cấu hình thư mục
INPUT_DIR = "INPUT"
OUTPUT_DIR = "OUTPUT"
EFFECTS_DIR = "EFFECTS" 
VIDEO_EXTENSIONS = ('.mp4', '.mov', '.mkv', '.avi')

def auto_render_run():
    if not os.path.exists(INPUT_DIR):
        print(f"❌ Lỗi: Không tìm thấy thư mục '{INPUT_DIR}'!")
        return

    if not os.path.exists(EFFECTS_DIR):
        print(f"❌ Lỗi: Không tìm thấy thư mục '{EFFECTS_DIR}' chứa các file text effect!")
        return

    if not os.path.exists(OUTPUT_DIR):
        os.makedirs(OUTPUT_DIR)

    input_videos = [f for f in os.listdir(INPUT_DIR) if f.lower().endswith(VIDEO_EXTENSIONS)]
    
    if not input_videos:
        print("❌ Không tìm thấy video mới nào trong thư mục INPUT!")
        return

    print(f"🚀 Tìm thấy {len(input_videos)} video mới. Bắt đầu ghép Text Effect tự động...")

    for index, video_name in enumerate(input_videos, start=1):
        input_video_path = os.path.join(INPUT_DIR, video_name)
        output_video_path = os.path.join(OUTPUT_DIR, f"{video_name}")

        # -------------------------------------------------------------------------
        # XỬ LÝ TÌM TEXT EFFECT THEO TIỀN TỐ (ĐÃ CẬP NHẬT)
        # Sử dụng thư viện 're' để tự động cắt chuỗi nếu gặp dấu '-', '_' hoặc '.'
        # VD: "sp002-0015.mp4" -> "sp002" | "sp006_video.mp4" -> "sp006"
        prefix = re.split(r'[-_.]', video_name)[0]
        
        matching_effects = [
            f for f in os.listdir(EFFECTS_DIR) 
            if f.startswith(prefix) and f.lower().endswith(VIDEO_EXTENSIONS)
        ]

        if not matching_effects:
            print(f" ⚠️ [{index}/{len(input_videos)}] BỎ QUA: {video_name}")
            print(f"    ↳ Lỗi: Không tìm thấy file text effect nào cho tiền tố '{prefix}' trong thư mục {EFFECTS_DIR}!")
            continue

        effect_filename = matching_effects[0]
        text_effect_path = os.path.join(EFFECTS_DIR, effect_filename)

        print(f" ⏳ [{index}/{len(input_videos)}] Đang xử lý: {video_name}")
        print(f"    ↳ Khớp với Text Effect: {effect_filename}...")

        # -------------------------------------------------------------------------
        # LỆNH FFMPEG
        cmd = [
            'ffmpeg', '-y',
            '-i', input_video_path,
            '-i', text_effect_path,
            '-filter_complex', 
            '[0:v]setpts=10/9*PTS[slowed]; '
            '[slowed]split=2[v1][v2]; '
            '[v2]hflip[v2_mirrored]; '
            '[v1][v2_mirrored]concat=n=2:v=1:a=0[bg_concat]; '
            '[bg_concat]scale=1080:1920,setsar=1[vid_scaled]; '
            '[1:v]scale=1080:1920,colorkey=0x00FF00:0.3:0.1[chroma_out]; '
            '[vid_scaled][chroma_out]overlay=x=(W-w)/2:y=(H-h)/2:shortest=1[video_out]',
            '-map', '[video_out]',
            '-map', '1:a',
            '-c:v', 'libx264',
            '-preset', 'slow',
            '-crf', '18',
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            '-b:a', '320k',
            output_video_path
        ]

        try:
            subprocess.run(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=True)
            print(f"    ✅ Thành công!")
        except subprocess.CalledProcessError:
            print(f"    ❌ Lỗi trong quá trình render FFmpeg!")

    print("\n🎉 ĐÃ XỬ LÝ XONG TOÀN BỘ! Bạn kiểm tra thư mục OUTPUT xem kết quả nhé.")

if __name__ == "__main__":
    auto_render_run()