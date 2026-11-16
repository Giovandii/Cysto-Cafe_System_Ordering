<?php
/**
 * ==============================================================================
 * HALAMAN: INSTRUKSI PEMBAYARAN & STATUS PESANAN PELANGGAN
 * Lokasi File: pelanggan/Pembayaran.php
 * Proyek     : Sistem Informasi Kafe (Cysto Cafe) - PBL
 * ==============================================================================
 * Fitur:
 * 1. Menampilkan QR Code QRIS Resmi dari Midtrans Core API (Tanpa Bank Transfer).
 * 2. Menampilkan string QRIS asli yang bisa di-scan via aplikasi m-Banking.
 * 3. Polling status otomatis ke Midtrans dan sistem internal kafe.
 * ==============================================================================
 */

session_start();

// -----------------------------------------------------------------------------
// 1. HUBUNGKAN KE DATABASE & HELPERS
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';

// -----------------------------------------------------------------------------
// 2. MENANGKAP PARAMETER DARI URL / REQUEST
// -----------------------------------------------------------------------------
$order_id = $_GET['order_id'] ?? ($_POST['order_id'] ?? '');
$metode   = strtolower($_GET['metode'] ?? 'kasir');

if (empty($order_id)) {
    die("
    <div style='font-family: serif; text-align: center; padding: 70px 20px; background-color: #F7F4EE; min-height: 100vh;'>
        <h2 style='color: #5C4033;'>Pesanan Tidak Ditemukan</h2>
        <p style='color: #7A6252; margin-top: 8px;'>Silakan lakukan pemesanan kembali dari menu kafe.</p>
        <a href='Menu.php' style='display:inline-block; margin-top:15px; color:#5C4033; font-weight:bold;'>Kembali ke Menu</a>
    </div>
    ");
}

// -----------------------------------------------------------------------------
// 3. HANDLER REQUEST AJAX (POST)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // =========================================================================
    // AKSI A: Konfirmasi Pembayaran Sukses (Simulator / Midtrans)
    // =========================================================================
    if ($_POST['action'] === 'simulasi_bayar_sukses') {
        try {
            $pdo->beginTransaction();

            $stmt_cek = $pdo->prepare("SELECT total FROM pesanan WHERE id_pesanan = :id");
            $stmt_cek->execute([':id' => $order_id]);
            $total_bayar = (float)($stmt_cek->fetchColumn() ?: 0);

            // 1. Perbarui status pesanan menjadi 'diproses' (dapur mulai memasak)
            $stmt_up = $pdo->prepare("UPDATE pesanan SET status = 'diproses' WHERE id_pesanan = :id");
            $stmt_up->execute([':id' => $order_id]);

            // 2. Catat ke tabel transaksi sebagai lunas via QRIS
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
                ':total'      => $total_bayar
            ]);

            $pdo->commit();

            echo json_encode(['status' => 'success', 'message' => 'Pembayaran QRIS berhasil dikonfirmasi!']);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }

    // =========================================================================
    // AKSI B: Polling Cek Status (Internal & Midtrans)
    // =========================================================================
    if ($_POST['action'] === 'cek_status') {
        $midtrans_id = $_POST['midtrans_order_id'] ?? '';

        // Jika ada midtrans_order_id, tanyakan ke server Midtrans apakah sudah dibayar
        if (!empty($midtrans_id)) {
            $midtrans_status = cek_status_midtrans($midtrans_id);
            if ($midtrans_status === 'settlement' || $midtrans_status === 'capture') {
                // Midtrans menyatakan lunas -> update database lokal
                $stmt_up = $pdo->prepare("UPDATE pesanan SET status = 'diproses' WHERE id_pesanan = :id");
                $stmt_up->execute([':id' => $order_id]);

                $stmt_trx = $pdo->prepare("
                    INSERT INTO transaksi (id_transaksi, id_pesanan, total_bayar, metode, terbayar, kembali, kasir_username)
                    VALUES (:id_trx, (SELECT total FROM pesanan WHERE id_pesanan = :id), 'QRIS', (SELECT total FROM pesanan WHERE id_pesanan = :id), 0, NULL)
                    ON CONFLICT (id_pesanan) DO UPDATE SET metode = 'QRIS'
                ");
                $stmt_trx->execute([
                    ':id_trx' => 'TRX-' . strtoupper(substr(uniqid(), -4)),
                    ':id'     => $order_id
                ]);

                echo json_encode(['status' => 'diproses']);
                exit;
            }
        }

        // Cek status lokal
        $stmt_status = $pdo->prepare("SELECT status FROM pesanan WHERE id_pesanan = :id");
        $stmt_status->execute([':id' => $order_id]);
        $status_terkini = $stmt_status->fetchColumn() ?: 'menunggu_pembayaran';

        echo json_encode(['status' => $status_terkini]);
        exit;
    }
}

