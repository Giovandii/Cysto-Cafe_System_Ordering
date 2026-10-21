<?php
$page_title = "Beranda - Cafe Menu";
require_once __DIR__ . '/includes/koneksi.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <!-- Memakai CSS Global yang sama di assets/css/style.css -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="mobile-container">
        <!-- Logo / Ikon Kopi -->
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

        <h1 class="main-title">Cafe Menu</h1>
        <p class="main-subtitle">Pilih cara masuk sesuai peranmu</p>

        <!-- Navigasi Pemilihan Peran -->
        <main class="role-menu">

            <!-- 2. Kasir -->
            <a href="kasir/LoginForKasir.php?role=kasir" class="role-btn">
                <div class="btn-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-3"></path>
                        <path d="M8 7h8"></path>
                        <path d="M8 11h8"></path>
                        <path d="M8 15h5"></path>
                    </svg>
                </div>
                <span class="btn-text">Kasir</span>
                <div class="btn-arrow">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14"></path>
                        <path d="m13 6 6 6-6 6"></path>
                    </svg>
                </div>
            </a>

            <!-- 3. Admin -->
            <a href="admin/LoginForAdmin.php?role=admin" class="role-btn">
                <div class="btn-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                </div>
                <span class="btn-text">Admin</span>
                <div class="btn-arrow">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14"></path>
                        <path d="m13 6 6 6-6 6"></path>
                    </svg>
                </div>
            </a>
        </main>
    </div>

</body>
</html>
