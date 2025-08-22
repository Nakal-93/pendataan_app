<?php
/**
 * Password Reset Tool untuk Aplikasi Kabupaten Madiun
 * File: reset_password.php
 * 
 * PENTING: HAPUS FILE INI SETELAH RESET PASSWORD SELESAI!
 * Tool ini memiliki akses full ke database dan berbahaya jika tidak dihapus
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include config
$configLoaded = false;
$configError = '';

if (file_exists('config.php')) {
    try {
        require_once 'config.php';
        $configLoaded = true;
    } catch (Exception $e) {
        $configError = $e->getMessage();
    }
}

$message = '';
$messageType = '';
$adminUsers = [];

// Get database connection
function getDBConnection() {
    global $configLoaded;
    
    if (!$configLoaded) {
        throw new Exception("Config file tidak dapat dimuat");
    }
    
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Koneksi database gagal: " . $e->getMessage());
    }
}

// Load existing admin users
if ($configLoaded) {
    try {
        $db = getDBConnection();
        
        // Check if admin table exists
        $stmt = $db->query("SHOW TABLES LIKE 'admin'");
        if ($stmt->rowCount() > 0) {
            // Get all admin users
            $stmt = $db->query("
                SELECT id, username, email, created_at, last_login, login_attempts, locked_until
                FROM admin 
                ORDER BY created_at ASC
            ");
            $adminUsers = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        $message = "Error loading admin users: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDBConnection();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_admin':
                    $result = createAdmin($db, $_POST);
                    break;
                case 'reset_password':
                    $result = resetPassword($db, $_POST);
                    break;
                case 'unlock_account':
                    $result = unlockAccount($db, $_POST);
                    break;
                case 'delete_admin':
                    $result = deleteAdmin($db, $_POST);
                    break;
                case 'create_table':
                    $result = createAdminTable($db);
                    break;
            }
            
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            
            // Reload admin users
            if ($result['success']) {
                $stmt = $db->query("
                    SELECT id, username, email, created_at, last_login, login_attempts, locked_until
                    FROM admin 
                    ORDER BY created_at ASC
                ");
                $adminUsers = $stmt->fetchAll();
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

function createAdminTable($db) {
    try {
        $sql = "
        CREATE TABLE IF NOT EXISTS admin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            login_attempts INT DEFAULT 0,
            locked_until TIMESTAMP NULL,
            INDEX idx_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $db->exec($sql);
        
        return [
            'success' => true,
            'message' => "✅ Tabel admin berhasil dibuat!"
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => "❌ Gagal membuat tabel admin: " . $e->getMessage()
        ];
    }
}

function createAdmin($db, $data) {
    $username = trim($data['username']);
    $password = $data['password'];
    $email = trim($data['email']);
    
    if (empty($username) || empty($password)) {
        return [
            'success' => false,
            'message' => "❌ Username dan password wajib diisi"
        ];
    }
    
    if (strlen($password) < 6) {
        return [
            'success' => false,
            'message' => "❌ Password minimal 6 karakter"
        ];
    }
    
    try {
        // Check if username exists
        $stmt = $db->prepare("SELECT id FROM admin WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            return [
                'success' => false,
                'message' => "❌ Username '{$username}' sudah ada"
            ];
        }
        
        // Create new admin
        $passwordHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
        $stmt = $db->prepare("
            INSERT INTO admin (username, password_hash, email) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $passwordHash, $email]);
        
        return [
            'success' => true,
            'message' => "✅ Admin '{$username}' berhasil dibuat!<br>Password: <strong>{$password}</strong><br>Silakan login dengan kredensial ini."
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => "❌ Error: " . $e->getMessage()
        ];
    }
}

function resetPassword($db, $data) {
    $adminId = (int)$data['admin_id'];
    $newPassword = $data['new_password'];
    
    if (empty($newPassword)) {
        return [
            'success' => false,
            'message' => "❌ Password baru wajib diisi"
        ];
    }
    
    if (strlen($newPassword) < 6) {
        return [
            'success' => false,
            'message' => "❌ Password minimal 6 karakter"
        ];
    }
    
    try {
        // Get admin info
        $stmt = $db->prepare("SELECT username FROM admin WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            return [
                'success' => false,
                'message' => "❌ Admin tidak ditemukan"
            ];
        }
        
        // Update password and unlock account
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => 12]);
        $stmt = $db->prepare("
            UPDATE admin 
            SET password_hash = ?, login_attempts = 0, locked_until = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$passwordHash, $adminId]);
        
        return [
            'success' => true,
            'message' => "✅ Password untuk '{$admin['username']}' berhasil direset!<br>Password baru: <strong>{$newPassword}</strong><br>Akun sudah di-unlock dan siap digunakan."
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => "❌ Error: " . $e->getMessage()
        ];
    }
}

function unlockAccount($db, $data) {
    $adminId = (int)$data['admin_id'];
    
    try {
        $stmt = $db->prepare("SELECT username FROM admin WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            return [
                'success' => false,
                'message' => "❌ Admin tidak ditemukan"
            ];
        }
        
        $stmt = $db->prepare("
            UPDATE admin 
            SET login_attempts = 0, locked_until = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$adminId]);
        
        return [
            'success' => true,
            'message' => "✅ Akun '{$admin['username']}' berhasil di-unlock!"
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => "❌ Error: " . $e->getMessage()
        ];
    }
}

function deleteAdmin($db, $data) {
    $adminId = (int)$data['admin_id'];
    
    try {
        $stmt = $db->prepare("SELECT username FROM admin WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            return [
                'success' => false,
                'message' => "❌ Admin tidak ditemukan"
            ];
        }
        
        // Don't allow deleting if it's the only admin
        $stmt = $db->query("SELECT COUNT(*) as count FROM admin");
        $count = $stmt->fetch()['count'];
        
        if ($count <= 1) {
            return [
                'success' => false,
                'message' => "❌ Tidak dapat menghapus admin terakhir"
            ];
        }
        
        $stmt = $db->prepare("DELETE FROM admin WHERE id = ?");
        $stmt->execute([$adminId]);
        
        return [
            'success' => true,
            'message' => "✅ Admin '{$admin['username']}' berhasil dihapus!"
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => "❌ Error: " . $e->getMessage()
        ];
    }
}

// Generate secure random password
function generateSecurePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

$securePassword = generateSecurePassword();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Tool - Kabupaten Madiun</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; padding: 20px;
        }
        .container {
            max-width: 1200px; margin: 0 auto;
            background: white; border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
            color: white; padding: 30px; text-align: center;
        }
        .header h1 { font-size: 2.2em; margin-bottom: 10px; font-weight: 600; }
        .content { padding: 30px; }
        .warning-box {
            background: #fff3cd; border: 2px solid #ffc107; color: #856404;
            padding: 20px; border-radius: 10px; margin-bottom: 25px;
            text-align: center;
        }
        .warning-box h3 { color: #e74c3c; margin-bottom: 10px; }
        .section {
            border: 1px solid #e0e6ed; border-radius: 10px;
            margin-bottom: 25px; padding: 20px; background: #f8f9fa;
        }
        .section h3 { color: #2c3e50; margin-bottom: 15px; font-size: 1.3em; }
        .admin-table {
            width: 100%; border-collapse: collapse; margin: 15px 0;
            background: white; border-radius: 8px; overflow: hidden;
        }
        .admin-table th, .admin-table td {
            padding: 12px 15px; text-align: left; border-bottom: 1px solid #e0e6ed;
        }
        .admin-table th { background: #2c3e50; color: white; font-weight: 600; }
        .admin-table tr:hover { background: #f8f9fa; }
        .status-badge {
            padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: 500;
        }
        .status-ok { background: #d4edda; color: #155724; }
        .status-locked { background: #f8d7da; color: #721c24; }
        .status-warning { background: #fff3cd; color: #856404; }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block; margin-bottom: 5px;
            font-weight: 600; color: #2c3e50;
        }
        .form-control {
            width: 100%; padding: 10px 12px; border: 1px solid #e0e6ed;
            border-radius: 5px; font-size: 1em; transition: all 0.3s ease;
        }
        .form-control:focus {
            outline: none; border-color: #e67e22;
            box-shadow: 0 0 0 2px rgba(230, 126, 34, 0.1);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn {
            background: #e67e22; color: white; padding: 10px 20px;
            border: none; border-radius: 5px; cursor: pointer;
            font-weight: 500; transition: all 0.3s ease;
            text-decoration: none; display: inline-block;
            margin: 5px 5px 5px 0; font-size: 0.9em;
        }
        .btn:hover { background: #d35400; transform: translateY(-1px); }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #e67e22; }
        .btn-small { padding: 6px 12px; font-size: 0.8em; }
        .alert { padding: 15px; border-radius: 8px; margin: 15px 0; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #cce5ff; color: #004085; border: 1px solid #b3d7ff; }
        .quick-actions {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .action-card {
            background: white; border: 1px solid #e0e6ed; border-radius: 8px;
            padding: 20px; text-align: center;
        }
        .action-card h4 { color: #2c3e50; margin-bottom: 10px; }
        .password-generator {
            background: #e8f5e8; border: 1px solid #c3e6c3;
            padding: 15px; border-radius: 5px; margin: 10px 0;
        }
        .password-display {
            background: #2c3e50; color: #ecf0f1; padding: 10px;
            border-radius: 5px; font-family: monospace; font-size: 1.1em;
            margin: 10px 0; word-break: break-all;
        }
        .no-admin-box {
            background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;
            padding: 20px; border-radius: 8px; text-align: center;
        }
        @media (max-width: 768px) {
            .container { margin: 10px; border-radius: 10px; }
            .header { padding: 20px; }
            .header h1 { font-size: 1.8em; }
            .content { padding: 20px; }
            .form-row { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: 1fr; }
            .admin-table { font-size: 0.9em; }
            .admin-table th, .admin-table td { padding: 8px 10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Password Reset Tool</h1>
            <p>Reset Password Admin - Kabupaten Madiun</p>
        </div>
        
        <div class="content">
            <div class="warning-box">
                <h3>⚠️ PERINGATAN KEAMANAN TINGGI!</h3>
                <p><strong>File ini memberikan akses penuh ke database!</strong></p>
                <p>🗑️ <strong>HAPUS FILE reset_password.php INI SEGERA setelah selesai reset password!</strong></p>
                <p>File ini berbahaya jika dibiarkan di server dan bisa diakses orang lain.</p>
            </div>
            
            <?php if (!$configLoaded): ?>
                <div class="alert alert-error">
                    <strong>❌ Config Error:</strong><br>
                    File config.php tidak dapat dimuat atau ada error.<br>
                    <?php if (!empty($configError)): ?>
                        Error: <?php echo htmlspecialchars($configError); ?>
                    <?php endif; ?>
                    <br><br>
                    <a href="db_check.php" class="btn">🩺 Diagnostic Tool</a>
                    <a href="mamp_fix.php" class="btn">🔧 MAMP Fix</a>
                </div>
            <?php else: ?>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($adminUsers)): ?>
                    <!-- No Admin Users - Show Create Admin -->
                    <div class="no-admin-box">
                        <h3>❌ Tidak Ada Admin User</h3>
                        <p>Tabel admin kosong atau belum ada. Anda perlu membuat admin user terlebih dahulu.</p>
                        
                        <?php
                        // Check if admin table exists
                        try {
                            $db = getDBConnection();
                            $stmt = $db->query("SHOW TABLES LIKE 'admin'");
                            $tableExists = $stmt->rowCount() > 0;
                        } catch (Exception $e) {
                            $tableExists = false;
                        }
                        ?>
                        
                        <?php if (!$tableExists): ?>
                            <p><strong>Tabel admin belum ada!</strong></p>
                            <form method="post" style="margin: 20px 0;">
                                <input type="hidden" name="action" value="create_table">
                                <button type="submit" class="btn btn-warning">🏗️ Buat Tabel Admin</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <div class="section">
                        <h3>👤 Buat Admin User Pertama</h3>
                        <form method="post">
                            <input type="hidden" name="action" value="create_admin">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Username:</label>
                                    <input type="text" name="username" class="form-control" value="admin" required>
                                </div>
                                <div class="form-group">
                                    <label>Email (opsional):</label>
                                    <input type="email" name="email" class="form-control" placeholder="admin@madiunkab.go.id">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Password:</label>
                                <input type="text" name="password" class="form-control" id="newPassword" value="<?php echo $securePassword; ?>" required>
                                <small>💡 Password aman sudah digenerate otomatis, atau ganti dengan password pilihan Anda</small>
                            </div>
                            
                            <button type="submit" class="btn btn-success">👤 Buat Admin User</button>
                            <button type="button" class="btn btn-warning" onclick="generateNewPassword()">🎲 Generate Password Baru</button>
                        </form>
                    </div>
                    
                <?php else: ?>
                    <!-- Show existing admins and management -->
                    <div class="section">
                        <h3>👥 Daftar Admin Users (<?php echo count($adminUsers); ?>)</h3>
                        
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminUsers as $admin): ?>
                                    <tr>
                                        <td><?php echo $admin['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($admin['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($admin['email'] ?: '-'); ?></td>
                                        <td>
                                            <?php
                                            if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
                                                echo '<span class="status-badge status-locked">🔒 Locked</span>';
                                            } elseif ($admin['login_attempts'] >= 3) {
                                                echo '<span class="status-badge status-warning">⚠️ ' . $admin['login_attempts'] . ' attempts</span>';
                                            } else {
                                                echo '<span class="status-badge status-ok">✅ Active</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo $admin['last_login'] ? date('d/m/Y H:i', strtotime($admin['last_login'])) : 'Never'; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-success btn-small" onclick="showResetForm(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['username']); ?>')">
                                                🔑 Reset Password
                                            </button>
                                            
                                            <?php if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()): ?>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="action" value="unlock_account">
                                                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                    <button type="submit" class="btn btn-warning btn-small">🔓 Unlock</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if (count($adminUsers) > 1): ?>
                                                <button class="btn btn-danger btn-small" onclick="confirmDelete(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['username']); ?>')">
                                                    🗑️ Delete
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <div class="action-card">
                            <h4>🚀 Quick Reset</h4>
                            <p>Reset password admin 'admin' dengan password 'admin123'</p>
                            <form method="post">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="admin_id" value="<?php echo $adminUsers[0]['id']; ?>">
                                <input type="hidden" name="new_password" value="admin123">
                                <button type="submit" class="btn btn-warning">⚡ Quick Reset ke 'admin123'</button>
                            </form>
                        </div>
                        
                        <div class="action-card">
                            <h4>👤 Add New Admin</h4>
                            <p>Tambah admin user baru</p>
                            <button class="btn btn-success" onclick="showCreateForm()">➕ Add New Admin</button>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Reset Password Form (Hidden by default) -->
                <div id="resetPasswordForm" class="section" style="display: none;">
                    <h3>🔑 Reset Password</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="admin_id" id="resetAdminId">
                        
                        <div class="alert alert-info">
                            Reset password untuk user: <strong id="resetUsername"></strong>
                        </div>
                        
                        <div class="password-generator">
                            <strong>🎲 Password Generator:</strong>
                            <div class="password-display" id="generatedPassword"><?php echo $securePassword; ?></div>
                            <button type="button" class="btn btn-warning" onclick="generateNewPassword()">🎲 Generate Baru</button>
                            <button type="button" class="btn" onclick="copyPassword()">📋 Copy Password</button>
                        </div>
                        
                        <div class="form-group">
                            <label>Password Baru:</label>
                            <input type="text" name="new_password" class="form-control" id="resetPasswordInput" value="<?php echo $securePassword; ?>" required>
                            <small>💡 Gunakan password dari generator di atas atau masukkan password pilihan Anda</small>
                        </div>
                        
                        <button type="submit" class="btn btn-success">🔑 Reset Password</button>
                        <button type="button" class="btn" onclick="hideResetForm()">❌ Cancel</button>
                    </form>
                </div>
                
                <!-- Create New Admin Form (Hidden by default) -->
                <div id="createAdminForm" class="section" style="display: none;">
                    <h3>👤 Buat Admin User Baru</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="create_admin">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Username:</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Email (opsional):</label>
                                <input type="email" name="email" class="form-control" placeholder="admin@madiunkab.go.id">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Password:</label>
                            <input type="text" name="password" class="form-control" id="createPasswordInput" value="<?php echo generateSecurePassword(); ?>" required>
                        </div>
                        
                        <button type="submit" class="btn btn-success">👤 Buat Admin</button>
                        <button type="button" class="btn" onclick="hideCreateForm()">❌ Cancel</button>
                    </form>
                </div>
                
                <!-- Delete Confirmation Form (Hidden) -->
                <div id="deleteConfirmForm" class="section" style="display: none; background: #f8d7da; border: 2px solid #f5c6cb;">
                    <h3 style="color: #721c24;">🗑️ Konfirmasi Hapus Admin</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="delete_admin">
                        <input type="hidden" name="admin_id" id="deleteAdminId">
                        
                        <div class="alert alert-error">
                            <strong>⚠️ PERINGATAN!</strong><br>
                            Anda akan menghapus admin user: <strong id="deleteUsername"></strong><br>
                            Tindakan ini tidak dapat dibatalkan!
                        </div>
                        
                        <button type="submit" class="btn btn-danger">🗑️ Ya, Hapus Admin</button>
                        <button type="button" class="btn" onclick="hideDeleteForm()">❌ Cancel</button>
                    </form>
                </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e6ed;">
                <a href="admin.php" class="btn btn-success">🔐 Test Login Admin</a>
                <a href="index.php" class="btn">🏠 Main App</a>
                <a href="db_check.php" class="btn">🩺 Database Check</a>
                
                <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 5px;">
                    <strong>🗑️ Jangan lupa HAPUS file reset_password.php setelah selesai!</strong>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function generateNewPassword() {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
            let password = '';
            for (let i = 0; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            document.getElementById('generatedPassword').textContent = password;
            if (document.getElementById('resetPasswordInput')) {
                document.getElementById('resetPasswordInput').value = password;
            }
            if (document.getElementById('newPassword')) {
                document.getElementById('newPassword').value = password;
            }
            if (document.getElementById('createPasswordInput')) {
                document.getElementById('createPasswordInput').value = password;
            }
        }
        
        function copyPassword() {
            const password = document.getElementById('generatedPassword').textContent;
            navigator.clipboard.writeText(password).then(() => {
                alert('Password copied to clipboard!');
            });
        }
        
        function showResetForm(adminId, username) {
            document.getElementById('resetAdminId').value = adminId;
            document.getElementById('resetUsername').textContent = username;
            document.getElementById('resetPasswordForm').style.display = 'block';
            document.getElementById('resetPasswordForm').scrollIntoView();
        }
        
        function hideResetForm() {
            document.getElementById('resetPasswordForm').style.display = 'none';
        }
        
        function showCreateForm() {
            document.getElementById('createAdminForm').style.display = 'block';
            document.getElementById('createAdminForm').scrollIntoView();
        }
        
        function hideCreateForm() {
            document.getElementById('createAdminForm').style.display = 'none';
        }
        
        function confirmDelete(adminId, username) {
            document.getElementById('deleteAdminId').value = adminId;
            document.getElementById('deleteUsername').textContent = username;
            document.getElementById('deleteConfirmForm').style.display = 'block';
            document.getElementById('deleteConfirmForm').scrollIntoView();
        }
        
        function hideDeleteForm() {
            document.getElementById('deleteConfirmForm').style.display = 'none';
        }
    </script>
</body>
</html>