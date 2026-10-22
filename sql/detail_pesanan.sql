-- TABEL DETAIL_PESANAN (Junction Table Pesanan dan Menu)
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

INSERT INTO detail_pesanan (id_pesanan, id_menu, jumlah, harga_satuan, subtotal, catatan) VALUES
-- Rincian untuk ORD-067A (Total: 45.000)
('ORD-067A', 1, 1, 15000.00, 15000.00, 'Saus sambal dipisah'),
('ORD-067A', 2, 1, 18000.00, 18000.00, 'Pedas sedang'),
('ORD-067A', 3, 1, 12000.00, 12000.00, 'Less ice'),
