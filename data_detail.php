<?php
/**
 * Aplikasi Pendataan Aplikasi Kabupaten Madiun
 * File: data_detail.php
 * View detail semua data
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

// Pagination settings
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search and filter parameters
$search = isset($_GET['search']) ? Security::sanitizeInput($_GET['search']) : '';
$filterOPD = isset($_GET['opd']) ? Security::sanitizeInput($_GET['opd']) : '';
$filterStatus = isset($_GET['status']) ? Security::sanitizeInput($_GET['status']) : '';
$filterJenis = isset($_GET['jenis']) ? Security::sanitizeInput($_GET['jenis']) : '';

try {
    $db = Security::initDB();
    
    // Build WHERE clause
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $whereClause .= " AND (nama_aplikasi LIKE ? OR nama_pengelola LIKE ? OR deskripsi_singkat LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($filterOPD)) {
        $whereClause .= " AND nama_perangkat_daerah = ?";
        $params[] = $filterOPD;
    }
    
    if (!empty($filterStatus)) {
        $whereClause .= " AND status_aplikasi = ?";
        $params[] = $filterStatus;
    }
    
    if (!empty($filterJenis)) {
        $whereClause .= " AND jenis_aplikasi = ?";
        $params[] = $filterJenis;
    }
    
    // Get total records for pagination
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM aplikasi_opd $whereClause");
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    // Get data with pagination
    $stmt = $db->prepare("
        SELECT 
            id,
            nama_perangkat_daerah,
            nama_aplikasi,
            deskripsi_singkat,
            alamat_domain,
            jenis_aplikasi,
            status_aplikasi,
            penyebab_tidak_aktif,
            nama_pengelola,
            nomor_wa_pengelola,
            DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as tanggal_input,
            ip_address
        FROM aplikasi_opd 
        $whereClause 
        ORDER BY created_at DESC 
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Get unique OPD list for filter
    $stmt = $db->query("SELECT DISTINCT nama_perangkat_daerah FROM aplikasi_opd ORDER BY nama_perangkat_daerah");
    $opdFilter = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    $data = [];
    $totalRecords = 0;
    $totalPages = 0;
    $opdFilter = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Detail - <?php echo SITE_NAME; ?></title>
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
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
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
            padding: 30px;
        }
        
        .filters {
            background: #f8f9fa;
            border: 1px solid #e0e6ed;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .filters h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-control {
            padding: 10px 12px;
            border: 1px solid #e0e6ed;
            border-radius: 5px;
            font-size: 0.95em;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #8e44ad;
            box-shadow: 0 0 0 2px rgba(142, 68, 173, 0.1);
        }
        
        .btn {
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(142, 68, 173, 0.3);
        }
        
        .btn.secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #e0e6ed;
            font-size: 0.9em;
        }
        
        .data-table th {
            background: #2c3e50;
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .status-aktif {
            background: #d4edda;
            color: #155724;
        }
        
        .status-tidak-aktif {
            background: #f8d7da;
            color: #721c24;
        }
        
        .jenis-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75em;
            font-weight: 500;
        }
        
        .jenis-daerah {
            background: #cce5ff;
            color: #004085;
        }
        
        .jenis-pusat {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .jenis-lainnya {
            background: #f8d7da;
            color: #721c24;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #e0e6ed;
            border-radius: 5px;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background: #8e44ad;
            color: white;
            border-color: #8e44ad;
        }
        
        .pagination .current {
            background: #8e44ad;
            color: white;
            border-color: #8e44ad;
        }
        
        .pagination .disabled {
            color: #ccc;
            cursor: not-allowed;
        }
        
        .info-bar {
            background: #e8f4f8;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
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
        
        .table-container {
            overflow-x: auto;
            max-height: 70vh;
        }
        
        .text-truncate {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .url-link {
            color: #3498db;
            text-decoration: none;
        }
        
        .url-link:hover {
            text-decoration: underline;
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
                padding: 20px;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .data-table th,
            .data-table td {
                padding: 8px 6px;
                font-size: 0.8em;
            }
            
            .text-truncate {
                max-width: 100px;
            }
            
            .info-bar {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Data Detail Aplikasi</h1>
            <p>Seluruh Data Aplikasi Kabupaten Madiun</p>
        </div>
        
        <div class="content">
            <!-- Filters -->
            <div class="filters">
                <h3>🔍 Filter & Pencarian</h3>
                <form method="GET" action="">
                    <div class="filter-grid">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Cari nama aplikasi, pengelola..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        
                        <select name="opd" class="form-control">
                            <option value="">-- Semua OPD --</option>
                            <?php foreach ($opdFilter as $opd): ?>
                                <option value="<?php echo htmlspecialchars($opd); ?>" 
                                    <?php echo $filterOPD === $opd ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opd); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="status" class="form-control">
                            <option value="">-- Semua Status --</option>
                            <option value="Aktif" <?php echo $filterStatus === 'Aktif' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="Tidak Aktif" <?php echo $filterStatus === 'Tidak Aktif' ? 'selected' : ''; ?>>Tidak Aktif</option>
                        </select>
                        
                        <select name="jenis" class="form-control">
                            <option value="">-- Semua Jenis --</option>
                            <option value="Aplikasi Khusus/Daerah" <?php echo $filterJenis === 'Aplikasi Khusus/Daerah' ? 'selected' : ''; ?>>Aplikasi Khusus/Daerah</option>
                            <option value="Aplikasi Pusat/Umum" <?php echo $filterJenis === 'Aplikasi Pusat/Umum' ? 'selected' : ''; ?>>Aplikasi Pusat/Umum</option>
                            <option value="Aplikasi Lainnya" <?php echo $filterJenis === 'Aplikasi Lainnya' ? 'selected' : ''; ?>>Aplikasi Lainnya</option>
                        </select>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <button type="submit" class="btn">🔍 Filter</button>
                        <a href="data_detail.php" class="btn secondary">🔄 Reset</a>
                    </div>
                </form>
            </div>
            
            <!-- Info Bar -->
            <div class="info-bar">
                <div>
                    <strong>Total Data: <?php echo number_format($totalRecords); ?></strong>
                    <?php if (!empty($search) || !empty($filterOPD) || !empty($filterStatus) || !empty($filterJenis)): ?>
                        (Hasil filter)
                    <?php endif; ?>
                </div>
                <div>
                    Halaman <?php echo $page; ?> dari <?php echo $totalPages; ?>
                </div>
            </div>
            
            <!-- Data Table -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>OPD</th>
                            <th>Nama Aplikasi</th>
                            <th>Deskripsi</th>
                            <th>Domain/Link</th>
                            <th>Jenis</th>
                            <th>Status</th>
                            <th>Pengelola</th>
                            <th>No. WA</th>
                            <th>Tanggal Input</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 40px; color: #7f8c8d;">
                                    📭 Tidak ada data yang ditemukan
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data as $index => $row): ?>
                                <tr>
                                    <td><?php echo $offset + $index + 1; ?></td>
                                    <td class="text-truncate" title="<?php echo htmlspecialchars($row['nama_perangkat_daerah']); ?>">
                                        <?php echo htmlspecialchars($row['nama_perangkat_daerah']); ?>
                                    </td>
                                    <td class="text-truncate" title="<?php echo htmlspecialchars($row['nama_aplikasi']); ?>">
                                        <strong><?php echo htmlspecialchars($row['nama_aplikasi']); ?></strong>
                                    </td>
                                    <td class="text-truncate" title="<?php echo htmlspecialchars($row['deskripsi_singkat']); ?>">
                                        <?php echo htmlspecialchars($row['deskripsi_singkat'] ?: '-'); ?>
                                    </td>
                                    <td class="text-truncate">
                                        <?php if (!empty($row['alamat_domain'])): ?>
                                            <a href="<?php echo htmlspecialchars($row['alamat_domain']); ?>" 
                                               target="_blank" class="url-link" 
                                               title="<?php echo htmlspecialchars($row['alamat_domain']); ?>">
                                                🔗 Link
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="jenis-badge <?php 
                                            echo $row['jenis_aplikasi'] === 'Aplikasi Khusus/Daerah' ? 'jenis-daerah' : 
                                                ($row['jenis_aplikasi'] === 'Aplikasi Pusat/Umum' ? 'jenis-pusat' : 'jenis-lainnya'); 
                                        ?>">
                                            <?php echo htmlspecialchars($row['jenis_aplikasi']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status_aplikasi'])); ?>">
                                            <?php echo $row['status_aplikasi']; ?>
                                        </span>
                                        <?php if ($row['status_aplikasi'] === 'Tidak Aktif' && !empty($row['penyebab_tidak_aktif'])): ?>
                                            <br><small title="<?php echo htmlspecialchars($row['penyebab_tidak_aktif']); ?>" style="color: #7f8c8d;">
                                                ⚠️ <?php echo htmlspecialchars(substr($row['penyebab_tidak_aktif'], 0, 30) . '...'); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-truncate" title="<?php echo htmlspecialchars($row['nama_pengelola']); ?>">
                                        <?php echo htmlspecialchars($row['nama_pengelola']); ?>
                                    </td>
                                    <td>
                                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $row['nomor_wa_pengelola']); ?>" 
                                           target="_blank" class="url-link" title="Chat WhatsApp">
                                            📱 <?php echo htmlspecialchars($row['nomor_wa_pengelola']); ?>
                                        </a>
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <?php echo $row['tanggal_input']; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $currentUrl = "data_detail.php?search=" . urlencode($search) . 
                                  "&opd=" . urlencode($filterOPD) . 
                                  "&status=" . urlencode($filterStatus) . 
                                  "&jenis=" . urlencode($filterJenis);
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="<?php echo $currentUrl; ?>&page=1">⏮️ First</a>
                        <a href="<?php echo $currentUrl; ?>&page=<?php echo $page - 1; ?>">⬅️ Prev</a>
                    <?php else: ?>
                        <span class="disabled">⏮️ First</span>
                        <span class="disabled">⬅️ Prev</span>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo $currentUrl; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo $currentUrl; ?>&page=<?php echo $page + 1; ?>">Next ➡️</a>
                        <a href="<?php echo $currentUrl; ?>&page=<?php echo $totalPages; ?>">Last ⏭️</a>
                    <?php else: ?>
                        <span class="disabled">Next ➡️</span>
                        <span class="disabled">Last ⏭️</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="back-link">
                <a href="admin.php">⬅️ Dashboard</a>
                <a href="data_export.php">📊 Export Data</a>
                <a href="index.php">🏠 Halaman Utama</a>
            </div>
        </div>
    </div>
</body>
</html>