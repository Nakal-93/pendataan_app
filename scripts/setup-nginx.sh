#!/bin/bash

# 🔧 Setup Script for Nginx Auto-Deploy
# =====================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

echo "🔧 Setting up Pendataan OPD with Nginx Auto-Deploy"
echo "=================================================="

# Check OS
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    OS="linux"
elif [[ "$OSTYPE" == "darwin"* ]]; then
    OS="macos"
elif [[ "$OSTYPE" == "msys" || "$OSTYPE" == "cygwin" ]]; then
    OS="windows"
else
    log_error "Unsupported operating system: $OSTYPE"
    exit 1
fi

log_info "Detected OS: $OS"

# Install Docker (if not installed)
install_docker() {
    if command -v docker &> /dev/null; then
        log_success "Docker is already installed"
        return
    fi

    log_info "Installing Docker..."
    
    case $OS in
        "linux")
            # Ubuntu/Debian
            if command -v apt &> /dev/null; then
                sudo apt update
                sudo apt install -y apt-transport-https ca-certificates curl gnupg lsb-release
                curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
                echo "deb [arch=amd64 signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
                sudo apt update
                sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
                sudo usermod -aG docker $USER
            # CentOS/RHEL
            elif command -v yum &> /dev/null; then
                sudo yum install -y yum-utils
                sudo yum-config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
                sudo yum install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
                sudo systemctl start docker
                sudo systemctl enable docker
                sudo usermod -aG docker $USER
            fi
            ;;
        "macos")
            if command -v brew &> /dev/null; then
                brew install --cask docker
                log_warning "Please start Docker Desktop manually"
            else
                log_error "Homebrew not found. Please install Docker Desktop manually from https://docker.com/products/docker-desktop"
                exit 1
            fi
            ;;
        "windows")
            log_error "Please install Docker Desktop for Windows manually from https://docker.com/products/docker-desktop"
            exit 1
            ;;
    esac
    
    log_success "Docker installation completed"
}

# Install Docker Compose (if not installed)
install_docker_compose() {
    if command -v docker-compose &> /dev/null; then
        log_success "Docker Compose is already installed"
        return
    fi

    log_info "Installing Docker Compose..."
    
    if [[ "$OS" == "linux" ]]; then
        sudo curl -L "https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
        sudo chmod +x /usr/local/bin/docker-compose
    elif [[ "$OS" == "macos" ]]; then
        brew install docker-compose
    fi
    
    log_success "Docker Compose installation completed"
}

# Setup environment files
setup_environment() {
    log_info "Setting up environment files..."
    
    # Create .env file if not exists
    if [ ! -f .env ]; then
        cp .env.example .env
        log_success "Created .env file from example"
    else
        log_warning ".env file already exists"
    fi
    
    # Create production environment
    if [ ! -f .env.production ]; then
        cat > .env.production << 'EOF'
# Production Environment Configuration
DB_HOST=db
DB_NAME=aplikasi_madiun
DB_USER=root
DB_PASS=your_secure_password_here
DB_CHARSET=utf8mb4

APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

SESSION_TIMEOUT=3600
CSRF_TOKEN_LIFETIME=7200
MAX_LOGIN_ATTEMPTS=3

# Email Configuration
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="Pendataan OPD"

# SSL/Security
FORCE_HTTPS=true
SECURE_COOKIES=true
EOF
        log_success "Created .env.production file"
    fi
    
    # Create staging environment
    if [ ! -f .env.staging ]; then
        cat > .env.staging << 'EOF'
# Staging Environment Configuration
DB_HOST=db
DB_NAME=aplikasi_madiun_staging
DB_USER=root
DB_PASS=staging_password
DB_CHARSET=utf8mb4

APP_ENV=staging
APP_DEBUG=true
APP_URL=https://staging.your-domain.com

SESSION_TIMEOUT=3600
CSRF_TOKEN_LIFETIME=7200
MAX_LOGIN_ATTEMPTS=5

# Email Configuration (use staging SMTP)
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=staging_username
MAIL_PASSWORD=staging_password
EOF
        log_success "Created .env.staging file"
    fi
}

