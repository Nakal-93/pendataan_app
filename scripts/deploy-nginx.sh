#!/bin/bash

# 🚀 Nginx Auto-Deploy Script for Pendataan OPD
# ==============================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
APP_NAME="pendataan-opd"
DOCKER_COMPOSE_FILE="docker-compose.nginx.yml"
BACKUP_DIR="/tmp/pendataan-backup-$(date +%Y%m%d_%H%M%S)"

# Functions
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

check_requirements() {
    log_info "Checking requirements..."
    
    # Check Docker
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed!"
        exit 1
    fi
    
    # Check Docker Compose
    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose is not installed!"
        exit 1
    fi
    
    # Check if compose file exists
    if [ ! -f "$DOCKER_COMPOSE_FILE" ]; then
        log_error "Docker Compose file ($DOCKER_COMPOSE_FILE) not found!"
        exit 1
    fi
    
    log_success "All requirements met!"
}

create_backup() {
    log_info "Creating backup..."
    
    mkdir -p "$BACKUP_DIR"
    
    # Backup database
    if docker-compose -f "$DOCKER_COMPOSE_FILE" ps | grep -q "pendataan_db"; then
        log_info "Backing up database..."
        docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T db \
            mysqldump -uroot -prootpassword aplikasi_madiun > "$BACKUP_DIR/database.sql" 2>/dev/null || {
            log_warning "Database backup failed, continuing..."
        }
    fi
    
    # Backup application files (exclude logs and cache)
    log_info "Backing up application files..."
    tar -czf "$BACKUP_DIR/application.tar.gz" \
        --exclude='logs/*' \
        --exclude='cache/*' \
        --exclude='.git/*' \
        --exclude='node_modules/*' \
        --exclude='vendor/*' \
        . 2>/dev/null || {
        log_warning "Application backup failed, continuing..."
    }
    
    log_success "Backup created at: $BACKUP_DIR"
}

pull_updates() {
    log_info "Pulling latest updates from Git..."
    
    # Check if git repository
    if [ -d ".git" ]; then
        # Save current branch
        CURRENT_BRANCH=$(git branch --show-current)
        
        # Fetch latest changes
        git fetch origin
        
        # Pull updates
        git pull origin "$CURRENT_BRANCH"
        
        log_success "Git updates pulled successfully!"
    else
        log_warning "Not a Git repository, skipping Git pull..."
    fi
}

build_and_deploy() {
    log_info "Building and deploying with Docker Compose..."
    
    # Generate SSL certificates if not exist
    if [ ! -f "docker/nginx/ssl/cert.pem" ]; then
        log_info "Generating SSL certificates..."
        chmod +x docker/nginx/ssl/generate-cert.sh
        docker run --rm -v "$(pwd)/docker/nginx/ssl:/etc/nginx/ssl" \
            alpine/openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout /etc/nginx/ssl/key.pem \
            -out /etc/nginx/ssl/cert.pem \
            -subj "/C=ID/ST=East Java/L=Madiun/O=Kabupaten Madiun/OU=IT Department/CN=pendataan.local"
    fi
    
    # Stop existing containers
    log_info "Stopping existing containers..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" down 2>/dev/null || true
    
    # Pull latest images
    log_info "Pulling latest Docker images..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" pull
    
    # Build and start services
    log_info "Building and starting services..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" up -d --build
    
    log_success "Services deployed successfully!"
}

wait_for_services() {
    log_info "Waiting for services to be ready..."
    
    # Wait for database
    log_info "Waiting for database..."
    timeout 120 bash -c 'until docker-compose -f "'$DOCKER_COMPOSE_FILE'" exec -T db mysqladmin ping -h localhost --silent; do sleep 2; done' || {
        log_error "Database failed to start!"
        exit 1
    }
    
    # Wait for PHP-FPM
    log_info "Waiting for PHP-FPM..."
    timeout 60 bash -c 'until docker-compose -f "'$DOCKER_COMPOSE_FILE'" exec -T php php -v > /dev/null 2>&1; do sleep 2; done' || {
        log_error "PHP-FPM failed to start!"
        exit 1
    }
    
    # Wait for Nginx
    log_info "Waiting for Nginx..."
    timeout 60 bash -c 'until docker-compose -f "'$DOCKER_COMPOSE_FILE'" exec -T nginx nginx -t > /dev/null 2>&1; do sleep 2; done' || {
        log_error "Nginx failed to start!"
        exit 1
    }
    
    log_success "All services are ready!"
}

run_health_checks() {
    log_info "Running health checks..."
    
    # Check if containers are running
    if ! docker-compose -f "$DOCKER_COMPOSE_FILE" ps | grep -q "Up"; then
        log_error "Some containers are not running!"
        docker-compose -f "$DOCKER_COMPOSE_FILE" ps
        exit 1
    fi
    
    # Test database connection
    log_info "Testing database connection..."
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T php php -r "
        try {
            \$pdo = new PDO('mysql:host=db;dbname=aplikasi_madiun', 'root', 'rootpassword');
            echo 'Database connection: OK' . PHP_EOL;
        } catch (Exception \$e) {
            echo 'Database connection failed: ' . \$e->getMessage() . PHP_EOL;
            exit(1);
        }
    " || {
        log_error "Database connection test failed!"
        exit 1
    }
    
    # Test web server
    log_info "Testing web server..."
    sleep 5
    if curl -f http://localhost/health > /dev/null 2>&1; then
        log_success "Web server is responding!"
    else
        log_warning "Web server health check failed, but continuing..."
    fi
    
    log_success "Health checks completed!"
}

