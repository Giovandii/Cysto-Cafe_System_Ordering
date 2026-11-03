<?php
session_start();

// 1. TANGKAP NOMOR MEJA DARI LINK QR (Contoh: menu.php?meja=5)
if (isset($_GET['meja'])) {
    $meja_baru = (int)$_GET['meja'];

    // Jika ganti meja, bersihkan keranjang
    if (isset($_SESSION['id_meja']) && $_SESSION['id_meja'] !== $meja_baru) {
        unset($_SESSION['keranjang']);
    }

    $_SESSION['id_meja'] = $meja_baru;
}

// Proteksi Meja
if (empty($_SESSION['id_meja'])) {
    die("
    <div style='font-family: serif; text-align: center; padding: 70px 20px; background-color: #F7F4EE; min-height: 100vh;'>
        <h2 style='color: #5C4033;'>Akses Ditolak</h2>
        <p style='color: #7A6252; margin-top: 8px;'>Silakan scan QR Code yang ada di meja kafe untuk mulai memesan!</p>
    </div>
    ");
}

$nomor_meja = $_SESSION['id_meja'];

// 2. TANGKAP REQUEST TAMBAH KERANJANG DENGAN CATATAN (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_keranjang') {
    $id      = (int)$_POST['id_menu'];
    $nama    = $_POST['nama'];
    $harga   = (float)$_POST['harga'];
    $gambar  = $_POST['gambar'] ?? 'default-menu.png';
    $catatan = trim($_POST['catatan'] ?? '');
    $jumlah  = max(1, (int)($_POST['jumlah'] ?? 1));

    if (!isset($_SESSION['keranjang'])) {
        $_SESSION['keranjang'] = [];
    }

    // Kunci unik berdasarkan ID Menu + Catatan Khusus
    $item_key = $id . '_' . md5(strtolower($catatan));

    if (isset($_SESSION['keranjang'][$item_key])) {
        $_SESSION['keranjang'][$item_key]['jumlah'] += $jumlah;
    } else {
        $_SESSION['keranjang'][$item_key] = [
            'key'      => $item_key,
            'id_menu'  => $id,
            'nama'     => $nama,
            'harga'    => $harga,
            'jumlah'   => $jumlah,
            'gambar'   => $gambar,
            'catatan'  => $catatan
        ];
    }

    // Hitung total item & harga
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

// 3. HITUNG JUMLAH KERANJANG SAAT RELOAD
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
    $kategori_map = ['makanan' => 'food', 'minuman' => 'drink', 'snack' => 'snack'];
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
    <style>
        :root {
            --bg-page: #F7F4EE;
            --color-primary: #5C4033;
            --color-primary-dark: #432E24;
            --color-text-title: #382519;
            --color-border: #C8B8A6;
            --font-serif: 'Playfair Display', Georgia, 'Times New Roman', serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #E5E5E5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            font-family: var(--font-serif);
        }

        .mobile-container {
            width: 100%;
            max-width: 410px;
            min-height: 100vh;
            background-color: var(--bg-page);
            padding: 24px 20px 100px 20px;
            position: relative;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .brand-area {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-icon {
            color: var(--color-primary);
            display: flex;
            align-items: center;
        }

        .brand-name {
            font-size: 24px;
            font-weight: 700;
            color: var(--color-primary);
            letter-spacing: 0.5px;
        }

        .table-pill {
            background-color: #FFFFFF;
            border: 1.5px solid var(--color-border);
            border-radius: 12px;
            padding: 6px 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--color-primary);
            font-size: 13.5px;
            font-weight: 700;
        }

        .search-box {
            width: 100%;
            background-color: #FFFFFF;
            border: 1.5px solid var(--color-border);
            border-radius: 14px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
        }

        .search-box svg { color: #8C7D70; }

        .search-box input {
            border: none;
            outline: none;
            width: 100%;
            font-family: inherit;
            font-size: 14px;
            background: transparent;
            color: var(--color-text-title);
        }

        .category-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 22px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .category-tabs::-webkit-scrollbar { display: none; }

        .tab-btn {
            flex: 1;
            text-align: center;
            padding: 8px 0;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            color: var(--color-primary);
            background-color: #FFFFFF;
            border: 1.5px solid var(--color-border);
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .tab-btn.active {
            background-color: var(--color-primary);
            color: #FFFFFF;
            border-color: var(--color-primary);
        }

        .menu-grid-2col {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .menu-card {
            background-color: #FFFFFF;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(92, 64, 51, 0.08);
            display: flex;
            flex-direction: column;
            border: 1px solid #ECE3DA;
            transition: transform 0.2s ease;
        }

        .menu-card:hover { transform: translateY(-3px); }

        .card-img-wrap {
            width: 100%;
            height: 125px;
            background-color: #EFE8DF;
            position: relative;
            overflow: hidden;
        }

        .card-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-body {
            padding: 10px 12px 12px 12px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 6px;
        }

        .card-info { flex: 1; }

        .card-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--color-text-title);
            margin-bottom: 4px;
            line-height: 1.25;
        }

        .card-price {
            font-size: 12px;
            font-weight: 600;
            color: #6B5547;
        }

        .btn-plus {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: var(--color-primary);
            color: #FFFFFF;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
            flex-shrink: 0;
        }

        .btn-plus:hover { background-color: var(--color-primary-dark); }

        .bottom-cart-bar {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 40px);
            max-width: 370px;
            background-color: var(--color-primary);
            border-radius: 18px;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            color: #FFFFFF;
            box-shadow: 0 8px 24px rgba(74, 51, 40, 0.35);
            text-decoration: none;
            z-index: 999;
            transition: transform 0.2s ease;
        }

        .cart-icon-group {
            position: relative;
            display: flex;
            align-items: center;
            padding-right: 14px;
            border-right: 1px solid rgba(255, 255, 255, 0.25);
        }

        .cart-badge-count {
            position: absolute;
            top: -6px;
            right: 6px;
            background-color: #B82C2C;
            color: #FFFFFF;
            font-size: 10px;
            font-weight: bold;
            width: 17px;
            height: 17px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1.5px solid var(--color-primary);
        }

        .cart-total-text {
            flex: 1;
            padding-left: 14px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .cart-arrow { display: flex; align-items: center; }

        /* Modal Pop-up Catatan Pesanan */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.55);
            display: none;
            align-items: flex-end;
            justify-content: center;
            z-index: 9999;
            padding-bottom: 0;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: #FFFFFF;
            width: 100%;
            max-width: 410px;
            border-radius: 24px 24px 0 0;
            padding: 24px 22px 30px 22px;
            animation: slideUp 0.25s ease-out;
            box-shadow: 0 -10px 25px rgba(0,0,0,0.15);
        }

        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .modal-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--color-text-title);
        }

        .btn-close-modal {
            background: transparent;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #8C7D70;
        }

        .modal-item-preview {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 16px;
            padding: 10px;
            background: #F8F5EE;
            border-radius: 14px;
        }

        .modal-thumb {
            width: 55px;
            height: 55px;
            border-radius: 10px;
            object-fit: cover;
        }

        .note-input {
            width: 100%;
            border: 1.5px solid var(--color-border);
            border-radius: 12px;
            padding: 10px 14px;
            font-family: inherit;
            font-size: 13.5px;
            outline: none;
            resize: none;
            margin-bottom: 14px;
            background: #FFFFFF;
        }

        .note-input:focus {
            border-color: var(--color-primary);
        }

        /* Checkbox Group Catatan */
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 12px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #FFFFFF;
            border: 1.5px solid var(--color-border);
            border-radius: 12px;
            padding: 10px 14px;
            cursor: pointer;
            font-size: 13.5px;
            color: #382519;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .checkbox-label:hover {
            border-color: var(--color-primary);
        }

        .checkbox-label input[type="checkbox"] {
            accent-color: var(--color-primary);
            width: 17px;
            height: 17px;
            cursor: pointer;
        }

        .checkbox-label.checked {
            border-color: var(--color-primary);
            background-color: #F5EFEB;
        }

        .btn-modal-add {
            width: 100%;
            background: var(--color-primary);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 14px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(92, 64, 51, 0.2);
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

        <!-- Search -->
        <form method="GET" action="" class="search-box">
            <input type="hidden" name="kategori" value="<?= e($kategori) ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
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
                            <!-- Tombol Plus Buka Modal Catatan -->
                            <button type="button" class="btn-plus" 
                                    onclick="bukaModalCatatan(<?= $m['id_menu'] ?>, '<?= e(addslashes($m['nama'])) ?>', <?= (int)$m['harga'] ?>, '<?= e($m['gambar'] ?? 'default-menu.png') ?>', '<?= e($m['kategori'] ?? 'food') ?>')" 
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

        <!-- Floating Cart Bar -->
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

    <!-- MODAL POP-UP CATATAN PESANAN -->
    <div class="modal-overlay" id="modalCatatan">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Tambah Catatan Menu</h3>
                <button type="button" class="btn-close-modal" onclick="tutupModalCatatan()">&times;</button>
            </div>

            <div class="modal-item-preview">
                <img src="" id="modalImg" class="modal-thumb" onerror="this.onerror=null; this.src='../assets/images/coffee-icon.svg';">
                <div>
                    <h4 id="modalNama" style="font-size: 14.5px; color: #382519; margin-bottom: 2px;"></h4>
                    <p id="modalHarga" style="font-size: 13px; color: #5C4033; font-weight: bold;"></p>
                </div>
            </div>

            <label style="font-size: 13px; font-weight: 700; color: #382519; display: block; margin-bottom: 8px;">Pilihan Catatan (Opsional):</label>
            
            <!-- Checkbox Dinamis Berdasarkan Kategori -->
            <div id="modalCheckboxOptions" class="checkbox-group">
                <!-- Diisi otomatis lewat JS -->
            </div>

            <!-- Area Input Teks Manual saat "Other" dipilih -->
            <div id="otherNoteContainer" style="display: none; margin-top: 6px;">
                <label style="font-size: 12px; font-weight: 600; color: #5C4033; display: block; margin-bottom: 4px;">Catatan Khusus Lainnya:</label>
                <textarea id="modalNote" rows="2" class="note-input" placeholder="Contoh: Es sedikit, bumbu dipisah, tanpa bawang..."></textarea>
            </div>

            <button type="button" class="btn-modal-add" id="btnKonfirmasiModal" onclick="submitItemDenganCatatan()">
                + Masukkan ke Keranjang
            </button>
        </div>
    </div>

    <!-- Script AJAX & Modal -->
    <script>
    let activeItem = null;

    function bukaModalCatatan(id, nama, harga, gambar, kategori) {
        activeItem = { id, nama, harga, gambar, kategori };
        document.getElementById('modalNama').innerText = nama;
        document.getElementById('modalHarga').innerText = 'Rp. ' + harga.toLocaleString('id-ID');
        document.getElementById('modalImg').src = '../assets/images/menu/' + gambar;
        document.getElementById('modalNote').value = '';

        const container = document.getElementById('modalCheckboxOptions');
        const otherContainer = document.getElementById('otherNoteContainer');
        otherContainer.style.display = 'none';

        // Deteksi Kategori: Minuman vs Makanan/Snack
        const kat = (kategori || '').toLowerCase();
        const isMinuman = (kat === 'drink' || kat === 'minuman');

        if (isMinuman) {
            container.innerHTML = `
                <label class="checkbox-label" onclick="handleCheckboxClick(event, this)">
                    <input type="checkbox" name="opt_catatan" value="Ice" onchange="handleCheckboxChange(this)">
                    <span>🧊 Ice (Dingin)</span>
                </label>
                <label class="checkbox-label" onclick="handleCheckboxClick(event, this)">
                    <input type="checkbox" name="opt_catatan" value="No Ice" onchange="handleCheckboxChange(this)">
                    <span>☕ No Ice (Hangat/Tanpa Es)</span>
                </label>
                <label class="checkbox-label" onclick="handleCheckboxClick(event, this)">
                    <input type="checkbox" name="opt_catatan" value="other" onchange="toggleOtherNote(this)">
                    <span>✏️ Other (Catatan Manual)</span>
                </label>
            `;
        } else {
            // Makanan & Snack
            container.innerHTML = `
                <label class="checkbox-label" onclick="handleCheckboxClick(event, this)">
                    <input type="checkbox" name="opt_catatan" value="Pedas" onchange="handleCheckboxChange(this)">
                    <span>🌶️ Pedas</span>
                </label>
                <label class="checkbox-label" onclick="handleCheckboxClick(event, this)">
                    <input type="checkbox" name="opt_catatan" value="Tidak Pedas" onchange="handleCheckboxChange(this)">
                    <span>🍃 Tidak Pedas</span>
                </label>
                <label class="checkbox-label" onclick="handleCheckboxClick(event, this)">
                    <input type="checkbox" name="opt_catatan" value="other" onchange="toggleOtherNote(this)">
                    <span>✏️ Other (Catatan Manual)</span>
                </label>
            `;
        }

        document.getElementById('modalCatatan').classList.add('active');
    }

    function tutupModalCatatan() {
        document.getElementById('modalCatatan').classList.remove('active');
    }

    // Mencegah konflik opsi berlawanan (Pedas vs Tidak Pedas, Ice vs No Ice)
    function handleCheckboxChange(el) {
        const parent = el.closest('.checkbox-label');
        if (el.checked) {
            parent.classList.add('checked');
            // Uncheck opsi yang berlawanan
            const allCheckboxes = document.querySelectorAll('input[name="opt_catatan"]');
            allCheckboxes.forEach(cb => {
                if (cb !== el && cb.value !== 'other') {
                    cb.checked = false;
                    cb.closest('.checkbox-label').classList.remove('checked');
                }
            });
        } else {
            parent.classList.remove('checked');
        }
    }

    // Toggle tampilan textarea saat opsi Other dipilih
    function toggleOtherNote(el) {
        const parent = el.closest('.checkbox-label');
        const otherContainer = document.getElementById('otherNoteContainer');
        const modalNote = document.getElementById('modalNote');

        if (el.checked) {
            parent.classList.add('checked');
            otherContainer.style.display = 'block';
            modalNote.focus();
        } else {
            parent.classList.remove('checked');
            otherContainer.style.display = 'none';
            modalNote.value = '';
        }
    }

    // Handler klik label agar konsisten
    function handleCheckboxClick(e, label) {
        if (e.target.tagName.toLowerCase() === 'input') return;
        const cb = label.querySelector('input[type="checkbox"]');
        cb.checked = !cb.checked;
        if (cb.value === 'other') {
            toggleOtherNote(cb);
        } else {
            handleCheckboxChange(cb);
        }
    }

    function submitItemDenganCatatan() {
        if (!activeItem) return;

        // Ambil semua opsi yang dipilih
        const selected = [];
        const checkboxes = document.querySelectorAll('input[name="opt_catatan"]:checked');
        
        checkboxes.forEach(cb => {
            if (cb.value !== 'other') {
                selected.push(cb.value);
            }
        });

        // Ambil catatan manual jika ada
        const manualNote = document.getElementById('modalNote').value.trim();
        if (manualNote !== '') {
            selected.push(manualNote);
        }

        // Gabungkan catatan (Contoh: "Pedas, tanpa sayur" atau "Ice, gula sedikit")
        const finalCatatan = selected.join(', ');

        const formData = new FormData();
        formData.append('action', 'tambah_keranjang');
        formData.append('id_menu', activeItem.id);
        formData.append('nama', activeItem.nama);
        formData.append('harga', activeItem.harga);
        formData.append('gambar', activeItem.gambar);
        formData.append('catatan', finalCatatan);
        formData.append('jumlah', 1);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('cartCount').innerText = data.total_item;
            document.getElementById('cartTotal').innerText = 'Total : ' + data.total_format;

            tutupModalCatatan();

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