# Create necessary directories
create_directories() {
    log_info "Creating necessary directories..."
    
    directories=(
        "logs"
        "logs/nginx"
        "logs/php"
        "cache"
        "uploads"
        "docker/mysql"
    )
    
    for dir in "${directories[@]}"; do
        if [ ! -d "$dir" ]; then
            mkdir -p "$dir"
            log_info "Created directory: $dir"
        fi
    done
    
    # Set permissions
    chmod 755 logs cache uploads
    chmod 777 logs/nginx logs/php
    
    log_success "Directory structure created"
}

# Setup database initialization
setup_database() {
    log_info "Setting up database initialization..."
    
    if [ ! -f docker/mysql/init.sql ]; then
        cat > docker/mysql/init.sql << 'EOF'
-- Database initialization for Pendataan OPD
CREATE DATABASE IF NOT EXISTS aplikasi_madiun CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aplikasi_madiun;

-- Create aplikasi_opd table
CREATE TABLE IF NOT EXISTS `aplikasi_opd` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `perangkat_daerah` varchar(255) NOT NULL,
  `nama_aplikasi` varchar(255) NOT NULL,
  `deskripsi_aplikasi` text,
  `domain_aplikasi` varchar(255),
  `jenis_aplikasi` enum('Daerah','Pusat','Lainnya') NOT NULL,
  `status_aplikasi` enum('Aktif','Tidak Aktif') NOT NULL,
  `nama_pengelola` varchar(255) NOT NULL,
  `nomor_wa` varchar(20) NOT NULL,
  `email_pengelola` varchar(255),
  `tanggal_input` datetime DEFAULT CURRENT_TIMESTAMP,
  `tanggal_update` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ip_address` varchar(45),
  PRIMARY KEY (`id`),
  INDEX `idx_perangkat_daerah` (`perangkat_daerah`),
  INDEX `idx_nama_aplikasi` (`nama_aplikasi`),
  INDEX `idx_jenis_aplikasi` (`jenis_aplikasi`),
  INDEX `idx_status_aplikasi` (`status_aplikasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create admin table
CREATE TABLE IF NOT EXISTS `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100),
  `full_name` varchar(100),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime,
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin (password: admin123)
INSERT IGNORE INTO `admin` (`username`, `password`, `email`, `full_name`) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@madiunkab.go.id', 'Administrator');

-- Create audit log table
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11),
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100),
  `record_id` int(11),
  `old_values` json,
  `new_values` json,
  `ip_address` varchar(45),
  `user_agent` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `admin`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create sessions table
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11),
  `ip_address` varchar(45),
  `user_agent` text,
  `data` text,
  `last_activity` int(11),
  PRIMARY KEY (`id`),
  INDEX `idx_last_activity` (`last_activity`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data
INSERT IGNORE INTO `aplikasi_opd` (
  `perangkat_daerah`, `nama_aplikasi`, `deskripsi_aplikasi`, 
  `domain_aplikasi`, `jenis_aplikasi`, `status_aplikasi`, 
  `nama_pengelola`, `nomor_wa`, `email_pengelola`, `ip_address`
) VALUES 
('Dinas Komunikasi dan Informatika', 'SIMPEG', 'Sistem Informasi Manajemen Kepegawaian', 'simpeg.madiunkab.go.id', 'Daerah', 'Aktif', 'John Doe', '081234567890', 'john@madiunkab.go.id', '127.0.0.1'),
('Bappeda', 'e-Planning', 'Sistem Perencanaan Elektronik', 'planning.madiunkab.go.id', 'Daerah', 'Aktif', 'Jane Smith', '081234567891', 'jane@madiunkab.go.id', '127.0.0.1');
EOF
        log_success "Created database initialization script"
    fi
}

# Setup SSL certificates
setup_ssl() {
    log_info "Setting up SSL certificates for development..."
    
    mkdir -p docker/nginx/ssl
    
    if [ ! -f docker/nginx/ssl/cert.pem ]; then
        # Create OpenSSL config for SAN
        cat > docker/nginx/ssl/openssl.conf << 'EOF'
[req]
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no

[req_distinguished_name]
C = ID
ST = East Java
L = Madiun
O = Kabupaten Madiun
OU = IT Department
CN = pendataan.local

[v3_req]
keyUsage = keyEncipherment, dataEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names

[alt_names]
DNS.1 = pendataan.local
DNS.2 = localhost
DNS.3 = *.pendataan.local
IP.1 = 127.0.0.1
IP.2 = ::1
EOF

        # Generate certificate
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout docker/nginx/ssl/key.pem \
            -out docker/nginx/ssl/cert.pem \
            -config docker/nginx/ssl/openssl.conf \
            -extensions v3_req 2>/dev/null || {
            log_warning "Failed to generate SSL certificate with SAN, using simple certificate..."
            openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
                -keyout docker/nginx/ssl/key.pem \
                -out docker/nginx/ssl/cert.pem \
                -subj "/C=ID/ST=East Java/L=Madiun/O=Kabupaten Madiun/OU=IT Department/CN=pendataan.local"
        }
        
        chmod 600 docker/nginx/ssl/key.pem
        chmod 644 docker/nginx/ssl/cert.pem
        
        log_success "SSL certificates generated"
    fi
}

# Setup hosts file
setup_hosts() {
    log_info "Setting up hosts file..."
    
    # Check if entry exists
    if ! grep -q "pendataan.local" /etc/hosts 2>/dev/null; then
        echo "127.0.0.1 pendataan.local" | sudo tee -a /etc/hosts > /dev/null
        log_success "Added pendataan.local to hosts file"
    else
        log_warning "pendataan.local already exists in hosts file"
    fi
}

# Make scripts executable
setup_scripts() {
    log_info "Setting up scripts..."
    
    chmod +x scripts/*.sh
    chmod +x docker/nginx/ssl/generate-cert.sh 2>/dev/null || true
    
    log_success "Scripts are now executable"
}

# Test basic functionality
test_setup() {
    log_info "Testing setup..."
    
    # Test Docker
    if docker --version > /dev/null 2>&1; then
        log_success "Docker is working"
    else
        log_error "Docker test failed"
        return 1
    fi
    
    # Test Docker Compose
    if docker-compose --version > /dev/null 2>&1; then
        log_success "Docker Compose is working"
    else
        log_error "Docker Compose test failed"
        return 1
    fi
    
    # Test file permissions
    if [ -x scripts/deploy-nginx.sh ]; then
        log_success "Scripts are executable"
    else
        log_error "Scripts are not executable"
        return 1
    fi
    
    log_success "All tests passed!"
}

# Display final instructions
show_instructions() {
    echo ""
    echo "🎉 Setup completed successfully!"
    echo "================================"
    echo ""
    echo "📋 Next Steps:"
    echo "1. Edit .env file with your configuration"
    echo "2. Start the application:"
    echo "   ./scripts/deploy-nginx.sh deploy"
    echo ""
    echo "🌐 After deployment, access your application at:"
    echo "   - HTTP:  http://localhost"
    echo "   - HTTPS: https://pendataan.local (self-signed certificate)"
    echo "   - Admin: http://localhost/admin.php"
    echo "   - phpMyAdmin: http://localhost:8080"
    echo ""
    echo "🔧 Useful Commands:"
    echo "   - Deploy: ./scripts/deploy-nginx.sh deploy"
    echo "   - Status: ./scripts/deploy-nginx.sh status"
    echo "   - Logs:   ./scripts/deploy-nginx.sh logs"
    echo "   - Stop:   ./scripts/deploy-nginx.sh stop"
    echo ""
    echo "📚 Documentation:"
    echo "   - README.md for complete guide"
    echo "   - CONTRIBUTING.md for development"
    echo "   - SECURITY.md for security guidelines"
    echo ""
    echo "🔐 Default Credentials:"
    echo "   - Admin: admin / admin123"
    echo "   - MySQL: root / rootpassword"
    echo ""
    log_warning "Remember to change default passwords in production!"
}

# Main setup function
main() {
    echo "Starting setup process..."
    
    install_docker
    install_docker_compose
    setup_environment
    create_directories
    setup_database
    setup_ssl
    setup_hosts
    setup_scripts
    test_setup
    
    show_instructions
}

# Run main function
main "$@"
