<?php
session_start();

// Proteksi Login
if (!isset($_SESSION['user_id'])) {
    header('Location: Login.php');
    exit;
}

$active_menu = 'UrusPesanan';

// 1. Hubungkan ke database & helper
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';

$raw_q = $_GET['q'] ?? '';
$search_query = is_string($raw_q) ? trim($raw_q) : '';
$search_by = $_GET['by'] ?? 'semua';

if (!in_array($search_by, ['semua', 'id', 'meja'], true)) {
    $search_by = 'semua';
}

$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

// 2. Susun klausa pencarian (nilai tetap diikat sebagai parameter, bukan digabung ke string SQL)
$where = [];
$params = [];
if ($search_query !== '') {
    $like = '%' . $search_query . '%';
    if ($search_by === 'meja') {
        $where[] = 'CAST(p.id_meja AS TEXT) ILIKE :q1';
    } elseif ($search_by === 'id') {
        $where[] = 'p.id_pesanan ILIKE :q1';
    } else {
        $where[] = '(p.id_pesanan ILIKE :q1 OR CAST(p.id_meja AS TEXT) ILIKE :q2)';
        $params[':q2'] = $like;
    }
    $params[':q1'] = $like;
}
$sql_where = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// 3. Hitung total baris untuk menentukan jumlah halaman
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
$params_cari = [];
if ($search_query !== '') {
    $params_cari['q'] = $search_query;
}
if ($search_by !== 'semua') {
    $params_cari['by'] = $search_by;
}

$params_halaman = $params_cari;
if (isset($_GET['order_id']) && is_string($_GET['order_id']) && $_GET['order_id'] !== '') {
    $params_halaman['order_id'] = $_GET['order_id'];
}

// 4. Query Mengambil Daftar Pesanan Aktif + Metode Pembayaran (jika ada), satu halaman saja
$sql = "
    SELECT p.id_pesanan, p.waktu_pesan, p.id_meja, p.total, p.status,
           COALESCE(t.metode, '-') AS metode, t.id_transaksi
    FROM pesanan p
    LEFT JOIN transaksi t ON p.id_pesanan = t.id_pesanan
" . $sql_where . "
    ORDER BY p.waktu_pesan DESC
    LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Tentukan Order Mana yang Sedang Dilihat Rinciannya (Default: Order Pertama)
$raw_order = $_GET['order_id'] ?? '';
$selected_id = is_string($raw_order) && $raw_order !== '' ? $raw_order : ($orders[0]['id_pesanan'] ?? '');

// Ambil item menu yang dibeli untuk pesanan yang dipilih
$order_items = [];
$selected_order = null;

