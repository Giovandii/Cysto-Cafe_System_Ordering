<?php
session_start();

// 1. TANGKAP NOMOR MEJA DARI LINK QR (Contoh: menu.php?meja=5)
if (isset($_GET['meja'])) {
    $meja_baru = (int)$_GET['meja'];

    // JIKA NOMOR MEJA BERBEDA DENGAN SESI SEBELUMNYA:
    // Anggap ini pesanan baru di meja baru, jadi BERSIHKAN KERANJANG!
    if (isset($_SESSION['id_meja']) && $_SESSION['id_meja'] !== $meja_baru) {
        unset($_SESSION['keranjang']);
    }

    $_SESSION['id_meja'] = $meja_baru;
}

// Proteksi: Jika pelanggan masuk tanpa nomor meja
if (empty($_SESSION['id_meja'])) {
    die("
    <div style='font-family: serif; text-align: center; padding: 70px 20px; background-color: #F7F4EE; min-height: 100vh;'>
        <h2 style='color: #5C4033;'>Akses Ditolak</h2>
        <p style='color: #7A6252; margin-top: 8px;'>Silakan scan QR Code yang ada di meja kafe untuk mulai memesan!</p>
    </div>
    ");
}

$nomor_meja = $_SESSION['id_meja'];

// 2. TANGKAP REQUEST TAMBAH KERANJANG DARI JAVASCRIPT (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_keranjang') {
    $id     = (int)$_POST['id_menu'];
    $nama   = $_POST['nama'];
    $harga  = (float)$_POST['harga'];
    $gambar = $_POST['gambar'] ?? 'default-menu.png';

    if (!isset($_SESSION['keranjang'])) {
        $_SESSION['keranjang'] = [];
    }

    if (isset($_SESSION['keranjang'][$id])) {
        $_SESSION['keranjang'][$id]['jumlah'] += 1;
    } else {
        $_SESSION['keranjang'][$id] = [
            'id_menu' => $id,
            'nama'    => $nama,
            'harga'   => $harga,
            'jumlah'  => 1,
            'gambar'  => $gambar
        ];
    }

    $total_item  = 0;
    $total_harga = 0;
    foreach ($_SESSION['keranjang'] as $item) {
        $total_item  += $item['jumlah'];
        $total_harga += ($item['harga'] * $item['jumlah']);
    }

    header('Content-Type: application/json');
    echo json_encode([
        'status'       => 'success',
        'total_item'   => $total_item,
        'total_format' => 'Rp. ' . number_format($total_harga, 0, ',', '.')
    ]);
    exit;
}

// 3. HITUNG JUMLAH KERANJANG SAAT HALAMAN DIBUKA / DI-RELOAD
$cart_count = 0;
$cart_total = 0;
if (!empty($_SESSION['keranjang'])) {
    foreach ($_SESSION['keranjang'] as $item) {
        $cart_count += $item['jumlah'];
        $cart_total += ($item['harga'] * $item['jumlah']);
    }
}

// 4. HUBUNGKAN KE DATABASE & HELPERS
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';

// 5. FILTER KATEGORI & PENCARIAN
$kategori = strtolower($_GET['kategori'] ?? 'semua');
$search   = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM menu WHERE 1=1";
$params = [];

if ($kategori !== 'semua') {
    $kategori_map = [
        'makanan' => 'food',
        'minuman' => 'drink',
        'snack'   => 'snack'
    ];
    $kat_db = $kategori_map[$kategori] ?? $kategori;
    $sql .= " AND LOWER(kategori) = :kategori";
    $params[':kategori'] = $kat_db;
}

if ($search !== '') {
    $sql .= " AND LOWER(nama) LIKE :search";
    $params[':search'] = '%' . strtolower($search) . '%';
}

$sql .= " ORDER BY id_menu ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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
    <title>Cysto Cafe - Meja <?= e($nomor_meja) ?></title>
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

        <!-- Search -->
        <form method="GET" action="" class="search-box">
            <input type="hidden" name="kategori" value="<?= e($kategori) ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" name="q" placeholder="Cari Menu..." value="<?= e($search) ?>" autocomplete="off">
        </form>

        <!-- Kategori -->
        <nav class="category-tabs">
            <a href="?kategori=semua" class="tab-btn <?= $kategori === 'semua' ? 'active' : '' ?>">Semua</a>
            <a href="?kategori=makanan" class="tab-btn <?= $kategori === 'makanan' ? 'active' : '' ?>">Makanan</a>
            <a href="?kategori=minuman" class="tab-btn <?= $kategori === 'minuman' ? 'active' : '' ?>">Minuman</a>
            <a href="?kategori=snack" class="tab-btn <?= $kategori === 'snack' ? 'active' : '' ?>">Snack</a>
        </nav>

        <!-- Grid Menu -->
        <main class="menu-grid-2col">
            <?php if (empty($menus)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px 10px; color: #7A6252;">
                    <p>Tidak ada menu yang ditemukan.</p>
                </div>
            <?php else: ?>
                <?php foreach ($menus as $m): ?>
                    <article class="menu-card">
                        <div class="card-img-wrap">
                            <img src="../assets/images/menu/<?= e($m['gambar'] ?? 'default-menu.png') ?>" 
                                 onerror="this.onerror=null; this.src='../assets/images/coffee-icon.svg';" 
                                 alt="<?= e($m['nama']) ?>" 
                                 class="card-img">
                        </div>
                        <div class="card-body">
                            <div class="card-info">
                                <h3 class="card-title"><?= e($m['nama']) ?></h3>
                                <p class="card-price">Rp. <?= number_format($m['harga'], 0, ',', '.') ?></p>
                            </div>
                            <button type="button" class="btn-plus" 
                                    onclick="tambahItem(<?= $m['id_menu'] ?>, '<?= e($m['nama']) ?>', <?= (int)$m['harga'] ?>, '<?= e($m['gambar'] ?? 'default-menu.png') ?>')" 
                                    title="Tambah ke keranjang">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <!-- Floating Cart Bar mengarah ke keranjang.php -->
        <a href="keranjang.php" class="bottom-cart-bar" id="cartBar">
            <div class="cart-icon-group">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                <span class="cart-badge-count" id="cartCount"><?= $cart_count ?></span>
            </div>
            <span class="cart-total-text" id="cartTotal">Total : Rp. <?= number_format($cart_total, 0, ',', '.') ?></span>
            <div class="cart-arrow">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </div>
        </a>

    </div>

    <!-- Script AJAX -->
    <script>
    function tambahItem(id, nama, harga, gambar) {
        const formData = new FormData();
        formData.append('action', 'tambah_keranjang');
        formData.append('id_menu', id);
        formData.append('nama', nama);
        formData.append('harga', harga);
        formData.append('gambar', gambar);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('cartCount').innerText = data.total_item;
            document.getElementById('cartTotal').innerText = 'Total : ' + data.total_format;

            const cartBar = document.getElementById('cartBar');
            cartBar.style.transform = 'translateX(-50%) scale(1.03)';
            setTimeout(() => {
                cartBar.style.transform = 'translateX(-50%) scale(1)';
            }, 150);
        })
        .catch(err => console.error('Error tambah item:', err));
    }
    </script>

</body>
</html>
