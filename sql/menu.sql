-- 2. TABEL MENU (Master Item Makanan & Minuman)
-- Ingat: detail_pesananid_detail SUDAH DIHAPUS karena itu cacat relasi!
CREATE TABLE IF NOT EXISTS menu (
    id_menu SERIAL PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    harga DECIMAL(12, 2) NOT NULL,
    stok INT NOT NULL DEFAULT 0
);