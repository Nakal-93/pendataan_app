<?php
/**
 * Aplikasi Pendataan Aplikasi Kabupaten Madiun
 * File: admin.php
 * Admin login dan dashboard
 */

require_once 'config.php';

// Initialize session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);

$message = '';
$messageType = '';

// Check if already logged in
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Handle logout
if (isset($_GET['logout']) && $isLoggedIn) {
    Security::logActivity('ADMIN_LOGOUT');
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLoggedIn) {
    // Check rate limiting
    if (!Security::checkRateLimit('admin_login')) {
        $message = 'Terlalu banyak percobaan login. Silakan coba lagi dalam beberapa menit.';
        $messageType = 'error';
    } else {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
            $message = 'Token keamanan tidak valid. Silakan refresh halaman.';
            $messageType = 'error';
        } else {
            $username = Security::sanitizeInput($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $message = 'Username dan password harus diisi.';
                $messageType = 'error';
            } else {
                try {
                    $db = Security::initDB();
                    
                    // Check if account is locked
                    $stmt = $db->prepare("SELECT id, username, password_hash, login_attempts, locked_until FROM admin WHERE username = ?");
                    $stmt->execute([$username]);
                    $admin = $stmt->fetch();
                    
                    if ($admin) {
                        // Check if account is locked
                        if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
                            $message = 'Akun terkunci. Silakan coba lagi nanti.';
                            $messageType = 'error';
                        } else {
                            // Verify password
                            if (password_verify($password, $admin['password_hash'])) {
                                // Reset login attempts and unlock account
                                $stmt = $db->prepare("UPDATE admin SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?");
                                $stmt->execute([$admin['id']]);
                                
                                // Set session
                                $_SESSION['admin_logged_in'] = true;
                                $_SESSION['admin_id'] = $admin['id'];
                                $_SESSION['admin_username'] = $admin['username'];
                                $_SESSION['login_time'] = time();
                                
                                Security::regenerateSession();
                                Security::logActivity('ADMIN_LOGIN_SUCCESS', 'admin', $admin['id']);
                                
                                header('Location: admin.php');
                                exit;
                            } else {
                                // Increment login attempts
                                $attempts = $admin['login_attempts'] + 1;
                                $lockedUntil = null;
                                
                                if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                                    $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_TIME);
                                }
                                
                                $stmt = $db->prepare("UPDATE admin SET login_attempts = ?, locked_until = ? WHERE id = ?");
                                $stmt->execute([$attempts, $lockedUntil, $admin['id']]);
                                
                                $message = 'Username atau password salah.';
                                $messageType = 'error';
                                
                                Security::logActivity('ADMIN_LOGIN_FAILED', 'admin', $admin['id'], null, ['username' => $username]);
                            }
                        }
                    } else {
                        $message = 'Username atau password salah.';
                        $messageType = 'error';
                        Security::logActivity('ADMIN_LOGIN_FAILED', null, null, null, ['username' => $username]);
                    }
                } catch (Exception $e) {
                    $message = 'Terjadi kesalahan sistem. Silakan coba lagi.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Check session timeout
if ($isLoggedIn) {
    if (!isset($_SESSION['login_time']) || (time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
        Security::logActivity('SESSION_TIMEOUT');
        session_destroy();
        header('Location: admin.php?timeout=1');
        exit;
    }
    
    // Update last activity
    $_SESSION['login_time'] = time();
}

// Generate new CSRF token
$csrfToken = Security::generateCSRFToken();

// Get statistics if logged in
$stats = null;
if ($isLoggedIn) {
    try {
        $db = Security::initDB();
        
        // Get total statistics
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total_aplikasi,
                SUM(CASE WHEN status_aplikasi = 'Aktif' THEN 1 ELSE 0 END) as aplikasi_aktif,
                SUM(CASE WHEN status_aplikasi = 'Tidak Aktif' THEN 1 ELSE 0 END) as aplikasi_tidak_aktif,
                COUNT(CASE WHEN jenis_aplikasi = 'Aplikasi Khusus/Daerah' THEN 1 END) as app_daerah,
                COUNT(CASE WHEN jenis_aplikasi = 'Aplikasi Pusat/Umum' THEN 1 END) as app_pusat,
                COUNT(CASE WHEN jenis_aplikasi = 'Aplikasi Lainnya' THEN 1 END) as app_lainnya
            FROM aplikasi_opd
        ");
        $stats = $stmt->fetch();
        
        // Get recent entries
        $stmt = $db->query("
            SELECT nama_perangkat_daerah, nama_aplikasi, status_aplikasi, created_at 
            FROM aplikasi_opd 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $recentEntries = $stmt->fetchAll();
        
        // Get data by OPD
        $stmt = $db->query("SELECT * FROM v_laporan_aplikasi LIMIT 20");
        $opdStats = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $stats = null;
    }
}

// Check for timeout message
if (isset($_GET['timeout'])) {
    $message = 'Sesi telah berakhir. Silakan login kembali.';
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        
        .header h1 {
            font-size: 2.2em;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .content {
            padding: 40px;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .login-form {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #e74c3c;
            background: white;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }
        
        .btn {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #e0e6ed;
        }
        
        .stat-card h3 {
            font-size: 2.5em;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .stat-card p {
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .stat-card.active {
            background: #d4edda;
            border-color: #c3e6cb;
        }
        
        .stat-card.inactive {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e6ed;
        }
        
        .data-table th {
            background: #2c3e50;
            color: white;
            font-weight: 600;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .status-aktif {
            background: #d4edda;
            color: #155724;
        }
        
        .status-tidak-aktif {
            background: #f8d7da;
            color: #721c24;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.5em;
            border-bottom: 2px solid #e0e6ed;
            padding-bottom: 10px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e6ed;
        }
        
        .back-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link a:hover {
            color: #2980b9;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 10px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 1.8em;
            }
            
            .content {
                padding: 25px;
            }
            
            .logout-btn {
                position: static;
                margin-top: 15px;
                display: inline-block;
            }
            
            .data-table {
                font-size: 0.9em;
            }
            
            .data-table th,
            .data-table td {
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Admin Dashboard</h1>
            <p><?php echo SITE_NAME; ?></p>
            <?php if ($isLoggedIn): ?>
                <a href="admin.php?logout=1" class="logout-btn">🚪 Logout</a>
            <?php endif; ?>
        </div>
        
        <div class="content">
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$isLoggedIn): ?>
                <!-- Login Form -->
                <div class="login-form">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" name="username" id="username" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn">🔑 Login</button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Dashboard -->
                <div class="section">
                    <h2>📊 Statistik Aplikasi</h2>
                    
                    <?php if ($stats): ?>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <h3><?php echo number_format($stats['total_aplikasi']); ?></h3>
                                <p>Total Aplikasi</p>
                            </div>
                            <div class="stat-card active">
                                <h3><?php echo number_format($stats['aplikasi_aktif']); ?></h3>
                                <p>Aplikasi Aktif</p>
                            </div>
                            <div class="stat-card inactive">
                                <h3><?php echo number_format($stats['aplikasi_tidak_aktif']); ?></h3>
                                <p>Aplikasi Tidak Aktif</p>
                            </div>
                            <div class="stat-card">
                                <h3><?php echo number_format($stats['app_daerah']); ?></h3>
                                <p>Aplikasi Daerah</p>
                            </div>
                            <div class="stat-card">
                                <h3><?php echo number_format($stats['app_pusat']); ?></h3>
                                <p>Aplikasi Pusat</p>
                            </div>
                            <div class="stat-card">
                                <h3><?php echo number_format($stats['app_lainnya']); ?></h3>
                                <p>Aplikasi Lainnya</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($recentEntries) && !empty($recentEntries)): ?>
                <div class="section">
                    <h2>🕒 Entri Terbaru</h2>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Perangkat Daerah</th>
                                <th>Nama Aplikasi</th>
                                <th>Status</th>
                                <th>Tanggal Input</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentEntries as $entry): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($entry['nama_perangkat_daerah']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['nama_aplikasi']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $entry['status_aplikasi'])); ?>">
                                            <?php echo $entry['status_aplikasi']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($entry['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <?php if (isset($opdStats) && !empty($opdStats)): ?>
                <div class="section">
                    <h2>📈 Rekapitulasi Per OPD</h2>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Perangkat Daerah</th>
                                <th>Total</th>
                                <th>Aktif</th>
                                <th>Tidak Aktif</th>
                                <th>Daerah</th>
                                <th>Pusat</th>
                                <th>Lainnya</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($opdStats as $opd): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($opd['nama_perangkat_daerah']); ?></td>
                                    <td><strong><?php echo $opd['total_aplikasi']; ?></strong></td>
                                    <td class="status-aktif"><?php echo $opd['aplikasi_aktif']; ?></td>
                                    <td class="status-tidak-aktif"><?php echo $opd['aplikasi_tidak_aktif']; ?></td>
                                    <td><?php echo $opd['app_daerah']; ?></td>
                                    <td><?php echo $opd['app_pusat']; ?></td>
                                    <td><?php echo $opd['app_lainnya']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <div class="section">
                    <p><strong>🔗 Menu Lainnya:</strong></p>
                    <p><a href="data_export.php" style="color: #3498db;">📊 Export Data Excel</a> | 
                       <a href="data_detail.php" style="color: #3498db;">📋 Lihat Data Detail</a></p>
                </div>
            <?php endif; ?>
            
            <div class="back-link">
                <a href="index.php">⬅️ Kembali ke Form Input</a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto refresh page every 5 minutes
        <?php if ($isLoggedIn): ?>
        setTimeout(function() {
            window.location.reload();
        }, 300000); // 5 minutes
        <?php endif; ?>
        
        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>