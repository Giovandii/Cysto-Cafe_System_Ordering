-- 4. TABEL DETAIL_PESANAN (Junction Table Pemesanan dan Menu)
CREATE TABLE IF NOT EXISTS detail_pesanan (
    id_detail BIGSERIAL PRIMARY KEY,
    jumlah INT NOT NULL,
    harga_satuan DECIMAL(12, 2) NOT NULL,
    subtotal DECIMAL(12, 2) NOT NULL,
    catatan VARCHAR(255),
    menuid_menu INT NOT NULL,
    pesanan_transaksiid_pesanan VARCHAR(36) NOT NULL,
    CONSTRAINT fk_detail_menu FOREIGN KEY (menuid_menu) 
        REFERENCES menu(id_menu) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_detail_pesanan FOREIGN KEY (pesanan_transaksiid_pesanan) 
        REFERENCES pesanan_transaksi(id_pesanan) ON UPDATE CASCADE ON DELETE CASCADE
);