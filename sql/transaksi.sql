-- TABEL TRANSAKSI (Mencatat pembayaran yang diselesaikan di kasir)
CREATE TABLE IF NOT EXISTS transaksi (
    id_transaksi VARCHAR(36) PRIMARY KEY,
    id_pesanan VARCHAR(36) NOT NULL UNIQUE, -- 1 pesanan punya 1 transaksi pembayaran
    waktu_transaksi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_bayar DECIMAL(12, 2) NOT NULL,
    metode VARCHAR(50) NOT NULL, -- tunai, qris, debit, transfer
    terbayar DECIMAL(12, 2) NOT NULL,
    kembali DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    kasir_username VARCHAR(50),
    CONSTRAINT fk_transaksi_pesanan FOREIGN KEY (id_pesanan) 
        REFERENCES pesanan(id_pesanan) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_transaksi_kasir FOREIGN KEY (kasir_username) 
        REFERENCES pengguna(username) ON UPDATE CASCADE ON DELETE SET NULL
);