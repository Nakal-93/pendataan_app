<?php
/**
 * Aplikasi Pendataan Aplikasi Kabupaten Madiun
 * File: index.php
 * Form input untuk OPD (tanpa login)
 */

require_once 'config.php';

// Initialize session for CSRF
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check rate limiting
    if (!Security::checkRateLimit('form_submit')) {
        $message = 'Terlalu banyak percobaan. Silakan coba lagi dalam beberapa menit.';
        $messageType = 'error';
    } else {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
            $message = 'Token keamanan tidak valid. Silakan refresh halaman.';
            $messageType = 'error';
        } else {
            // Sanitize all inputs
            $data = Security::sanitizeInput($_POST);
            
            // Validation
            $errors = [];
            
            if (empty($data['nama_perangkat_daerah'])) {
                $errors[] = 'Nama Perangkat Daerah harus dipilih';
            } elseif (!in_array($data['nama_perangkat_daerah'], $opdList)) {
                $errors[] = 'Perangkat Daerah tidak valid';
            }
            
            if (empty($data['nama_aplikasi']) || strlen($data['nama_aplikasi']) < 3) {
                $errors[] = 'Nama Aplikasi minimal 3 karakter';
            }
            
            if (strlen($data['nama_aplikasi']) > 255) {
                $errors[] = 'Nama Aplikasi maksimal 255 karakter';
            }
            
            if (!empty($data['alamat_domain']) && !Security::validateURL($data['alamat_domain'])) {
                $errors[] = 'Format URL/Domain tidak valid';
            }
            
            if (!in_array($data['jenis_aplikasi'], ['Aplikasi Khusus/Daerah', 'Aplikasi Pusat/Umum', 'Aplikasi Lainnya'])) {
                $errors[] = 'Jenis Aplikasi tidak valid';
            }
            
            if (!in_array($data['status_aplikasi'], ['Aktif', 'Tidak Aktif'])) {
                $errors[] = 'Status Aplikasi tidak valid';
            }
            
            if ($data['status_aplikasi'] === 'Tidak Aktif' && empty($data['penyebab_tidak_aktif'])) {
                $errors[] = 'Penyebab Tidak Aktif harus diisi jika status tidak aktif';
            }
            
            if (empty($data['nama_pengelola']) || strlen($data['nama_pengelola']) < 3) {
                $errors[] = 'Nama Pengelola minimal 3 karakter';
            }
            
            if (empty($data['nomor_wa_pengelola']) || !Security::validatePhone($data['nomor_wa_pengelola'])) {
                $errors[] = 'Nomor WhatsApp tidak valid (format: 08xxxxxxxxxx atau +628xxxxxxxxxx)';
            }
            
            // Check for XSS attempts
            foreach ($data as $key => $value) {
                if (Security::containsXSS($value)) {
                    $errors[] = 'Input mengandung kode berbahaya';
                    Security::logActivity('XSS_ATTEMPT', 'aplikasi_opd', null, null, ['field' => $key, 'value' => $value]);
                    break;
                }
            }
            
            if (empty($errors)) {
                try {
                    $db = Security::initDB();
                    
                    $stmt = $db->prepare("
                        INSERT INTO aplikasi_opd 
                        (nama_perangkat_daerah, nama_aplikasi, deskripsi_singkat, alamat_domain, 
                         jenis_aplikasi, status_aplikasi, penyebab_tidak_aktif, nama_pengelola, 
                         nomor_wa_pengelola, ip_address, user_agent) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $data['nama_perangkat_daerah'],
                        $data['nama_aplikasi'],
                        $data['deskripsi_singkat'],
                        $data['alamat_domain'],
                        $data['jenis_aplikasi'],
                        $data['status_aplikasi'],
                        $data['status_aplikasi'] === 'Tidak Aktif' ? $data['penyebab_tidak_aktif'] : null,
                        $data['nama_pengelola'],
                        $data['nomor_wa_pengelola'],
                        Security::getClientIP(),
                        Security::getUserAgent()
                    ]);
                    
                    $recordId = $db->lastInsertId();
                    
                    // Log successful submission
                    Security::logActivity('INSERT', 'aplikasi_opd', $recordId, null, $data);
                    
                    $message = 'Data aplikasi berhasil disimpan!';
                    $messageType = 'success';
                    
                    // Clear form data
                    $_POST = [];
                    
                } catch (Exception $e) {
                    $message = 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.';
                    $messageType = 'error';
                    Security::logActivity('INSERT_ERROR', 'aplikasi_opd', null, null, ['error' => $e->getMessage()]);
                }
            } else {
                $message = implode('<br>', $errors);
                $messageType = 'error';
            }
        }
    }
}