cleanup_old_images() {
    log_info "Cleaning up old Docker images..."
    
    # Remove dangling images
    docker image prune -f > /dev/null 2>&1 || true
    
    # Remove old images (keep latest 3)
    docker images --format "table {{.Repository}}\t{{.Tag}}\t{{.ID}}\t{{.CreatedAt}}" | \
        grep "$APP_NAME" | \
        tail -n +4 | \
        awk '{print $3}' | \
        xargs -r docker rmi > /dev/null 2>&1 || true
    
    log_success "Docker cleanup completed!"
}

show_status() {
    log_info "Deployment Status:"
    echo "===================="
    
    # Show running containers
    echo -e "\n${BLUE}Running Containers:${NC}"
    docker-compose -f "$DOCKER_COMPOSE_FILE" ps
    
    # Show service URLs
    echo -e "\n${BLUE}Service URLs:${NC}"
    echo "📱 Main Application: http://localhost"
    echo "🔐 Admin Panel: http://localhost/admin.php"
    echo "📊 phpMyAdmin: http://localhost:8080"
    echo "🔒 HTTPS (with self-signed cert): https://localhost"
    
    # Show logs command
    echo -e "\n${BLUE}Useful Commands:${NC}"
    echo "📋 View logs: docker-compose -f $DOCKER_COMPOSE_FILE logs -f"
    echo "🔄 Restart services: docker-compose -f $DOCKER_COMPOSE_FILE restart"
    echo "⏹️  Stop services: docker-compose -f $DOCKER_COMPOSE_FILE down"
    echo "🧹 Clean up: docker system prune -f"
    
    log_success "Deployment completed successfully! 🎉"
}

rollback() {
    log_warning "Rolling back to previous version..."
    
    if [ -z "$1" ]; then
        log_error "Please specify backup directory for rollback"
        echo "Usage: $0 rollback /path/to/backup"
        exit 1
    fi
    
    ROLLBACK_DIR="$1"
    
    if [ ! -d "$ROLLBACK_DIR" ]; then
        log_error "Backup directory not found: $ROLLBACK_DIR"
        exit 1
    fi
    
    # Stop current services
    docker-compose -f "$DOCKER_COMPOSE_FILE" down
    
    # Restore application files
    if [ -f "$ROLLBACK_DIR/application.tar.gz" ]; then
        log_info "Restoring application files..."
        tar -xzf "$ROLLBACK_DIR/application.tar.gz"
    fi
    
    # Restore database
    if [ -f "$ROLLBACK_DIR/database.sql" ]; then
        log_info "Restoring database..."
        docker-compose -f "$DOCKER_COMPOSE_FILE" up -d db
        sleep 10
        docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T db \
            mysql -uroot -prootpassword aplikasi_madiun < "$ROLLBACK_DIR/database.sql"
    fi
    
    # Restart all services
    docker-compose -f "$DOCKER_COMPOSE_FILE" up -d
    
    log_success "Rollback completed!"
}

# Main deployment function
main() {
    echo "🚀 Starting Nginx Auto-Deploy for $APP_NAME"
    echo "=============================================="
    
    case "${1:-deploy}" in
        "deploy")
            check_requirements
            create_backup
            pull_updates
            build_and_deploy
            wait_for_services
            run_health_checks
            cleanup_old_images
            show_status
            ;;
        "rollback")
            rollback "$2"
            ;;
        "status")
            show_status
            ;;
        "backup")
            create_backup
            ;;
        "logs")
            docker-compose -f "$DOCKER_COMPOSE_FILE" logs -f "${2:-}"
            ;;
        "restart")
            docker-compose -f "$DOCKER_COMPOSE_FILE" restart "${2:-}"
            ;;
        "stop")
            docker-compose -f "$DOCKER_COMPOSE_FILE" down
            ;;
        "start")
            docker-compose -f "$DOCKER_COMPOSE_FILE" up -d
            ;;
        *)
            echo "Usage: $0 {deploy|rollback|status|backup|logs|restart|stop|start}"
            echo ""
            echo "Commands:"
            echo "  deploy   - Full deployment (default)"
            echo "  rollback - Rollback to backup (requires backup path)"
            echo "  status   - Show current status"
            echo "  backup   - Create backup only"
            echo "  logs     - Show logs (optional: service name)"
            echo "  restart  - Restart services (optional: service name)"
            echo "  stop     - Stop all services"
            echo "  start    - Start all services"
            echo ""
            echo "Examples:"
            echo "  $0 deploy"
            echo "  $0 rollback /tmp/pendataan-backup-20250821_143022"
            echo "  $0 logs nginx"
            echo "  $0 restart php"
            exit 1
            ;;
    esac
}

# Run main function
main "$@"
