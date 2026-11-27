<?php
session_start();

// Proteksi Login Khusus Admin
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: Login.php?role=admin');
    exit;
}

// Menandai menu aktif di SideBar.php
$active_menu = 'pendapatan';

require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';

// Periode yang dipilih ('hari_ini', 'mingguan', 'bulanan')
$periode = $_GET['periode'] ?? 'hari_ini';
$allowed_periode = ['hari_ini', 'mingguan', 'bulanan'];
if (!in_array($periode, $allowed_periode, true)) {
    $periode = 'hari_ini';
}

// Susun klausa filter waktu PostgreSQL
$where_waktu = "DATE(t.waktu_transaksi) = CURRENT_DATE";
if ($periode === 'mingguan') {
    $where_waktu = "t.waktu_transaksi >= DATE_TRUNC('week', CURRENT_DATE)";
} elseif ($periode === 'bulanan') {
    $where_waktu = "DATE_TRUNC('month', t.waktu_transaksi) = DATE_TRUNC('month', CURRENT_DATE)";
}

// Query Rekapitulasi Pendapatan & Jumlah Transaksi dari Tabel transaksi
$total_pendapatan = 0;
$total_transaksi  = 0;
$pendapatan_qris  = 0;
$pendapatan_tunai = 0;

try {
    $sql = "
        SELECT 
            COALESCE(SUM(t.total_bayar), 0) AS total_pendapatan,
            COUNT(t.id_transaksi) AS total_transaksi,
            COALESCE(SUM(CASE WHEN UPPER(t.metode) = 'QRIS' THEN t.total_bayar ELSE 0 END), 0) AS total_qris,
            COALESCE(SUM(CASE WHEN UPPER(t.metode) IN ('CASH', 'TUNAI') THEN t.total_bayar ELSE 0 END), 0) AS total_tunai
        FROM transaksi t
        LEFT JOIN pesanan p ON t.id_pesanan = p.id_pesanan
        WHERE {$where_waktu}
          AND LOWER(p.status) = 'selesai'
    ";
    $stmt = $pdo->query($sql);
    $rekap = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rekap) {
        $total_pendapatan = (float)$rekap['total_pendapatan'];
        $total_transaksi  = (int)$rekap['total_transaksi'];
        $pendapatan_qris  = (float)$rekap['total_qris'];
        $pendapatan_tunai = (float)$rekap['total_tunai'];
    }
} catch (PDOException $e) {
    // Jika terjadi kendala query, nilai default tetap 0
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Pendapatan - Cafe Menu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ================= STYLING KHUSUS REKAP PENDAPATAN ================= */
        .periode-filter-bar {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            background-color: #F8F5EE;
            border: 1.5px solid #9C8A7D;
            border-radius: 12px;
            padding: 8px 16px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .periode-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13.5px;
            font-weight: 600;
            color: #5A3D2D;
        }

        .periode-btn-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Varian Tombol Periode sesuai Figma */
        .btn-periode {
            text-decoration: none;
            background-color: #F3ECE1;
            color: #5A3D2D;
            border: 1px solid #B5A599;
            border-radius: 8px;
            padding: 6px 22px;
            font-size: 12.5px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-periode:hover {
            background-color: #EAE0D1;
        }

        .btn-periode.active {
            background-color: #EFE5D6;
            color: #382519;
            border: 1.8px solid #5A3D2D;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(90, 61, 45, 0.12);
        }

        .section-divider {
            border: none;
            border-top: 1px solid #E2D9CE;
            margin-bottom: 22px;
        }

        /* Grid 2x2 Kartu Ringkasan Pendapatan sesuai Figma */
        .revenue-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            max-width: 920px;
        }

        .revenue-card {
            background-color: #F8F5EE;
            border: 1.5px solid #9C8A7D;
            border-radius: 16px;
            padding: 20px 24px;
            min-height: 175px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 12px rgba(90, 61, 45, 0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .revenue-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(90, 61, 45, 0.12);
        }

        .revenue-card-title {
            font-size: 15px;
            font-weight: 600;
            color: #5A3D2D;
        }

        .revenue-card-value-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-bottom: 10px;
        }

        .revenue-card-value {
            font-size: 30px;
            font-weight: 800;
            color: #5A3D2D;
            letter-spacing: -0.3px;
        }

        @media (max-width: 768px) {
            .revenue-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="dashboard-body">

    <div class="dashboard-layout">
        <!-- Sidebar Modular -->
        <?php include __DIR__ . '/../includes/SideBar.php'; ?>

        <!-- Konten Utama -->
        <main class="dashboard-content">
            <div class="content-header">
                <h1 class="page-title">Rekap Pendapatan</h1>
                <p class="page-subtitle">Rekap Riwayat Pendapatan</p>
            </div>

            <!-- Filter Berdasarkan Periode -->
            <div>
                <div class="periode-filter-bar">
                    <div class="periode-label">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                        </svg>
                        <span>Filter berdasarkan periode</span>
                    </div>

                    <div class="periode-btn-group">
                        <a href="?periode=hari_ini" class="btn-periode <?= $periode === 'hari_ini' ? 'active' : '' ?>">Hari ini</a>
                        <a href="?periode=mingguan" class="btn-periode <?= $periode === 'mingguan' ? 'active' : '' ?>">Mingguan</a>
                        <a href="?periode=bulanan" class="btn-periode <?= $periode === 'bulanan' ? 'active' : '' ?>">Bulanan</a>
                    </div>
                </div>
            </div>

            <hr class="section-divider">

            <!-- Grid 4 Kartu Ringkasan -->
            <div class="revenue-grid">
                <!-- Kartu 1: Total Pendapatan -->
                <div class="revenue-card">
                    <div class="revenue-card-title">Total Pendapatan</div>
                    <div class="revenue-card-value-wrap">
                        <div class="revenue-card-value">Rp. <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
                    </div>
                </div>

                <!-- Kartu 2: Total Transaksi -->
                <div class="revenue-card">
                    <div class="revenue-card-title">Total Transaksi</div>
                    <div class="revenue-card-value-wrap">
                        <div class="revenue-card-value"><?= number_format($total_transaksi, 0, ',', '.') ?></div>
                    </div>
                </div>

                <!-- Kartu 3: Pembayaran QRIS -->
                <div class="revenue-card">
                    <div class="revenue-card-title">Pembayaran QRIS</div>
                    <div class="revenue-card-value-wrap">
                        <div class="revenue-card-value">Rp. <?= number_format($pendapatan_qris, 0, ',', '.') ?></div>
                    </div>
                </div>

                <!-- Kartu 4: Pembayaran Tunai -->
                <div class="revenue-card">
                    <div class="revenue-card-title">Pembayaran Tunai</div>
                    <div class="revenue-card-value-wrap">
                        <div class="revenue-card-value">Rp. <?= number_format($pendapatan_tunai, 0, ',', '.') ?></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

</body>
</html>