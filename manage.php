#!/usr/bin/env php
<?php
/**
 * Aplikasi Pendataan Aplikasi Kabupaten Madiun
 * File: manage.php
 * Command Line Interface untuk management aplikasi
 * 
 * Usage: php manage.php [command] [options]
 */

// Ensure this is run from command line
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/maintenance.php';

class AppManager {
    private $commands = [
        'maintenance:on' => 'Enable maintenance mode',
        'maintenance:off' => 'Disable maintenance mode',
        'maintenance:status' => 'Check maintenance status',
        'health:check' => 'Check system health',
        'logs:clean' => 'Clean old log files',
        'admin:create' => 'Create new admin user',
        'admin:password' => 'Change admin password',
        'stats:show' => 'Show application statistics',
        'backup:create' => 'Create database backup',
        'security:scan' => 'Run security scan',
        'help' => 'Show this help message'
    ];
    
    public function run($argv) {
        $command = $argv[1] ?? 'help';
        $options = array_slice($argv, 2);
        
        echo "🏛️  Aplikasi Pendataan Kabupaten Madiun - Management CLI\n";
        echo "========================================================\n\n";
        
        switch ($command) {
            case 'maintenance:on':
                $this->enableMaintenance($options);
                break;
            case 'maintenance:off':
                $this->disableMaintenance();
                break;
            case 'maintenance:status':
                $this->checkMaintenanceStatus();
                break;
            case 'health:check':
                $this->checkHealth();
                break;
            case 'logs:clean':
                $this->cleanLogs($options);
                break;
            case 'admin:create':
                $this->createAdmin($options);
                break;
            case 'admin:password':
                $this->changeAdminPassword($options);
                break;
            case 'stats:show':
                $this->showStats();
                break;
            case 'backup:create':
                $this->createBackup();
                break;
            case 'security:scan':
                $this->securityScan();
                break;
            case 'help':
            default:
                $this->showHelp();
                break;
        }
        
        echo "\n";
    }
    
    private function enableMaintenance($options) {
        $message = isset($options[0]) ? implode(' ', $options) : '';
        
        if (MaintenanceMode::enable($message)) {
            echo "✅ Maintenance mode enabled.\n";
            if ($message) {
                echo "📝 Message: {$message}\n";
            }
        } else {
            echo "❌ Failed to enable maintenance mode.\n";
        }
    }
    
    private function disableMaintenance() {
        if (MaintenanceMode::disable()) {
            echo "✅ Maintenance mode disabled.\n";
        } else {
            echo "❌ Maintenance mode was not active.\n";
        }
    }
    
    private function checkMaintenanceStatus() {
        if (MaintenanceMode::isActive()) {
            $info = MaintenanceMode::getInfo();
            echo "🔧 Maintenance mode is ACTIVE\n";
            echo "📅 Enabled at: " . date('d/m/Y H:i:s', strtotime($info['enabled_at'])) . "\n";
            echo "👤 Enabled by: " . $info['enabled_by'] . "\n";
            echo "💬 Message: " . $info['message'] . "\n";
        } else {
            echo "✅ Maintenance mode is INACTIVE\n";
        }
    }
    
    private function checkHealth() {
        echo "🩺 Running system health check...\n\n";
        
        $health = SecurityUtils::getSystemHealth();
        
        $statusIcon = [
            'healthy' => '✅',
            'degraded' => '⚠️',
            'critical' => '❌'
        ];
        
        echo $statusIcon[$health['status']] . " Overall Status: " . strtoupper($health['status']) . "\n\n";
        
        foreach ($health['checks'] as $check => $result) {
            $checkName = ucwords(str_replace('_', ' ', $check));
            
            if (is_array($result)) {
                $status = $result['status'];
                echo ($status === 'ok' ? '✅' : '⚠️') . " {$checkName}: " . strtoupper($status);
                
                if (isset($result['free_percent'])) {
                    echo " ({$result['free_percent']}% free)";
                }
                if (isset($result['usage_percent'])) {
                    echo " ({$result['usage_percent']}% used)";
                }
                if (isset($result['size_mb'])) {
                    echo " ({$result['size_mb']} MB)";
                }
                echo "\n";
            } else {
                echo ($result === 'ok' ? '✅' : '❌') . " {$checkName}: " . strtoupper($result) . "\n";
            }
        }
    }
    
