<?php
/**
 * Webhook Listener untuk Notifikasi Pembayaran QRIS (GoPay Merchant / Bank)
 * Dipicu oleh MacroDroid / Automation App dari HP
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';

// Tangkap data dari POST JSON maupun form-encoded / GET
$input_raw = file_get_contents('php://input');
$json_data = json_decode($input_raw, true) ?? [];

$nominal_masuk = $_POST['nominal'] ?? ($json_data['nominal'] ?? ($_GET['nominal'] ?? 0));
$keterangan    = $_POST['pesan'] ?? ($json_data['pesan'] ?? ($_GET['pesan'] ?? ''));

// Bersihkan nominal (hanya ambil angka murni)
$nominal_clean = (float) preg_replace('/[^0-9]/', '', (string)$nominal_masuk);

if ($nominal_clean <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Nominal tidak valid atau kosong'
    ]);
    exit;
}

try {
    // 1. Cari pesanan yang statusnya 'menunggu_pembayaran' dengan nominal yang cocok
    // Urutkan dari pesanan yang paling baru dibuat
    $stmt = $pdo->prepare("
        SELECT id_pesanan, id_meja, total 
        FROM pesanan 
        WHERE status = 'menunggu_pembayaran' AND total = :total
        ORDER BY waktu_pesan DESC 
        LIMIT 1
    ");
    $stmt->execute([':total' => $nominal_clean]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        $order_id = $order['id_pesanan'];
        $no_meja  = $order['id_meja'];

        $pdo->beginTransaction();

        // Update status pesanan jadi 'diproses'
        $stmt_up = $pdo->prepare("UPDATE pesanan SET status = 'diproses' WHERE id_pesanan = :id");
        $stmt_up->execute([':id' => $order_id]);

        // Catat ke transaksi
        $id_trx = 'TRX-' . strtoupper(substr(uniqid(), -4));
        $stmt_trx = $pdo->prepare("
            INSERT INTO transaksi (id_transaksi, id_pesanan, total_bayar, metode, terbayar, kembali, kasir_username)
            VALUES (:id_trx, :id_pesanan, :total, 'QRIS', :total, 0, NULL)
            ON CONFLICT (id_pesanan) DO UPDATE 
            SET metode = 'QRIS', total_bayar = EXCLUDED.total_bayar, terbayar = EXCLUDED.total_bayar, kembali = 0, kasir_username = NULL
        ");
        $stmt_trx->execute([
            ':id_trx'     => $id_trx,
            ':id_pesanan' => $order_id,
            ':total'      => $nominal_clean
        ]);

        $pdo->commit();

        // Ambil item pesanan untuk Telegram
        $stmt_items = $pdo->prepare("
            SELECT dp.jumlah, dp.catatan, m.nama 
            FROM detail_pesanan dp
            JOIN menu m ON dp.id_menu = m.id_menu
            WHERE dp.id_pesanan = :id
        ");
        $stmt_items->execute([':id' => $order_id]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        $daftar_menu = "";
        foreach ($items as $item) {
            $daftar_menu .= "• " . (int)$item['jumlah'] . "x <b>" . htmlspecialchars($item['nama']) . "</b>\n";
        }

        $total_fmt = "Rp " . number_format($nominal_clean, 0, ',', '.');
        $jam = date('H:i') . ' WIB';

        $notif = "⚡ <b>PEMBAYARAN QRIS OTOMATIS TERDETEKSI!</b> ⚡\n";
        $notif .= "━━━━━━━━━━━━━━━━━━━━\n";
        $notif .= "📦 <b>ID Pesanan</b> : <code>{$order_id}</code>\n";
        $notif .= "🧾 <b>ID Transaksi</b>: <code>{$id_trx}</code>\n";
        $notif .= "🪑 <b>Lokasi</b>     : Meja {$no_meja}\n";
        $notif .= "💰 <b>Nominal</b>    : <b>{$total_fmt}</b>\n";
        $notif .= "⏰ <b>Waktu</b>      : {$jam}\n";
        $notif .= "━━━━━━━━━━━━━━━━━━━━\n";
        $notif .= "📋 <b>Menu:</b>\n{$daftar_menu}";
        $notif .= "━━━━━━━━━━━━━━━━━━━━\n";
        $notif .= "🍳 <i>Sistem kasir otomatis melunasi pesanan. Dapur mulai memasak!</i>";

        kirim_notifikasi_telegram($notif);

        echo json_encode([
            'status' => 'success',
            'message' => "Pesanan {$order_id} berhasil dilunasi otomatis!",
            'order_id' => $order_id,
            'nominal' => $nominal_clean
        ]);
        exit;
    } else {
        // Jika ada uang masuk tapi tidak ada pesanan dengan nominal persis
        $total_fmt = "Rp " . number_format($nominal_clean, 0, ',', '.');
        $notif_unmatch = "⚠️ <b>DANA QRIS MASUK (TIDAK DITEMUKAN PESANAN COCOK)</b> ⚠️\n";
        $notif_unmatch .= "Nominal: {$total_fmt}\n";
        if (!empty($keterangan)) {
            $notif_unmatch .= "Keterangan: " . htmlspecialchars($keterangan) . "\n";
        }
        $notif_unmatch .= "Silakan cek mutasi atau konfirmasi manual di meja kasir.";
        kirim_notifikasi_telegram($notif_unmatch);

        echo json_encode([
            'status' => 'unmatched',
            'message' => 'Uang masuk tercatat, tetapi tidak ada pesanan menunggu dengan nominal tersebut'
        ]);
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit;
}
