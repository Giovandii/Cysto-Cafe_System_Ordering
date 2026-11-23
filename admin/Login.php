<?php
// Mulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hubungkan dependensi proyek
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

// Tentukan peran yang diminta dari parameter URL (admin atau kasir)
$role_param = strtolower(trim($_POST['role'] ?? $_GET['role'] ?? 'kasir'));
if ($role_param !== 'admin' && $role_param !== 'kasir') {
    $role_param = 'kasir';
}
if ($role_param === 'kasir') {
    $judul_login = 'Login Kasir';
    $placeholder_user = 'Username Kasir';
    $btn_text = 'Masuk Kasir';
} else {
    $judul_login = 'Login Admin';
    $placeholder_user = 'Username Admin';
    $btn_text = 'Masuk Admin';
}

$error = '';

// Proses saat tombol submit login ditekan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi token keamanan CSRF
    csrf_verify();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan kata sandi wajib diisi!';
    } else {
        try {
            // Ambil data user dari database berdasarkan username
            $stmt = $pdo->prepare('SELECT username, password, role FROM pengguna WHERE username = :username LIMIT 1');
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Validasi:
            // 1. User ditemukan
            // 2. Role adalah admin atau kasir
            // 3. Password cocok (mendukung hash bcrypt atau plain-text untuk data dummy awal)
            if (
                $user &&
                in_array(strtolower($user['role']), ['admin', 'kasir']) &&
                (password_verify($password, $user['password']) || $password === $user['password'])
            ) {
                // Regenerasi session ID demi keamanan
                session_regenerate_id(true);

                $_SESSION['user_id']  = $user['username'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = strtolower($user['role']); // 'admin' atau 'kasir'

                // Redirect sukses masuk ke dashboard pengelolaan pesanan
                header('Location: UrusPesanan.php');
                exit;
            } else {
                $error = 'Username atau kata sandi tidak valid!';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem saat menghubungi database.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Cafe Menu</title>
    <!-- Menggunakan CSS Global di assets -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Gaya khusus pesan alert error */
        .alert-error {
            width: 100%;
            background-color: #FDE8E8;
            color: #9B1C1C;
            border: 1px solid #F8B4B4;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
            text-align: center;
            font-family: sans-serif;
        }
    </style>
</head>
<body>

    <div class="mobile-container">
        <!-- Dekorasi Cangkir Kopi Atas -->
        <header class="header-deco">
            <div class="deco-line"></div>
            <div class="coffee-icon">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M7 2v2"></path>
                    <path d="M11 1v3"></path>
                    <path d="M15 2v2"></path>
                    <path d="M4 8h12v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8z"></path>
                    <path d="M16 9h1.5a2.5 2.5 0 0 1 0 5H16"></path>
                </svg>
            </div>
            <div class="deco-line"></div>
        </header>

        <!-- Judul Header -->
        <h1 class="main-title">Cafe Menu</h1>
        <p class="main-subtitle">Pilih cara masuk sesuai peranmu</p>

        <!-- Kartu Putih Melayang Form Login -->
        <main class="auth-card">
            <h2 class="card-title">
    Login <?= in_array(strtolower($_GET['role'] ?? ''), ['kasir', 'admin']) ? ucfirst(strtolower($_GET['role'])) : 'Petugas' ?>
</h2>
            <p class="card-subtitle">Masukkan akun untuk akses manajemen</p>

            <!-- Tampilkan Pesan Error Jika Ada -->
            <?php if (!empty($error)): ?>
                <div class="alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-form">
                <!-- Token CSRF Wajib -->
                <?= csrf_field() ?>

                <!-- Input Username -->
                <div class="input-box">
                    <span class="input-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="7" r="4"></circle>
                            <path d="M5.5 21a6.5 6.5 0 0 1 13 0"></path>
                        </svg>
                    </span>
                    <input type="text" name="username" class="form-input" placeholder="Username Admin" value="<?= isset($username) ? e($username) : '' ?>" required autocomplete="username">
                </div>

                <!-- Input Password -->
                <div class="input-box">
                    <span class="input-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </span>
                    <input type="password" name="password" class="form-input" placeholder="Kata Sandi" required autocomplete="current-password">
                </div>

                <!-- Tombol Masuk -->
                <button type="submit" class="btn-submit">
                    <span>Masuk Admin</span>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14"></path>
                        <path d="m13 6 6 6-6 6"></path>
                    </svg>
                </button>
            </form>
        </main>
    </div>

</body>
</html>