    private function cleanLogs($options) {
        $days = isset($options[0]) ? (int)$options[0] : 30;
        
        echo "🧹 Cleaning logs older than {$days} days...\n";
        
        $deleted = SecurityUtils::cleanOldLogs($days);
        echo "✅ Deleted {$deleted} old log files.\n";
        
        // Clean database logs
        try {
            $db = Security::initDB();
            $stmt = $db->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$days]);
            $deletedDb = $stmt->rowCount();
            echo "✅ Deleted {$deletedDb} database log entries.\n";
        } catch (Exception $e) {
            echo "❌ Error cleaning database logs: " . $e->getMessage() . "\n";
        }
    }
    
    private function createAdmin($options) {
        if (count($options) < 2) {
            echo "❌ Usage: php manage.php admin:create <username> <password> [email]\n";
            return;
        }
        
        $username = $options[0];
        $password = $options[1];
        $email = $options[2] ?? '';
        
        try {
            $db = Security::initDB();
            
            // Check if username exists
            $stmt = $db->prepare("SELECT id FROM admin WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->rowCount() > 0) {
                echo "❌ Username already exists.\n";
                return;
            }
            
            // Create admin
            $passwordHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
            $stmt = $db->prepare("INSERT INTO admin (username, password_hash, email) VALUES (?, ?, ?)");
            $stmt->execute([$username, $passwordHash, $email]);
            
            echo "✅ Admin user '{$username}' created successfully.\n";
            
            Security::logActivity('ADMIN_CREATED', 'admin', $db->lastInsertId(), null, ['username' => $username]);
            
        } catch (Exception $e) {
            echo "❌ Error creating admin: " . $e->getMessage() . "\n";
        }
    }
    
    private function changeAdminPassword($options) {
        if (count($options) < 2) {
            echo "❌ Usage: php manage.php admin:password <username> <new_password>\n";
            return;
        }
        
        $username = $options[0];
        $newPassword = $options[1];
        
        try {
            $db = Security::initDB();
            
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => 12]);
            $stmt = $db->prepare("UPDATE admin SET password_hash = ?, login_attempts = 0, locked_until = NULL WHERE username = ?");
            $stmt->execute([$passwordHash, $username]);
            
            if ($stmt->rowCount() > 0) {
                echo "✅ Password updated for user '{$username}'.\n";
                Security::logActivity('ADMIN_PASSWORD_CHANGED', 'admin', null, null, ['username' => $username]);
            } else {
                echo "❌ User '{$username}' not found.\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Error updating password: " . $e->getMessage() . "\n";
        }
    }
    
    private function showStats() {
        echo "📊 Application Statistics\n";
        echo "========================\n\n";
        
        try {
            $db = Security::initDB();
            
            // Total applications
            $stmt = $db->query("SELECT COUNT(*) as total FROM aplikasi_opd");
            $total = $stmt->fetch()['total'];
            echo "📱 Total Applications: {$total}\n";
            
            // By status
            $stmt = $db->query("
                SELECT status_aplikasi, COUNT(*) as count 
                FROM aplikasi_opd 
                GROUP BY status_aplikasi
            ");
            echo "\n📊 By Status:\n";
            while ($row = $stmt->fetch()) {
                echo "   {$row['status_aplikasi']}: {$row['count']}\n";
            }
            
            // By type
            $stmt = $db->query("
                SELECT jenis_aplikasi, COUNT(*) as count 
                FROM aplikasi_opd 
                GROUP BY jenis_aplikasi
            ");
            echo "\n🏷️  By Type:\n";
            while ($row = $stmt->fetch()) {
                echo "   {$row['jenis_aplikasi']}: {$row['count']}\n";
            }
            
            // Top OPDs
            $stmt = $db->query("
                SELECT nama_perangkat_daerah, COUNT(*) as count 
                FROM aplikasi_opd 
                GROUP BY nama_perangkat_daerah 
                ORDER BY count DESC 
                LIMIT 5
            ");
            echo "\n🏆 Top 5 OPDs:\n";
            while ($row = $stmt->fetch()) {
                echo "   {$row['nama_perangkat_daerah']}: {$row['count']} apps\n";
            }
            
            // Recent activity
            $stmt = $db->query("
                SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM aplikasi_opd 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at) 
                ORDER BY date DESC
            ");
            echo "\n📅 Last 7 Days Activity:\n";
            while ($row = $stmt->fetch()) {
                echo "   {$row['date']}: {$row['count']} new entries\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Error fetching statistics: " . $e->getMessage() . "\n";
        }
    }
    
    private function createBackup() {
        echo "💾 Creating database backup...\n";
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupDir = __DIR__ . '/backups';
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . "/backup_{$timestamp}.sql";
        
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s > %s',
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME,
            $backupFile
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            // Compress the backup
            exec("gzip {$backupFile}");
            echo "✅ Backup created: backup_{$timestamp}.sql.gz\n";
            
            Security::logActivity('BACKUP_CREATED', null, null, null, ['file' => "backup_{$timestamp}.sql.gz"]);
            
            // Clean old backups (keep only 7 days)
            $oldBackups = glob($backupDir . '/backup_*.sql.gz');
            foreach ($oldBackups as $oldBackup) {
                if (filemtime($oldBackup) < time() - (7 * 24 * 60 * 60)) {
                    unlink($oldBackup);
                    echo "🗑️  Removed old backup: " . basename($oldBackup) . "\n";
                }
            }
        } else {
            echo "❌ Backup failed. Check database credentials.\n";
        }
    }
    
    private function securityScan() {
        echo "🔍 Running security scan...\n\n";
        
        $issues = [];
        
        // Check file permissions
        $files = [
            'config.php' => 644,
            '.htaccess' => 644,
            'index.php' => 644
        ];
        
        foreach ($files as $file => $expectedPerm) {
            if (file_exists($file)) {
                $actualPerm = fileperms($file) & 0777;
                if ($actualPerm != octdec($expectedPerm)) {
                    $issues[] = "File {$file} has wrong permissions: " . decoct($actualPerm) . " (expected: {$expectedPerm})";
                }
            }
        }
        
        // Check for suspicious files
        $suspiciousPatterns = [
            '*.php.bak',
            '*.php.old',
            '*.php~',
            'shell.php',
            'backdoor.php',
            'c99.php'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            $matches = glob($pattern);
            foreach ($matches as $match) {
                $issues[] = "Suspicious file found: {$match}";
            }
        }
        
        // Check database for admin accounts
        try {
            $db = Security::initDB();
            $stmt = $db->query("SELECT COUNT(*) as count FROM admin");
            $adminCount = $stmt->fetch()['count'];
            
            if ($adminCount == 0) {
                $issues[] = "No admin accounts found";
            } elseif ($adminCount > 5) {
                $issues[] = "Too many admin accounts ({$adminCount})";
            }
            
            // Check for default password
            $stmt = $db->query("SELECT username FROM admin WHERE password_hash = '$2y$12\$QlVvK3QKNKsrSQC5IpL/suV7WV9H3/vFy1QxVMJ8F8Z9Dg1Cp9Vpe'");
            if ($stmt->rowCount() > 0) {
                $issues[] = "Default admin password detected";
            }
            
        } catch (Exception $e) {
            $issues[] = "Database connection error: " . $e->getMessage();
        }
        
        // Check for recent security incidents
        try {
            $db = Security::initDB();
            $stmt = $db->query("
                SELECT COUNT(*) as count 
                FROM activity_logs 
                WHERE action = 'SECURITY_INCIDENT' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $incidents = $stmt->fetch()['count'];
            
            if ($incidents > 10) {
                $issues[] = "High number of security incidents in last 24h: {$incidents}";
            }
        } catch (Exception $e) {
            // Ignore database errors for this check
        }
        
        // Report results
        if (empty($issues)) {
            echo "✅ No security issues found.\n";
        } else {
            echo "⚠️  Security issues found:\n";
            foreach ($issues as $issue) {
                echo "   ❌ {$issue}\n";
            }
        }
        
        Security::logActivity('SECURITY_SCAN', null, null, null, ['issues_found' => count($issues)]);
    }
    
    private function showHelp() {
        echo "Available commands:\n\n";
        
        foreach ($this->commands as $command => $description) {
            echo sprintf("  %-20s %s\n", $command, $description);
        }
        
        echo "\nExamples:\n";
        echo "  php manage.php maintenance:on \"System update in progress\"\n";
        echo "  php manage.php admin:create newadmin secretpassword admin@example.com\n";
        echo "  php manage.php logs:clean 7\n";
        echo "  php manage.php health:check\n";
    }
}

// Run the application
$manager = new AppManager();
$manager->run($argv);
?>