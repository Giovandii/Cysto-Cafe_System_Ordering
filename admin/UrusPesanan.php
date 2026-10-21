<?php
session_start();
// Proteksi login
if (!isset($_SESSION['user_id'])) {
    header('Location: LoginForAdmin.php');
    exit;
}

$active_menu = 'urus_pesanan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urus Pesanan - Cafe Menu</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-body">

    <div class="dashboard-layout">
        <!-- 1. Panggil Sidebar Modular -->
        <?php include __DIR__ . '/../includes/SideBar.php'; ?>

        <!-- 2. Konten Utama Halaman -->
        <main class="dashboard-content">
            <div class="content-header">
                <h1 class="page-title">Urus Pesanan</h1>
                <p class="page-subtitle">Kelola pesanan pelanggan dengan mudah</p>
            </div>

            <!-- Panel Putih Utama -->
            <div class="order-main-panel">
                
                <!-- Filter & Search Bar -->
                <div class="filter-bar">
                    <div class="search-group">
                        <div class="search-input-box">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" placeholder="Cari pesanan (ID/meja/nama)">
                        </div>
                        <div class="select-search-by">
                            <span>Cari Berdasarkan</span>
                            <select>
                                <option value="id">ID</option>
                                <option value="meja">Meja</option>
                                <option value="nama">Nama</option>
                            </select>
                        </div>
                    </div>

                    <div class="time-filter-box">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                        </svg>
                        <span>Filter Waktu:</span>
                        <select>
                            <option value="hari_ini">Hari ini</option>
                            <option value="minggu_ini">Minggu ini</option>
                            <option value="bulan_ini">Bulan ini</option>
                        </select>
                    </div>
                </div>

                <!-- Tabel Riwayat Pesanan Masuk -->
                <div class="table-responsive">
                    <table class="order-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Waktu Pesan</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Baris 1: Selesai -->
                            <tr>
                                <td class="fw-bold">#067A</td>
                                <td>26-08-2026, 14:45:47</td>
                                <td>QRIS</td>
                                <td><span class="badge badge-success">Selesai</span></td>
                                <td class="action-btns">
                                    <button class="btn-action btn-detail"><i class="icon-eye"></i> Detail</button>
                                    <button class="btn-action btn-edit"><i class="icon-edit"></i> Edit</button>
                                    <button class="btn-action btn-delete"><i class="icon-trash"></i> Hapus</button>
                                </td>
                            </tr>
                            <!-- Baris 2: Diproses -->
                            <tr>
                                <td class="fw-bold">#067B</td>
                                <td>26-08-2026, 15:12:03</td>
                                <td>QRIS</td>
                                <td><span class="badge badge-warning">Diproses</span></td>
                                <td class="action-btns">
                                    <button class="btn-action btn-detail">Detail</button>
                                    <button class="btn-action btn-edit">Edit</button>
                                    <button class="btn-action btn-delete">Hapus</button>
                                </td>
                            </tr>
                            <!-- Baris 3: Menunggu -->
                            <tr>
                                <td class="fw-bold">#067C</td>
                                <td>26-08-2026, 16:15:11</td>
                                <td>Cash</td>
                                <td><span class="badge badge-danger">Menunggu</span></td>
                                <td class="action-btns">
                                    <button class="btn-action btn-detail">Detail</button>
                                    <button class="btn-action btn-edit">Edit</button>
                                    <button class="btn-action btn-delete">Hapus</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Box Detail / Edit Order Bawah -->
                <div class="detail-edit-card">
                    <div class="detail-header">
                        Detail/Edit Order: ID #067A
                    </div>
                    <div class="detail-body">
                        <!-- Sisi Kiri: Tabel Menu yang Dipesan -->
                        <div class="detail-left">
                            <table class="item-detail-table">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Nama Item</th>
                                        <th>Jumlah</th>
                                        <th>Harga</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>1</td>
                                        <td>French Fries</td>
                                        <td>1</td>
                                        <td>Rp15.000</td>
                                        <td>Rp15.000</td>
                                    </tr>
                                    <tr>
                                        <td>2</td>
                                        <td>Nasi Goreng</td>
                                        <td>1</td>
                                        <td>Rp18.000</td>
                                        <td>Rp18.000</td>
                                    </tr>
                                    <tr>
                                        <td>3</td>
                                        <td>Ice Chocolate</td>
                                        <td>1</td>
                                        <td>Rp12.000</td>
                                        <td>Rp12.000</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="total-bar">
                                Total Bayar : Rp45.000
                            </div>
                        </div>

                        <!-- Sisi Kanan: Panel Konfirmasi Kasir -->
                        <div class="detail-right">
                            <label class="form-label">Tipe Pembayaran</label>
                            <div class="payment-toggle">
                                <button type="button" class="btn-pay active">Cash</button>
                                <button type="button" class="btn-pay">QRIS</button>
                            </div>

                            <label class="form-label">ID Pembayaran</label>
                            <input type="text" class="input-id-payment" placeholder="">

                            <label class="form-label">Status</label>
                            <div class="status-toggle">
                                <button type="button" class="btn-status-pick danger active">Menunggu</button>
                                <button type="button" class="btn-status-pick warning">Diproses</button>
                                <button type="button" class="btn-status-pick success">Selesai</button>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn-cancel">Batal</button>
                                <button type="submit" class="btn-save">Simpan</button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

</body>
</html>