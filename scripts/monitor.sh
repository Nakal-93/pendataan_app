#!/bin/bash

# 📊 Monitoring Script for Pendataan OPD
# ======================================

DOCKER_COMPOSE_FILE="docker-compose.nginx.yml"
ALERT_EMAIL="admin@madiunkab.go.id"
SLACK_WEBHOOK_URL=""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] INFO:${NC} $1"
}

log_success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS:${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1"
}

log_error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1"
}

# Health check functions
check_container_health() {
    local container_name=$1
    local status=$(docker inspect --format='{{.State.Health.Status}}' "$container_name" 2>/dev/null)
    
    if [ "$status" = "healthy" ]; then
        return 0
    else
        return 1
    fi
}

check_web_response() {
    local url=$1
    local expected_code=${2:-200}
    
    local response_code=$(curl -s -o /dev/null -w "%{http_code}" "$url" --max-time 10)
    
    if [ "$response_code" = "$expected_code" ]; then
        return 0
    else
        log_error "Web check failed: $url returned $response_code (expected $expected_code)"
        return 1
    fi
}

check_database_connection() {
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T db mysql -uroot -prootpassword -e "SELECT 1;" > /dev/null 2>&1
    return $?
}

check_php_fpm() {
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T php php -r "echo 'OK';" > /dev/null 2>&1
    return $?
}

check_nginx_config() {
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T nginx nginx -t > /dev/null 2>&1
    return $?
}

# System resource checks
check_disk_space() {
    local threshold=${1:-85}
    local usage=$(df / | tail -1 | awk '{print $5}' | sed 's/%//')
    
    if [ "$usage" -gt "$threshold" ]; then
        log_warning "Disk usage is $usage% (threshold: $threshold%)"
        return 1
    else
        return 0
    fi
}

check_memory_usage() {
    local threshold=${1:-85}
    local usage=$(free | grep Mem | awk '{printf("%.0f", $3/$2 * 100.0)}')
    
    if [ "$usage" -gt "$threshold" ]; then
        log_warning "Memory usage is $usage% (threshold: $threshold%)"
        return 1
    else
        return 0
    fi
}

check_docker_logs() {
    local container=$1
    local error_count=$(docker logs "$container" --since 1h 2>&1 | grep -i error | wc -l)
    
    if [ "$error_count" -gt 10 ]; then
        log_warning "Container $container has $error_count errors in the last hour"
        return 1
    else
        return 0
    fi
}

# Notification functions
send_slack_notification() {
    local message=$1
    local color=${2:-"danger"}
    
    if [ -n "$SLACK_WEBHOOK_URL" ]; then
        curl -X POST -H 'Content-type: application/json' \
            --data "{\"attachments\":[{\"color\":\"$color\",\"text\":\"$message\"}]}" \
            "$SLACK_WEBHOOK_URL" > /dev/null 2>&1
    fi
}

send_email_notification() {
    local subject=$1
    local message=$2
    
    if command -v mail &> /dev/null && [ -n "$ALERT_EMAIL" ]; then
        echo "$message" | mail -s "$subject" "$ALERT_EMAIL"
    fi
}

# Main health check
run_health_checks() {
    local errors=0
    local warnings=0
    
    log_info "Starting health checks..."
    
    # Check if Docker Compose services are running
    if ! docker-compose -f "$DOCKER_COMPOSE_FILE" ps | grep -q "Up"; then
        log_error "Some Docker services are not running"
        ((errors++))
    fi
    
    # Check individual services
    services=("pendataan_nginx" "pendataan_php" "pendataan_db")
    for service in "${services[@]}"; do
        if ! docker ps | grep -q "$service"; then
            log_error "Service $service is not running"
            ((errors++))
        else
            log_success "Service $service is running"
        fi
    done
    
    # Check web responses
    urls=("http://localhost/health" "http://localhost")
    for url in "${urls[@]}"; do
        if check_web_response "$url"; then
            log_success "Web check passed: $url"
        else
            log_error "Web check failed: $url"
            ((errors++))
        fi
    done
    
    # Check database connection
    if check_database_connection; then
        log_success "Database connection OK"
    else
        log_error "Database connection failed"
        ((errors++))
    fi
    
    # Check PHP-FPM
    if check_php_fpm; then
        log_success "PHP-FPM is working"
    else
        log_error "PHP-FPM check failed"
        ((errors++))
    fi
    
    # Check Nginx configuration
    if check_nginx_config; then
        log_success "Nginx configuration is valid"
    else
        log_error "Nginx configuration is invalid"
        ((errors++))
    fi
    
    # Check system resources
    if check_disk_space 85; then
        log_success "Disk space is OK"
    else
        log_warning "Disk space is running low"
        ((warnings++))
    fi
    
    if check_memory_usage 85; then
        log_success "Memory usage is OK"
    else
        log_warning "Memory usage is high"
        ((warnings++))
    fi
    
    # Check Docker logs for errors
    for service in "${services[@]}"; do
        if check_docker_logs "$service"; then
            log_success "No significant errors in $service logs"
        else
            log_warning "High error count in $service logs"
            ((warnings++))
        fi
    done
    
    # Summary
    echo ""
    log_info "Health check summary:"
    echo "  - Errors: $errors"
    echo "  - Warnings: $warnings"
    
    # Send notifications if needed
    if [ "$errors" -gt 0 ]; then
        local message="❌ Pendataan OPD Health Check FAILED with $errors errors and $warnings warnings"
        send_slack_notification "$message" "danger"
        send_email_notification "Pendataan OPD Health Check Failed" "$message"
        return 1
    elif [ "$warnings" -gt 0 ]; then
        local message="⚠️ Pendataan OPD Health Check completed with $warnings warnings"
        send_slack_notification "$message" "warning"
        return 0
    else
        log_success "All health checks passed!"
        return 0
    fi
}

