CREATE TABLE IF NOT EXISTS menu (
    id_menu SERIAL PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    kategori VARCHAR(50) NOT NULL DEFAULT 'food', -- Nilai: 'food', 'drink', 'snack'
    harga DECIMAL(12, 2) NOT NULL,
    stok INT NOT NULL DEFAULT 0,
    gambar VARCHAR(255) DEFAULT 'default-menu.png'
);

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