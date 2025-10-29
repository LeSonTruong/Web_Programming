# HNM Chia Sẻ Tài Liệu

## 🚀 Setup Project

### 1. Clone repository
```bash
git clone https://github.com/ricecracker12/hnm_chiasetailieu.git
cd hnm_chiasetailieu
```

### 2. Setup PHP Dependencies
```bash
composer install
```

### 3. Setup Environment Variables
```bash
# Copy và chỉnh sửa file .env
cp .env.example .env
# Cập nhật thông tin database trong .env
```

### 4. Setup Database
```bash
# Import database từ thư mục DB/
# Hoặc chạy migration scripts
```

### 5. Setup AI Service (Optional)
```bash
cd ai_service

# Tạo virtual environment
python -m venv venv

# Kích hoạt virtual environment
# Windows:
venv\Scripts\activate
# Linux/Mac:
source venv/bin/activate

# Cài đặt dependencies
pip install -r requirements.txt

# Download AI model
# (Thêm script download model hoặc hướng dẫn)
```

### 6. Setup Web Server
- Cấu hình web server (Apache/Nginx) point đến project root
- Đảm bảo thư mục `uploads/` có quyền write

## 📁 Project Structure
```
├── includes/          # Core PHP files
├── css/              # Stylesheets  
├── DB/               # Database files
├── ai_service/       # Python AI service
├── uploads/          # User uploaded files (gitignored)
├── vendor/           # PHP dependencies (gitignored)
└── *.php            # Main application files
```

## 🔧 Development Notes
- File `.env` chứa thông tin nhạy cảm (không commit)
- Thư mục `vendor/` và `ai_service/venv/` sẽ được tạo lại khi setup
- AI models cần download riêng do kích thước lớn