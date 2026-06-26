# 1. Chạy API 
pm2 start python3 --name "auto-push-api" -- /Users/thanhvhv/personal/tool/site/core/app.py
cd /Users/thanhvhv/personal/tool/site/core
pm2 start /Users/thanhvhv/personal/tool/site/core/worker.js --name "auto-render-worker"

# Lưu trạng thái để máy Mac khởi động lại nó tự chạy
pm2 save
## Nếu muốn kill hoặc xem log
pm2 logs auto-push-api
pm2 stop auto-push-api
pm2 reload auto-push-api
pm2 reload auto-render-worker

# 2. Vào browser
/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --remote-debugging-port=9222 --user-data-dir="/Users/thanhvhv/personal/tool/tiktok-browser" --profile-directory="thanhkx2000" --no-first-run https://www.tiktok.com
/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --remote-debugging-port=9222 --user-data-dir="/Users/thanhvhv/personal/tool/tiktok-browser" --profile-directory="thanha1k43" --no-first-run https://www.tiktok.com
/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --remote-debugging-port=9222 --user-data-dir="/Users/thanhvhv/personal/tool/tiktok-browser" --profile-directory="hoangvuhust2002" --no-first-run https://www.tiktok.com

# tiktok-push-video
