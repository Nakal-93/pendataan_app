# 📊 Aplikasi Pendataan OPD

tes tambah teks
Aplikasi web untuk pendataan dan pengelolaan aplikasi-aplikasi yang digunakan oleh Organisasi Perangkat Daerah (OPD) di Kabupaten Madiun.

## 🚀 Fitur Utama

### 📝 **Pendataan Aplikasi**
- Form input data aplikasi OPD
- Validasi input secara real-time
- Penyimpanan data dengan enkripsi
- Pencatatan IP address dan timestamp

### 👤 **Manajemen Admin**
- Login admin dengan keamanan berlapis
- Session management
- Logout otomatis untuk keamanan
- Rate limiting untuk mencegah brute force

### 📊 **Pengelolaan Data**
- Tampilan data dalam tabel responsif
- Pencarian dan filter data
- Export data ke Excel/CSV
- Detail lengkap setiap aplikasi

### 🔒 **Keamanan**
- CSRF Protection
- SQL Injection Prevention
- XSS Protection
- Session Security
- Input Validation & Sanitization

## 🛠️ Teknologi

- **Backend**: PHP 8.1+
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript
- **Server**: Apache 2.4+
- **Containerization**: Docker & Docker Compose

## 📋 Persyaratan Sistem

### Minimum Requirements:
- PHP 8.1 atau lebih tinggi
- MySQL 8.0 atau MariaDB 10.6+
- Apache 2.4+ dengan mod_rewrite
- RAM minimal 512MB
- Storage minimal 1GB

### Recommended:
- PHP 8.2+
- MySQL 8.0+
- Apache 2.4+ atau Nginx 1.20+
- RAM 2GB+
- SSD Storage 5GB+

## 🐳 Instalasi dengan Docker + Nginx (Recommended)

### 1. Clone Repository
```bash
git clone https://github.com/your-username/pendataan-opd.git
cd pendataan-opd
```

### 2. Auto Setup (Recommended)
```bash
# Jalankan setup otomatis
chmod +x scripts/setup-nginx.sh
./scripts/setup-nginx.sh
```

### 3. Manual Setup
```bash
# Copy file environment
cp .env.example .env

# Edit konfigurasi sesuai kebutuhan
nano .env

# Generate SSL certificates
mkdir -p docker/nginx/ssl
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout docker/nginx/ssl/key.pem \
    -out docker/nginx/ssl/cert.pem \
    -subj "/C=ID/ST=East Java/L=Madiun/O=Kabupaten Madiun/CN=pendataan.local"

# Add to hosts file
echo "127.0.0.1 pendataan.local" | sudo tee -a /etc/hosts
```

### 4. Deploy dengan Nginx
```bash
# Deploy menggunakan script otomatis
chmod +x scripts/deploy-nginx.sh
./scripts/deploy-nginx.sh deploy

# Atau manual dengan docker-compose
docker-compose -f docker-compose.nginx.yml up -d --build
```

### 5. Akses Aplikasi
- **HTTP**: http://localhost
- **HTTPS**: https://pendataan.local (self-signed)
- **Admin Panel**: http://localhost/admin.php
- **phpMyAdmin**: http://localhost:8080
- **Health Check**: http://localhost/health

## 🔧 Instalasi Manual

### 1. Persiapan Server
```bash
# Install dependencies (Ubuntu/Debian)
sudo apt update
sudo apt install apache2 php8.1 php8.1-mysql php8.1-mbstring php8.1-xml mysql-server

# Enable Apache modules
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 2. Download dan Setup
```bash
# Clone repository
git clone https://github.com/your-username/pendataan-opd.git
cd pendataan-opd

# Set permissions
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
```

### 3. Database Setup
```sql
-- Login ke MySQL
mysql -u root -p

-- Buat database
CREATE DATABASE aplikasi_madiun CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Buat user
CREATE USER 'pendataan_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON aplikasi_madiun.* TO 'pendataan_user'@'localhost';
FLUSH PRIVILEGES;

