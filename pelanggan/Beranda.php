<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Pelanggan - Cafe</title>
    <!-- Hubungkan ke CSS di assets -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="menu-page">

    <div class="mobile-container menu-container">
        
        <!-- 1. Header: Welcome & Ikon Kopi -->
        <header class="menu-header">
            <div class="header-left">
                <h1 class="welcome-title">WELCOME</h1>
                <p class="welcome-sub">You Can Order Here</p>
            </div>
            <div class="header-right">
                <div class="coffee-icon">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 2v2"></path>
                        <path d="M11 1v3"></path>
                        <path d="M15 2v2"></path>
                        <path d="M4 8h12v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8z"></path>
                        <path d="M16 9h1.5a2.5 2.5 0 0 1 0 5H16"></path>
                    </svg>
                </div>
            </div>
        </header>

        <!-- 2. Navigasi Kategori (Food, Drink, Snack) -->
        <nav class="category-nav">
            <button type="button" class="category-btn active">FOOD</button>
            <button type="button" class="category-btn">DRINK</button>
            <button type="button" class="category-btn">SNACK</button>
        </nav>

        <!-- 3. Grid Daftar Menu (3 Kolom) -->
        <main class="menu-grid-catalog">
            
            <!-- Item 1 -->
            <article class="menu-item-card">
                <figure class="menu-img-wrap">
                    <img src="../assets/images/fried-rice.png" alt="Fried Rice" class="menu-img">
                </figure>
                <h3 class="item-name">Fried Rice</h3>
                <span class="item-price">Rp. 12.000</span>
            </article>

            <!-- Item 2 -->
            <article class="menu-item-card">
                <figure class="menu-img-wrap">
                    <img src="../assets/images/iced-tea.png" alt="Iced Tea" class="menu-img">
                </figure>
                <h3 class="item-name">Iced Tea</h3>
                <span class="item-price">Rp. 5.000</span>
            </article>

            <!-- Item 3 -->
            <article class="menu-item-card">
                <figure class="menu-img-wrap">
                    <img src="../assets/images/french-fries.png" alt="French Fries" class="menu-img">
                </figure>
                <h3 class="item-name">French Fries</h3>
                <span class="item-price">Rp. 8.000</span>
            </article>

            <!-- Item 4 (Baris 2) -->
            <article class="menu-item-card">
                <figure class="menu-img-wrap">
                    <img src="../assets/images/fried-rice.png" alt="Fried Rice" class="menu-img">
                </figure>
                <h3 class="item-name">Fried Rice</h3>
                <span class="item-price">Rp. 12.000</span>
            </article>

            <!-- Item 5 -->
            <article class="menu-item-card">
                <figure class="menu-img-wrap">
                    <img src="../assets/images/iced-tea.png" alt="Iced Tea" class="menu-img">
                </figure>
                <h3 class="item-name">Iced Tea</h3>
                <span class="item-price">Rp. 5.000</span>
            </article>

            <!-- Item 6 -->
            <article class="menu-item-card">
                <figure class="menu-img-wrap">
                    <img src="../assets/images/french-fries.png" alt="French Fries" class="menu-img">
                </figure>
                <h3 class="item-name">French Fries</h3>
                <span class="item-price">Rp. 8.000</span>
            </article>

            <!-- Item 7 (Baris 3) -->
            <article class="menu-item-card">
                <figure class="menu-img-wrap">
                    <img src="../assets/images/fried-rice.png" alt="Fried Rice" class="menu-img">
                </figure>
                <h3 class="item-name">Fried Rice</h3>
                <span class="item-price">Rp. 12.000</span>
            </article>

            <!-- Item 8 -->
            <article class="menu-item-card">
                <figure class="menu-img-wrap">
                    <img src="../assets/images/iced-tea.png" alt="Iced Tea" class="menu-img">
                </figure>
                <h3 class="item-name">Iced Tea</h3>
                <span class="item-price">Rp. 5.000</span>
            </article>

            <!-- Item 9 -->
            <article class="menu-item-card">
                <figure class="menu-img-wrap">
                    <img src="../assets/images/french-fries.png" alt="French Fries" class="menu-img">
                </figure>
                <h3 class="item-name">French Fries</h3>
                <span class="item-price">Rp. 8.000</span>
            </article>

            <!-- Item 10 (Baris 4) -->
            <article class="menu-item-card">
                <figure class="menu-img-wrap">
                    <img src="../assets/images/fried-rice.png" alt="Fried Rice" class="menu-img">
                </figure>
                <h3 class="item-name">Fried Rice</h3>
                <span class="item-price">Rp. 12.000</span>
            </article>

            <!-- Item 11 -->
            <article class="menu-item-card">
                <figure class="menu-img-wrap">
                    <img src="../assets/images/iced-tea.png" alt="Iced Tea" class="menu-img">
                </figure>
                <h3 class="item-name">Iced Tea</h3>
                <span class="item-price">Rp. 5.000</span>
            </article>

            <!-- Item 12 -->
            <article class="menu-item-card">
                <figure class="menu-img-wrap">
                    <img src="../assets/images/french-fries.png" alt="French Fries" class="menu-img">
                </figure>
                <h3 class="item-name">French Fries</h3>
                <span class="item-price">Rp. 8.000</span>
            </article>

        </main>

        <!-- 4. Footer Bar Bawah (Cek Status, Keranjang, Check Out) -->
        <footer class="bottom-bar">
            <!-- Tombol Kiri: Cek Status Pesanan -->
            <div class="bottom-left">
                <a href="status_pesanan.php" class="btn-status">Cek Status Pesanan</a>
            </div>

            <!-- Tombol Tengah: Keranjang dengan Badge Angka -->
            <div class="bottom-center">
                <button type="button" class="btn-cart-toggle">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="8" cy="21" r="1"></circle>
                        <circle cx="19" cy="21" r="1"></circle>
                        <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>
                    </svg>
                    <!-- Badge Notifikasi Jumlah Item -->
                    <span class="cart-badge">1</span>
                </button>
            </div>

            <!-- Tombol Kanan: Check Out -->
            <div class="bottom-right">
                <a href="checkout.php" class="btn-checkout">CHECK OUT</a>
            </div>
        </footer>

    </div>

</body>
</html>