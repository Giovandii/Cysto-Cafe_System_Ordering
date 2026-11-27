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
INSERT INTO menu (id_menu, nama, harga, stok, kategori, gambar, deskripsi) VALUES
(1, 'French Fries', 15000.00, 0, 'snack', 'french-fries.png', 'Kentang goreng renyah dengan saus sambal dan mayones.'),
(2, 'Nasi Goreng Spesial', 18000.00, 0, 'food', 'nasi-goreng.png', 'Nasi goreng dengan bumbu khas, telur ceplok, udang, dan kerupuk.'),
(3, 'Ice Chocolate', 12000.00, 33, 'drink', 'ice-chocolate.png', 'Minuman cokelat es dengan rasa manis dan creamy.'),
(4, 'Salted Caramel', 18000.00, 63, 'drink', 'salted-caramel.png', 'Minuman kopi susu dengan rasa caramel manis dan sedikit asin.'),
(5, 'Potato Wedges', 16000.00, 23, 'snack', 'potato-wedges.png', 'Potongan kentang berbentuk wedges, renyah di luar dan lembut di dalam.'),
(6, 'Chicken Nugget', 15000.00, 14, 'snack', 'chicken-nugget.png', 'Nugget ayam dengan tekstur renyah disajikan bersama saus sambal.'),
(7, 'Iced Tea', 5000.00, 49, 'drink', 'iced-tea.png', 'Minuman teh dingin yang segar dan nikmat.')
ON CONFLICT (id_menu) DO UPDATE SET
    nama      = EXCLUDED.nama,
    harga     = EXCLUDED.harga,
    stok      = EXCLUDED.stok,
    kategori  = EXCLUDED.kategori,
    gambar    = EXCLUDED.gambar,
    deskripsi = EXCLUDED.deskripsi;

-- Sinkronkan sequence SERIAL agar insert menu baru berikutnya langsung ke ID 8
SELECT setval(pg_get_serial_sequence('menu', 'id_menu'), COALESCE((SELECT MAX(id_menu) FROM menu), 1));
