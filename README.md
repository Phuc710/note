# 📝 Note App - Hệ thống ghi chú đa năng

> Ứng dụng ghi chú hiện đại với tính năng upload ảnh tự động nén và rút gọn link

## ✨ Tính năng chính

### 📒 Quản lý ghi chú
- Tạo, sửa, xóa ghi chú với trình soạn thảo rich text (Quill.js)
- Tổ chức ghi chú theo thư mục (folder/subfolder)
- Chia sẻ ghi chú công khai qua link
- Drag & drop để di chuyển ghi chú giữa các thư mục
- Dark mode hỗ trợ

### 🖼️ Upload ảnh thông minh
- Upload nhiều ảnh cùng lúc
- **Tự động nén ảnh** giảm dung lượng (resize nếu > 1920px)
- Chuyển PNG không có transparency sang JPG
- Hỗ trợ: JPG, PNG, GIF, WEBP
- Nhận link ngay: Direct URL, HTML, Markdown, BBCode
- **Lưu file vào `/uploads/`** và metadata vào database

### 🔗 Rút gọn link
- Tạo link ngắn với custom alias
- Theo dõi số lượt click
- Quản lý tất cả link đã tạo

## 🚀 Cài đặt

### Yêu cầu
- PHP 7.4+
- MySQL/MariaDB
- Extension: GD, PDO

### Các bước cài đặt

1. **Clone/Download source code**
```bash
git clone <repo-url>
cd note
```

2. **Tạo database**
```sql
CREATE DATABASE note CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. **Import database**
```bash
mysql -u root -p note < note.sql
```

4. **Cấu hình database** - Sửa file `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'note');
define('DB_USER', 'root');
define('DB_PASS', '');
```

5. **Phân quyền thư mục uploads**
```bash
chmod 755 uploads/
chmod 755 uploader/uploads/
```

6. **Truy cập ứng dụng**
```
http://localhost/note/
```

### Tài khoản mặc định
- **Username:** `admin`
- **Password:** `admin` (đổi ngay sau khi đăng nhập)

## 📁 Cấu trúc thư mục

```
note/
├── config.php          # Cấu hình database & timezone
├── database.php        # Kết nối database (PDO)
├── index.php           # Trang chủ - quản lý ghi chú
├── api.php             # REST API cho ghi chú
├── upload.php          # API upload & nén ảnh
├── login.php           # Đăng nhập
├── logout.php          # Đăng xuất
├── manage.php          # Quản lý user (admin)
├── all.php             # Xem tất cả ghi chú/link/ảnh
├── share.php           # Xem ghi chú được chia sẻ
├── shares.php          # Quản lý các link chia sẻ
├── note.sql            # Database schema
├── uploads/            # Thư mục lưu ảnh đã upload
├── src/                # CSS & JS
│   ├── index.css
│   ├── index.js
│   └── theme.css
├── shortener/          # Module rút gọn link
│   ├── index.php
│   └── redirect.php
└── uploader/           # Module upload ảnh
    └── index.php
```

## 🗄️ Database

### Bảng chính
- **users** - Quản lý người dùng (admin/user)
- **notes** - Lưu ghi chú & thư mục (tree structure)
- **shares** - Link chia sẻ ghi chú công khai
- **short_links** - Link rút gọn
- **uploaded_images** - Metadata ảnh đã upload

### Lưu ý quan trọng
- File ảnh được lưu vật lý vào thư mục `/uploads/`
- Metadata (tên file, kích thước, MIME type, user_id) được lưu vào bảng `uploaded_images`
- Có thể theo dõi và quản lý ảnh đã upload qua database

## 🎨 Công nghệ sử dụng

- **Backend:** PHP 8.0, MySQL
- **Frontend:** Tailwind CSS, Quill.js (rich text editor)
- **Icons:** Font Awesome 6
- **Image Processing:** PHP GD Library

## 📝 Hướng dẫn sử dụng

### Upload ảnh
1. Vào menu **Công cụ** → **Upload ảnh**
2. Kéo thả hoặc chọn nhiều ảnh
3. Hệ thống tự động nén và trả về link
4. Copy link theo format mong muốn (URL, HTML, Markdown...)

### Rút gọn link
1. Vào menu **Công cụ** → **Rút gọn link**
2. Nhập URL gốc và custom alias (tùy chọn)
3. Nhận link ngắn và theo dõi số click

### Chia sẻ ghi chú
1. Mở ghi chú cần chia sẻ
2. Click nút **Chia sẻ**
3. Tạo link công khai (không cần đăng nhập để xem)

## 🔒 Bảo mật

- Password được hash bằng `password_hash()` (bcrypt)
- Session-based authentication
- SQL injection protection (PDO prepared statements)
- File upload validation (MIME type check)
- XSS protection (htmlspecialchars)

## 📄 License

MIT License - Tự do sử dụng và chỉnh sửa

## 🤝 Đóng góp

Mọi đóng góp đều được chào đón! Tạo issue hoặc pull request.

---

**Phát triển bởi:** [Tên của bạn]  
**Version:** 1.0.0  
**Ngày cập nhật:** 02/11/2025