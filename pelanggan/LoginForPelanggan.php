<?php
$page_title = "Login Pelanggan - Cafe Menu";
require_once __DIR__ . '/../includes/koneksi.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <!-- CSS Terpisah di Folder Assets -->
    <link rel="stylesheet" href="/assets/css/style.css">
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

        <!-- Judul Utama Halaman -->
        <h1 class="main-title">Cafe Menu</h1>
        <p class="main-subtitle">Pilih cara masuk sesuai peranmu</p>

        <!-- Kartu Form Login Pelanggan -->
        <main class="auth-card">
            <h2 class="card-title">Login Pelanggan</h2>
            <p class="card-subtitle">Silakan isi data untuk mulai memesan</p>

            <form action="pelanggan/menu.php" method="POST" class="auth-form">
                <!-- 1. Input Nama Anda -->
                <div class="input-box">
                    <span class="input-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="7" r="4"></circle>
                            <path d="M5.5 21a6.5 6.5 0 0 1 13 0"></path>
                        </svg>
                    </span>
                    <input type="text" name="nama_pelanggan" class="form-input" placeholder="Nama Anda" required autocomplete="off">
                </div>

                <!-- 2. Dropdown Nomor Meja -->
                <div class="select-wrapper">
                    <select name="id_meja" class="form-select" required>
                        <option value="" disabled selected>Pilih Nomor Meja</option>
                        <option value="1">Meja 01</option>
                        <option value="2">Meja 02</option>
                        <option value="3">Meja 03</option>
                        <option value="4">Meja 04</option>
                        <option value="5">Meja 05</option>
                        <option value="6">Meja 06</option>
                        <option value="7">Meja 07</option>
                        <option value="8">Meja 08</option>
                    </select>
                    <span class="select-arrow">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m6 9 6 6 6-6"></path>
                        </svg>
                    </span>
                </div>

                <!-- 3. Tombol Submit Lihat Menu -->
                <button type="submit" class="btn-submit">
                    <span>Lihat Menu</span>
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
