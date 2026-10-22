-- TABEL PESANAN (Murni dibuat dari pemesanan meja pelanggan)
CREATE TABLE IF NOT EXISTS pesanan (
    id_pesanan VARCHAR(36) PRIMARY KEY,
    waktu_pesan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    nama_pelanggan VARCHAR(100) NOT NULL,
    id_meja INT NOT NULL,
    total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    status VARCHAR(50) NOT NULL DEFAULT 'menunggu_pembayaran' 
    -- Status: menunggu_pembayaran, diproses, selesai, batal
);

INSERT INTO pesanan (id_pesanan, waktu_pesan, nama_pelanggan, id_meja, total, status) VALUES
('ORD-067A', '2026-08-26 14:45:47', 'Budi Santoso', 3, 45000.00, 'selesai'),
('ORD-067B', '2026-08-26 15:12:03', 'Citra Kirana', 1, 30000.00, 'diproses'),
('ORD-067C', '2026-08-26 16:15:11', 'Dimas Anggara', 5, 18000.00, 'menunggu_pembayaran'),
('ORD-055C', '2026-08-26 12:20:15', 'Rian Hidayat', 2, 45000.00, 'selesai'),
('ORD-053B', '2026-08-26 11:05:00', 'Ahmad Dani', 4, 15000.00, 'batal')
ON CONFLICT (id_pesanan) DO NOTHING;