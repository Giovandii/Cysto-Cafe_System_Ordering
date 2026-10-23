<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: LoginForAdmin.php');
    exit;
}

$active_menu = 'RiwayatPesanan';

require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';

// Filter Status dari tombol tab di atas
$filter_status = $_GET['status'] ?? 'semua';
$filter_waktu = $_GET['waktu'] ?? 'hari_ini';
$search_query = $_GET['q'] ?? '';
$search_by = $_GET['by'] ?? 'id';

// Susun Query Berdasarkan Filter
$sql = "
    SELECT p.id_pesanan, p.waktu_pesan, p.nama_pelanggan, p.id_meja, p.total, p.status,
           COALESCE(t.metode, '-') AS metode, t.waktu_transaksi
    FROM pesanan p
    LEFT JOIN transaksi t ON p.id_pesanan = t.id_pesanan
    WHERE 1=1
";

$params = [];

if ($filter_status === 'terlayani') {
    $sql .= " AND LOWER(p.status) = 'selesai'";
} elseif ($filter_status === 'batal') {
    $sql .= " AND LOWER(p.status) = 'batal'";
}

$sql .= " ORDER BY p.waktu_pesan DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - Cafe Menu</title>
    <!-- Memakai stylesheet dashboard yang sama -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        
    </style>