// Generate new CSRF token
$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
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
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
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
        
        .form-container {
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
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1em;
        }
        
        .required {
            color: #e74c3c;
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
            border-color: #3498db;
            background: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
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
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .admin-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e6ed;
        }
        
        .admin-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .admin-link a:hover {
            color: #2980b9;
        }
        
        .info-box {
            background: #e8f4f8;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
        }
        
        .info-box h3 {
            color: #0c5460;
            margin-bottom: 8px;
        }
        
        .info-box p {
            color: #0c5460;
            margin: 0;
            line-height: 1.5;
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
            
            .form-container {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo SITE_NAME; ?></h1>
            <p>Form Pendataan Aplikasi untuk Organisasi Perangkat Daerah</p>
        </div>
        
        <div class="form-container">
            <div class="info-box">
                <h3>📋 Petunjuk Pengisian</h3>
                <p>Silakan isi form ini untuk mendaftarkan aplikasi yang dikelola oleh OPD Anda. 
                   Data yang diisi akan membantu dalam monitoring dan evaluasi aplikasi di lingkungan Kabupaten Madiun.</p>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="applicationForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="form-group">
                    <label for="nama_perangkat_daerah">Nama Perangkat Daerah <span class="required">*</span></label>
                    <select name="nama_perangkat_daerah" id="nama_perangkat_daerah" class="form-control" required>
                        <option value="">-- Pilih Perangkat Daerah --</option>
                        <?php foreach ($opdList as $opd): ?>
                            <option value="<?php echo htmlspecialchars($opd); ?>" 
                                <?php echo (isset($_POST['nama_perangkat_daerah']) && $_POST['nama_perangkat_daerah'] === $opd) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opd); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="nama_aplikasi">Nama Aplikasi <span class="required">*</span></label>
                    <input type="text" name="nama_aplikasi" id="nama_aplikasi" class="form-control" 
                           value="<?php echo isset($_POST['nama_aplikasi']) ? htmlspecialchars($_POST['nama_aplikasi']) : ''; ?>"
                           placeholder="Contoh: SIMPEG, SIPKD, E-Office" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="deskripsi_singkat">Deskripsi Singkat</label>
                    <textarea name="deskripsi_singkat" id="deskripsi_singkat" class="form-control" 
                              placeholder="Jelaskan secara singkat fungsi dan tujuan aplikasi"><?php echo isset($_POST['deskripsi_singkat']) ? htmlspecialchars($_POST['deskripsi_singkat']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="alamat_domain">Alamat Domain / Link</label>
                    <input type="url" name="alamat_domain" id="alamat_domain" class="form-control" 
                           value="<?php echo isset($_POST['alamat_domain']) ? htmlspecialchars($_POST['alamat_domain']) : ''; ?>"
                           placeholder="https://contoh.madiunkab.go.id">
                </div>
                
                <div class="form-group">
                    <label for="jenis_aplikasi">Jenis Aplikasi <span class="required">*</span></label>
                    <select name="jenis_aplikasi" id="jenis_aplikasi" class="form-control" required>
                        <option value="">-- Pilih Jenis Aplikasi --</option>
                        <option value="Aplikasi Khusus/Daerah" <?php echo (isset($_POST['jenis_aplikasi']) && $_POST['jenis_aplikasi'] === 'Aplikasi Khusus/Daerah') ? 'selected' : ''; ?>>
                            Aplikasi Khusus/Daerah
                        </option>
                        <option value="Aplikasi Pusat/Umum" <?php echo (isset($_POST['jenis_aplikasi']) && $_POST['jenis_aplikasi'] === 'Aplikasi Pusat/Umum') ? 'selected' : ''; ?>>
                            Aplikasi Pusat/Umum
                        </option>
                        <option value="Aplikasi Lainnya" <?php echo (isset($_POST['jenis_aplikasi']) && $_POST['jenis_aplikasi'] === 'Aplikasi Lainnya') ? 'selected' : ''; ?>>
                            Aplikasi Lainnya
                        </option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status_aplikasi">Status Aplikasi <span class="required">*</span></label>
                    <select name="status_aplikasi" id="status_aplikasi" class="form-control" required>
                        <option value="">-- Pilih Status --</option>
                        <option value="Aktif" <?php echo (isset($_POST['status_aplikasi']) && $_POST['status_aplikasi'] === 'Aktif') ? 'selected' : ''; ?>>
                            Aktif
                        </option>
                        <option value="Tidak Aktif" <?php echo (isset($_POST['status_aplikasi']) && $_POST['status_aplikasi'] === 'Tidak Aktif') ? 'selected' : ''; ?>>
                            Tidak Aktif
                        </option>
                    </select>
                </div>
                
                <div class="form-group" id="penyebab_group" style="display: none;">
                    <label for="penyebab_tidak_aktif">Penyebab Tidak Aktif <span class="required">*</span></label>
                    <textarea name="penyebab_tidak_aktif" id="penyebab_tidak_aktif" class="form-control" 
                              placeholder="Jelaskan alasan mengapa aplikasi tidak aktif"><?php echo isset($_POST['penyebab_tidak_aktif']) ? htmlspecialchars($_POST['penyebab_tidak_aktif']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="nama_pengelola">Nama Pengelola <span class="required">*</span></label>
                    <input type="text" name="nama_pengelola" id="nama_pengelola" class="form-control" 
                           value="<?php echo isset($_POST['nama_pengelola']) ? htmlspecialchars($_POST['nama_pengelola']) : ''; ?>"
                           placeholder="Nama lengkap pengelola aplikasi" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="nomor_wa_pengelola">Nomor WhatsApp Pengelola <span class="required">*</span></label>
                    <input type="tel" name="nomor_wa_pengelola" id="nomor_wa_pengelola" class="form-control" 
                           value="<?php echo isset($_POST['nomor_wa_pengelola']) ? htmlspecialchars($_POST['nomor_wa_pengelola']) : ''; ?>"
                           placeholder="08xxxxxxxxxx atau +628xxxxxxxxxx" required>
                </div>
                
                <button type="submit" class="btn">💾 Simpan Data Aplikasi</button>
            </form>
            
            <div class="admin-link">
                <a href="admin.php">🔐 Login Admin</a>
            </div>
        </div>
    </div>
    
    <script>
        // Show/hide penyebab tidak aktif based on status
        document.getElementById('status_aplikasi').addEventListener('change', function() {
            const penyebabGroup = document.getElementById('penyebab_group');
            const penyebabField = document.getElementById('penyebab_tidak_aktif');
            
            if (this.value === 'Tidak Aktif') {
                penyebabGroup.style.display = 'block';
                penyebabField.required = true;
            } else {
                penyebabGroup.style.display = 'none';
                penyebabField.required = false;
                penyebabField.value = '';
            }
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.getElementById('status_aplikasi');
            if (statusSelect.value === 'Tidak Aktif') {
                document.getElementById('penyebab_group').style.display = 'block';
                document.getElementById('penyebab_tidak_aktif').required = true;
            }
        });
        
        // Form validation
        document.getElementById('applicationForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#e74c3c';
                } else {
                    field.style.borderColor = '#e0e6ed';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Silakan lengkapi semua field yang wajib diisi (*)');
            }
        });
        
        // Auto-format phone number
        document.getElementById('nomor_wa_pengelola').addEventListener('input', function() {
            let value = this.value.replace(/[^0-9+]/g, '');
            
            // Convert 08xx to +628xx
            if (value.startsWith('08')) {
                value = '+62' + value.substring(1);
            }
            // Convert 62xx to +62xx
            else if (value.startsWith('62') && !value.startsWith('+62')) {
                value = '+' + value;
            }
            
            this.value = value;
        });
    </script>
</body>
</html>