<?php
session_start();

// 1. Tangkap nomor meja dari URL QR Code (misal: ?meja=3)
if (isset($_GET['meja'])) {
    $_SESSION['id_meja'] = (int)$_GET['meja'];
}

// 2. Proteksi: Kalau masuk tanpa scan QR dan belum ada session meja
if (empty($_SESSION['id_meja'])) {
    die("
    <div style='font-family: serif; text-align: center; padding: 50px 20px; background-color: #F7F4EE; min-height: 100vh;'>
        <h2 style='color: #5C4033;'>Akses Ditolak</h2>
        <p style='color: #7A6252;'>Silakan scan QR Code yang ada di meja kafe untuk mulai memesan!</p>
    </div>
    ");
}

$nomor_meja = $_SESSION['id_meja'];

// 3. Ambil data menu dari database PostgreSQL
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';

$menus = [];
try {
    $stmt = $pdo->query("SELECT * FROM menu ORDER BY id_menu ASC");
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Jika tabel menu belum ada atau kosong
    $menus = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Menu - Meja <?= e($nomor_meja) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /*.table-badge {
            background-color: #5C4033;
            color: #FFFFFF;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 12px;
        }

        .menu-grid {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 10px;
        }

        .menu-card {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(92, 64, 51, 0.06);
            border: 1px solid #ECE7E1;
        }

        .menu-info h3 {
            font-size: 16px;
            color: #382519;
            margin-bottom: 4px;
        }

        .menu-info p {
            font-size: 14px;
            color: #7A6252;
            font-weight: 600;
        }

        .btn-add {
            background-color: #5C4033;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            font-family: inherit;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-add:hover {
            background-color: #4A3328;
        }*/
    </style>
</head>
<body>

    <div class="mobile-container">
        <!-- Header Kopi -->
        <header class="header-deco">
            <div class="deco-line"></div>
            <div class="coffee-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M7 2v2"></path>
                    <path d="M11 1v3"></path>
                    <path d="M15 2v2"></path>
                    <path d="M4 8h12v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8z"></path>
                    <path d="M16 9h1.5a2.5 2.5 0 0 1 0 5H16"></path>
                </svg>
            </div>
            <div class="deco-line"></div>
        </header>

        <!-- Badge Nomor Meja yang didapat dari QR -->
        <div style="text-align: center;">
            <span class="table-badge">📍 Terdeteksi di Meja #<?= str_pad($nomor_meja, 2, '0', STR_PAD_LEFT) ?></span>
        </div>

        <h1 class="main-title" style="font-size: 24px;">Pilih Menu Favoritmu</h1>
        <p class="main-subtitle" style="margin-bottom: 20px;">Silakan pilih makanan & minuman langsung dari mejamu</p>

        <!-- Daftar Menu -->
        <main class="menu-grid">
            <?php if (empty($menus)): ?>
                <!-- Menu Contoh jika Database Belum Diisi -->
                <div class="menu-card">
                    <div class="menu-info">
                        <h3>Kopi Susu Senja</h3>
                        <p>Rp 18.000</p>
                    </div>
                    <button class="btn-add">+ Pesan</button>
                </div>
                <div class="menu-card">
                    <div class="menu-info">
                        <h3>Americano Ice</h3>
                        <p>Rp 15.000</p>
                    </div>
                    <button class="btn-add">+ Pesan</button>
                </div>
                <div class="menu-card">
                    <div class="menu-info">
                        <h3>Croissant Butter</h3>
                        <p>Rp 22.000</p>
                    </div>
                    <button class="btn-add">+ Pesan</button>
                </div>
            <?php else: ?>
                <?php foreach ($menus as $m): ?>
                    <div class="menu-card">
                        <div class="menu-info">
                            <h3><?= e($m['nama']) ?></h3>
                            <p>Rp <?= number_format($m['harga'], 0, ',', '.') ?></p>
                        </div>
                        <button class="btn-add">+ Pesan</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>

</body>
</html>