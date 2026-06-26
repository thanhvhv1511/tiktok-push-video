-- Ép MySQL sử dụng UTF-8 ngay từ lúc đọc file này
SET NAMES utf8mb4;
SET character_set_client = utf8mb4;

-- Xóa theo thứ tự ngược để tránh lỗi khóa ngoại
DROP TABLE IF EXISTS render_logs;
DROP TABLE IF EXISTS render_queue;
DROP TABLE IF EXISTS video_publish_history;
DROP TABLE IF EXISTS processed_videos;
DROP TABLE IF EXISTS auto_push_configs;
DROP TABLE IF EXISTS video_publish_logs;
DROP TABLE IF EXISTS product_sales;
DROP TABLE IF EXISTS accounts;
DROP TABLE IF EXISTS products;

-- 1. BẢNG SẢN PHẨM
CREATE TABLE products (
    product_id VARCHAR(50) PRIMARY KEY,
    item_id VARCHAR(100),
    name VARCHAR(255) NOT NULL,
    caption TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    units_sold INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO products (product_id, item_id, name, caption) VALUES
('sp001', '1730533234178689632', 'Bộ thể thao nữ 2 lớp', 'Bộ thể thao nữ 2 lớp, quần có túi tiện lợi, co giãn #xuhuong #thoitrangnu #setthethao'),
('sp002', '1730566004091095683', 'Set ngủ nữ kẻ caro', 'Set ngủ nữ kẻ caro chất đũi nhăn mềm mại, thoáng mát #donguxinh #thoitrangnu #dongunu'),
('sp003', '1729851503422180335', 'Áo khoác gym nữ thun lạnh', 'Áo khoác gym nữ thun lạnh co giãn, thấm hút môi hôi #xuhuong #thoitrangnu #aokhoacnu'),
('sp004', '1735778501014881843', 'Bộ Nữ Thun Lạnh Có Túi', 'Bộ Nữ Thun Lạnh Có Túi Form Rộng Chất Vải Visco Mềm Mịn #thoitrangnu #xuhuong #thunlanh'),
('sp005', 'HL41', 'Set Đồ Nữ Cami Áo 2 Dây', 'HL41-Set Đồ Nữ Cami Áo 2 Dây [ CÓ MÚT VÀ KHÔNG MÚT ] + Quần Short Kẻ Sọc Mềm, Thoáng Mát – Đồ Bộ Mặc Nhà, Set áo hai dây kẻ sọc mặc nhà hay đi dạo phố đều xinh xỉu up xỉu down #vayhe #setdonu'),
('sp006', '1731335971693823057', 'Combo 2 Bộ Pijama Nữ', 'Combo 2 Bộ Pijama Nữ – Mặc Mát, Xinh #donguxinh #thoitrangnu #dongunu'),
('sp007', '1735402037700560774', 'Set Đồ Nữ Cami 2 Dây', 'Set Đồ Nữ Cami 2 Dây [ CÓ MÚT VÀ KHÔNG MÚT ] + Quần Short Kẻ Sọc #thoitrangnu #ao2day #xuhuong'),
('sp008', '1734445059999499977', 'Bộ quần áo nữ họa tiết chữ', 'Bộ quần áo nữ, họa tiết chữ, chất liệu cotton, mát, co giãn #thoitrangnu #xuhuong'),
('sp009', '1732500060254340203', 'Áo 2 dây thắt eo', 'CÓ MÚT, KHÔNG MÚT, Áo 2 dây, thắt eo, siêu tôn dáng #thoitrangnu #ao2day #xuhuong'),
('sp010', '1731778107575535462', 'Áo sơ mi đũi cổ tim phối ren', 'Áo sơ mi đũi cổ tim phối ren, style Hàn Quốc, dáng babydoll #thoitrangnu #xuhuong'),
('sp011', '1730677749987706961', 'Combo 3 bộ Pijama mặc nhà', 'Combo 3 bộ Pijama mặc nhà nhiều hình cute, có túi áo #thoitrangnu #donguxinh #xuhuong'),
('sp012', '1735207798918973339', 'Quần ngủ Nữ ống rộng', 'Quần ngủ Nữ ống rộng, quần pijama đũi xốp mỏng nhẹ dễ thương #thoitrangnu #quanngu #xuhuong');

-- 2. BẢNG TÀI KHOẢN
CREATE TABLE accounts (
    account_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    cdp_port INT NOT NULL UNIQUE -- ⚡ ĐÃ THÊM: Đảm bảo các luồng profile Chrome không bao giờ bị trùng Port CDP với nhau
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm dữ liệu mẫu khớp với tên Profile thực tế trên Mac của bạn để chạy bot mượt mà
INSERT INTO accounts (name, cdp_port) VALUES 
('nangshop-thanhkx2000', 9222), 
('xinhshop-thanha1k43', 9223), 
('nangstore-hoangvuhust2004', 9224);

-- 3. BẢNG LỊCH SỬ SỐ BÁN
CREATE TABLE product_sales (
    product_id VARCHAR(50),
    account_id INT,
    sale_date DATE,
    quantity INT DEFAULT 0,
    PRIMARY KEY (product_id, account_id, sale_date),
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(account_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. BẢNG LỊCH SỬ ĐĂNG VIDEO
CREATE TABLE video_publish_logs (
    product_id VARCHAR(50),
    account_id INT,
    publish_date DATE,
    videos_posted INT DEFAULT 0,
    PRIMARY KEY (product_id, account_id, publish_date),
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(account_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BẢNG CẤU HÌNH
CREATE TABLE auto_push_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note VARCHAR(255) NOT NULL,
    is_pinned TINYINT(1) DEFAULT 0,
    config_data JSON NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. BẢNG QUẢN LÝ VIDEO (Bảng cha của video_publish_history)
CREATE TABLE processed_videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(50) NULL,
    product_id VARCHAR(50),
    original_filename VARCHAR(255) NOT NULL,
    processed_filename VARCHAR(255) NOT NULL,
    publish_time DATETIME NULL,
    status ENUM('ready', 'scheduled', 'pushed', 'used', 'error') DEFAULT 'ready',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL, 
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    INDEX idx_product_status (product_id, status),
    INDEX idx_batch_id (batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. BẢNG LỊCH SỬ ĐĂNG TỪNG KÊNH (Nâng cấp để Tracking Log Đa Luồng)
CREATE TABLE video_publish_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id INT,
    account_id INT,
    status ENUM('PENDING', 'PROCESSING', 'SUCCESS', 'ERROR', 'CANCELLED') DEFAULT 'PENDING', -- ⚡ Trạng thái chi tiết của từng video trên kênh đó
    error_message TEXT NULL, -- ⚡ Lưu nguyên nhân nếu Bot báo lỗi (kẹt mạng, sai định dạng...)
    pushed_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- ⚡ Tự động cập nhật giờ mỗi khi Bot đổi trạng thái
    FOREIGN KEY (video_id) REFERENCES processed_videos(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(account_id) ON DELETE CASCADE,
    UNIQUE KEY unique_publish (video_id, account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. BẢNG HÀNG ĐỢI RENDER
CREATE TABLE render_queue (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id VARCHAR(50) NOT NULL,
    process_count INT DEFAULT 0,
    status ENUM('pending', 'running', 'done', 'error') DEFAULT 'pending',
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. BẢNG NHẬT KÝ RENDER
CREATE TABLE render_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT,
    product_id VARCHAR(50) NOT NULL,
    log_level ENUM('INFO', 'WARNING', 'ERROR') DEFAULT 'INFO',
    message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES render_queue(task_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;