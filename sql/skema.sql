CREATE TABLE IF NOT EXISTS menu (
    id_menu SERIAL PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    kategori VARCHAR(50) NOT NULL DEFAULT 'food', -- Nilai: 'food', 'drink', 'snack'
    harga DECIMAL(12, 2) NOT NULL,
    stok INT NOT NULL DEFAULT 0,
    gambar VARCHAR(255) DEFAULT 'default-menu.png',
    deskripsi text default ''
);

--TABEL PENGGUNA (Admin, Kasir, Owner)
CREATE TABLE IF NOT EXISTS pengguna (
    username VARCHAR(50) PRIMARY KEY,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS pesanan (
    id_pesanan VARCHAR(36) PRIMARY KEY,
    waktu_pesan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_meja INT NOT NULL,
    total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    status VARCHAR(50) NOT NULL DEFAULT 'menunggu_pembayaran' 
    -- Status: menunggu_pembayaran, diproses, selesai, batal
);

CREATE TABLE IF NOT EXISTS detail_pesanan (
    id_detail BIGSERIAL PRIMARY KEY,
    id_menu INT NOT NULL,
    jumlah INT NOT NULL,
    harga_satuan DECIMAL(12, 2) NOT NULL,
    subtotal DECIMAL(12, 2) NOT NULL,
    catatan VARCHAR(255),
    id_pesanan VARCHAR(36) NOT NULL,
    CONSTRAINT fk_detail_menu FOREIGN KEY (id_menu) 
        REFERENCES menu(id_menu) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_detail_pesanan FOREIGN KEY (id_pesanan) 
        REFERENCES pesanan(id_pesanan) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS transaksi (
    id_transaksi VARCHAR(36) PRIMARY KEY,
    id_pesanan VARCHAR(36) NOT NULL UNIQUE, 
    waktu_transaksi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_bayar DECIMAL(12, 2) NOT NULL,
    metode VARCHAR(50) NOT NULL,
    terbayar DECIMAL(12, 2) NOT NULL,
    kembali DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    kasir_username VARCHAR(50),
    CONSTRAINT fk_transaksi_pesanan FOREIGN KEY (id_pesanan) 
        REFERENCES pesanan(id_pesanan) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_transaksi_kasir FOREIGN KEY (kasir_username) 
        REFERENCES pengguna(username) ON UPDATE CASCADE ON DELETE SET NULL
);