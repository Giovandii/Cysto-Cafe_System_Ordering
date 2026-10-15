-- 3. TABEL PESANAN_TRANSAKSI (Transaksi Pemesanan)
CREATE TABLE IF NOT EXISTS pesanan_transaksi (
    id_pesanan VARCHAR(36) PRIMARY KEY,
    waktu_pesan TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Lebih baik TIMESTAMP dibanding DATE agar jam tercatat
    nama_pelanggan VARCHAR(100) NOT NULL,
    id_meja INT NOT NULL,
    qr_pesanan VARCHAR(255),
    metode VARCHAR(255),
    status VARCHAR(255) NOT NULL DEFAULT 'menunggu_pembayaran',
    total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    terbayar DECIMAL(12, 2),
    kembali DECIMAL(12, 2),
    penggunausername VARCHAR(50),
    CONSTRAINT fk_pesanan_pengguna FOREIGN KEY (penggunausername) 
        REFERENCES pengguna(username) ON UPDATE CASCADE ON DELETE SET NULL
);