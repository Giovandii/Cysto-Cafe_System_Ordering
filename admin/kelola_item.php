<?php
$page_title = "Kelola Item - Cafe Menu";
require_once __DIR__ . '/../includes/koneksi.php';

// sidebar.php membaca $_SESSION['role'], jadi session harus aktif
if (session_status() === PHP_SESSION_NONE) session_start();

// Aktifkan jika halaman ini hanya boleh dibuka admin:
// if (strtolower($_SESSION['role'] ?? '') !== 'admin') { header('Location: LoginForAdmin.php?role=admin'); exit; }

$active_menu = 'kelola_item';   // menandai menu aktif di sidebar.php

/* =====================================================
   PENGATURAN - sesuaikan dengan struktur databasemu
   ===================================================== */
$cfg = [
    'tabel'  => 'menu',
    'id'     => 'id_menu',
    'nama'   => 'nama',
    'jenis'  => 'kategori',
    'harga'  => 'harga',
    'stok'   => 'stok',
    'gambar' => 'gambar',
    // Kolom varian opsional. Dipakai otomatis kalau kolomnya ada di tabel.
    // Untuk mengaktifkan: ALTER TABLE menu ADD COLUMN varian VARCHAR(50);
    'varian' => 'varian',
    // Folder gambar (relatif terhadap file ini)
    'folder_gambar' => '/../assets/images/menu/',
];

$jenis_opsi  = ['food' => 'Makanan', 'drink' => 'Minuman', 'snack' => 'Snack'];
$varian_opsi = ['Tidak Pedas', 'Pedas', 'Extra Pedas'];

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function rupiah($n) { return 'Rp' . number_format((float)$n, 0, ',', '.'); }

/* =====================================================
   Koneksi database (dari koneksi.php)
   ===================================================== */
function db_conn()
{
    foreach (['pdo', 'conn', 'db', 'koneksi', 'link'] as $nama) {
        if (!empty($GLOBALS[$nama])) return $GLOBALS[$nama];
    }
    return null;
}

function kolom_ada($db, string $tabel, string $kolom): bool
{
    if (!($db instanceof PDO)) return false;
    try {
        $st = $db->prepare('SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1');
        $st->execute([$tabel, $kolom]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $ex) {
        return false;
    }
}

$db = db_conn();
if (!kolom_ada($db, $cfg['tabel'], (string)$cfg['varian'])) $cfg['varian'] = null;

/* =====================================================
   Ambil data. Otomatis mengenali koneksi dari koneksi.php:
   PDO, PostgreSQL (pg_*), atau mysqli.
   Mengembalikan null bila tidak ada variabel koneksi.
   ===================================================== */
function ambil_items(array $cfg, string $filter, string $by, string $q, ?string &$error): ?array
{
    $db = db_conn();
    if ($db === null) return null;

    $kolom = [
        "{$cfg['id']} AS id", "{$cfg['nama']} AS nama", "{$cfg['jenis']} AS jenis",
        "{$cfg['harga']} AS harga", "{$cfg['stok']} AS stok", "{$cfg['gambar']} AS gambar",
    ];
    if ($cfg['varian']) $kolom[] = "{$cfg['varian']} AS varian";
    $cols = implode(', ', $kolom);

    $where = [];
    $params = [];
    if ($filter !== '') {
        $where[]  = "LOWER({$cfg['jenis']}) = LOWER(?)";
        $params[] = $filter;
    }
    if ($q !== '') {
        if ($by === 'nama') {
            $where[]  = "LOWER({$cfg['nama']}) LIKE LOWER(?)";
            $params[] = "%$q%";
        } else {                       // harga / stok (sudah divalidasi whitelist)
            $where[]  = "{$cfg[$by]} = ?";
            $params[] = (int)$q;
        }
    }
    $sql = "SELECT $cols FROM {$cfg['tabel']}"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY {$cfg['id']} ASC";

    try {
        if ($db instanceof PDO) {
            $st = $db->prepare($sql);
            $st->execute($params);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($db instanceof mysqli) {
            $st = $db->prepare($sql);
            if ($params) $st->bind_param(str_repeat('s', count($params)), ...$params);
            $st->execute();
            return $st->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        if ($db instanceof \PgSql\Connection || is_resource($db)) {
            $i = 0;
            $sqlPg = preg_replace_callback('/\?/', function () use (&$i) { return '$' . (++$i); }, $sql);
            $res = pg_query_params($db, $sqlPg, $params);
            if (!$res) { $error = pg_last_error($db); return []; }
            return pg_fetch_all($res) ?: [];
        }
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
        return [];
    }
    return null;
}

/* =====================================================
   Upload gambar menu. Mengembalikan nama berkas baru,
   null bila tidak ada berkas, dan mengisi $err bila gagal.
   ===================================================== */
function simpan_gambar(array $f, string $folder, ?string &$err): ?string
{
    $kode = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($kode === UPLOAD_ERR_NO_FILE) return null;
    if ($kode !== UPLOAD_ERR_OK) { $err = 'Upload gambar gagal. Coba lagi.'; return null; }
    if ($f['size'] > 2 * 1024 * 1024) { $err = 'Ukuran gambar maksimal 2 MB.'; return null; }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext  = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) { $err = 'Format gambar harus PNG, JPG, atau WEBP.'; return null; }

    $dir = __DIR__ . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) { $err = 'Folder gambar tidak bisa dibuat.'; return null; }

    $nama = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . $nama)) { $err = 'Gambar gagal disimpan di server.'; return null; }
    return $nama;
}

