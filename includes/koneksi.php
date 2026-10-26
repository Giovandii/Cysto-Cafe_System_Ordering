<?php
$config = require __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        "pgsql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']}",
        $config['db_user'],
        $config['db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// PROSES UPDATE STATUS PESANAN OLEH KASIR/ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order') {
    $id_pesanan    = $_POST['id_pesanan'] ?? '';
    $status_baru   = $_POST['status'] ?? 'menunggu_pembayaran';
    $metode_bayar  = $_POST['metode'] ?? 'Cash';
    $kasir_user    = $_SESSION['username'] ?? 'kasir1';

    if (!empty($id_pesanan)) {
        // 1. Update status di tabel pesanan
        $stmt_up = $pdo->prepare("UPDATE pesanan SET status = :status WHERE id_pesanan = :id");
        $stmt_up->execute([':status' => $status_baru, ':id' => $id_pesanan]);

        // 2. Jika status berubah jadi 'selesai', catat/update ke tabel transaksi
        if ($status_baru === 'selesai') {
            // Cek total bayar dari pesanan
            $stmt_cek = $pdo->prepare("SELECT total FROM pesanan WHERE id_pesanan = :id");
            $stmt_cek->execute([':id' => $id_pesanan]);
            $total_bayar = $stmt_cek->fetchColumn() ?: 0;

            $id_trx = 'TRX-' . time();
            $stmt_trx = $pdo->prepare("
                INSERT INTO transaksi (id_transaksi, id_pesanan, total_bayar, metode, terbayar, kembali, kasir_username)
                VALUES (:id_trx, :id_pesanan, :total, :metode, :total, 0, :kasir)
                ON CONFLICT (id_pesanan) DO UPDATE 
                SET metode = EXCLUDED.metode, total_bayar = EXCLUDED.total_bayar, kasir_username = EXCLUDED.kasir_username
            ");
            $stmt_trx->execute([
                ':id_trx'     => $id_trx,
                ':id_pesanan' => $id_pesanan,
                ':total'      => $total_bayar,
                ':metode'     => $metode_bayar,
                ':kasir'      => $kasir_user
            ]);
        }

        header("Location: UrusPesanan.php?order_id=" . urlencode($id_pesanan) . "&pesan=sukses");
        exit;
    }
}
