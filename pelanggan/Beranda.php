<?php
session_start();

// 1. Tangkap Nomor Meja dari QR Code (misal: ?meja=3)
if (isset($_GET['meja'])) {
    $_SESSION['id_meja'] = (int)$_GET['meja'];
}

// Proteksi: Wajib ada nomor meja
if (empty($_SESSION['id_meja'])) {
    die("
    <div style='font-family: serif; text-align: center; padding: 60px 20px; background-color: #F7F4EE; min-height: 100vh;'>
        <h2 style='color: #5C4033;'>Akses Ditolak</h2>
        <p style='color: #7A6252;'>Silakan scan QR Code yang ada di meja kafe untuk mulai memesan!</p>
    </div>
    ");
}

$nomor_meja = $_SESSION['id_meja'];

// 2. Hubungkan ke database PostgreSQL
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';

// 3. Filter Kategori (Default: food)
$kategori = strtolower($_GET['kategori'] ?? 'food');

// Query menu berdasarkan kategori yang dipilih
try {
    $stmt = $pdo->prepare("SELECT * FROM menu WHERE LOWER(kategori) = :kategori ORDER BY id_menu ASC");
    $stmt->execute([':kategori' => $kategori]);
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $menus = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Pelanggan - Meja <?= e($nomor_meja) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="menu-page">

    <div class="mobile-container menu-container">
        
        <!-- 1. Header: Welcome & Ikon Kopi -->
        <header class="menu-header">
            <div class="header-left">
                <h1 class="welcome-title">WELCOME</h1>
                <p class="welcome-sub">Meja #<?= str_pad($nomor_meja, 2, '0', STR_PAD_LEFT) ?> &bull; Silakan Pilih Menu</p>
            </div>
            <div class="header-right">
                <div class="coffee-icon">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 2v2"></path><path d="M11 1v3"></path><path d="M15 2v2"></path>
                        <path d="M4 8h12v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8z"></path>
                        <path d="M16 9h1.5a2.5 2.5 0 0 1 0 5H16"></path>
                    </svg>
                </div>
            </div>
        </header>

        <!-- 2. Navigasi Kategori (FOOD, DRINK, SNACK Dinamis) -->
        <nav class="category-nav">
            <a href="?kategori=food" class="category-btn <?= $kategori === 'food' ? 'active' : '' ?>" style="text-decoration:none; text-align:center;">FOOD</a>
            <a href="?kategori=drink" class="category-btn <?= $kategori === 'drink' ? 'active' : '' ?>" style="text-decoration:none; text-align:center;">DRINK</a>
            <a href="?kategori=snack" class="category-btn <?= $kategori === 'snack' ? 'active' : '' ?>" style="text-decoration:none; text-align:center;">SNACK</a>
        </nav>

        <!-- 3. Grid Daftar Menu (DINAMIS DARI DATABASE POSTGRESQL) -->
        <main class="menu-grid-catalog">
            <?php if (empty($menus)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px 10px; color: #7A6252;">
                    <p>Belum ada menu di kategori ini.</p>
                </div>
            <?php else: ?>
                <?php foreach ($menus as $m): ?>
                    <article class="menu-item-card" onclick="alert('Menu <?= e($m['nama']) ?> ditambahkan ke keranjang!');">
                        <figure class="menu-img-wrap">
                            <img src="../assets/images/menu/<?= e($m['gambar'] ?? 'default-menu.png') ?>" 
                                 onerror="this.onerror=null; this.src='../assets/images/coffee-icon.svg';" 
                                 alt="<?= e($m['nama']) ?>" 
                                 class="menu-img">
                        </figure>
                        <h3 class="item-name"><?= e($m['nama']) ?></h3>
                        <span class="item-price">Rp. <?= number_format($m['harga'], 0, ',', '.') ?></span>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <!-- 4. Footer Bar Bawah -->
        <footer class="bottom-bar">
            <div class="bottom-left">
                <a href="status_pesanan.php" class="btn-status">Cek Status Pesanan</a>
            </div>

            <div class="bottom-center">
                <button type="button" class="btn-cart-toggle">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="8" cy="21" r="1"></circle>
                        <circle cx="19" cy="21" r="1"></circle>
                        <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>
                    </svg>
                    <span class="cart-badge">1</span>
                </button>
            </div>

            <div class="bottom-right">
                <a href="checkout.php" class="btn-checkout">CHECK OUT</a>
            </div>
        </footer>

    </div>

</body>
</html>