/* ---------- Filter & pencarian ---------- */
$filter  = strtolower($_GET['jenis'] ?? '');
$cari_by = $_GET['by'] ?? 'nama';
$keyword = trim($_GET['q'] ?? '');

if (!isset($jenis_opsi[$filter])) $filter = '';
if (!in_array($cari_by, ['nama', 'harga', 'stok'], true)) $cari_by = 'nama';

/* =====================================================
   Proses simpan (tambah / edit) dari popup
   ===================================================== */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$form_error = '';
$form_data  = null;   // diisi bila validasi gagal, supaya popup terbuka lagi dengan isian tadi

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    $id     = (int)($_POST['id'] ?? 0);
    $nama   = trim($_POST['nama'] ?? '');
    $jenis  = strtolower($_POST['jenis'] ?? '');
    $harga  = trim($_POST['harga'] ?? '');
    $stok   = trim($_POST['stok'] ?? '');
    $varian = trim($_POST['varian'] ?? '');

    $form_data = [
        'id' => $id, 'nama' => $nama, 'jenis' => $jenis, 'harga' => $harga,
        'stok' => $stok, 'varian' => $varian, 'gambar' => $_POST['gambar_lama'] ?? '',
    ];

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $form_error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($nama === '' || mb_strlen($nama) > 100) {
        $form_error = 'Nama item wajib diisi (maksimal 100 karakter).';
    } elseif (!isset($jenis_opsi[$jenis])) {
        $form_error = 'Kategori item tidak valid.';
    } elseif (!ctype_digit($harga) || (int)$harga < 1) {
        $form_error = 'Harga harus berupa angka lebih dari 0.';
    } elseif (!ctype_digit($stok)) {
        $form_error = 'Stok harus berupa angka 0 atau lebih.';
    } elseif ($varian !== '' && !in_array($varian, $varian_opsi, true)) {
        $form_error = 'Varian tidak valid.';
    } elseif (!($db instanceof PDO)) {
        $form_error = 'Penyimpanan hanya mendukung koneksi PDO. Cek includes/koneksi.php.';
    } else {
        $gambar_baru = simpan_gambar($_FILES['gambar'] ?? [], $cfg['folder_gambar'], $form_error);

        if ($form_error === '') {
            $kolom  = [$cfg['nama'], $cfg['jenis'], $cfg['harga'], $cfg['stok']];
            $nilai  = [$nama, $jenis, (int)$harga, (int)$stok];
            if ($gambar_baru !== null) { $kolom[] = $cfg['gambar']; $nilai[] = $gambar_baru; }
            if ($cfg['varian'])        { $kolom[] = $cfg['varian']; $nilai[] = $varian !== '' ? $varian : null; }

            try {
                if ($id > 0) {
                    $set = implode(', ', array_map(fn($k) => "$k = ?", $kolom));
                    $nilai[] = $id;
                    $st = $db->prepare("UPDATE {$cfg['tabel']} SET $set WHERE {$cfg['id']} = ?");
                } else {
                    $st = $db->prepare("INSERT INTO {$cfg['tabel']} (" . implode(', ', $kolom) . ') VALUES ('
                        . implode(', ', array_fill(0, count($kolom), '?')) . ')');
                }
                $st->execute($nilai);

                $arah = array_filter(['jenis' => $filter, 'by' => $cari_by !== 'nama' ? $cari_by : '', 'q' => $keyword]);
                $arah['msg'] = $id > 0 ? 'diubah' : 'ditambah';
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($arah));
                exit;
            } catch (Throwable $ex) {
                $form_error = 'Gagal menyimpan item. Pastikan data valid lalu coba lagi.';
            }
        }
    }
}

