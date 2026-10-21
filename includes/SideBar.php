<?php
// Ambil role dan username dari session
$user_role     = strtolower($_SESSION['role'] ?? 'kasir');
$user_name     = $_SESSION['username'] ?? 'User';
$active_menu   = $active_menu ?? 'urus_pesanan'; // Menandai menu yang aktif
?>

<aside class="sidebar-container">
    <!-- Header Logo Cafe -->
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M7 2v2"></path>
                <path d="M11 1v3"></path>
                <path d="M15 2v2"></path>
                <path d="M4 8h12v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8z"></path>
                <path d="M16 9h1.5a2.5 2.5 0 0 1 0 5H16"></path>
            </svg>
        </div>
        <span class="brand-text">Cafe Menu</span>
    </div>

    <!-- Sapaan User (Halo, Admin / Halo, Kasir) -->
    <div class="sidebar-user">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="7" r="4"></circle>
            <path d="M5.5 21a6.5 6.5 0 0 1 13 0"></path>
        </svg>
        <span class="user-greeting">Halo, <?= ucfirst(htmlspecialchars($user_role)) ?></span>
    </div>

    <!-- Daftar Navigasi -->
    <nav class="sidebar-nav">
        <!-- 1. MENU BERSAMA (Bisa Diakses Kasir & Admin) -->
        <a href="UrusPesanan.php" class="nav-btn <?= ($active_menu === 'urus_pesanan') ? 'active' : '' ?>">
            <span class="btn-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M8 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-3"></path>
                    <path d="M8 7h8"></path><path d="M8 11h8"></path><path d="M8 15h5"></path>
                </svg>
            </span>
            <span class="btn-label">Urus Pesanan</span>
        </a>

        <a href="RiwayatPesanan.php" class="nav-btn <?= ($active_menu === 'riwayat_pesanan') ? 'active' : '' ?>">
            <span class="btn-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </span>
            <span class="btn-label">Riwayat Pesanan</span>
        </a>

        <a href="stok_item.php" class="nav-btn <?= ($active_menu === 'stok_item') ? 'active' : '' ?>">
            <span class="btn-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </span>
            <span class="btn-label">Stok Item</span>
        </a>

        <!-- 2. FITUR KHUSUS ADMIN (Hanya Muncul Jika Role = Admin) -->
        <?php if ($user_role === 'admin'): ?>
            <a href="kelola_item.php" class="nav-btn <?= ($active_menu === 'kelola_item') ? 'active' : '' ?>">
                <span class="btn-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                        <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                    </svg>
                </span>
                <span class="btn-label">Kelola Item</span>
            </a>

            <a href="kelola_kasir.php" class="nav-btn <?= ($active_menu === 'kelola_kasir') ? 'active' : '' ?>">
                <span class="btn-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </span>
                <span class="btn-label">Kelola Kasir</span>
            </a>

            <a href="pendapatan.php" class="nav-btn <?= ($active_menu === 'pendapatan') ? 'active' : '' ?>">
                <span class="btn-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"></path>
                        <path d="M4 6v12a2 2 0 0 0 2 2h14v-4"></path>
                        <circle cx="16" cy="14" r="1"></circle>
                    </svg>
                </span>
                <span class="btn-label">Pendapatan</span>
            </a>
        <?php endif; ?>

        <!-- 3. TOMBOL LOGOUT (Paling Bawah) -->
        <div class="sidebar-footer">
            <a href="../index.php" class="nav-btn logout-btn" onclick="return confirm('Yakin ingin keluar?');">
                <span class="btn-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </span>
                <span class="btn-label">LogOut</span>
            </a>
        </div>
    </nav>
</aside>