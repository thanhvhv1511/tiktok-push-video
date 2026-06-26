from fastapi import FastAPI, BackgroundTasks, Form
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel  # ⚡ FIX 1: Import BaseModel để không bị lỗi crash NameError
import subprocess
import os
import sys

CORE_DIR = os.path.dirname(os.path.abspath(__file__))
SITE_DIR = os.path.dirname(CORE_DIR)

# ⚡ Định nghĩa file lưu PID tạm thời của phiên render đang chạy
PID_FILE = os.path.join(SITE_DIR, "data", "current_render.pid")

try:
    from dotenv import load_dotenv
    dotenv_path = os.path.join(SITE_DIR, ".env")
    load_dotenv(dotenv_path=dotenv_path)
    print(f"✅ [FASTAPI] Đã nạp cấu hình .env tại: {dotenv_path}")
except Exception as e:
    print(f"⚠️ [FASTAPI] Không nạp được file .env: {str(e)}")

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

LOG_FILE = os.getenv("LOG_FILE_PATH", os.path.join(SITE_DIR, "data", "process.log"))
PIPELINE_SCRIPT = os.getenv("PIPELINE_SCRIPT_PATH", os.path.join(CORE_DIR, "run-pipeline.js"))


def run_process(product_id: str = None, process_count: int = None):
    os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)
    
    with open(LOG_FILE, "a", encoding="utf-8") as f:
        f.write(f"\n==================================================\n")
        f.write(f"🚀 [FASTAPI DEBUG] Bắt đầu khởi chạy Background Task...\n")
        f.write(f"📍 Mã SP nhận được: {product_id if product_id else 'Trống'}\n")
        f.write(f"🔢 Số lượng yêu cầu: {process_count if process_count else 'Lấy hết'}\n")
        f.flush()

    if not os.path.exists(PIPELINE_SCRIPT):
        with open(LOG_FILE, "a", encoding="utf-8") as f:
            f.write(f"❌ [LỖI] Không tìm thấy file {PIPELINE_SCRIPT}\n")
        return

    custom_env = os.environ.copy()
    custom_env["PATH"] = "/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:" + custom_env.get("PATH", "")
    custom_env["NODE_ENV"] = "production"
    
    if product_id:
        custom_env["CURRENT_BATCH_PID"] = str(product_id)
    if process_count:
        custom_env["PROCESS_COUNT"] = str(process_count)

    try:
        # Gọi luồng Node.js chạy ngầm bất đồng bộ
        process = subprocess.Popen(
            ["node", PIPELINE_SCRIPT],
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,  
            text=True,
            cwd=SITE_DIR,
            env=custom_env
        )

        # Ghi lại PID của tiến trình Node.js vừa mở vào file tạm
        with open(PID_FILE, "w") as pf:
            pf.write(str(process.pid))

        with open(LOG_FILE, "a", encoding="utf-8") as f:
            f.write(f"⚡ [FASTAPI DEBUG] Tiến trình Node.js đã mở thành công với PID: {process.pid}\n")
            f.flush()

        for line in process.stdout:
            sys.stdout.write(line)
            sys.stdout.flush()

        process.wait()
        
        with open(LOG_FILE, "a", encoding="utf-8") as f:
            f.write(f"\n🛑 [FASTAPI DEBUG] Tiến trình hoàn tất hoàn toàn với Exit Code: {process.returncode}\n")
            f.flush()

    except Exception as e:
        with open(LOG_FILE, "a", encoding="utf-8") as f:
            f.write(f"❌ [LỖI HỆ THỐNG] Bị gãy luồng: {str(e)}\n")
            f.flush()
    finally:
        if os.path.exists(PID_FILE):
            os.remove(PID_FILE)


@app.post("/run")
def trigger_run(
    background_tasks: BackgroundTasks, 
    product_id: str = Form(None),
    process_count: int = Form(None)
):
    if os.path.exists(PID_FILE):
        return {"status": "error", "message": "Xưởng đang có một tiến trình render chạy dở dưới nền!"}

    target_pid = product_id
    background_tasks.add_task(run_process, target_pid, process_count)
    return {"status": "success", "message": "Hệ thống Pipeline bắt đầu hoạt động..."}


# =========================================================================
# ⚡ ENDPOINT: TRUY QUÉT TẬN GỐC CẢ NODE, PYTHON VÀ FFMPEG
# =========================================================================
@app.post("/stop")
def stop_run():
    if not os.getenv("PIPELINE_SCRIPT_PATH") and not os.path.exists(PID_FILE):
        return {"status": "warning", "message": "Không tìm thấy tiến trình nào đang hoạt động dưới nền!"}
    
    try:
        pid = None
        if os.path.exists(PID_FILE):
            with open(PID_FILE, "r") as pf:
                pid = int(pf.read().strip())
        
        with open(LOG_FILE, "a", encoding="utf-8") as f:
            f.write(f"\n⚠️ [HỆ THỐNG] Người dùng bấm nút STOP từ UI Web. Kích hoạt truy quét diện rộng...\n")
            f.flush()

        subprocess.run(["pkill", "-f", "run-pipeline.js"])
        subprocess.run(["pkill", "-f", "auto_render.py"])
        subprocess.run(["killall", "ffmpeg"])
        
        if pid:
            subprocess.run(["kill", "-9", str(pid)])
        
        if os.path.exists(PID_FILE):
            os.remove(PID_FILE)
            
        with open(LOG_FILE, "a", encoding="utf-8") as f:
            f.write(f"🛑 [HỆ THỐNG] Đã hủy tiến trình vật lý hoàn toàn. Xưởng Render dừng hoạt động!\n")
            f.flush()

        return {"status": "success", "message": "Đã hủy toàn bộ cụm tiến trình Render thành công!"}
    except Exception as e:
        return {"status": "error", "message": f"Không thể hủy tiến trình: {str(e)}"}