# Performance monitoring
monitor_performance() {
    log_info "Monitoring performance metrics..."
    
    # Get container stats
    echo "=== Container Resource Usage ==="
    docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}\t{{.BlockIO}}"
    
    echo ""
    echo "=== Database Performance ==="
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T db mysql -uroot -prootpassword -e "
        SHOW GLOBAL STATUS LIKE 'Threads_connected';
        SHOW GLOBAL STATUS LIKE 'Queries';
        SHOW GLOBAL STATUS LIKE 'Slow_queries';
        SHOW GLOBAL STATUS LIKE 'Uptime';
    " 2>/dev/null || log_warning "Could not fetch database stats"
    
    echo ""
    echo "=== Nginx Access Log Summary (Last Hour) ==="
    if [ -f "logs/nginx/access.log" ]; then
        tail -1000 logs/nginx/access.log | awk -v date="$(date -d '1 hour ago' '+%d/%b/%Y:%H')" '$4 > "["date {print}' | \
            awk '{print $9}' | sort | uniq -c | sort -nr | head -10
    else
        log_warning "Nginx access log not found"
    fi
    
    echo ""
    echo "=== Application Errors (Last Hour) ==="
    if [ -f "logs/php/error.log" ]; then
        grep "$(date -d '1 hour ago' '+%Y-%m-%d %H')" logs/php/error.log | wc -l | \
            awk '{print "PHP Errors in last hour: " $1}'
    fi
    
    if [ -f "logs/nginx/error.log" ]; then
        grep "$(date -d '1 hour ago' '+%Y/%m/%d %H')" logs/nginx/error.log | wc -l | \
            awk '{print "Nginx Errors in last hour: " $1}'
    fi
}

# Log rotation
rotate_logs() {
    log_info "Rotating logs..."
    
    # Rotate application logs
    for logfile in logs/nginx/access.log logs/nginx/error.log logs/php/error.log; do
        if [ -f "$logfile" ]; then
            # Keep last 7 days of logs
            mv "$logfile" "$logfile.$(date +%Y%m%d)"
            touch "$logfile"
            chmod 644 "$logfile"
            
            # Compress old logs
            gzip "$logfile.$(date +%Y%m%d)" 2>/dev/null || true
            
            # Remove logs older than 7 days
            find logs/ -name "*.gz" -mtime +7 -delete
            
            log_success "Rotated $logfile"
        fi
    done
    
    # Signal nginx to reopen log files
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec nginx nginx -s reopen 2>/dev/null || true
}

# Database backup
backup_database() {
    log_info "Creating database backup..."
    
    local backup_dir="backups/$(date +%Y%m%d)"
    mkdir -p "$backup_dir"
    
    local backup_file="$backup_dir/database_$(date +%H%M%S).sql"
    
    if docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T db \
        mysqldump -uroot -prootpassword aplikasi_madiun > "$backup_file"; then
        
        # Compress backup
        gzip "$backup_file"
        log_success "Database backup created: $backup_file.gz"
        
        # Remove backups older than 30 days
        find backups/ -name "*.sql.gz" -mtime +30 -delete
        
        return 0
    else
        log_error "Database backup failed"
        return 1
    fi
}

# Update check
check_for_updates() {
    log_info "Checking for updates..."
    
    if [ -d ".git" ]; then
        git fetch origin
        local behind=$(git rev-list HEAD..origin/main --count 2>/dev/null || echo "0")
        
        if [ "$behind" -gt 0 ]; then
            log_warning "Repository is $behind commits behind origin/main"
            return 1
        else
            log_success "Repository is up to date"
            return 0
        fi
    else
        log_warning "Not a git repository, cannot check for updates"
        return 0
    fi
}

# Main function
main() {
    case "${1:-health}" in
        "health")
            run_health_checks
            ;;
        "performance")
            monitor_performance
            ;;
        "logs")
            rotate_logs
            ;;
        "backup")
            backup_database
            ;;
        "updates")
            check_for_updates
            ;;
        "full")
            run_health_checks
            monitor_performance
            backup_database
            check_for_updates
            ;;
        *)
            echo "Usage: $0 {health|performance|logs|backup|updates|full}"
            echo ""
            echo "Commands:"
            echo "  health      - Run health checks (default)"
            echo "  performance - Monitor performance metrics"
            echo "  logs        - Rotate log files"
            echo "  backup      - Create database backup"
            echo "  updates     - Check for application updates"
            echo "  full        - Run all monitoring tasks"
            exit 1
            ;;
    esac
}

# Run main function
main "$@"
