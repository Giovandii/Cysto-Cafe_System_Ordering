<?php
session_start();

// 1. Tangkap nomor meja
if (isset($_GET['meja'])) {
    $meja_baru = (int)$_GET['meja'];
    if (isset($_SESSION['id_meja']) && $_SESSION['id_meja'] !== $meja_baru) {
        unset($_SESSION['keranjang']);
    }
    $_SESSION['id_meja'] = $meja_baru;
}

$nomor_meja = $_SESSION['id_meja'] ?? 5;

// Inisialisasi keranjang kosong jika belum ada (ANTI DATA DUMMY!)
if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

// 2. Hubungkan Helpers
require_once __DIR__ . '/../includes/helpers.php';

// 3. Logika Tambah / Kurang / Hapus Item di Keranjang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)$_POST['id_menu'];

    if ($_POST['action'] === 'tambah' && isset($_SESSION['keranjang'][$id])) {
        $_SESSION['keranjang'][$id]['jumlah'] += 1;
    } elseif ($_POST['action'] === 'kurang' && isset($_SESSION['keranjang'][$id])) {
        if ($_SESSION['keranjang'][$id]['jumlah'] > 1) {
            $_SESSION['keranjang'][$id]['jumlah'] -= 1;
        } else {
            unset($_SESSION['keranjang'][$id]);
        }
    } elseif ($_POST['action'] === 'hapus' && isset($_SESSION['keranjang'][$id])) {
        unset($_SESSION['keranjang'][$id]);
    }

    header('Location: keranjang.php');
    exit;
}

// 4. Hitung Total Bayar
$total_bayar = 0;
foreach ($_SESSION['keranjang'] as $item) {
    $total_bayar += ($item['harga'] * $item['jumlah']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Pesanan - Cysto</title>
    <link rel="stylesheet" href="../assets/css/menuPelanggan.css">
</head>
<body>

    <div class="mobile-container">
        
        <!-- Header Brand & Meja -->
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

        <!-- Judul & Tombol Kembali -->
        <h2 class="page-title">Keranjang Pesanan</h2>
        <a href="Menu.php?meja=<?= e($nomor_meja) ?>" class="btn-back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>Kembali ke Menu</span>
        </a>

        <!-- Daftar Item yang Dipilih -->
        <main class="cart-list">
            <?php if (empty($_SESSION['keranjang'])): ?>
                <div style="text-align: center; padding: 60px 10px; color: #7A6252;">
                    <p style="font-size: 16px; margin-bottom: 12px;">Keranjang pesananmu masih kosong.</p>
                    <a href="Menu.php?meja=<?= e($nomor_meja) ?>" style="color: #5C4033; font-weight: bold; text-decoration: underline;">Pilih menu sekarang</a>
                </div>
            <?php else: ?>
                <?php foreach ($_SESSION['keranjang'] as $id => $item): ?>
                    <article class="cart-card">
                        <img src="../assets/images/menu/<?= e($item['gambar']) ?>" 
                             onerror="this.onerror=null; this.src='../assets/images/coffee-icon.svg';" 
                             alt="<?= e($item['nama']) ?>" 
                             class="cart-img">
                        
                        <div class="cart-details">
                            <h3 class="item-name"><?= e($item['nama']) ?></h3>
                            <span class="item-price">Rp. <?= number_format($item['harga'], 0, ',', '.') ?></span>
                            
                            <!-- Pengatur Jumlah Porsi (- 1 +) -->
                            <div class="qty-control">
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="id_menu" value="<?= $id ?>">
                                    <input type="hidden" name="action" value="kurang">
                                    <button type="submit" class="btn-qty">-</button>
                                </form>
                                <span class="qty-val"><?= $item['jumlah'] ?></span>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="id_menu" value="<?= $id ?>">
                                    <input type="hidden" name="action" value="tambah">
                                    <button type="submit" class="btn-qty">+</button>
                                </form>
                            </div>
                        </div>

                        <!-- Tombol Hapus Item -->
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="id_menu" value="<?= $id ?>">
                            <input type="hidden" name="action" value="hapus">
                            <button type="submit" class="btn-delete" title="Hapus item">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <!-- Bagian Bawah: Total & Tombol Lanjut ke Ringkasan -->
        <?php if (!empty($_SESSION['keranjang'])): ?>
            <footer class="cart-footer">
                <div class="total-row">
                    <span class="total-label">Total</span>
                    <span class="total-amount">Rp. <?= number_format($total_bayar, 0, ',', '.') ?></span>
                </div>

                <a href="ringkasan.php" class="btn-submit-order">
                    <div class="btn-left">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <span>Lanjut ke Ringkasan</span>
                    </div>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </a>
            </footer>
        <?php endif; ?>

    </div>

</body>
</html>