// -----------------------------------------------------------------------------
// 4. PENGAMBILAN DATA PESANAN UNTUK RENDERING
// -----------------------------------------------------------------------------
try {
    $stmt = $pdo->prepare("
        SELECT p.*, COALESCE(t.metode, :metode_default) AS metode_bayar, t.id_transaksi
        FROM pesanan p
        LEFT JOIN transaksi t ON p.id_pesanan = t.id_pesanan
        WHERE p.id_pesanan = :id
    ");
    $stmt->execute([
        ':id'             => $order_id,
        ':metode_default' => ($metode === 'qris' ? 'QRIS' : 'Cash')
    ]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("Pesanan dengan ID " . htmlspecialchars($order_id) . " tidak ditemukan.");
    }
} catch (PDOException $e) {
    die("Gagal mengambil data pesanan: " . $e->getMessage());
}

$nomor_meja     = (int)$order['id_meja'];
$total_bayar    = (float)$order['total'];
$status_pesanan = strtolower($order['status'] ?? 'menunggu_pembayaran');
$is_lunas       = ($status_pesanan === 'diproses' || $status_pesanan === 'selesai');
$is_qris        = ($metode === 'qris' || strtolower($order['metode_bayar']) === 'qris');

// Request QRIS Asli dari Midtrans Core API jika mode QRIS aktif & belum lunas
$qris_data = null;
if ($is_qris && !$is_lunas) {
    $qris_data = buat_midtrans_qris($order_id, $total_bayar);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - Cysto Cafe</title>
    
    <link rel="stylesheet" href="../assets/css/menuPelanggan.css">
    <link rel="stylesheet" href="../assets/css/pembayaran.css">
</head>
<body>

    <div class="mobile-container">
        
        <!-- Header -->
        <header class="top-header">
            <div class="brand-area">
                <div class="brand-icon">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 2v2"></path><path d="M11 1v3"></path><path d="M15 2v2"></path>
                        <path d="M4 8h12v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8z"></path>
                        <path d="M16 9h1.5a2.5 2.5 0 0 1 0 5H16"></path>
                    </svg>
                </div>
                <span class="brand-name">Cysto</span>
            </div>

            <div class="table-pill">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span>Meja <?= e($nomor_meja) ?></span>
            </div>
        </header>

        <h2 class="page-title">
            <?= $is_lunas ? 'Status Pesanan' : 'Instruksi Pembayaran' ?>
        </h2>

        <!-- ================================================================== -->
        <!-- KONDISI 1: SUDAH LUNAS / SEDANG DIPROSES                          -->
        <!-- ================================================================== -->
        <?php if ($is_lunas): ?>

            <div class="pay-card-main">
                <div class="success-icon-wrap">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>

                <span class="pay-status-badge badge-success-pay">
                    ✓ Pembayaran Berhasil
                </span>

                <?php if ($status_pesanan === 'selesai'): ?>
                    <h3 style="font-size: 18px; color: #10B981; margin-bottom: 6px;">
                        Pesanan Sedang Diantar / Siap!
                    </h3>
                    <p style="font-size: 13px; color: #7A6252;">
                        Makanan & minumanmu sudah siap dan sedang diantarkan ke Meja <?= e($nomor_meja) ?>. Selamat menikmati!
                    </p>
                <?php else: ?>
                    <h3 style="font-size: 18px; color: var(--color-text-title); margin-bottom: 6px;">
                        Pesanan Sedang Diproses!
                    </h3>
                    <p style="font-size: 13px; color: #7A6252;">
                        Pembayaran telah diterima oleh sistem. Dapur dan barista sedang menyiapkan pesanan mejamu.
                    </p>
                <?php endif; ?>

                <!-- Status Timeline Tracker Dinamis -->
                <div class="order-timeline">
                    <div class="timeline-step done">
                        <div class="timeline-dot">✓</div>
                        <span class="timeline-label">Terbayar</span>
                    </div>

                    <div class="timeline-step <?= $status_pesanan === 'selesai' ? 'done' : 'active' ?>">
                        <div class="timeline-dot"><?= $status_pesanan === 'selesai' ? '✓' : '⏱' ?></div>
                        <span class="timeline-label">Diproses</span>
                    </div>

                    <div class="timeline-step <?= $status_pesanan === 'selesai' ? 'done' : '' ?>">
                        <div class="timeline-dot"><?= $status_pesanan === 'selesai' ? '✓' : '3' ?></div>
                        <span class="timeline-label">Diantar</span>
                    </div>
                </div>

                <!-- Bukti Ringkasan Transaksi -->
                <div class="success-receipt">
                    <div class="receipt-row">
                        <span>ID Pesanan:</span>
                        <strong><?= e($order_id) ?></strong>
                    </div>
                    <div class="receipt-row">
                        <span>Nomor Meja:</span>
                        <span>Meja <?= e($nomor_meja) ?></span>
                    </div>
                    <div class="receipt-row">
                        <span>Metode Pembayaran:</span>
                        <span><?= strtoupper(e($order['metode_bayar'])) ?> (Lunas)</span>
                    </div>
                    <div class="receipt-row">
                        <span>Waktu Pesan:</span>
                        <span><?= date('H:i:s, d M Y', strtotime($order['waktu_pesan'])) ?></span>
                    </div>
                    <div class="receipt-row">
                        <span>Total Terbayar:</span>
                        <span>Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
                    </div>
                </div>

                <p style="font-size: 12px; color: #8C7D70;">
                    Silakan duduk manis di Meja <?= e($nomor_meja) ?>. Pesananmu akan segera diantarkan!
                </p>
            </div>

        <!-- ================================================================== -->
        <!-- KONDISI 2: METODE QRIS MURNI (MIDTRANS CORE API)                   -->
        <!-- ================================================================== -->
        <?php elseif ($is_qris): ?>
            
            <div class="pay-card-main">
                <span class="pay-status-badge badge-pending">
                    ⏱ Menunggu Scan QRIS
                </span>

                <div class="qris-header">
                    <span class="qris-badge-title">QRIS</span>
                    <span style="font-size: 11px; background: #C0292B; color: white; padding: 2px 6px; border-radius: 4px; font-weight: bold;">GPN / MIDTRANS</span>
                </div>

                <p style="font-size: 13px; color: #7A6252;">Scan kode QR di bawah untuk menyelesaikan pembayaran:</p>

                <!-- Box Tampilan Gambar QR Code Resmi Midtrans -->
                <?php if ($qris_data && !empty($qris_data['qr_image_url'])): ?>
                <div class="qris-box-qr">
                    <img src="<?= e($qris_data['qr_image_url']) ?>" 
                         alt="QR Code QRIS Midtrans" 
                         class="qris-img"
                         onerror="this.onerror=null; this.src='../assets/images/coffee-icon.svg';">
                </div>
                <?php else: ?>
                <div style="padding: 14px; background: #FFF3CD; color: #856404; border-radius: 10px; font-size: 13px; margin: 15px 0;">
                    Gagal memuat QRIS dari server Midtrans.
                </div>
                <?php endif; ?>

                <div style="font-size: 12px; color: #8C7D70; margin-bottom: 3px;">Total Tagihan:</div>
                <div style="font-size: 24px; font-weight: 800; color: var(--color-primary); margin-bottom: 4px;">
                    Rp <?= number_format($total_bayar, 0, ',', '.') ?>
                </div>
                <div style="font-size: 12px; color: #8C7D70; margin-bottom: 14px;">
                    Kode Pesanan: <strong><?= e($order_id) ?></strong> (Meja <?= $nomor_meja ?>)
                </div>

                <!-- Info Panduan Scan -->
                <div class="info-step-list">
                    <strong>Cara Pembayaran:</strong>
                    <ol>
                        <li>Buka aplikasi m-Banking (BCA, Mandiri) atau E-Wallet (GoPay, OVO, ShopeePay, DANA).</li>
                        <li>Pilih menu <strong>Scan QRIS</strong> dan arahkan kamera ke kode di atas.</li>
                        <li>Pastikan nama merchant: <strong>Cysto</strong> dengan nominal yang sesuai.</li>
                    </ol>
                </div>

                <!-- Tombol Konfirmasi Simulasi Pembayaran -->
                <div class="mock-sandbox-box" style="margin-top: 15px;">
                    <div class="mock-sandbox-title">
                        <span>SIMULATOR SANDBOX</span>
                    </div>
                    <div class="mock-sandbox-desc">
                        Untuk demo PBL, klik tombol di bawah setelah scan untuk menyimulasikan respon sukses:
                    </div>
                    <button type="button" class="btn-simulate-pay" id="btnSimulatePay" onclick="jalankanSimulasiBayar()">
                        <span>✓ Konfirmasi Bayar Berhasil</span>
                    </button>
                </div>
            </div>

        <!-- ================================================================== -->
        <!-- KONDISI 3: METODE TUNAI (KASIR)                                   -->
        <!-- ================================================================== -->
        <?php else: ?>

            <div class="pay-card-main">
                <span class="pay-status-badge badge-pending">
                    ⏱ Menunggu Pembayaran Tunai
                </span>

                <h3 style="font-size: 16px; color: var(--color-text-title); margin-bottom: 6px;">
                    Tunjukkan Kode Ini ke Kasir
                </h3>
                <p style="font-size: 12.5px; color: #7A6252;">Sebutkan kode pesananmu kepada kasir untuk melakukan pembayaran:</p>

                <div>
                    <span class="order-id-highlight"><?= e($order_id) ?></span>
                </div>

                <div style="margin: 10px 0 16px 0;">
                    <div style="font-size: 12px; color: #8C7D70; margin-bottom: 3px;">Total yang Harus Dibayar:</div>
                    <div style="font-size: 22px; font-weight: 800; color: var(--color-primary);">
                        Rp <?= number_format($total_bayar, 0, ',', '.') ?>
                    </div>
                </div>

                <div class="info-step-list">
                    <strong>Langkah Selanjutnya:</strong>
                    <ol>
                        <li>Silakan menuju meja kasir Cysto Cafe.</li>
                        <li>Tunjukkan atau sebutkan Kode Pesanan <strong><?= e($order_id) ?></strong> (Meja <?= e($nomor_meja) ?>).</li>
                        <li>Lakukan pembayaran tunai sesuai nominal di atas.</li>
                        <li>Kasir akan mengonfirmasi pesananmu di sistem kasir dan pesanan akan segera diantar ke mejamu.</li>
                    </ol>
                </div>
            </div>

        <?php endif; ?>

        <!-- Tombol Kembali -->
        <a href="Menu.php?meja=<?= e($nomor_meja) ?>" class="btn-home">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            <span>Kembali ke Menu / Pesan Lagi</span>
        </a>

    </div>

    <!-- ====================================================================== -->
    <!-- JAVASCRIPT: POLLING & SIMULATOR                                        -->
    <!-- ====================================================================== -->
    <script>
    const orderId         = "<?= e($order_id) ?>";
    const currentStatus   = "<?= e($status_pesanan) ?>";
    const midtransOrderId = "<?= e($qris_data['midtrans_order_id'] ?? '') ?>";

    // 1. Fungsi Konfirmasi Bayar Lunas
    function jalankanSimulasiBayar() {
        const btn = document.getElementById('btnSimulatePay');
        if (btn) btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'simulasi_bayar_sukses');
        formData.append('order_id', orderId);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                alert("Gagal: " + (data.message || 'Terjadi kesalahan'));
                if (btn) btn.disabled = false;
            }
        })
        .catch(err => {
            console.error(err);
            if (btn) btn.disabled = false;
        });
    }

    // 2. Polling Realtime ke Server (Mengecek status lokal & Midtrans)
    setInterval(() => {
        const formData = new FormData();
        formData.append('action', 'cek_status');
        formData.append('order_id', orderId);
        if (midtransOrderId) {
            formData.append('midtrans_order_id', midtransOrderId);
        }

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status && data.status !== currentStatus) {
                window.location.reload();
            }
        })
        .catch(() => {});
    }, 3000);
    </script>

</body>
</html>