$db_error = null;
$notice   = '';
$items = ambil_items($cfg, $filter, $cari_by, $keyword, $db_error);

if ($items === null) {
    $notice = 'Variabel koneksi database tidak ditemukan di includes/koneksi.php, jadi yang tampil adalah data contoh.';
    $items = [
        ['id'=>1,'nama'=>'Salted Caramel','jenis'=>'drink','harga'=>25000,'stok'=>67,'gambar'=>'salted-caramel.png'],
        ['id'=>2,'nama'=>'Potato Wedges','jenis'=>'snack','harga'=>18000,'stok'=>25,'gambar'=>'potato-wedges.png'],
        ['id'=>3,'nama'=>'French Fries','jenis'=>'snack','harga'=>15000,'stok'=>28,'gambar'=>'french-fries.png'],
        ['id'=>4,'nama'=>'Chicken Nugget','jenis'=>'snack','harga'=>26000,'stok'=>15,'gambar'=>'chicken-nugget.png'],
        ['id'=>5,'nama'=>'Ice Chocolate','jenis'=>'drink','harga'=>12000,'stok'=>38,'gambar'=>'ice-chocolate.png'],
    ];
    if ($filter !== '') $items = array_filter($items, fn($i) => $i['jenis'] === $filter);
    if ($keyword !== '') {
        $items = array_filter($items, fn($i) => $cari_by === 'nama'
            ? stripos($i['nama'], $keyword) !== false
            : (string)$i[$cari_by] === $keyword);
    }
} elseif ($db_error) {
    $notice = 'Query gagal: ' . $db_error . ' (cek nama tabel dan kolom di bagian PENGATURAN).';
}