# =========================================================================
# ⚡ ENDPOINT MỚI: DÀNH RIÊNG CHO BOT ĐĂNG TIKTOK ĐA LUỒNG
# =========================================================================
class BotPayload(BaseModel):
    account: str
    batch_id: str
    port: str
    account_id: str

def run_bot_process(payload: BotPayload):
    bot_log_file = os.path.join(SITE_DIR, "data", "bot_publish.log")
    os.makedirs(os.path.dirname(bot_log_file), exist_ok=True)
    
    # ⚡ FIX 2: Thống nhất tên biến môi trường thành BOT_SCRIPT_PATH cho chuẩn cấu hình chung
    default_script = os.path.join(SITE_DIR, "push-video", "2-run-upload.js")
    bot_script = os.getenv("BOT_SCRIPT_PATH", default_script)
    
    working_dir = os.path.dirname(bot_script) if os.path.exists(bot_script) else os.path.join(SITE_DIR, "push-video")

    with open(bot_log_file, "a", encoding="utf-8") as f:
        f.write(f"\n==================================================\n")
        f.write(f"🚀 [BOT TIKTOK] Nhận lệnh chạy kênh: {payload.account}\n")
        f.write(f"📍 Script thực thi: {bot_script}\n") 
        f.write(f"📦 Đợt: {payload.batch_id} | Port: {payload.port}\n")
        f.flush()

    if not os.path.exists(bot_script):
        with open(bot_log_file, "a", encoding="utf-8") as f:
            f.write(f"❌ [LỖI] Không tìm thấy script Bot tại: {bot_script}\n")
        return

    custom_env = os.environ.copy()
    custom_env["PATH"] = "/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:" + custom_env.get("PATH", "")
    
    try:
        cmd = ["node", bot_script, payload.account, payload.batch_id, payload.port, payload.account_id]
        
        process = subprocess.Popen(
            cmd,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            cwd=working_dir, 
            env=custom_env
        )

        for line in process.stdout:
            sys.stdout.write(line)
            sys.stdout.flush()
            
        process.wait()
        
        with open(bot_log_file, "a", encoding="utf-8") as f:
            f.write(f"✅ [BOT TIKTOK] Hoàn tất luồng cho kênh {payload.account} (Exit Code: {process.returncode})\n")
            f.flush()

    except Exception as e:
        with open(bot_log_file, "a", encoding="utf-8") as f:
            f.write(f"❌ [LỖI HỆ THỐNG BOT] {str(e)}\n")
            f.flush()


@app.post("/run-bot")
def trigger_bot(payload: BotPayload, background_tasks: BackgroundTasks):
    # Khác với luồng Render bị khóa PID (chỉ chạy 1 lần), luồng Bot có thể nhận nhiều request 
    # và chạy song song (Multi-threading) nhờ cơ chế ném thẳng vào background_tasks độc lập.
    background_tasks.add_task(run_bot_process, payload)
    
    # ⚡ FIX 3: Sửa dấu '=>' thành dấu ':' chuẩn cú pháp Python Dictionary
    return {"status": "success", "message": f"Đã phát lệnh nổ máy Bot cho kênh {payload.account}!"}


# =========================================================================
# ⚡ ENDPOINT: DỌN DẸP CHROME KẸT TỪ GIAO DIỆN WEB
# =========================================================================
@app.post("/kill-chrome")
def kill_chrome():
    try:
        # FastAPI chạy ngoài Mac nên có quyền gọi thẳng pkill của Mac
        subprocess.run(["pkill", "-9", "-f", "Google Chrome"])
        
        with open(LOG_FILE, "a", encoding="utf-8") as f:
            f.write(f"\n🧹 [HỆ THỐNG] Đã nhận lệnh dọn dẹp và ép đóng toàn bộ Chrome ngầm!\n")
            f.flush()
            
        return {"status": "success", "message": "Đã ép đóng toàn bộ Chrome ngầm trên Mac!"}
    except Exception as e:
        return {"status": "error", "message": f"Lỗi khi dọn Chrome: {str(e)}"}

# =========================================================================

if __name__ == "__main__":
    import uvicorn
    port = int(os.getenv("API_PORT", 9001))
    uvicorn.run(app, host="0.0.0.0", port=port)