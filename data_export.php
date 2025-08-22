<?php
/**
 * Aplikasi Pendataan Aplikasi Kabupaten Madiun
 * File: data_export.php
 * Export data ke Excel
 */

require_once 'config.php';

// Initialize session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

// Check session timeout
if (!isset($_SESSION['login_time']) || (time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
    session_destroy();
    header('Location: admin.php?timeout=1');
    exit;
}

// Update last activity
$_SESSION['login_time'] = time();

$message = '';
$messageType = '';

// Handle export request
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    if (!Security::checkRateLimit('export')) {
        $message = 'Terlalu banyak permintaan export. Silakan coba lagi dalam beberapa menit.';
        $messageType = 'error';
    } else {
        try {
            $db = Security::initDB();
            
            // Get all data
            $stmt = $db->query("
                SELECT 
                    ROW_NUMBER() OVER (ORDER BY created_at) as no,
                    nama_perangkat_daerah,
                    nama_aplikasi,
                    deskripsi_singkat,
                    alamat_domain,
                    jenis_aplikasi,
                    status_aplikasi,
                    penyebab_tidak_aktif,
                    nama_pengelola,
                    nomor_wa_pengelola,
                    DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as tanggal_input
                FROM aplikasi_opd 
                ORDER BY created_at DESC
            ");
            $data = $stmt->fetchAll();
            
            if (empty($data)) {
                $message = 'Tidak ada data untuk diexport.';
                $messageType = 'error';
            } else {
                // Log export activity
                Security::logActivity('DATA_EXPORT', 'aplikasi_opd', null, null, ['total_records' => count($data)]);
                
                // Set headers for Excel download
                $filename = 'Data_Aplikasi_Kabupaten_Madiun_' . date('Y-m-d_H-i-s') . '.csv';
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                // Create file pointer connected to the output stream
                $output = fopen('php://output', 'w');
                
                // Add BOM for proper UTF-8 handling in Excel
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
                
                // Add headers
                fputcsv($output, [
                    'No',
                    'Nama Perangkat Daerah',
                    'Nama Aplikasi',
                    'Deskripsi Singkat',
                    'Alamat Domain/Link',
                    'Jenis Aplikasi',
                    'Status Aplikasi',
                    'Penyebab Tidak Aktif',
                    'Nama Pengelola',
                    'Nomor WA Pengelola',
                    'Tanggal Input'
                ]);
                
                // Add data rows
                foreach ($data as $row) {
                    fputcsv($output, [
                        $row['no'],
                        $row['nama_perangkat_daerah'],
                        $row['nama_aplikasi'],
                        $row['deskripsi_singkat'],
                        $row['alamat_domain'],
                        $row['jenis_aplikasi'],
                        $row['status_aplikasi'],
                        $row['penyebab_tidak_aktif'],
                        $row['nama_pengelola'],
                        $row['nomor_wa_pengelola'],
                        $row['tanggal_input']
                    ]);
                }
                
                fclose($output);
                exit;
            }
        } catch (Exception $e) {
            $message = 'Terjadi kesalahan saat export data.';
            $messageType = 'error';
        }
    }
}

// Get statistics for display
$stats = null;
try {
    $db = Security::initDB();
    
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_records,
            COUNT(DISTINCT nama_perangkat_daerah) as total_opd,
            MIN(created_at) as earliest_entry,
            MAX(created_at) as latest_entry
        FROM aplikasi_opd
    ");
    $stats = $stmt->fetch();
} catch (Exception $e) {
    $stats = null;
}

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data - <?php echo SITE_NAME; ?></title>
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
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
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
        
        .info-box {
            background: #e8f4f8;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .info-box h3 {
            color: #0c5460;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: rgba(255,255,255,0.7);
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        
        .stat-item strong {
            display: block;
            font-size: 1.5em;
            color: #2c3e50;
        }
        
        .stat-item span {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .export-section {
            background: #f8f9fa;
            border: 1px solid #e0e6ed;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .export-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .export-btn {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-right: 15px;
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);
        }
        
        .export-btn:active {
            transform: translateY(0);
        }
        
        .export-btn.secondary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }
        
        .export-btn.secondary:hover {
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
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
            margin: 0 15px;
        }
        
        .back-link a:hover {
            color: #2980b9;
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .warning strong {
            display: block;
            margin-bottom: 5px;
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
            
            .export-btn {
                width: 100%;
                margin-bottom: 10px;
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Export Data</h1>
            <p>Download Data Aplikasi Kabupaten Madiun</p>
        </div>
        
        <div class="content">
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h3>📈 Informasi Data</h3>
                
                <?php if ($stats): ?>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <strong><?php echo number_format($stats['total_records']); ?></strong>
                            <span>Total Records</span>
                        </div>
                        <div class="stat-item">
                            <strong><?php echo number_format($stats['total_opd']); ?></strong>
                            <span>Total OPD</span>
                        </div>
                        <div class="stat-item">
                            <strong><?php echo $stats['earliest_entry'] ? date('d/m/Y', strtotime($stats['earliest_entry'])) : '-'; ?></strong>
                            <span>Entry Pertama</span>
                        </div>
                        <div class="stat-item">
                            <strong><?php echo $stats['latest_entry'] ? date('d/m/Y', strtotime($stats['latest_entry'])) : '-'; ?></strong>
                            <span>Entry Terakhir</span>
                        </div>
                    </div>
                <?php else: ?>
                    <p>Data statistik tidak tersedia.</p>
                <?php endif; ?>
            </div>
            
            <div class="export-section">
                <h3>📥 Download Data</h3>
                
                <div class="warning">
                    <strong>⚠️ Perhatian:</strong>
                    File yang didownload berisi data sensitif. Pastikan untuk menjaga kerahasiaan data dan tidak membagikannya kepada pihak yang tidak berwenang.
                </div>
                
                <p style="margin-bottom: 20px;">
                    Export semua data aplikasi dalam format CSV yang kompatibel dengan Microsoft Excel. 
                    File akan berisi semua kolom data termasuk informasi pengelola.
                </p>
                
                <a href="data_export.php?export=excel" class="export-btn" id="exportBtn">
                    📊 Download Excel/CSV
                </a>
                
                <a href="data_detail.php" class="export-btn secondary">
                    👁️ Lihat Data Detail
                </a>
            </div>
            
            <div class="export-section">
                <h3>📋 Format Data Export</h3>
                <p>File CSV yang didownload akan berisi kolom-kolom berikut:</p>
                <ul style="margin-top: 10px; margin-left: 20px; line-height: 1.8;">
                    <li>No (Nomor urut)</li>
                    <li>Nama Perangkat Daerah</li>
                    <li>Nama Aplikasi</li>
                    <li>Deskripsi Singkat</li>
                    <li>Alamat Domain/Link</li>
                    <li>Jenis Aplikasi</li>
                    <li>Status Aplikasi</li>
                    <li>Penyebab Tidak Aktif</li>
                    <li>Nama Pengelola</li>
                    <li>Nomor WA Pengelola</li>
                    <li>Tanggal Input</li>
                </ul>
            </div>
            
            <div class="back-link">
                <a href="admin.php">⬅️ Kembali ke Dashboard</a>
                <a href="index.php">🏠 Halaman Utama</a>
            </div>
        </div>
    </div>
    
    <script>
        // Add loading state to export button
        document.getElementById('exportBtn').addEventListener('click', function() {
            this.innerHTML = '⏳ Memproses Export...';
            this.style.pointerEvents = 'none';
            
            // Reset after 5 seconds
            setTimeout(() => {
                this.innerHTML = '📊 Download Excel/CSV';
                this.style.pointerEvents = 'auto';
            }, 5000);
        });
    </script>
</body>
</html>