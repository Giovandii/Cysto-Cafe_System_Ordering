<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: LoginForAdmin.php');
    exit;
}

if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: UrusPesanan.php');
    exit;
}

$active_menu = 'kelola_kasir';

require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

$errors = [];
$sukses = '';

/* ========================== SIMPAN (TAMBAH / EDIT) ========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    csrf_verify();

    $role     = trim($_POST['role'] ?? 'kasir');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $is_edit  = isset($_POST['id_lama']) && trim($_POST['id_lama']) !== '';
    $id_lama  = trim($_POST['id_lama'] ?? '');

    $role = strtolower($role);

    if (!in_array($role, ['admin', 'kasir', 'owner'], true)) {
        $errors[] = "Role '{$role}' tidak dikenal.";
    }

    if ($username === '') {
        $errors[] = 'Username wajib diisi.';
    } elseif (mb_strlen($username) > 50) {
        $errors[] = 'Username maksimal 50 karakter.';
    }

    if ($password === '' && !$is_edit) {
        $errors[] = 'Password wajib diisi untuk pengguna baru.';
    } elseif (mb_strlen($password) > 255) {
        $errors[] = 'Password maksimal 255 karakter.';
    }

    if (!$errors) {
        try {
            $cek = $pdo->prepare("SELECT username, role FROM pengguna WHERE LOWER(username) = LOWER(:u)");
            $cek->execute([':u' => $username]);
            $existing = $cek->fetch(PDO::FETCH_ASSOC);

            if ($is_edit) {
                $lama = $pdo->prepare("SELECT username FROM pengguna WHERE username = :u");
                $lama->execute([':u' => $id_lama]);

                if (!$lama->fetchColumn()) {
                    $errors[] = "Pengguna '{$id_lama}' tidak ditemukan.";
                } elseif ($existing && strtolower($existing['username']) !== strtolower($id_lama)) {
                    $errors[] = "Username '{$username}' sudah dipakai akun lain.";
                } else {
                    $hash = ($password === '') ? null : password_hash($password, PASSWORD_DEFAULT);

                    if ($hash === null) {
                        $pdo->prepare("UPDATE pengguna SET username = :u, role = :r WHERE username = :id")
                            ->execute([':u' => $username, ':r' => $role, ':id' => $id_lama]);
                    } else {
                        $pdo->prepare("UPDATE pengguna SET username = :u, role = :r, password = :p WHERE username = :id")
                            ->execute([':u' => $username, ':r' => $role, ':p' => $hash, ':id' => $id_lama]);
                    }

                    $sukses = "Pengguna '{$username}' berhasil diperbarui.";
                }
            } else {
                if ($existing) {
                    $errors[] = "Username '{$username}' sudah dipakai.";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("INSERT INTO pengguna (username, password, role) VALUES (:u, :p, :r)")
                        ->execute([':u' => $username, ':p' => $hash, ':r' => $role]);

                    $sukses = "Pengguna '{$username}' berhasil ditambahkan.";
                }
            }
        } catch (Throwable $ex) {
            $errors[] = 'Gagal menyimpan data pengguna ke database.';
        }
    }
}

/* ================================== HAPUS ================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    csrf_verify();

    $target = trim($_POST['username'] ?? '');

    if ($target === '') {
        $errors[] = 'Pengguna yang akan dihapus tidak ditentukan.';
    } elseif (strtolower($target) === strtolower((string) ($_SESSION['username'] ?? ''))) {
        $errors[] = 'Tidak bisa menghapus akun yang sedang masuk.';
    } else {
        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE transaksi SET kasir_username = NULL WHERE kasir_username = :u")
                ->execute([':u' => $target]);

            $hapus = $pdo->prepare("DELETE FROM pengguna WHERE username = :u");
            $hapus->execute([':u' => $target]);

            $pdo->commit();

            if ($hapus->rowCount() === 0) {
                $errors[] = "Pengguna '{$target}' tidak ditemukan.";
            } else {
                $sukses = "Pengguna '{$target}' berhasil dihapus.";
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Gagal menghapus pengguna. Coba lagi.';
        }
    }
}

/* ============================== DATA TABEL ============================== */
$rows       = [];
$cari       = trim($_GET['q'] ?? '');
$kolom      = ($_GET['by'] ?? 'pengguna') === 'role' ? 'role' : 'pengguna';
$list_count = 0;

