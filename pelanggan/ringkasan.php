<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Pastikan Nomor Meja Tersedia
$nomor_meja = $_SESSION['id_meja'] ?? 5;

// Jika keranjang kosong, kembalikan ke menu
if (empty($_SESSION['keranjang'])) {
    header("Location: Menu.php?meja=" . urlencode($nomor_meja));
    exit;
}

// 2. Hubungkan Database & Helper
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';

// 3. Hitung Total Tagihan
$total_bayar = 0;
foreach ($_SESSION['keranjang'] as $item) {
    $total_bayar += ($item['harga'] * $item['jumlah']);
}

// 4. Logika Konfirmasi Pesanan (Simpan ke Database PostgreSQL)
$pesan_sukses = '';
$order_id_berhasil = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'konfirmasi_pesanan') {
    $nama_pemesan = trim($_POST['nama_pelanggan'] ?? '');
    if ($nama_pemesan === '') {
        $nama_pemesan = 'Pelanggan Meja ' . $nomor_meja;
    }

    // Buat ID Pesanan Unik (Format: ORD-XXXX)
    $id_pesanan = 'ORD-' . strtoupper(substr(uniqid(), -4));

    try {
        $pdo->beginTransaction();

        // 1. Simpan ke tabel pesanan
        $stmt_pesanan = $pdo->prepare("
            INSERT INTO pesanan (id_pesanan, waktu_pesan, nama_pelanggan, id_meja, total, status)
            VALUES (:id, NOW(), :nama, :meja, :total, 'menunggu_pembayaran')
        ");
        $stmt_pesanan->execute([
            ':id'    => $id_pesanan,
            ':nama'  => $nama_pemesan,
            ':meja'  => $nomor_meja,
            ':total' => $total_bayar
        ]);

        // 2. Simpan setiap item ke tabel detail_pesanan
        $stmt_detail = $pdo->prepare("
            INSERT INTO detail_pesanan (id_pesanan, id_menu, jumlah, harga_satuan, subtotal, catatan)
            VALUES (:id_pesanan, :id_menu, :jumlah, :harga, :subtotal, :catatan)
        ");

        foreach ($_SESSION['keranjang'] as $item) {
            $subtotal = $item['harga'] * $item['jumlah'];
            $stmt_detail->execute([
                ':id_pesanan' => $id_pesanan,
                ':id_menu'    => $item['id_menu'],
                ':jumlah'     => $item['jumlah'],
                ':harga'      => $item['harga'],
                ':subtotal'   => $subtotal,
                ':catatan'    => null
            ]);
        }

        $pdo->commit();

        // 3. Kosongkan keranjang setelah berhasil disimpan
        unset($_SESSION['keranjang']);
        $order_id_berhasil = $id_pesanan;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Gagal memproses pesanan: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ringkasan Pesanan - Cysto</title>
    <link rel="stylesheet" href="../assets/css/menuPelanggan.css">
</head>
<body>

    <div class="mobile-container">
        
        <!-- Header -->
        <header class="top-header">
            <div class="brand-area">
                <div class="brand-icon">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 2v2"></path><path d="M11 1v3"></path><path d="M15 2v2"></path>
                        <path d="M4 8h12v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8z"></path>
                        <path d="M16 9h1.5a2.5 2.5 0 0 1 0 5H16"></path>
                    </svg>
                </div>
                <span class="brand-name">Cysto</span>
            </div>

            <div class="table-pill">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span>Meja <?= e($nomor_meja) ?></span>
            </div>
        </header>

        <h2 class="page-title">Ringkasan Pesanan</h2>

        <!-- Form Konfirmasi Pesanan -->
        <form method="POST" action="" style="display: flex; flex-direction: column; flex: 1;">
            <input type="hidden" name="action" value="konfirmasi_pesanan">

            <!-- Input Nama Pemesan -->
            <div style="margin-bottom: 14px;">
                <input type="text" name="nama_pelanggan" placeholder="Nama Anda (Contoh: Budi)" 
                       style="width: 100%; height: 44px; border: 1.5px solid var(--color-border); border-radius: 12px; padding: 0 14px; font-family: inherit; font-size: 13.5px; outline: none; background: #FFFFFF;" required autocomplete="name">
            </div>

            <!-- Kartu Rincian Pesanan -->
            <div class="summary-card">
                <div class="summary-header">
                    <div>
                        <div class="header-sub">No. Meja</div>
                        <div class="header-main">Meja <?= e($nomor_meja) ?></div>
                    </div>
                    <div class="pin-circle">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                    </div>
                </div>

                <div class="summary-body">
                    <div class="section-label">Detail Pesanan</div>

                    <!-- Daftar Item -->
                    <div class="item-list">
                        <?php if (!empty($_SESSION['keranjang'])): ?>
                            <?php foreach ($_SESSION['keranjang'] as $item): ?>
                                <div class="item-row">
                                    <div class="item-left">
                                        <span class="item-name"><?= e($item['nama']) ?></span>
                                        <span class="item-price">Rp <?= number_format($item['harga'], 0, ',', '.') ?></span>
                                    </div>
                                    <span class="item-qty"><?= $item['jumlah'] ?>x</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="divider"></div>

                    <!-- Baris Total Tagihan -->
                    <div class="total-row">
                        <span class="total-text">Total</span>
                        <span class="total-val">Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
                    </div>
                </div>
            </div>

            <!-- Section Metode Pembayaran -->
            <h3 class="payment-title">Metode Pembayaran</h3>
            <div class="payment-card">
                <div>
                    <div class="pay-info-title">Bayar ke Kasir</div>
                    <div class="pay-info-sub">Pembayaran dilakukan di kasir</div>
                </div>
                <div class="pay-arrow">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
            </div>

            <!-- Tombol Konfirmasi Pesanan -->
            <button type="submit" class="btn-confirm">
                <span>Konfirmasi Pesanan</span>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
        </form>

    </div>

    <!-- Modal Pop-up Sukses -->
    <?php if (!empty($order_id_berhasil)): ?>
        <div class="modal-success">
            <div class="modal-box">
                <h3 style="color: #5C4033; font-size: 19px; margin-bottom: 8px;">Pesanan Berhasil!</h3>
                <p style="font-size: 13px; color: #7A6252;">Tunjukkan kode pesanan ini ke kasir untuk melakukan pembayaran:</p>
                <div class="order-badge-code"><?= e($order_id_berhasil) ?></div>
                <p style="font-size: 12px; color: #888; margin-bottom: 20px;">Pesananmu otomatis tercatat di dashboard kasir.</p>
                <a href="Menu.php?meja=<?= e($nomor_meja) ?>" style="display: block; background: #5C4033; color: white; padding: 12px; border-radius: 12px; text-decoration: none; font-weight: bold;">
                    Pesan Lagi / Selesai
                </a>
            </div>
        </div>
    <?php endif; ?>

</body>
</html>
