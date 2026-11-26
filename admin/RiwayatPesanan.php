<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: Login.php');
    exit;
}

$active_menu = 'RiwayatPesanan';

require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';

// Filter Status dari tombol tab di atas
$filter_status = $_GET['status'] ?? 'semua';
$filter_waktu = $_GET['waktu'] ?? 'hari_ini';
$raw_q = $_GET['q'] ?? '';
$search_query = is_string($raw_q) ? trim($raw_q) : '';
$search_by = $_GET['by'] ?? 'id';

if (!in_array($filter_status, ['semua', 'terlayani', 'batal'], true)) {
    $filter_status = 'semua';
}
if (!in_array($filter_waktu, ['hari_ini', 'kemarin', 'minggu_ini', 'bulan_ini'], true)) {
    $filter_waktu = 'hari_ini';
}
if (!in_array($search_by, ['id', 'meja'], true)) {
    $search_by = 'id';
}

$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

// Susun klausa filter (nilai tetap diikat sebagai parameter, bukan digabung ke string SQL)
$where = [];
$params = [];

if ($filter_status === 'terlayani') {
    $where[] = "LOWER(p.status) = 'selesai'";
} elseif ($filter_status === 'batal') {
    $where[] = "LOWER(p.status) = 'batal'";
}

if ($filter_waktu === 'hari_ini') {
    $where[] = 'p.waktu_pesan >= CURRENT_DATE AND p.waktu_pesan < CURRENT_DATE + 1';
} elseif ($filter_waktu === 'kemarin') {
    $where[] = 'p.waktu_pesan >= CURRENT_DATE - 1 AND p.waktu_pesan < CURRENT_DATE';
} elseif ($filter_waktu === 'minggu_ini') {
    $where[] = "p.waktu_pesan >= date_trunc('week', CURRENT_DATE)";
} elseif ($filter_waktu === 'bulan_ini') {
    $where[] = "p.waktu_pesan >= date_trunc('month', CURRENT_DATE)";
}

if ($search_query !== '') {
    $like = '%' . $search_query . '%';
    $where[] = ($search_by === 'meja')
        ? 'CAST(p.id_meja AS TEXT) ILIKE :q1'
        : 'p.id_pesanan ILIKE :q1';
    $params[':q1'] = $like;
}

$sql_where = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// Hitung total baris untuk menentukan jumlah halaman
$pdo_jml = $pdo->prepare(
    "SELECT COUNT(*) FROM pesanan p LEFT JOIN transaksi t ON p.id_pesanan = t.id_pesanan" . $sql_where
);
$pdo_jml->execute($params);
$total_rows = (int)$pdo_jml->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;
$baris_awal = ($total_rows === 0) ? 0 : $offset + 1;
$baris_akhir = min($offset + $per_page, $total_rows);

// Daftar nomor halaman dengan windowing: 1 ... 4 5 [6] 7 8 ... 20
$daftar_halaman = [];
for ($i = 1; $i <= $total_pages; $i++) {
    if ($i === 1 || $i === $total_pages || abs($i - $page) <= 1) {
        $daftar_halaman[] = $i;
    } elseif (end($daftar_halaman) !== '...') {
        $daftar_halaman[] = '...';
    }
}

// Parameter filter yang harus ikut di setiap link navigasi
$params_nav = [
    'status' => $filter_status,
    'waktu'  => $filter_waktu,
];
if ($search_query !== '') {
    $params_nav['q'] = $search_query;
}
if ($search_by !== 'id') {
    $params_nav['by'] = $search_by;
}

// Susun Query Berdasarkan Filter
$sql = "
    SELECT p.id_pesanan, p.waktu_pesan, p.id_meja, p.total, p.status,
           COALESCE(t.metode, '-') AS metode, t.waktu_transaksi
    FROM pesanan p
    LEFT JOIN transaksi t ON p.id_pesanan = t.id_pesanan
