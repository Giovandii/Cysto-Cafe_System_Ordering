CREATE TABLE IF NOT EXISTS menu (
    id_menu SERIAL PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    harga DECIMAL(12, 2) NOT NULL,
    stok INT NOT NULL DEFAULT 0,
    kategori VARCHAR(50) NOT NULL DEFAULT 'food', -- Nilai: 'food', 'drink', 'snack'
    gambar VARCHAR(255) DEFAULT 'default-menu.png'
);