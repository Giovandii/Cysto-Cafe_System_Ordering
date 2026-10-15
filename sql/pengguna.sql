-- 1. TABEL PENGGUNA (Admin, Kasir, Owner)
CREATE TABLE IF NOT EXISTS pengguna (
    username VARCHAR(50) PRIMARY KEY,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(255) NOT NULL
);