-- Import struktur database
USE aplikasi_madiun;
SOURCE docker/init.sql;
```

### 4. Konfigurasi
```bash
# Copy dan edit config
cp config.example.php config.php
nano config.php

# Set environment
cp .env.example .env
nano .env
```

## ⚙️ Konfigurasi

### Environment Variables (.env)
```env
# Database Configuration
DB_HOST=localhost
DB_NAME=aplikasi_madiun
DB_USER=pendataan_user
DB_PASS=secure_password
DB_CHARSET=utf8mb4

# Application Configuration
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Security Configuration
SESSION_TIMEOUT=3600
CSRF_TOKEN_LIFETIME=7200
MAX_LOGIN_ATTEMPTS=5

# Email Configuration (optional)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
```

### Apache Virtual Host
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/html/pendataan
    
    <Directory /var/www/html/pendataan>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Security headers
        Header always set X-Content-Type-Options nosniff
        Header always set X-Frame-Options DENY
        Header always set X-XSS-Protection "1; mode=block"
        Header always set Strict-Transport-Security "max-age=31536000"
    </Directory>
    
    # Hide sensitive files
    <Files "config.php">
        Require all denied
    </Files>
    
    <Files ".env">
        Require all denied
    </Files>
    
    ErrorLog ${APACHE_LOG_DIR}/pendataan_error.log
    CustomLog ${APACHE_LOG_DIR}/pendataan_access.log combined
</VirtualHost>
```

## 📱 Penggunaan

### 👨‍💼 Untuk Admin

1. **Login Admin**
   ```
   URL: /admin.php
   Username: admin
   Password: admin123 (ganti setelah login pertama)
   ```

2. **Kelola Data**
   - Lihat semua data aplikasi di `/manage.php`
   - Export data ke Excel di `/data_export.php`
   - Lihat detail aplikasi di `/data_detail.php?id={id}`

3. **Reset Password**
   - Gunakan fitur reset password di `/reset_password.php`
   - Link reset akan dikirim via email

### 👥 Untuk User/OPD

1. **Input Data Aplikasi**
   - Akses halaman utama `/`
   - Isi form pendataan aplikasi
   - Submit untuk menyimpan data

2. **Cek Status**
   - Data yang sudah disubmit akan masuk ke database
   - Admin dapat melihat dan memverifikasi data

## 🔄 Auto Deployment

### GitHub Actions Setup

1. **Setup Secrets di GitHub**
   ```
   Repository → Settings → Secrets and variables → Actions
   
   Secrets yang diperlukan:
   - HOST: IP/domain server
   - USERNAME: SSH username
   - SSH_KEY: Private SSH key
   - PORT: SSH port (biasanya 22)
   ```

2. **Deploy Otomatis**
   - Push ke branch `main` akan trigger auto-deploy
   - GitHub Actions akan menjalankan tests dan deploy
   - Notifikasi status via email/Slack

3. **Manual Deploy**
   ```bash
   # Jalankan script deploy
   ./scripts/deploy.sh
   
   # Atau dengan Docker
   docker-compose pull
   docker-compose up -d --build
   ```

## 🧪 Testing

### Unit Tests
```bash
# Test database connection
php tests/db_test.php

# Test form validation
php tests/validation_test.php

# Test security functions
php tests/security_test.php
```

### Manual Testing
```bash
# Test dengan curl
curl -X POST http://localhost:8080/1.php \
  -d "perangkat_daerah=Test" \
  -d "nama_aplikasi=Test App" \
  -d "jenis_aplikasi=Daerah" \
  -d "status_aplikasi=Aktif" \
  -d "nama_pengelola=Test User" \
  -d "nomor_wa=081234567890"
```

## 📊 Monitoring

### Log Files
```bash
# Application logs
tail -f logs/app.log

# Error logs
tail -f logs/error.log

# Access logs
tail -f /var/log/apache2/pendataan_access.log

# Docker logs
docker-compose logs -f web
```

### Database Monitoring
```sql
-- Cek performa query
SHOW PROCESSLIST;

-- Statistik tabel
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    DATA_LENGTH,
    INDEX_LENGTH
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'aplikasi_madiun';
```

