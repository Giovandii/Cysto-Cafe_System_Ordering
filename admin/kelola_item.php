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
    'tabel'    => 'menu',
    'id'       => 'id_menu',
    'nama'     => 'nama',
    'jenis'    => 'kategori',
    'harga'    => 'harga',
    'stok'     => 'stok',
    'gambar'   => 'gambar',
    'edit_url' => 'edit_item.php',   // halaman edit, dipanggil dengan ?id=
];

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function rupiah($n) { return 'Rp' . number_format((float)$n, 0, ',', '.'); }

/* =====================================================
   Ambil data. Otomatis mengenali koneksi dari koneksi.php:
   PDO, PostgreSQL (pg_*), atau mysqli.
   Mengembalikan null bila tidak ada variabel koneksi.
   ===================================================== */
function ambil_items(array $cfg, string $filter, string $by, string $q, ?string &$error): ?array
{
    $db = null;
    foreach (['pdo', 'conn', 'db', 'koneksi', 'link'] as $nama) {
        if (!empty($GLOBALS[$nama])) { $db = $GLOBALS[$nama]; break; }
    }
    if ($db === null) return null;

    $cols = implode(', ', [
        "{$cfg['id']} AS id", "{$cfg['nama']} AS nama", "{$cfg['jenis']} AS jenis",
        "{$cfg['harga']} AS harga", "{$cfg['stok']} AS stok", "{$cfg['gambar']} AS gambar",
    ]);

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

/* ---------- Filter & pencarian ---------- */
$jenis_opsi = ['drink' => 'Drink', 'snack' => 'Snack', 'food' => 'Food'];
$filter  = strtolower($_GET['jenis'] ?? '');
$cari_by = $_GET['by'] ?? 'nama';
$keyword = trim($_GET['q'] ?? '');

if (!isset($jenis_opsi[$filter])) $filter = '';
if (!in_array($cari_by, ['nama', 'harga', 'stok'], true)) $cari_by = 'nama';

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

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
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
                                         src="../assets/images/menu/<?= e($it['gambar'] ?: 'default.png') ?>"
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
                                <a class="btn-edit" href="<?= e($cfg['edit_url']) ?>?id=<?= (int)$it['id'] ?>" aria-label="Edit <?= e($it['nama']) ?>">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>