if (!empty($selected_id)) {
    // Ambil data induk pesanan + metode dan total yang sebenarnya dari tabel transaksi jika ada
    $stmt_sel = $pdo->prepare("
        SELECT p.*, 
               COALESCE(t.metode, 'Cash') AS metode_bayar,
               t.total_bayar AS total_transaksi,
               t.id_transaksi
        FROM pesanan p
        LEFT JOIN transaksi t ON p.id_pesanan = t.id_pesanan
        WHERE p.id_pesanan = :id
    ");
    $stmt_sel->execute([':id' => $selected_id]);
    $selected_order = $stmt_sel->fetch(PDO::FETCH_ASSOC);

    // Ambil item detail pesanan + nama menu
    $stmt_items = $pdo->prepare("
        SELECT d.id_detail, d.jumlah, d.harga_satuan, d.subtotal, d.catatan, m.nama AS nama_menu
        FROM detail_pesanan d
        JOIN menu m ON d.id_menu = m.id_menu
        WHERE d.id_pesanan = :id
        ORDER BY d.id_detail ASC
    ");
    $stmt_items->execute([':id' => $selected_id]);
    $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urus Pesanan - Cafe Menu</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>

<body class="dashboard-body">

    <div class="dashboard-layout">
        <!-- Panggil Sidebar Modular -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Konten Utama -->
        <main class="dashboard-content">
            <div class="content-header">
                <h1 class="page-title">Urus Pesanan</h1>
                <p class="page-subtitle">Kelola pesanan pelanggan dengan mudah</p>
            </div>

            <div class="order-main-panel">

                <!-- Filter & Search Bar -->
                <div class="filter-bar">
                    <form method="GET" action="" class="search-group">
                        <div class="select-search-by">
                            <span>Cari berdasarkan:</span>
                            <select name="by" onchange="this.form.submit()">
                                <option value="semua" <?= $search_by === 'semua' ? 'selected' : '' ?>>Semua</option>
                                <option value="id" <?= $search_by === 'id' ? 'selected' : '' ?>>ID Pesanan</option>
                                <option value="meja" <?= $search_by === 'meja' ? 'selected' : '' ?>>No. Meja</option>
                            </select>
                        </div>
                        <div class="search-input-box">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" name="q" placeholder="Cari pesanan (ID/meja)" value="<?= e($search_query) ?>">
                        </div>
                        <button type="submit" class="btn-action">Cari</button>
                        <?php if ($search_query !== ''): ?>
                            <?php $params_reset = $params_halaman; ?>
                            <?php unset($params_reset['q']); ?>
                            <a href="?<?= e(http_build_query(array_merge($params_reset, ['page' => 1]))) ?>" class="btn-action" style="text-decoration:none;">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- TABEL PESANAN (DINAMIS DARI DATABASE) -->
                <div class="table-responsive">
                    <table class="order-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Waktu Pesan</th>
                                <th>Meja</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding: 20px;">
                                        <?= $search_query !== '' ? 'Tidak ada pesanan yang cocok dengan pencarian.' : 'Belum ada data pesanan di database.' ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $o): ?>
                                    <?php
                                    // Penyesuaian warna badge status
                                    $badge_class = 'badge-danger';
                                    $status_label = 'Menunggu';
                                    if (strtolower($o['status']) === 'selesai') {
                                        $badge_class = 'badge-success';
                                        $status_label = 'Selesai';
                                    } elseif (strtolower($o['status']) === 'diproses') {
                                        $badge_class = 'badge-warning';
                                        $status_label = 'Diproses';
                                    }
                                    ?>
                                    <tr style="<?= ($selected_id === $o['id_pesanan']) ? 'background-color: #EDE5DD;' : '' ?>">
                                        <td class="fw-bold"><?= e($o['id_pesanan']) ?></td>
                                        <td><?= date('d-m-Y, H:i:s', strtotime($o['waktu_pesan'])) ?></td>
                                        <td>Meja <?= e($o['id_meja']) ?></td>
                                        <td><?= strtoupper(e($o['metode'])) ?></td>
                                        <td><span class="badge <?= $badge_class ?>"><?= $status_label ?></span></td>
                                        <td class="action-btns">
                                            <!-- Tombol Detail akan me-reload halaman dengan order_id yang dipilih -->
                                            <a href="?<?= e(http_build_query(array_merge($params_halaman, ['order_id' => $o['id_pesanan'], 'page' => $page]))) ?>" class="btn-action btn-detail" style="text-decoration:none;">Detail</a>
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
                            Menampilkan <?= $baris_awal ?>&ndash;<?= $baris_akhir ?> dari <?= $total_rows ?> pesanan
                        </span>
                        <nav class="pagination">
                            <?php if ($page > 1): ?>
                                <a class="page-btn" href="?<?= e(http_build_query(array_merge($params_halaman, ['page' => $page - 1]))) ?>">&lsaquo; Sebelumnya</a>
                            <?php else: ?>
                                <span class="page-btn is-disabled">&lsaquo; Sebelumnya</span>
                            <?php endif; ?>

                            <?php foreach ($daftar_halaman as $h): ?>
                                <?php if ($h === '...'): ?>
                                    <span class="page-dots">&hellip;</span>
                                <?php elseif ($h === $page): ?>
                                    <span class="page-btn active"><?= $h ?></span>
                                <?php else: ?>
                                    <a class="page-btn" href="?<?= e(http_build_query(array_merge($params_halaman, ['page' => $h]))) ?>"><?= $h ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php if ($page < $total_pages): ?>
                                <a class="page-btn" href="?<?= e(http_build_query(array_merge($params_halaman, ['page' => $page + 1]))) ?>">Berikutnya &rsaquo;</a>
                            <?php else: ?>
                                <span class="page-btn is-disabled">Berikutnya &rsaquo;</span>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>

                <!-- BOX DETAIL ORDER (DINAMIS BERDASARKAN ORDER YANG DIPILIH) -->
                <?php if ($selected_order): ?>
                    <div class="detail-edit-card">
                        <div class="detail-header">
                            Detail/Edit Order: ID <?= e($selected_order['id_pesanan']) ?> <?= e($selected_order['id_meja']) ?>
                        </div>
                        <div class="detail-body">
                            <!-- Sisi Kiri: Daftar Menu yang Dipesan -->
                            <div class="detail-left">
                                <table class="item-detail-table">
                                    <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>Nama Item</th>
                                            <th>Jumlah</th>
                                            <th>Harga</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($order_items)): ?>
                                            <tr>
                                                <td colspan="5" style="text-align:center;">Tidak ada item pesanan.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1;
                                            foreach ($order_items as $item): ?>
                                                <tr>
                                                    <td><?= $no++ ?></td>
                                                    <td>
                                                        <strong><?= e($item['nama_menu']) ?></strong>
<?php if (!empty($item['catatan'])): ?>
                                                            <br><small style="color: #8C5435;">Catatan: <?= e($item['catatan']) ?></small>
                                                        <?php endif; ?>  
                                                    </td>
                                                    <td><?= e($item['jumlah']) ?></td>
                                                    <td>Rp<?= number_format($item['harga_satuan'], 0, ',', '.') ?></td>
                                                    <td>Rp<?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <?php
// Hitung apakah pesanan ini via QRIS atau memiliki kode unik meja
$subtotal_menu = (float)$selected_order['total'];
$no_meja_kasir = (int)$selected_order['id_meja'];
$metode_kasir  = strtoupper($selected_order['metode_bayar'] ?? 'CASH');

// Jika sudah ada transaksi tercatat, pakai total_transaksi. Jika belum, hitung kode unik jika QRIS
if (!empty($selected_order['total_transaksi'])) {
    $total_final_kasir = (float)$selected_order['total_transaksi'];
    $kode_unik_kasir   = max(0, $total_final_kasir - $subtotal_menu);
} else {
    $kode_unik_kasir   = ($metode_kasir === 'QRIS') ? $no_meja_kasir : 0;
    $total_final_kasir = $subtotal_menu + $kode_unik_kasir;
}
?>

<div class="total-bar" style="text-align: right; line-height: 1.5; font-size: 13.5px;">
    <div>Subtotal Menu: <strong>Rp<?= number_format($subtotal_menu, 0, ',', '.') ?></strong></div>
    <?php if ($kode_unik_kasir > 0): ?>
        <div style="font-size: 12px; color: #2563EB;">
            + Kode Unik Meja <?= $no_meja_kasir ?>: <strong>+ Rp<?= number_format($kode_unik_kasir, 0, ',', '.') ?></strong>
        </div>
    <?php endif; ?>
    <div style="font-size: 16px; font-weight: 800; color: #5C4033; margin-top: 4px; border-top: 1px dashed #CFC5B8; padding-top: 4px;">
        Total Tagihan: Rp<?= number_format($total_final_kasir, 0, ',', '.') ?>
        <span style="font-size: 11px; font-weight: normal; color: #7A6252;">(<?= e($metode_kasir) ?>)</span>
    </div>
</div>
                            </div>

                            <!-- Sisi Kanan: Panel Update Status Kasir -->
                            <div class="detail-right">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_order">
                                    <input type="hidden" name="id_pesanan" value="<?= e($selected_order['id_pesanan']) ?>">

                                    <label class="form-label">Metode Pembayaran (Pilihan Pelanggan)</label>
<div class="payment-locked-box" style="display: flex; align-items: center; justify-content: space-between; background-color: #EDE5DD; border: 1.5px solid #CFC5B8; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px;">
    <div style="display: flex; align-items: center; gap: 8px;">
        
        <strong style="color: #5C4033; font-size: 14px; letter-spacing: 0.5px;">
            <?= strtoupper(e($selected_order['metode_bayar'] ?? 'Cash')) ?>
            <?= (strtoupper($selected_order['metode_bayar'] ?? '') === 'CASH') ? '(Tunai di Kasir)' : '(QRIS)' ?>
        </strong>
    </div></div>
<input type="hidden" name="metode" value="<?= e($selected_order['metode_bayar'] ?? 'Cash') ?>">

                                    <label class="form-label" style="margin-top: 10px;">Status Pesanan</label>
                                    <div class="status-toggle">
                                        <select name="status" class="form-control" style="width: 100%; height: 36px; border-radius: 8px; border: 1px solid #CFC5B8; padding: 0 10px; font-family: inherit;">
                                            <option value="menunggu_pembayaran" <?= $selected_order['status'] === 'menunggu_pembayaran' ? 'selected' : '' ?>>Menunggu Pembayaran</option>
                                            <option value="diproses" <?= $selected_order['status'] === 'diproses' ? 'selected' : '' ?>>Diproses (Sedang Dimasak)</option>
                                            <option value="selesai" <?= $selected_order['status'] === 'selesai' ? 'selected' : '' ?>>Selesai (Sudah Lunas)</option>
                                        </select>
                                    </div>

                                    <div class="form-actions" style="margin-top: 20px;">
                                        <button type="button" class="btn-cancel" onclick="location.href='UrusPesanan.php?<?= e(http_build_query(array_merge($params_halaman, ['page' => $page]))) ?>'">Batal</button>
                                        <button type="submit" class="btn-save">Simpan Perubahan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

</body>

</html>