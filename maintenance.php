<?php
/**
 * Aplikasi Pendataan Aplikasi Kabupaten Madiun
 * File: maintenance.php
 * Maintenance mode dan security utilities
 */

require_once 'config.php';

class MaintenanceMode {
    private static $maintenanceFile = __DIR__ . '/.maintenance';
    private static $allowedIPs = [
        '127.0.0.1',
        '::1',
        // Tambahkan IP admin yang diizinkan saat maintenance
        // '192.168.1.100',
    ];
    
    /**
     * Check if maintenance mode is active
     */
    public static function isActive() {
        return file_exists(self::$maintenanceFile);
    }
    
    /**
     * Enable maintenance mode
     */
    public static function enable($message = '') {
        $data = [
            'enabled_at' => date('Y-m-d H:i:s'),
            'enabled_by' => $_SESSION['admin_username'] ?? 'system',
            'message' => $message ?: 'Sistem sedang dalam pemeliharaan. Silakan coba lagi nanti.',
            'ip_address' => Security::getClientIP()
        ];
        
        file_put_contents(self::$maintenanceFile, json_encode($data, JSON_PRETTY_PRINT));
        Security::logActivity('MAINTENANCE_ENABLED', null, null, null, $data);
        
        return true;
    }
    
    /**
     * Disable maintenance mode
     */
    public static function disable() {
        if (file_exists(self::$maintenanceFile)) {
            $data = json_decode(file_get_contents(self::$maintenanceFile), true);
            $data['disabled_at'] = date('Y-m-d H:i:s');
            $data['disabled_by'] = $_SESSION['admin_username'] ?? 'system';
            
            Security::logActivity('MAINTENANCE_DISABLED', null, null, null, $data);
            unlink(self::$maintenanceFile);
            
            return true;
        }
        return false;
    }
    
    /**
     * Check if current IP is allowed during maintenance
     */
    public static function isIPAllowed($ip = null) {
        $ip = $ip ?: Security::getClientIP();
        return in_array($ip, self::$allowedIPs);
    }
    
    /**
     * Get maintenance info
     */
    public static function getInfo() {
        if (file_exists(self::$maintenanceFile)) {
            return json_decode(file_get_contents(self::$maintenanceFile), true);
        }
        return null;
    }
    