$sql = "SELECT username, role FROM pengguna WHERE 1=1";
$params = [];

if ($cari !== '') {
    if ($kolom === 'role') {
        $sql .= " AND LOWER(role) LIKE :q";
    } else {
        $sql .= " AND username ILIKE :q";
    }
    $params[':q'] = '%' . strtolower($cari) . '%';
}

$sql .= " ORDER BY username ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $list_count = count($rows);
} catch (PDOException $ex) {
    $rows = [];
}

/* ==================== POPUP TAMBAH / EDIT PENGGUNA ==================== */
$mode        = $_GET['mode'] ?? 'list';
$edit_id     = trim($_GET['id'] ?? '');
$edit_row    = null;
$form_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_errors = $errors;
    $edit_row    = [
        'username' => $_POST['username'] ?? '',
        'role'     => $_POST['role'] ?? 'kasir',
    ];
    $is_edit     = isset($_POST['id_lama']) && trim($_POST['id_lama']) !== '';
    $id_lama     = trim($_POST['id_lama'] ?? '');
    $mode        = 'form';
} else {
    if ($mode === 'form' && $edit_id !== '') {
        $stmt = $pdo->prepare("SELECT username, role FROM pengguna WHERE username = :u");
        $stmt->execute([':u' => $edit_id]);
        $edit_row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $is_edit = ($mode === 'form' && $edit_row !== null);
    $id_lama = $is_edit ? $edit_row['username'] : '';
}

$modal_open  = ($mode === 'form' || ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($sukses)));
$popup_title = $is_edit ? 'Edit Kasir' : 'Tambah / Edit Kasir';

