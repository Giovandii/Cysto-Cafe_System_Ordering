-- TABEL PESANAN (Murni dibuat dari pemesanan meja pelanggan)
CREATE TABLE IF NOT EXISTS pesanan (
    id_pesanan VARCHAR(36) PRIMARY KEY,
    waktu_pesan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    nama_pelanggan VARCHAR(100) NOT NULL,
    id_meja INT NOT NULL,
    qr_pesanan VARCHAR(255),
    total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    status VARCHAR(50) NOT NULL DEFAULT 'menunggu_pembayaran' 
    -- Status: menunggu_pembayaran, diproses, selesai, batal
);