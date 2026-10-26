<?php
session_start();

// Proteksi Login
if (!isset($_SESSION['user_id'])) {
    header('Location: LoginForKasir.php');
    exit;
}

$active_menu = 'UrusPesanan';

// 1. Hubungkan ke database & helper
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';

$search_query = $_GET['q'] ?? '';
$search_by = $_GET['by'] ?? 'id';
$filter_waktu = $_GET['waktu'] ?? 'hari ini';

// 2. Query Mengambil Daftar Pesanan Aktif + Metode Pembayaran (jika ada)
$sql = "
    SELECT p.id_pesanan, p.waktu_pesan, p.nama_pelanggan, p.id_meja, p.total, p.status,
           COALESCE(t.metode, '-') AS metode, t.id_transaksi
    FROM pesanan p
    LEFT JOIN transaksi t ON p.id_pesanan = t.id_pesanan
    ORDER BY p.waktu_pesan DESC
";
$stmt = $pdo->query($sql);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Tentukan Order Mana yang Sedang Dilihat Rinciannya (Default: Order Pertama)
$selected_id = $_GET['order_id'] ?? ($orders[0]['id_pesanan'] ?? '');

// Ambil item menu yang dibeli untuk pesanan yang dipilih
$order_items = [];
$selected_order = null;

if (!empty($selected_id)) {
    // Ambil data induk pesanan
    $stmt_sel = $pdo->prepare("SELECT * FROM pesanan WHERE id_pesanan = :id");
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
                    <div class="search-group">
                        <div class="search-input-box">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" placeholder="Cari pesanan (ID/meja/nama)">
                        </div>
                    </div>
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
                                    <td colspan="6" style="text-align:center; padding: 20px;">Belum ada data pesanan di database.</td>
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
                                            <a href="?order_id=<?= urlencode($o['id_pesanan']) ?>" class="btn-action btn-detail" style="text-decoration:none;">Detail</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

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
                                <div class="total-bar">
                                    Total Bayar : Rp<?= number_format($selected_order['total'], 0, ',', '.') ?>
                                </div>
                            </div>

                            <!-- Sisi Kanan: Panel Update Status Kasir -->
                            <div class="detail-right">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_order">
                                    <input type="hidden" name="id_pesanan" value="<?= e($selected_order['id_pesanan']) ?>">

                                    <label class="form-label">Tipe Pembayaran</label>
                                    <div class="payment-toggle">
                                        <select name="metode" class="form-control" style="width: 100%; height: 36px; border-radius: 8px; border: 1px solid #CFC5B8; padding: 0 10px; font-family: inherit;">
                                            <option value="Cash">Cash (Tunai)</option>
                                            <!-- <option value="QRIS">QRIS</option> -->
                                        </select>
                                    </div>

                                    <label class="form-label" style="margin-top: 10px;">Status Pesanan</label>
                                    <div class="status-toggle">
                                        <select name="status" class="form-control" style="width: 100%; height: 36px; border-radius: 8px; border: 1px solid #CFC5B8; padding: 0 10px; font-family: inherit;">
                                            <option value="menunggu_pembayaran" <?= $selected_order['status'] === 'menunggu_pembayaran' ? 'selected' : '' ?>>Menunggu Pembayaran</option>
                                            <option value="diproses" <?= $selected_order['status'] === 'diproses' ? 'selected' : '' ?>>Diproses (Sedang Dimasak)</option>
                                            <option value="selesai" <?= $selected_order['status'] === 'selesai' ? 'selected' : '' ?>>Selesai (Sudah Lunas)</option>
                                        </select>
                                    </div>

                                    <div class="form-actions" style="margin-top: 20px;">
                                        <button type="button" class="btn-cancel" onclick="location.href='UrusPesanan.php'">Batal</button>
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