$notif = '';
if ($sukses !== '') {
    $notif = ['tipe' => 'sukses', 'teks' => $sukses];
} elseif (!empty($errors)) {
    $notif = ['tipe' => 'error', 'teks' => implode(' ', $errors)];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kasir - Cafe Menu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inria+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/kelolaKasir.css">
</head>
<body class="dashboard-body kelola-kasir-body">

    <div class="dashboard-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="dashboard-content kk-content">
            <div class="kk-head">
                <div>
                    <h1 class="kk-title">Kelola Kasir</h1>
                    <p class="kk-subtitle">Data pengguna beserta peran akun pada sistem</p>
                </div>
                <a href="?mode=form" class="kk-add-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>Tambah Kasir</span>
                </a>
            </div>

            <?php if ($notif !== ''): ?>
                <div class="kk-notif <?= $notif['tipe'] === 'sukses' ? 'ok' : 'err' ?>">
                    <?= e($notif['teks']) ?>
                </div>
            <?php endif; ?>

            <form method="GET" action="" class="kk-search">
                <span class="kk-search-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </span>
                <span class="kk-search-label">Cari Berdasarkan :</span>

                <select name="by" class="kk-select" aria-label="Kategori pencarian">
                    <option value="pengguna" <?= $kolom === 'pengguna' ? 'selected' : '' ?>>Pengguna</option>
                    <option value="role" <?= $kolom === 'role' ? 'selected' : '' ?>>Role</option>
                </select>

                <input type="text" name="q" class="kk-search-bar" placeholder="Cari.." value="<?= e($cari) ?>">

                <button type="submit" class="kk-search-btn">Cari</button>
            </form>

            <table class="kk-table">
                <thead>
                    <tr>
                        <th class="kk-col-user">Pengguna</th>
                        <th class="kk-col-role">Role</th>
                        <th class="kk-col-act">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="3" class="kk-empty">
                                <?php if ($cari !== ''): ?>
                                    Tidak ada pengguna dengan <?= $kolom === 'role' ? 'role' : 'username' ?> "<?= e($cari) ?>"<?= ' pada kolom ' . ($kolom === 'role' ? 'Role' : 'Pengguna') ?>.
                                <?php else: ?>
                                    Belum ada pengguna terdaftar. Klik "Tambah Kasir" untuk menambahkan.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e($row['username']) ?></td>
                                <td>
                                    <span class="kk-badge role-<?= e(strtolower($row['role'])) ?>">
                                        <?= e(ucfirst(strtolower($row['role']))) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="kk-act">
                                        <a href="?mode=form&amp;id=<?= urlencode($row['username']) ?>" class="kk-btn kk-btn-edit">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 20h9"></path>
                                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                            </svg>
                                            <span>Edit</span>
                                        </a>
                                        <button type="button" class="kk-btn kk-btn-del" data-hapus="<?= e($row['username']) ?>">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                                                <path d="M10 11v6"></path>
                                                <path d="M14 11v6"></path>
                                                <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
                                            </svg>
                                            <span>Hapus</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($list_count > 0): ?>
                <p class="kk-count">Menampilkan <?= $list_count ?> pengguna<?= $cari !== '' ? ' (hasil pencarian)' : '' ?>.</p>
            <?php endif; ?>
        </main>
    </div>

    <div class="kk-overlay <?= $modal_open ? 'open' : '' ?>" id="kk-overlay">
        <div class="kk-popup" role="dialog" aria-modal="true" aria-labelledby="kk-popup-title">
            <div class="kk-popup-head" id="kk-popup-title"><?= e($popup_title) ?></div>

            <form method="POST" action="" class="kk-form" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="simpan">
                <input type="hidden" name="id_lama" value="<?= e($is_edit ? $id_lama : '') ?>">

                <div class="kk-field">
                    <label class="kk-label" for="kk-user">Username</label>
                    <input type="text" id="kk-user" name="username" class="kk-input"
                           value="<?= e($is_edit ? $edit_row['username'] : '') ?>" maxlength="50"
                           placeholder="Username pengguna" required>
                </div>

                <div class="kk-field">
                    <label class="kk-label" for="kk-role">Role</label>
                    <select id="kk-role" name="role" class="kk-input kk-select-full" required>
                        <?php foreach (['kasir', 'admin', 'owner'] as $r): ?>
                            <option value="<?= e($r) ?>" <?= (($edit_row['role'] ?? 'kasir') === $r) ? 'selected' : '' ?>>
                                <?= e(ucfirst($r)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="kk-field">
                    <label class="kk-label" for="kk-pass">Password</label>
                    <input type="password" id="kk-pass" name="password" class="kk-input" maxlength="255"
                           placeholder="<?= $is_edit ? 'Biarkan kosong jika tidak diubah' : 'Password pengguna' ?>"
                           <?= $is_edit ? '' : 'required' ?>>
                    <?php if ($is_edit): ?>
                        <p class="kk-hint">Isi hanya bila ingin mengganti password.</p>
                    <?php endif; ?>
                </div>

                <div class="kk-form-foot">
                    <button type="button" class="kk-btn-cancel" data-tutup>Batal</button>
                    <button type="submit" class="kk-btn-save">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="kk-overlay" id="kk-del">
        <div class="kk-popup kk-popup-sm" role="dialog" aria-modal="true" aria-labelledby="kk-del-title">
            <div class="kk-popup-head" id="kk-del-title">Hapus Kasir</div>

            <form method="POST" action="" class="kk-form">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="username" id="kk-del-user" value="">

                <p class="kk-warn">Hapus pengguna <strong id="kk-del-nama">-</strong> dari daftar pengguna?</p>

                <div class="kk-form-foot">
                    <button type="button" class="kk-btn-cancel" data-tutup>Batal</button>
                    <button type="submit" class="kk-btn-save">Hapus</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var overlay = document.getElementById('kk-overlay');
            var delBox  = document.getElementById('kk-del');

            function tutupForm() {
                window.location.href = '?mode=list';
            }

            document.querySelectorAll('[data-hapus]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var nama = btn.getAttribute('data-hapus');
                    document.getElementById('kk-del-user').value = nama;
                    document.getElementById('kk-del-nama').textContent = nama;
                    delBox.classList.add('open');
                });
            });

            document.querySelectorAll('[data-tutup]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var box = btn.closest('.kk-overlay');
                    if (box === overlay && box.classList.contains('open')) {
                        tutupForm();
                    } else {
                        box.classList.remove('open');
                    }
                });
            });

            [overlay, delBox].forEach(function (box) {
                box.addEventListener('click', function (ev) {
                    if (ev.target !== box) {
                        return;
                    }
                    if (box === overlay && box.classList.contains('open')) {
                        tutupForm();
                    } else {
                        box.classList.remove('open');
                    }
                });
            });

            document.addEventListener('keydown', function (ev) {
                if (ev.key !== 'Escape') {
                    return;
                }
                if (overlay.classList.contains('open')) {
                    tutupForm();
                }
                delBox.classList.remove('open');
            });
        })();
    </script>

</body>
</html>