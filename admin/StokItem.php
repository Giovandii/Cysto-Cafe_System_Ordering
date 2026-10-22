<?php
session_start();

// Proteksi login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// 1. Set menu aktif agar tombol "Stok Item" di navbar otomatis menyala
$active_menu = 'stok_item';

// 2. Hubungkan ke database & helpers
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';

// 3. Logika Update Stok (Jika tombol centang simpan ditekan)
$pesan_sukses = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stok') {
    $id_menu   = (int)$_POST['id_menu'];
    $stok_baru = (int)$_POST['stok'];

    if ($stok_baru >= 0) {
        $stmt_update = $pdo->prepare("UPDATE menu SET stok = :stok WHERE id_menu = :id");
        $stmt_update->execute([':stok' => $stok_baru, ':id' => $id_menu]);
        $pesan_sukses = "Stok berhasil diperbarui!";
    }
}

// 4. Filter Kategori & Pencarian
$filter_jenis = $_GET['jenis'] ?? 'semua';
$search_query = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM menu WHERE 1=1";
$params = [];

if ($filter_jenis !== 'semua') {
    // Normalisasi 'makanan' -> 'food', 'minuman' -> 'drink', 'snacks' -> 'snack'
    $kategori_map = ['makanan' => 'food', 'minuman' => 'drink', 'snacks' => 'snack'];
    $kat_db = $kategori_map[strtolower($filter_jenis)] ?? $filter_jenis;
    $sql .= " AND LOWER(kategori) = :kategori";
    $params[':kategori'] = $kat_db;
}

if ($search_query !== '') {
    $sql .= " AND LOWER(nama) LIKE :q";
    $params[':q'] = '%' . strtolower($search_query) . '%';
}