    /**
     * Show maintenance page
     */
    public static function showMaintenancePage() {
        $info = self::getInfo();
        $message = $info['message'] ?? 'Sistem sedang dalam pemeliharaan.';
        
        http_response_code(503);
        header('Retry-After: 3600'); // 1 hour
        
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Maintenance - <?php echo SITE_NAME; ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
                }
                .maintenance-container {
                    background: white; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                    text-align: center; padding: 60px 40px; max-width: 600px; width: 100%;
                }
                .maintenance-icon { font-size: 5em; margin-bottom: 20px; }
                .maintenance-title { font-size: 2.5em; color: #2c3e50; margin-bottom: 15px; font-weight: 600; }
                .maintenance-message { font-size: 1.2em; color: #7f8c8d; line-height: 1.6; margin-bottom: 30px; }
                .maintenance-time { background: #f8f9fa; padding: 15px; border-radius: 8px; color: #6c757d; }
                @media (max-width: 768px) {
                    .maintenance-container { padding: 40px 30px; }
                    .maintenance-title { font-size: 2em; }
                    .maintenance-message { font-size: 1em; }
                }
            </style>
        </head>
        <body>
            <div class="maintenance-container">
                <div class="maintenance-icon">🔧</div>
                <h1 class="maintenance-title">Sistem Dalam Pemeliharaan</h1>
                <p class="maintenance-message"><?php echo htmlspecialchars($message); ?></p>
                <?php if (isset($info['enabled_at'])): ?>
                <div class="maintenance-time">
                    Dimulai: <?php echo date('d/m/Y H:i', strtotime($info['enabled_at'])); ?> WIB
                </div>
                <?php endif; ?>
            </div>
            <script>
                // Auto refresh every 5 minutes
                setTimeout(function() { window.location.reload(); }, 300000);
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

class SecurityUtils {
    /**
     * Check for common attack patterns
     */
    public static function detectAttack($input) {
        $patterns = [
            // SQL Injection patterns
            '/union\s+select/i',
            '/or\s+1\s*=\s*1/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
            '/update\s+set/i',
            '/delete\s+from/i',
            
            // XSS patterns
            '/<script[^>]*>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>/i',
            
            // Path traversal
            '/\.\.\//i',
            '/\.\.\\/i',
            
            // Command injection
            '/;\s*cat\s+/i',
            '/;\s*ls\s+/i',
            '/;\s*rm\s+/i',
            '/\|\s*cat\s+/i',
            
            // PHP code injection
            '/<\?php/i',
            '/eval\s*\(/i',
            '/base64_decode/i',
            '/file_get_contents/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log security incident
     */
    public static function logSecurityIncident($type, $details = []) {
        $incident = [
            'type' => $type,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => Security::getClientIP(),
            'user_agent' => Security::getUserAgent(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'details' => $details
        ];
        
        // Log to database
        Security::logActivity('SECURITY_INCIDENT', null, null, null, $incident);
        
        // Log to file for immediate analysis
        $logFile = __DIR__ . '/logs/security.log';
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        $logLine = date('Y-m-d H:i:s') . " [{$type}] " . 
                   Security::getClientIP() . " " . 
                   json_encode($details) . PHP_EOL;
        
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        return true;
    }
    
    /**
     * Get system health status
     */
    public static function getSystemHealth() {
        $health = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'healthy',
            'checks' => []
        ];
        
        try {
            // Database check
            $db = Security::initDB();
            $stmt = $db->query("SELECT 1");
            $health['checks']['database'] = $stmt ? 'ok' : 'error';
        } catch (Exception $e) {
            $health['checks']['database'] = 'error';
            $health['status'] = 'degraded';
        }
        
        // Disk space check
        $freeBytes = disk_free_space(__DIR__);
        $totalBytes = disk_total_space(__DIR__);
        $freePercent = ($freeBytes / $totalBytes) * 100;
        
        $health['checks']['disk_space'] = [
            'status' => $freePercent > 10 ? 'ok' : 'warning',
            'free_percent' => round($freePercent, 2)
        ];
        
        if ($freePercent < 5) {
            $health['status'] = 'critical';
        } elseif ($freePercent < 10) {
            $health['status'] = 'degraded';
        }
        
        // Memory usage check
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = self::parseMemoryLimit($memoryLimit);
        
        $memoryPercent = ($memoryUsage / $memoryLimitBytes) * 100;
        $health['checks']['memory'] = [
            'status' => $memoryPercent < 80 ? 'ok' : 'warning',
            'usage_percent' => round($memoryPercent, 2)
        ];
        
        // Log files check
        $logDir = __DIR__ . '/logs';
        if (is_dir($logDir)) {
            $logSize = self::getDirectorySize($logDir);
            $health['checks']['logs'] = [
                'status' => $logSize < 100 * 1024 * 1024 ? 'ok' : 'warning', // 100MB
                'size_mb' => round($logSize / 1024 / 1024, 2)
            ];
        }
        
        return $health;
    }
    
    /**
     * Parse memory limit string to bytes
     */
    private static function parseMemoryLimit($limit) {
        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;
        
        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }
    
    /**
     * Get directory size
     */
    private static function getDirectorySize($directory) {
        $size = 0;
        if (is_dir($directory)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                $size += $file->getSize();
            }
        }
        return $size;
    }
    
    /**
     * Clean old logs
     */
    public static function cleanOldLogs($days = 30) {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) return false;
        
        $cutoff = time() - ($days * 24 * 60 * 60);
        $deleted = 0;
        
        foreach (glob($logDir . '/*.log') as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $deleted++;
            }
        }
        
        Security::logActivity('LOG_CLEANUP', null, null, null, ['deleted_files' => $deleted, 'days' => $days]);
        
        return $deleted;
    }
}

// Auto-check for maintenance mode on every request
if (MaintenanceMode::isActive() && !MaintenanceMode::isIPAllowed()) {
    MaintenanceMode::showMaintenancePage();
}

// Security monitoring on every request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if (SecurityUtils::detectAttack($value)) {
            SecurityUtils::logSecurityIncident('ATTACK_DETECTED', [
                'field' => $key,
                'value' => substr($value, 0, 100) // Only log first 100 chars
            ]);
            
            // Block the request
            http_response_code(403);
            exit('Request blocked for security reasons.');
        }
    }
}

// Rate limiting check for suspicious activity
$suspicious_endpoints = ['admin.php', 'data_export.php'];
$current_script = basename($_SERVER['PHP_SELF']);

if (in_array($current_script, $suspicious_endpoints)) {
    if (!Security::checkRateLimit('admin_access')) {
        SecurityUtils::logSecurityIncident('RATE_LIMIT_EXCEEDED', [
            'endpoint' => $current_script
        ]);
        http_response_code(429);
        exit('Too many requests. Please try again later.');
    }
}
?>