" . $sql_where . "
    ORDER BY p.waktu_pesan DESC
    LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

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
                            <?php
                            $opsi_waktu = [
                                'hari_ini'   => 'Hari ini',
                                'kemarin'    => 'Kemarin',
                                'minggu_ini' => 'Minggu ini',
                                'bulan_ini'  => 'Bulan ini',
                            ];
                            $dasar_waktu = $params_nav;
                            unset($dasar_waktu['waktu']);
                            foreach ($opsi_waktu as $val_waktu => $label_waktu):
                                $href_waktu = '?' . http_build_query(array_merge($dasar_waktu, ['waktu' => $val_waktu]));
                            ?>
                                <option value="<?= e($href_waktu) ?>" <?= $filter_waktu === $val_waktu ? 'selected' : '' ?>><?= $label_waktu ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tombol Tab Status: Sesuai Figma [Semua] [Terlayani] [Batal] -->
                    <div class="status-tab-group">
                        <?php
                        $dasar_status = $params_nav;
                        unset($dasar_status['status']);
                        foreach (['semua' => 'Semua', 'terlayani' => 'Terlayani', 'batal' => 'Batal'] as $val_status => $label_status):
                            $href_status = '?' . http_build_query(array_merge($dasar_status, ['status' => $val_status]));
                        ?>
                            <a href="<?= e($href_status) ?>" class="btn-tab <?= $filter_status === $val_status ? 'active' : '' ?>"><?= $label_status ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Baris Filter 2: Search Input & Cari Berdasarkan -->
                <div class="filter-bar" style="margin-bottom: 20px;">
                    <form method="GET" action="" class="search-group">
                        <input type="hidden" name="status" value="<?= e($filter_status) ?>">
                        <input type="hidden" name="waktu" value="<?= e($filter_waktu) ?>">
                        <div class="search-input-box">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" name="q" placeholder="Cari arsip pesanan..." value="<?= e($search_query) ?>">
                        </div>
                        <div class="select-search-by">
                            <span>Cari Berdasarkan:</span>
                            <select name="by" onchange="this.form.submit()">
                                <option value="id" <?= $search_by === 'id' ? 'selected' : '' ?>>ID Pesanan</option>
                                <option value="meja" <?= $search_by === 'meja' ? 'selected' : '' ?>>Nomor Meja</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-action">Cari</button>
                        <?php if ($search_query !== ''): ?>
                            <a href="?<?= e(http_build_query(array_merge($params_nav, ['q' => '', 'page' => 1]))) ?>" class="btn-action" style="text-decoration:none;">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Tabel Data Riwayat Pesanan -->
                <div class="table-responsive">
                    <table class="order-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Waktu Pesan</th>
                                <th>Meja</th>
                                <th>Payment</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
    <?php if (empty($history_orders)): ?>
        <tr>
            <td colspan="7" style="text-align: center; padding: 20px;">
                <?= $search_query !== '' ? 'Tidak ada riwayat yang cocok dengan pencarian.' : 'Tidak ada riwayat transaksi yang ditemukan.' ?>
            </td>
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
                <td>Meja <?= e($h['id_meja']) ?></td>
                <td><?= strtoupper(e($h['metode'])) ?></td>
                <td class="fw-bold">Rp<?= number_format($h['total'], 0, ',', '.') ?></td>
                <td><span class="badge <?= $badge_class ?>"><?= $label ?></span></td>
                <td class="action-btns">
                    <a href="?<?= e(http_build_query(array_merge($params_nav, ['order_id' => $h['id_pesanan'], 'page' => $page]))) ?>" class="btn-action btn-detail" style="text-decoration:none;">Detail</a>
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

                <!-- PAGINATION (SERVER-SIDE, BERDASARKAN ?page=) -->
                <?php if ($total_rows > 0): ?>
                    <div class="pagination-bar">
                        <span class="pagination-info">
                            Menampilkan <?= $baris_awal ?>&ndash;<?= $baris_akhir ?> dari <?= $total_rows ?> riwayat
                        </span>
                        <nav class="pagination">
                            <?php if ($page > 1): ?>
                                <a class="page-btn" href="?<?= e(http_build_query(array_merge($params_nav, ['page' => $page - 1]))) ?>">&lsaquo; Sebelumnya</a>
                            <?php else: ?>
                                <span class="page-btn is-disabled">&lsaquo; Sebelumnya</span>
                            <?php endif; ?>

                            <?php foreach ($daftar_halaman as $h): ?>
                                <?php if ($h === '...'): ?>
                                    <span class="page-dots">&hellip;</span>
                                <?php elseif ($h === $page): ?>
                                    <span class="page-btn active"><?= $h ?></span>
                                <?php else: ?>
                                    <a class="page-btn" href="?<?= e(http_build_query(array_merge($params_nav, ['page' => $h]))) ?>"><?= $h ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php if ($page < $total_pages): ?>
                                <a class="page-btn" href="?<?= e(http_build_query(array_merge($params_nav, ['page' => $page + 1]))) ?>">Berikutnya &rsaquo;</a>
                            <?php else: ?>
                                <span class="page-btn is-disabled">Berikutnya &rsaquo;</span>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>

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