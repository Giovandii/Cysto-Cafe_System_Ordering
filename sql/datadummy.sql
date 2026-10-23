-- =========================================================================
-- DATA DUMMY SISTEM KAFE (PBL KELOMPOK 5)
-- =========================================================================

-- 1. DATA PENGGUNA (Admin & Kasir)
-- Password bisa pakai teks biasa untuk testing awal (admin123 / kasir123)
INSERT INTO pengguna (username, password, role) VALUES
('admin', 'admin123', 'admin'),
('kasir1', 'kasir123', 'kasir'),
('dina', 'kasir123', 'kasir')
ON CONFLICT (username) DO NOTHING;


-- 2. DATA MASTER MENU
INSERT INTO menu (id_menu, nama, kategori, harga, stok, gambar) VALUES
(1, 'French Fries', 'snack', 15000.00, 28, 'french-fries.png'),
(2, 'Nasi Goreng Spesial', 'food', 18000.00, 21, 'nasi-goreng.png'),
(3, 'Ice Chocolate', 'drink', 12000.00, 38, 'ice-chocolate.png'),
(4, 'Salted Caramel', 'drink', 18000.00, 67, 'salted-caramel.png'),
(5, 'Potato Wedges', 'snack', 16000.00, 25, 'potato-wedges.png'),
(6, 'Chicken Nugget', 'snack', 15000.00, 15, 'chicken-nugget.png'),
(7, 'Iced Tea', 'drink', 5000.00, 50, 'iced-tea.png')
ON CONFLICT (id_menu) DO UPDATE 
SET nama = EXCLUDED.nama, kategori = EXCLUDED.kategori, harga = EXCLUDED.harga, stok = EXCLUDED.stok;


-- 3. DATA PESANAN (Tampil di Dashboard Urus Pesanan & Riwayat)
INSERT INTO pesanan (id_pesanan, waktu_pesan, nama_pelanggan, id_meja, total, status) VALUES
('ORD-067A', '2026-08-26 14:45:47', 'Budi Santoso', 3, 45000.00, 'selesai'),
('ORD-067B', '2026-08-26 15:12:03', 'Citra Kirana', 1, 30000.00, 'diproses'),
('ORD-067C', '2026-08-26 16:15:11', 'Dimas Anggara', 5, 18000.00, 'menunggu_pembayaran'),
('ORD-055C', '2026-08-26 12:20:15', 'Rian Hidayat', 2, 45000.00, 'selesai'),
('ORD-053B', '2026-08-26 11:05:00', 'Ahmad Dani', 4, 15000.00, 'batal')
ON CONFLICT (id_pesanan) DO NOTHING;


-- 4. DATA RINCIAN DETAIL PESANAN
-- Menghubungkan pesanan dengan menu yang dipesan (Persis isi rincian mockup Figma)
INSERT INTO detail_pesanan (id_pesanan, id_menu, jumlah, harga_satuan, subtotal, catatan) VALUES
-- Rincian untuk ORD-067A (Total: 45.000)
('ORD-067A', 1, 1, 15000.00, 15000.00, 'Saus sambal dipisah'),
('ORD-067A', 2, 1, 18000.00, 18000.00, 'Pedas sedang'),
('ORD-067A', 3, 1, 12000.00, 12000.00, 'Less ice'),

-- Rincian untuk ORD-067B (Total: 30.000)
('ORD-067B', 1, 2, 15000.00, 30000.00, NULL),

-- Rincian untuk ORD-067C (Total: 18.000)
('ORD-067C', 2, 1, 18000.00, 18000.00, 'Jangan pakai telur'),

-- Rincian untuk ORD-055C (Total: 45.000)
('ORD-055C', 4, 1, 18000.00, 18000.00, NULL),
('ORD-055C', 5, 1, 16000.00, 16000.00, NULL),
('ORD-055C', 7, 1, 5000.00, 5000.00, 'Manis sedang'),
('ORD-055C', 3, 1, 6000.00, 6000.00, NULL),

-- Rincian untuk ORD-053B (Batal)
('ORD-053B', 1, 1, 15000.00, 15000.00, NULL);


-- 5. DATA TRANSAKSI PEMBAYARAN KASIR
-- Hanya untuk pesanan yang statusnya sudah lunas / selesai
INSERT INTO transaksi (id_transaksi, id_pesanan, waktu_transaksi, total_bayar, metode, terbayar, kembali, kasir_username) VALUES
('TRX-001', 'ORD-067A', '2026-08-26 14:47:00', 45000.00, 'QRIS', 45000.00, 0.00, 'dina'),
('TRX-002', 'ORD-055C', '2026-08-26 12:25:30', 45000.00, 'Cash', 50000.00, 5000.00, 'kasir1')
ON CONFLICT (id_transaksi) DO NOTHING;