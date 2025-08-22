<?php
/**
 * Database Diagnostic Tool
 * File: db_check.php
 * 
 * Jalankan file ini untuk mendiagnosis masalah database
 * Akses: http://localhost:8888/pendataan/db_check.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include config jika ada
$configExists = file_exists('config.php');
if ($configExists) {
    try {
        require_once 'config.php';
    } catch (Exception $e) {
        $configExists = false;
        $configError = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Diagnostic - Kabupaten Madiun</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; padding: 20px;
        }
        .container {
            max-width: 1000px; margin: 0 auto;
            background: white; border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white; padding: 30px; text-align: center;
        }
        .header h1 { font-size: 2.2em; margin-bottom: 10px; font-weight: 600; }
        .content { padding: 30px; }
        .check-item {
            border: 1px solid #e0e6ed; border-radius: 8px;
            margin-bottom: 15px; padding: 20px;
            background: #f8f9fa;
        }
        .check-item h3 {
            margin-bottom: 10px; color: #2c3e50;
            font-size: 1.2em;
        }
        .status-ok { border-left: 5px solid #27ae60; background: #d4edda; }
        .status-warning { border-left: 5px solid #f39c12; background: #fff3cd; }
        .status-error { border-left: 5px solid #e74c3c; background: #f8d7da; }
        .code-block {
            background: #2c3e50; color: #ecf0f1; padding: 15px;
            border-radius: 5px; margin: 10px 0;
            font-family: 'Courier New', monospace;
            overflow-x: auto; font-size: 0.9em;
        }
        .solution {
            background: #e8f4f8; border: 1px solid #bee5eb;
            padding: 15px; border-radius: 5px; margin-top: 10px;
        }
        .solution h4 { color: #0c5460; margin-bottom: 8px; }
        .btn {
            background: #3498db; color: white; padding: 10px 20px;
            border: none; border-radius: 5px; cursor: pointer;
            text-decoration: none; display: inline-block;
            margin: 5px 5px 5px 0;
        }
        .btn:hover { background: #2980b9; }
        .config-form {
            background: #f8f9fa; border: 1px solid #e0e6ed;
            padding: 20px; border-radius: 8px; margin-top: 20px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { 
            display: block; margin-bottom: 5px; 
            font-weight: 600; color: #2c3e50; 
        }
        .form-control {
            width: 100%; padding: 10px; border: 1px solid #e0e6ed;
            border-radius: 5px; font-size: 1em;
        }
        .alert { padding: 15px; border-radius: 5px; margin: 10px 0; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #2c3e50; color: white; }
        .info-box { background: #cce5ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Database Diagnostic Tool</h1>
            <p>Sistem Deteksi Masalah Database - Kabupaten Madiun</p>
        </div>
        
        <div class="content">
            <?php
            echo "<div class='info-box'>";
            echo "<strong>📍 Environment Info:</strong><br>";
            echo "• PHP Version: " . PHP_VERSION . "<br>";
            echo "• Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
            echo "• Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
            echo "• Current Script: " . __FILE__ . "<br>";
            echo "• Time: " . date('Y-m-d H:i:s') . "<br>";
            echo "</div>";
            
            $checks = [];
            $hasError = false;
            
            // Check 1: PHP Extensions
            echo "<div class='check-item ";
            $requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json'];
            $missingExtensions = [];
            
            foreach ($requiredExtensions as $ext) {
                if (!extension_loaded($ext)) {
                    $missingExtensions[] = $ext;
                }
            }
            
            if (empty($missingExtensions)) {
                echo "status-ok'>";
                echo "<h3>✅ PHP Extensions - OK</h3>";
                echo "<p>Semua ekstensi PHP yang diperlukan sudah terinstall.</p>";
                echo "<p><strong>Extensions loaded:</strong> " . implode(', ', $requiredExtensions) . "</p>";
            } else {
                echo "status-error'>";
                echo "<h3>❌ PHP Extensions - ERROR</h3>";
                echo "<p><strong>Missing extensions:</strong> " . implode(', ', $missingExtensions) . "</p>";
                echo "<div class='solution'>";
                echo "<h4>💡 Solusi:</h4>";
                echo "<p>Install ekstensi PHP yang kurang:</p>";
                echo "<div class='code-block'>";
                echo "# Ubuntu/Debian:<br>";
                foreach ($missingExtensions as $ext) {
                    echo "sudo apt install php" . PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION . "-" . str_replace('pdo_', '', $ext) . "<br>";
                }
                echo "<br># CentOS/RHEL:<br>";
                foreach ($missingExtensions as $ext) {
                    echo "sudo yum install php-" . str_replace('pdo_', '', $ext) . "<br>";
                }
                echo "</div>";
                echo "</div>";
                $hasError = true;
            }
            echo "</div>";
            
            // Check 2: Config File
            echo "<div class='check-item ";
            if ($configExists) {
                echo "status-ok'>";
                echo "<h3>✅ Config File - OK</h3>";
                echo "<p>File config.php ditemukan dan dapat di-load.</p>";
                if (defined('DB_HOST')) {
                    echo "<p><strong>Database Config:</strong></p>";
                    echo "<ul>";
                    echo "<li>Host: " . DB_HOST . "</li>";
                    echo "<li>Database: " . DB_NAME . "</li>";
                    echo "<li>Username: " . DB_USER . "</li>";
                    echo "<li>Password: " . (DB_PASS ? str_repeat('*', strlen(DB_PASS)) : 'Empty') . "</li>";
                    echo "</ul>";
                }
            } else {
                echo "status-error'>";
                echo "<h3>❌ Config File - ERROR</h3>";
                echo "<p>File config.php tidak ditemukan atau error saat loading.</p>";
                if (isset($configError)) {
                    echo "<p><strong>Error:</strong> " . htmlspecialchars($configError) . "</p>";
                }
                echo "<div class='solution'>";
                echo "<h4>💡 Solusi:</h4>";
                echo "<p>1. Pastikan file config.php ada di direktori yang sama</p>";
                echo "<p>2. Periksa syntax PHP di config.php</p>";
                echo "<p>3. Atau isi konfigurasi database di form bawah ini</p>";
                echo "</div>";
                $hasError = true;
            }
            echo "</div>";
            
            // Check 3: Database Connection (jika config ada)
            if ($configExists && defined('DB_HOST')) {
                echo "<div class='check-item ";
                try {
                    $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
                    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 5
                    ]);
                    
                    echo "status-ok'>";
                    echo "<h3>✅ MySQL Connection - OK</h3>";
                    echo "<p>Koneksi ke MySQL server berhasil.</p>";
                    
                    // Get MySQL version
                    $stmt = $pdo->query("SELECT VERSION() as version");
                    $version = $stmt->fetch(PDO::FETCH_ASSOC)['version'];
                    echo "<p><strong>MySQL Version:</strong> " . htmlspecialchars($version) . "</p>";
                    
                } catch (PDOException $e) {
                    echo "status-error'>";
                    echo "<h3>❌ MySQL Connection - ERROR</h3>";
                    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
                    echo "<div class='solution'>";
                    echo "<h4>💡 Solusi Berdasarkan Error:</h4>";
                    
                    $errorMsg = $e->getMessage();
                    if (strpos($errorMsg, 'Access denied') !== false) {
                        echo "<p><strong>Masalah:</strong> Username atau password salah</p>";
                        echo "<div class='code-block'>";
                        echo "# Login ke MySQL sebagai root:<br>";
                        echo "mysql -u root -p<br><br>";
                        echo "# Buat user dan database:<br>";
                        echo "CREATE DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;<br>";
                        echo "CREATE USER '" . DB_USER . "'@'localhost' IDENTIFIED BY 'your_password';<br>";
                        echo "GRANT ALL PRIVILEGES ON " . DB_NAME . ".* TO '" . DB_USER . "'@'localhost';<br>";
                        echo "FLUSH PRIVILEGES;";
                        echo "</div>";
                    } elseif (strpos($errorMsg, 'Connection refused') !== false) {
                        echo "<p><strong>Masalah:</strong> MySQL service tidak berjalan</p>";
                        echo "<div class='code-block'>";
                        echo "# Ubuntu/Debian:<br>";
                        echo "sudo systemctl start mysql<br>";
                        echo "sudo systemctl enable mysql<br><br>";
                        echo "# CentOS/RHEL:<br>";
                        echo "sudo systemctl start mysqld<br>";
                        echo "sudo systemctl enable mysqld<br><br>";
                        echo "# XAMPP/MAMP:<br>";
                        echo "Start MySQL dari control panel";
                        echo "</div>";
                    } elseif (strpos($errorMsg, "Can't connect to MySQL server") !== false) {
                        echo "<p><strong>Masalah:</strong> MySQL server tidak dapat dijangkau</p>";
                        echo "<p>1. Periksa apakah MySQL berjalan</p>";
                        echo "<p>2. Periksa hostname/port</p>";
                        echo "<p>3. Periksa firewall</p>";
                    }
                    echo "</div>";
                    $hasError = true;
                }
                echo "</div>";
                
                // Check 4: Database Exists (jika connection OK)
                if (!$hasError) {
                    echo "<div class='check-item ";
                    try {
                        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                        ]);
                        
                        echo "status-ok'>";
                        echo "<h3>✅ Database Exists - OK</h3>";
                        echo "<p>Database <strong>" . DB_NAME . "</strong> ditemukan.</p>";
                        
                    } catch (PDOException $e) {
                        echo "status-error'>";
                        echo "<h3>❌ Database Exists - ERROR</h3>";
                        echo "<p>Database <strong>" . DB_NAME . "</strong> tidak ditemukan.</p>";
                        echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
                        echo "<div class='solution'>";
                        echo "<h4>💡 Solusi:</h4>";
                        echo "<p>Buat database terlebih dahulu:</p>";
                        echo "<div class='code-block'>";
                        echo "mysql -u root -p<br>";
                        echo "CREATE DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
                        echo "</div>";
                        echo "</div>";
                        $hasError = true;
                    }
                    echo "</div>";
                    
                    // Check 5: Required Tables (jika database OK)
                    if (!$hasError) {
                        echo "<div class='check-item ";
                        try {
                            $requiredTables = ['admin', 'aplikasi_opd', 'activity_logs', 'rate_limits'];
                            $stmt = $pdo->query("SHOW TABLES");
                            $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            $missingTables = array_diff($requiredTables, $existingTables);
                            
                            if (empty($missingTables)) {
                                echo "status-ok'>";
                                echo "<h3>✅ Database Tables - OK</h3>";
                                echo "<p>Semua tabel yang diperlukan sudah ada.</p>";
                                echo "<p><strong>Tables:</strong> " . implode(', ', $existingTables) . "</p>";
                                
                                // Check admin user
                                $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin");
                                $adminCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                if ($adminCount > 0) {
                                    echo "<p>✅ <strong>Admin users:</strong> {$adminCount} found</p>";
                                } else {
                                    echo "<p>⚠️ <strong>Warning:</strong> No admin users found</p>";
                                }
                                
                                // Check data count
                                $stmt = $pdo->query("SELECT COUNT(*) as count FROM aplikasi_opd");
                                $dataCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                echo "<p>📊 <strong>Application data:</strong> {$dataCount} records</p>";
                                
                            } else {
                                echo "status-error'>";
                                echo "<h3>❌ Database Tables - ERROR</h3>";
                                echo "<p><strong>Missing tables:</strong> " . implode(', ', $missingTables) . "</p>";
                                echo "<p><strong>Existing tables:</strong> " . implode(', ', $existingTables) . "</p>";
                                echo "<div class='solution'>";
                                echo "<h4>💡 Solusi:</h4>";
                                echo "<p>Import schema database:</p>";
                                echo "<div class='code-block'>";
                                echo "mysql -u " . DB_USER . " -p " . DB_NAME . " < aplikasi_madiun.sql";
                                echo "</div>";
                                echo "</div>";
                                $hasError = true;
                            }
                            
                        } catch (PDOException $e) {
                            echo "status-error'>";
                            echo "<h3>❌ Database Tables - ERROR</h3>";
                            echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
                            $hasError = true;
                        }
                        echo "</div>";
                    }
                }
            }
            
            // Test Connection Form
            if ($hasError || !$configExists) {
                ?>
                <div class="config-form">
                    <h3>🔧 Test Database Connection</h3>
                    <p>Isi form ini untuk test koneksi database secara langsung:</p>
                    
                    <form method="post" action="">
                        <input type="hidden" name="test_connection" value="1">
                        <div class="form-group">
                            <label>Database Host:</label>
                            <input type="text" name="db_host" class="form-control" value="localhost" required>
                        </div>
                        <div class="form-group">
                            <label>Database Name:</label>
                            <input type="text" name="db_name" class="form-control" value="aplikasi_madiun" required>
                        </div>
                        <div class="form-group">
                            <label>Database Username:</label>
                            <input type="text" name="db_user" class="form-control" value="root" required>
                        </div>
                        <div class="form-group">
                            <label>Database Password:</label>
                            <input type="password" name="db_pass" class="form-control" placeholder="Enter password">
                        </div>
                        <button type="submit" class="btn">🔍 Test Connection</button>
                    </form>
                    
                    <?php
                    if (isset($_POST['test_connection'])) {
                        $host = $_POST['db_host'];
                        $name = $_POST['db_name'];
                        $user = $_POST['db_user'];
                        $pass = $_POST['db_pass'];
                        
                        echo "<div style='margin-top: 20px;'>";
                        try {
                            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
                            $testPdo = new PDO($dsn, $user, $pass, [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                PDO::ATTR_TIMEOUT => 5
                            ]);
                            
                            echo "<div class='alert alert-success'>";
                            echo "✅ <strong>Connection SUCCESS!</strong><br>";
                            echo "Database connection berhasil dengan parameter:<br>";
                            echo "• Host: {$host}<br>";
                            echo "• Database: {$name}<br>";
                            echo "• Username: {$user}<br>";
                            
                            // Generate config.php content
                            echo "<br><strong>📝 Copy konfigurasi ini ke file config.php:</strong>";
                            echo "<div class='code-block'>";
                            echo "define('DB_HOST', '{$host}');<br>";
                            echo "define('DB_NAME', '{$name}');<br>";
                            echo "define('DB_USER', '{$user}');<br>";
                            echo "define('DB_PASS', '{$pass}');<br>";
                            echo "</div>";
                            echo "</div>";
                            
                        } catch (PDOException $e) {
                            echo "<div class='alert alert-error'>";
                            echo "❌ <strong>Connection FAILED!</strong><br>";
                            echo "Error: " . htmlspecialchars($e->getMessage());
                            echo "</div>";
                        }
                        echo "</div>";
                    }
                    ?>
                </div>
                <?php
            }
            
            // Summary
            echo "<div class='check-item " . ($hasError ? 'status-error' : 'status-ok') . "'>";
            if ($hasError) {
                echo "<h3>❌ Overall Status: ISSUES FOUND</h3>";
                echo "<p>Ada beberapa masalah yang perlu diperbaiki sebelum aplikasi dapat berjalan dengan normal.</p>";
                echo "<p><strong>Langkah selanjutnya:</strong></p>";
                echo "<ol>";
                echo "<li>Perbaiki masalah yang ditandai dengan ❌</li>";
                echo "<li>Refresh halaman ini untuk mengecek ulang</li>";
                echo "<li>Jika sudah OK semua, coba akses <a href='admin.php'>admin.php</a> lagi</li>";
                echo "</ol>";
            } else {
                echo "<h3>✅ Overall Status: ALL GOOD</h3>";
                echo "<p>Semua pemeriksaan berhasil! Database siap digunakan.</p>";
                echo "<p><strong>Aplikasi siap digunakan:</strong></p>";
                echo "<p>";
                echo "<a href='index.php' class='btn'>📝 Form Input OPD</a> ";
                echo "<a href='admin.php' class='btn'>🔐 Admin Dashboard</a>";
                echo "</p>";
            }
            echo "</div>";
            ?>
            
            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e6ed;">
                <button onclick="location.reload()" class="btn">🔄 Refresh Check</button>
                <a href="admin.php" class="btn">🔐 Try Admin Login</a>
                <a href="index.php" class="btn">🏠 Main Form</a>
            </div>
        </div>
    </div>
</body>
</html>