</head>
<body class="dashboard-body">

    <div class="dashboard-layout">
        <!-- 1. Panggil Sidebar Modular (Otomatis menyesuaikan Admin / Kasir) -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- 2. Konten Utama: Riwayat Pesanan -->
        <main class="dashboard-content">
            <div class="content-header">
                <h1 class="page-title">Riwayat Pesanan</h1>
                <p class="page-subtitle">Arsip data riwayat pesanan dan rekapan transaksi</p>
            </div>

            <!-- Panel Pembungkus Utama -->
            <div class="order-main-panel">
                
                <!-- Baris Filter 1: Filter Waktu & Tab Status (Semua, Terlayani, Batal) -->
                <div class="top-filter-row">
                    <!-- Dropdown Filter Waktu -->
                    <div class="time-filter-box">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                        </svg>
                        <span>Filter Waktu:</span>
                        <select onchange="location = this.value;">
                            <option value="?waktu=hari_ini&status=<?= e($filter_status) ?>" <?= $filter_waktu === 'hari_ini' ? 'selected' : '' ?>>Hari ini</option>
                            <option value="?waktu=kemarin&status=<?= e($filter_status) ?>" <?= $filter_waktu === 'kemarin' ? 'selected' : '' ?>>Kemarin</option>
                            <option value="?waktu=minggu_ini&status=<?= e($filter_status) ?>" <?= $filter_waktu === 'minggu_ini' ? 'selected' : '' ?>>Minggu ini</option>
                            <option value="?waktu=bulan_ini&status=<?= e($filter_status) ?>" <?= $filter_waktu === 'bulan_ini' ? 'selected' : '' ?>>Bulan ini</option>
                        </select>
                    </div>

                    <!-- Tombol Tab Status: Sesuai Figma [Semua] [Terlayani] [Batal] -->
                    <div class="status-tab-group">
                        <a href="?status=semua&waktu=<?= e($filter_waktu) ?>" class="btn-tab <?= $filter_status === 'semua' ? 'active' : '' ?>">Semua</a>
                        <a href="?status=terlayani&waktu=<?= e($filter_waktu) ?>" class="btn-tab <?= $filter_status === 'terlayani' ? 'active' : '' ?>">Terlayani</a>
                        <a href="?status=batal&waktu=<?= e($filter_waktu) ?>" class="btn-tab <?= $filter_status === 'batal' ? 'active' : '' ?>">Batal</a>
                    </div>
                </div>

                <!-- Baris Filter 2: Search Input & Cari Berdasarkan -->
                <div class="filter-bar" style="margin-bottom: 20px;">
                    <div class="search-group">
                        <div class="search-input-box">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" placeholder="Cari arsip pesanan..." value="<?= e($search_query) ?>">
                        </div>
                        <div class="select-search-by">
                            <span>Cari Berdasarkan:</span>
                            <select>
                                <option value="id" <?= $search_by === 'id' ? 'selected' : '' ?>>ID Pesanan</option>
                                <option value="meja" <?= $search_by === 'meja' ? 'selected' : '' ?>>Nomor Meja</option>
                                <option value="nama" <?= $search_by === 'nama' ? 'selected' : '' ?>>Nama Pelanggan</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tabel Data Riwayat Pesanan -->
                <div class="table-responsive">
                    <table class="order-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Waktu Pesan</th>
                                <th>Pelanggan / Meja</th>
                                <th>Payment</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
    <?php if (empty($history_orders)): ?>
        <tr>
            <td colspan="7" style="text-align: center; padding: 20px;">Tidak ada riwayat transaksi yang ditemukan.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($history_orders as $h): ?>
            <?php
                $is_selesai = strtolower($h['status']) === 'selesai';
                $badge_class = $is_selesai ? 'badge-success' : 'badge-danger';
                $label = $is_selesai ? 'Terlayani' : 'Batal';
            ?>
            <tr>
                <td class="fw-bold"><?= e($h['id_pesanan']) ?></td>
                <td><?= date('d-m-Y, H:i:s', strtotime($h['waktu_pesan'])) ?></td>
                <td><?= e($h['nama_pelanggan']) ?> (Meja <?= e($h['id_meja']) ?>)</td>
                <td><?= strtoupper(e($h['metode'])) ?></td>
                <td class="fw-bold">Rp<?= number_format($h['total'], 0, ',', '.') ?></td>
                <td><span class="badge <?= $badge_class ?>"><?= $label ?></span></td>
                <td class="action-btns">
                    <a href="?order_id=<?= urlencode($h['id_pesanan']) ?>&status=<?= e($filter_status) ?>" class="btn-action btn-detail" style="text-decoration:none;">Detail</a>
                    <?php if ($is_selesai): ?>
                        <button class="btn-action" onclick="window.print()">Cetak</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>
                    </table>
                </div>

                <!-- Panel Detail / Rincian Riwayat Transaksi -->
                <div class="detail-edit-card" id="detailPanel">
                    <div class="detail-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Rincian Riwayat Order: ID #55C</span>
                        <span style="font-size: 12px; font-weight: normal;">Kasir: Dina | Status: Lunas</span>
                    </div>
                    <div class="detail-body">
                        <!-- Rincian Item Pesanan -->
                        <div class="detail-left" style="flex: 1.5;">
                            <table class="item-detail-table">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Nama Menu</th>
                                        <th>Jumlah</th>
                                        <th>Harga Satuan</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>1</td>
                                        <td>French Fries</td>
                                        <td>1</td>
                                        <td>Rp 15.000</td>
                                        <td>Rp 15.000</td>
                                    </tr>
                                    <tr>
                                        <td>2</td>
                                        <td>Nasi Goreng Spesial</td>
                                        <td>1</td>
                                        <td>Rp 18.000</td>
                                        <td>Rp 18.000</td>
                                    </tr>
                                    <tr>
                                        <td>3</td>
                                        <td>Ice Chocolate</td>
                                        <td>1</td>
                                        <td>Rp 12.000</td>
                                        <td>Rp 12.000</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="total-bar">
                                Total Transaksi : Rp 45.000
                            </div>
                        </div>

                        <!-- Data Pembayaran & Cetak Struk -->
                        <div class="detail-right">
                            <label class="form-label">Metode Pembayaran</label>
                            <p style="font-size: 14px; font-weight: bold; margin-bottom: 8px;">QRIS (ShopeePay/GoPay)</p>

                            <label class="form-label">Waktu Pembayaran Selesai</label>
                            <p style="font-size: 13px; color: #555; margin-bottom: 12px;">26-08-2026, 14:46:10</p>

                            <label class="form-label">Catatan Transaksi</label>
                            <p style="font-size: 12px; color: #666; margin-bottom: 16px;">Transaksi berhasil terverifikasi otomatis.</p>

                            <div class="form-actions">
                                <button type="button" class="btn-save" onclick="window.print()" style="background-color: #5A3D2D;">
                                    🖨️ Cetak Struk
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

</body>
</html>