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

// 3. Hitung Total Tagihan & Siapkan Salinan Item
$total_bayar = 0;
$daftar_ringkasan = $_SESSION['keranjang'] ?? [];
foreach ($daftar_ringkasan as $item) {
    $total_bayar += ($item['harga'] * $item['jumlah']);
}

// 4. Logika Konfirmasi Pesanan (Simpan ke Database PostgreSQL)
$pesan_sukses = '';
$order_id_berhasil = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'konfirmasi_pesanan') {
    // Buat ID Pesanan Unik (Format: ORD-XXXX)
    $id_pesanan = 'ORD-' . strtoupper(substr(uniqid(), -4));
    $metode_pembayaran = strtolower($_POST['metode_pembayaran'] ?? 'kasir');

    try {
        $pdo->beginTransaction();

        // 1. Simpan ke tabel pesanan
        $stmt_pesanan = $pdo->prepare("
            INSERT INTO pesanan (id_pesanan, waktu_pesan, id_meja, total, status)
            VALUES (:id, NOW(), :meja, :total, 'menunggu_pembayaran')
        ");
        $stmt_pesanan->execute([
            ':id'    => $id_pesanan,
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
                ':catatan'    => !empty($item['catatan']) ? $item['catatan'] : null
            ]);
        }

        // 3. Simpan pilihan metode awal ke tabel transaksi
        $id_trx = 'TRX-' . strtoupper(substr(uniqid(), -4));
        $stmt_trx = $pdo->prepare("
            INSERT INTO transaksi (id_transaksi, id_pesanan, total_bayar, metode, terbayar, kembali)
            VALUES (:id_trx, :id_pesanan, :total, :metode, 0, 0)
            ON CONFLICT (id_pesanan) DO UPDATE SET metode = EXCLUDED.metode
        ");
        $stmt_trx->execute([
            ':id_trx'     => $id_trx,
            ':id_pesanan' => $id_pesanan,
            ':total'      => $total_bayar,
            ':metode'     => ($metode_pembayaran === 'qris' ? 'QRIS' : 'Cash')
        ]);

        $pdo->commit();

        // 4. Kosongkan keranjang setelah berhasil disimpan
        unset($_SESSION['keranjang']);

        // 5. Redirect ke halaman pembayaran sesuai metode
        header("Location: pembayaran.php?order_id=" . urlencode($id_pesanan) . "&metode=" . urlencode($metode_pembayaran));
        exit;

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
    <style>
        .payment-options-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 24px;
        }

        .pay-option-card {
            background-color: #FFFFFF;
            border: 1.5px solid var(--color-border);
            border-radius: 14px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(92, 64, 51, 0.04);
            transition: all 0.2s ease;
        }

        .pay-option-card:hover {
            border-color: var(--color-primary);
        }

        .pay-option-card input[type="radio"] {
            accent-color: var(--color-primary);
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .pay-option-card:has(input[type="radio"]:checked) {
            border-color: var(--color-primary);
            background-color: #FAF5F0;
        }

        .pay-info {
            flex: 1;
        }

        .pay-info-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--color-text-title);
            margin-bottom: 2px;
        }

        .pay-info-sub {
            font-size: 12px;
            color: #8C7D70;
        }
    </style>
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

        <?php if (!empty($error_msg)): ?>
            <div style="background-color: #F8D7DA; color: #721C24; padding: 12px; border-radius: 10px; margin-bottom: 16px; font-size: 13px;">
                <?= e($error_msg) ?>
            </div>
        <?php endif; ?>

        <!-- Form Konfirmasi Pesanan -->
        <form method="POST" action="" style="display: flex; flex-direction: column; flex: 1;">
            <input type="hidden" name="action" value="konfirmasi_pesanan">

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
                        <?php if (!empty($daftar_ringkasan)): ?>
                            <?php foreach ($daftar_ringkasan as $item): ?>
                                <div class="item-row">
                                    <div class="item-left">
                                        <span class="item-name"><?= e($item['nama']) ?></span>
                                        <?php if (!empty($item['catatan'])): ?>
                                            <small style="color: #8C5435; display: block; font-size: 11.5px; font-style: italic; margin-top: 1px;">
                                                Catatan: <?= e($item['catatan']) ?>
                                            </small>
                                        <?php endif; ?>
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
            <h3 class="payment-title">Pilih Metode Pembayaran</h3>
            
            <div class="payment-options-group">
                <!-- Opsi 1: Bayar ke Kasir -->
                <label class="pay-option-card">
                    <input type="radio" name="metode_pembayaran" value="kasir" checked>
                    <div class="pay-info">
                        <div class="pay-info-title">💵 Bayar ke Kasir (Tunai)</div>
                        <div class="pay-info-sub">Tunjukkan kode pesanan dan bayar langsung di kasir</div>
                    </div>
                </label>

                <!-- Opsi 2: QRIS -->
                <label class="pay-option-card">
                    <input type="radio" name="metode_pembayaran" value="qris">
                    <div class="pay-info">
                        <div class="pay-info-title">📱 QRIS (Instant)</div>
                        <div class="pay-info-sub">Scan QR via BCA, GoPay, OVO, ShopeePay, Dana, m-Banking</div>
                    </div>
                </label>
            </div>

            <!-- Tombol Konfirmasi Pesanan -->
            <button type="submit" class="btn-confirm">
                <div style="display:flex; align-items:center; gap: 10px;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    <span>Lakukan Pembayaran</span>
                </div>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
        </form>

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