$pesan_sukses = ['diubah' => 'Item berhasil diperbarui.', 'ditambah' => 'Item baru berhasil ditambahkan.'][$_GET['msg'] ?? ''] ?? '';
$path_gambar  = '..' . $cfg['folder_gambar'];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* ===== Popup tambah / edit item ===== */
        .btn-tambah { display: inline-flex; align-items: center; justify-content: center; width: 2.4rem; height: 2.4rem; border: 0; border-radius: 50%; background: #5b3d2e; color: #fff; cursor: pointer; margin-left: auto; transition: background .15s ease; }
        .btn-tambah:hover { background: #3f261b; }
        .btn-tambah:focus-visible { outline: 3px solid #e3c78b; outline-offset: 2px; }
        button.btn-edit { border: 0; background: transparent; cursor: pointer; padding: 4px; color: inherit; }

        .flash { padding: 10px 14px; border-radius: 8px; margin: 0 0 12px; background: #e6f2e6; color: #2d5a2d; }

        dialog.modal { border: 0; padding: 0; border-radius: 8px; width: min(420px, 92vw); background: #f6efe6; color: #5a3d2b; overflow: hidden; box-shadow: 0 12px 40px rgba(40, 25, 15, .35); }
        dialog.modal::backdrop { background: rgba(40, 25, 15, .5); }
        .modal-head { background: #6b4630; color: #fff; padding: 14px 18px; font-weight: 600; }
        .modal-body { padding: 16px 18px 6px; display: grid; gap: 12px; max-height: 70vh; overflow-y: auto; }
        .modal-body label { display: grid; gap: 5px; font-size: 14px; font-weight: 600; }
        .modal-body input[type=text], .modal-body input[type=number], .modal-body select {
            width: 100%; padding: 8px 10px; border: 1px solid #e2d6c6; border-radius: 6px;
            background: #fffdf9; color: #5a3d2b; font: inherit; font-weight: 400;
        }
        .modal-body input:focus, .modal-body select:focus { outline: 2px solid #b08968; outline-offset: 0; }
        .upload-row { display: flex; align-items: center; gap: 10px; padding: 6px 10px; border: 1px solid #e2d6c6; border-radius: 6px; background: #fffdf9; cursor: pointer; font-weight: 400; }
        .upload-row img { width: 34px; height: 34px; object-fit: cover; border-radius: 4px; background: #efe5d8; }
        .upload-row span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .upload-row input[type=file] { position: absolute; width: 1px; height: 1px; opacity: 0; }
        .form-error { margin: 0; padding: 8px 10px; border-radius: 6px; background: #f8dcd8; color: #8a2a1f; font-size: 14px; }
        .modal-foot { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 18px 18px; }
        .modal-foot button { border: 0; border-radius: 6px; padding: 7px 18px; font: inherit; cursor: pointer; }
        .btn-batal { background: #ead9a9; color: #6b4630; }
        .btn-simpan { background: #8b6f5e; color: #fff; }
        .btn-simpan:hover { background: #6b4630; }
    </style>
</head>
<body>
<div class="layout">

    <!-- ===== Sidebar (dipakai bersama semua halaman admin) ===== -->
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- ===== Konten ===== -->
    <main class="content">
        <header class="page-head">
            <h1>Kelola Item</h1>
            <p>Kelola data menu yang tersedia di cafe</p>
        </header>

        <div class="card">
            <?php if ($pesan_sukses): ?>
                <p class="flash" role="status"><?= e($pesan_sukses) ?></p>
            <?php endif; ?>

            <form class="toolbar" method="get">
                <label class="tool">
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18l-7 9v6l-4 2v-8z"/></svg>
                    Filter Jenis Item :
                    <select name="jenis" onchange="this.form.submit()">
                        <option value="">Semua</option>
                        <?php foreach ($jenis_opsi as $val => $lbl): ?>
                            <option value="<?= e($val) ?>" <?= $filter === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="tool search">
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <label for="by" class="lbl-big">Cari:</label>
                    <select name="by" id="by" aria-label="Cari berdasarkan">
                        <?php foreach (['nama' => 'Nama', 'harga' => 'Harga', 'stok' => 'Stok'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $cari_by === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="search" name="q" value="<?= e($keyword) ?>" placeholder="Ketik di sini..." aria-label="Kata kunci pencarian">
                    <button type="submit">Cari</button>
                </div>

                <button type="button" class="btn-tambah" id="btn-tambah" title="Tambah menu baru" aria-label="Tambah menu baru">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                </button>
            </form>

            <?php if ($notice): ?>
                <p class="notif" role="alert"><?= e($notice) ?></p>
            <?php endif; ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col" class="col-no">No.</th>
                            <th scope="col">Nama Item</th>
                            <th scope="col" class="center">Jenis</th>
                            <th scope="col" class="center">Harga</th>
                            <th scope="col" class="center">Stok</th>
                            <th scope="col" class="center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$items): ?>
                        <tr><td colspan="6" class="empty">Tidak ada item yang cocok. Ubah filter atau kata kunci pencarian.</td></tr>
                    <?php else: $no = 1; foreach ($items as $it): $stok = (int)$it['stok']; ?>
                        <tr id="row-<?= (int)$it['id'] ?>">
                            <td class="no"><?= $no++ ?></td>
                            <td>
                                <div class="item-cell">
                                    <img class="item-thumb"
                                         src="<?= e($path_gambar . ($it['gambar'] ?: 'default.png')) ?>"
                                         alt="<?= e($it['nama']) ?>"
                                         onerror="this.onerror=null; this.src='../assets/images/coffee-icon.svg'">
                                    <span><?= e($it['nama']) ?></span>
                                </div>
                            </td>
                            <td class="center"><span class="badge-jenis"><?= e(ucfirst(strtolower($it['jenis'] ?? ''))) ?></span></td>
                            <td class="center harga"><?= rupiah($it['harga']) ?></td>
                            <td class="center">
                                <span class="badge-stok<?= $stok <= 0 ? ' habis' : '' ?>">
                                    <?php if ($stok > 0): ?>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                    <?php endif; ?>
                                    <?= $stok ?>
                                </span>
                            </td>
                            <td class="center">
                                <button type="button" class="btn-edit btn-buka-edit"
                                        data-id="<?= (int)$it['id'] ?>"
                                        data-nama="<?= e($it['nama']) ?>"
                                        data-jenis="<?= e(strtolower($it['jenis'] ?? '')) ?>"
                                        data-harga="<?= (int)$it['harga'] ?>"
                                        data-stok="<?= $stok ?>"
                                        data-gambar="<?= e($it['gambar'] ?? '') ?>"
                                        data-varian="<?= e($it['varian'] ?? '') ?>"
                                        aria-label="Edit <?= e($it['nama']) ?>">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- ===== Popup Tambah / Edit Item ===== -->
<dialog class="modal" id="dlg-item" aria-labelledby="judul-modal">
    <form method="post" enctype="multipart/form-data" id="form-item" autocomplete="off">
        <div class="modal-head" id="judul-modal">Tambah / Edit Item</div>

        <div class="modal-body">
            <p class="form-error" id="form-error" role="alert" <?= $form_error ? '' : 'hidden' ?>><?= e($form_error) ?></p>

            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="id" id="f-id" value="0">
            <input type="hidden" name="gambar_lama" id="f-gambar-lama" value="">

            <label>Nama Item
                <input type="text" name="nama" id="f-nama" maxlength="100" required>
            </label>

            <label>Kategori Item
                <select name="jenis" id="f-jenis" required>
                    <?php foreach ($jenis_opsi as $val => $lbl): ?>
                        <option value="<?= e($val) ?>"><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Harga
                <input type="number" name="harga" id="f-harga" min="1" step="1" inputmode="numeric" required>
            </label>

            <label>Stok
                <input type="number" name="stok" id="f-stok" min="0" step="1" inputmode="numeric" required>
            </label>

            <label>Gambar Menu
                <span class="upload-row">
                    <img id="f-preview" src="../assets/images/coffee-icon.svg" alt="">
                    <span id="f-namafile">Upload Gambar</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    <input type="file" name="gambar" id="f-gambar" accept="image/png,image/jpeg,image/webp">
                </span>
            </label>

            <?php if ($cfg['varian']): ?>
            <label>Edit Varian
                <select name="varian" id="f-varian">
                    <?php foreach ($varian_opsi as $v): ?>
                        <option value="<?= e($v) ?>"><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
        </div>

        <div class="modal-foot">
            <button type="button" class="btn-batal" id="btn-batal">Batal</button>
            <button type="submit" class="btn-simpan">Simpan</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    const dlg     = document.getElementById('dlg-item');
    const galat   = document.getElementById('form-error');
    const gambarDefault = '../assets/images/coffee-icon.svg';
    const folder  = <?= json_encode($path_gambar) ?>;

    const f = {
        id:     document.getElementById('f-id'),
        lama:   document.getElementById('f-gambar-lama'),
        nama:   document.getElementById('f-nama'),
        jenis:  document.getElementById('f-jenis'),
        harga:  document.getElementById('f-harga'),
        stok:   document.getElementById('f-stok'),
        varian: document.getElementById('f-varian'),
        file:   document.getElementById('f-gambar'),
        nfile:  document.getElementById('f-namafile'),
        prev:   document.getElementById('f-preview')
    };

    function isi(d) {
        d = d || {};
        const edit = Number(d.id) > 0;
        f.id.value    = edit ? d.id : 0;
        f.lama.value  = d.gambar || '';
        f.nama.value  = d.nama || '';
        f.jenis.value = d.jenis || f.jenis.options[0].value;
        f.harga.value = d.harga || '';
        f.stok.value  = d.stok === undefined ? '' : d.stok;
        if (f.varian) f.varian.value = d.varian || f.varian.options[0].value;
        f.file.value  = '';
        f.nfile.textContent = d.gambar ? d.gambar : 'Upload Gambar';
        f.prev.src = d.gambar ? folder + d.gambar : gambarDefault;
        f.prev.onerror = function () { this.onerror = null; this.src = gambarDefault; };
    }

    function buka(d, pesan) {
        isi(d);
        galat.hidden = !pesan;
        galat.textContent = pesan || '';
        dlg.showModal();
        f.nama.focus();
    }

    document.getElementById('btn-tambah').addEventListener('click', function () { buka(null); });

    document.querySelectorAll('.btn-buka-edit').forEach(function (b) {
        b.addEventListener('click', function () { buka(b.dataset); });
    });

    document.getElementById('btn-batal').addEventListener('click', function () { dlg.close(); });

    // klik di luar kotak popup menutupnya
    dlg.addEventListener('click', function (ev) {
        const r = dlg.getBoundingClientRect();
        if (ev.clientX < r.left || ev.clientX > r.right || ev.clientY < r.top || ev.clientY > r.bottom) dlg.close();
    });

    f.file.addEventListener('change', function () {
        const berkas = f.file.files[0];
        if (!berkas) return;
        if (berkas.size > 2 * 1024 * 1024) {
            alert('Ukuran gambar maksimal 2 MB.');
            f.file.value = '';
            return;
        }
        f.nfile.textContent = berkas.name;
        f.prev.src = URL.createObjectURL(berkas);
    });

    <?php if ($form_data !== null && $form_error !== ''): ?>
    // validasi server gagal: buka lagi popup dengan isian sebelumnya
    buka(<?= json_encode($form_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($form_error, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
    <?php endif; ?>
})();
</script>
</body>
</html>