## 🔒 Keamanan

### Best Practices
- Selalu update dependencies
- Gunakan HTTPS di production
- Backup database secara berkala
- Monitor logs untuk aktivitas mencurigakan
- Implement rate limiting
- Gunakan strong passwords

### Security Checklist
- [ ] HTTPS enabled
- [ ] Database credentials secure
- [ ] File permissions correct (644/755)
- [ ] Error reporting disabled in production
- [ ] Security headers configured
- [ ] Input validation implemented
- [ ] SQL injection prevention
- [ ] XSS protection
- [ ] CSRF protection
- [ ] Session security

## 💾 Backup & Restore

### Database Backup
```bash
# Backup dengan Docker
docker-compose exec db mysqldump -uroot -prootpassword aplikasi_madiun > backup_$(date +%Y%m%d_%H%M%S).sql

# Backup manual
mysqldump -u username -p aplikasi_madiun > backup.sql
```

### File Backup
```bash
# Backup aplikasi
tar -czf pendataan_backup_$(date +%Y%m%d).tar.gz \
  --exclude='logs/*' \
  --exclude='cache/*' \
  .
```

### Restore
```bash
# Restore database
docker-compose exec -T db mysql -uroot -prootpassword aplikasi_madiun < backup.sql

# Restore files
tar -xzf pendataan_backup.tar.gz
```

## 🐛 Troubleshooting

### Common Issues

**Database Connection Error**
```bash
# Cek status MySQL
sudo systemctl status mysql

# Test koneksi
php -r "
try {
    \$pdo = new PDO('mysql:host=localhost;dbname=aplikasi_madiun', 'user', 'pass');
    echo 'Connected successfully';
} catch(PDOException \$e) {
    echo 'Connection failed: ' . \$e->getMessage();
}
"
```

**Permission Issues**
```bash
# Set correct permissions
sudo chown -R www-data:www-data /var/www/html/pendataan
sudo chmod -R 755 /var/www/html/pendataan
sudo chmod -R 777 logs/
```

**Apache Issues**
```bash
# Cek Apache status
sudo systemctl status apache2

# Cek error logs
sudo tail -f /var/log/apache2/error.log

# Test Apache config
sudo apache2ctl configtest
```

### Debug Mode
```php
// Enable di config.php untuk development
define('APP_DEBUG', true);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
```

## 📞 Support

### Contact Information
- **Developer**: [Your Name]
- **Email**: developer@madiunkab.go.id
- **Phone**: +62-xxx-xxxx-xxxx
- **Documentation**: [Wiki Link]

### Reporting Issues
1. Buka issue di GitHub repository
2. Sertakan informasi:
   - PHP version
   - MySQL version
   - Error message
   - Steps to reproduce

### Feature Requests
- Gunakan GitHub Issues dengan label "enhancement"
- Jelaskan kebutuhan dan use case
- Sertakan mockup jika ada

## 📄 License

MIT License - see [LICENSE](LICENSE) file for details.

## 🔄 Changelog

### v2.0.0 (2025-08-21)
- ✨ Docker support
- 🔒 Enhanced security features
- 🚀 Auto-deployment pipeline
- 📊 Improved admin dashboard
- 🐛 Bug fixes and performance improvements

### v1.5.0 (2025-07-15)
- 📱 Responsive design
- 📊 Data export features
- 🔍 Advanced search and filtering
- 🔐 Password reset functionality

### v1.0.0 (2025-06-01)
- 🎉 Initial release
- 📝 Basic CRUD operations
- 👤 Admin authentication
- 📊 Data management

## 🤝 Contributing

1. Fork repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

### Development Guidelines
- Follow PSR-12 coding standards
- Write meaningful commit messages
- Add tests for new features
- Update documentation
- Use semantic versioning

---

**📌 Made with ❤️ for Kabupaten Madiun**

*Untuk informasi lebih lanjut, silakan hubungi tim developer atau buka dokumentasi lengkap di wiki repository.*