$sql .= " ORDER BY id_menu ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $items = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Pesanan - Cafe Menu</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ================= STYLING KHUSUS STOK ITEM ================= */
        .stock-table th {
            padding: 14px 16px;
            font-size: 13.5px;
            letter-spacing: 0.3px;
        }

        .stock-table td {
            padding: 12px 16px;
            vertical-align: middle;
            font-size: 13px;
        }

        /* Kolom Item dengan Thumbnail */
        .item-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .item-thumb {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #E2D9D0;
            background-color: #F8F5EE;
        }

        .item-title {
            font-weight: 700;
            color: #382519;
            font-size: 13.5px;
        }

        /* Badge Jenis Kategori */
        .badge-jenis {
            background-color: #EFE7DE;
            color: #5A3D2D;
            padding: 5px 14px;
            border-radius: 14px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        /* Badge Stok Hijau */
        .badge-stok {
            background-color: #D8E8D5;
            color: #446840;
            padding: 5px 12px;
            border-radius: 14px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Tombol Aksi Pensil */
        .btn-edit-icon {
            background: transparent;
            border: none;
            cursor: pointer;
            color: #5A3D2D;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6px;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .btn-edit-icon:hover {
            background: #EFE8E1;
        }

        /* Baris In-Line Editing Aktif */
        .row-editing {
            background-color: #EDE5DD !important;
        }

        /* Input Spinner Angka */
        .input-stock-spinner {
            width: 65px;
            height: 32px;
            border: 1.5px solid #BCAFA4;
            border-radius: 6px;
            padding: 2px 6px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            outline: none;
            background: #FFFFFF;
        }

        /* Tombol Simpan & Batal di Baris */
        .btn-inline-save {
            background-color: #D8E8D5;
            color: #2D5A27;
            border: 1px solid #B8D6B3;
            border-radius: 6px;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-inline-cancel {
            background-color: #EBB5AF;
            color: #8C2419;
            border: 1px solid #DF9B93;
            border-radius: 6px;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
        }

        .inline-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }
    </style>
</head>
<body class="dashboard-body">

    <div class="dashboard-layout">
        <!-- 1. Panggil Sidebar Modular -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- 2. Konten Utama: Stok Item -->
        <main class="dashboard-content">
            <div class="content-header">
                <h1 class="page-title">Stok Pesanan</h1>
                <p class="page-subtitle">Kelola ketersediaan menu cafe</p>
            </div>

            <div class="order-main-panel">
                
                <!-- Filter Bar Atas -->
                <div class="filter-bar" style="margin-bottom: 22px;">
                    <!-- Filter Jenis Item -->
                    <div class="time-filter-box">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                        </svg>
                        <span>Filter Jenis Item :</span>
                        <select onchange="window.location.href = '?jenis=' + this.value;">
                            <option value="semua" <?= $filter_jenis === 'semua' ? 'selected' : '' ?>>Semua</option>
                            <option value="makanan" <?= $filter_jenis === 'makanan' ? 'selected' : '' ?>>Makanan</option>
                            <option value="minuman" <?= $filter_jenis === 'minuman' ? 'selected' : '' ?>>Minuman</option>
                            <option value="snacks" <?= $filter_jenis === 'snacks' ? 'selected' : '' ?>>Snacks</option>
                        </select>
                    </div>

                    <!-- Pencarian Berdasarkan Nama -->
                    <form method="GET" action="" class="search-input-box" style="max-width: 320px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <span>Cari :</span>
                        <input type="text" name="q" placeholder="Nama menu..." value="<?= e($search_query) ?>" style="border:none; outline:none; font-family:inherit;">
                    </form>
                </div>

                <!-- Tabel Stok -->
                <div class="table-responsive">
                    <table class="order-table stock-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No.</th>
                                <th>Nama Item</th>
                                <th style="text-align: center;">Jenis</th>
                                <th style="text-align: center;">Stok Tersedia</th>
                                <th style="text-align: center; width: 90px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <!-- Contoh Tampilan Statis Sesuai Mockup jika Data DB Belum Masuk -->
                                <tr id="row-1">
                                    <td>1</td>
                                    <td>
                                        <div class="item-cell">
                                            <img src="../assets/images/salted-caramel.png" onerror="this.src='../assets/images/coffee-icon.svg'" class="item-thumb" alt="Salted Caramel">
                                            <span class="item-title">Salted Caramel</span>
                                        </div>
                                    </td>
                                    <td style="text-align: center;"><span class="badge-jenis">Minuman</span></td>
                                    <td style="text-align: center;">
                                        <span class="badge-stok">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            <span class="stock-val">67</span>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <button class="btn-edit-icon" onclick="enableEdit(1, 67)">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                        </button>
                                    </td>
                                </tr>

                                <tr id="row-2">
                                    <td>2</td>
                                    <td>
                                        <div class="item-cell">
                                            <img src="../assets/images/potato-wedges.png" onerror="this.src='../assets/images/coffee-icon.svg'" class="item-thumb" alt="Potato Wedges">
                                            <span class="item-title">Potato Wedges</span>
                                        </div>
                                    </td>
                                    <td style="text-align: center;"><span class="badge-jenis">Snacks</span></td>
                                    <td style="text-align: center;">
                                        <span class="badge-stok">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            <span class="stock-val">25</span>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <button class="btn-edit-icon" onclick="enableEdit(2, 25)">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                        </button>
                                    </td>
                                </tr>

                                <tr id="row-3">
                                    <td>3</td>
                                    <td>
                                        <div class="item-cell">
                                            <img src="../assets/images/french-fries.png" onerror="this.src='../assets/images/coffee-icon.svg'" class="item-thumb" alt="French Fries">
                                            <span class="item-title">French Fries</span>
                                        </div>
                                    </td>
                                    <td style="text-align: center;"><span class="badge-jenis">Snacks</span></td>
                                    <td style="text-align: center;">
                                        <span class="badge-stok">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            <span class="stock-val">28</span>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <button class="btn-edit-icon" onclick="enableEdit(3, 28)">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <!-- Perulangan Riil dari Database PostgreSQL -->
                                <?php $no = 1; foreach ($items as $item): ?>
                                    <tr id="row-<?= $item['id_menu'] ?>">
                                        <td><?= $no++ ?></td>
                                        <td>
                                            <div class="item-cell">
                                                <img src="../assets/images/menu/<?= e($item['gambar'] ?? 'default.png') ?>" onerror="this.src='../assets/images/coffee-icon.svg'" class="item-thumb" alt="<?= e($item['nama']) ?>">
                                                <span class="item-title"><?= e($item['nama']) ?></span>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="badge-jenis"><?= ucfirst(e($item['kategori'] ?? 'Makanan')) ?></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="badge-stok">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                <span class="stock-val"><?= (int)$item['stok'] ?></span>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <button class="btn-edit-icon" onclick="enableEdit(<?= $item['id_menu'] ?>, <?= (int)$item['stok'] ?>)">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>

    <!-- JavaScript Interaktif untuk In-Line Editing -->
    <script>
        function enableEdit(id, currentStock) {
            const row = document.getElementById('row-' + id);
            if (!row) return;

            // Simpan HTML awal untuk cadangan jika dibatalkan
            row.setAttribute('data-original-html', row.innerHTML);
            row.classList.add('row-editing');

            const cells = row.getElementsByTagName('td');
            const stockCell = cells[3];
            const actionCell = cells[4];

            // 1. Ubah cell stok menjadi Input Number Spinner
            stockCell.innerHTML = `
                <form id="form-stock-${id}" method="POST" style="margin:0; display:inline-block;">
                    <input type="hidden" name="action" value="update_stok">
                    <input type="hidden" name="id_menu" value="${id}">
                    <input type="number" name="stok" class="input-stock-spinner" value="${currentStock}" min="0" required>
                </form>
            `;

            // 2. Ubah cell aksi menjadi Tombol Centang Simpan & Silang Batal
            actionCell.innerHTML = `
                <div class="inline-actions" style="justify-content: center;">
                    <button type="submit" form="form-stock-${id}" class="btn-inline-save" title="Simpan">✓</button>
                    <button type="button" class="btn-inline-cancel" onclick="cancelEdit(${id})" title="Batal">✕</button>
                </div>
            `;
        }

        function cancelEdit(id) {
            const row = document.getElementById('row-' + id);
            if (!row) return;

            // Kembalikan ke tampilan semula
            row.innerHTML = row.getAttribute('data-original-html');
            row.classList.remove('row-editing');
        }
    </script>

</body>
</html>