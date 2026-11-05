<?php
/**
 * Fungsi Halaman:
 * 1. Menampilkan instruksi pembayaran QRIS dinamis (nominal otomatis terkunci).
 * 2. Menampilkan kode pesanan untuk pelanggan yang memilih bayar tunai di kasir.
 * 3. Menghandle AJAX aksi pembayaran (simulasi/lunas) dan mencatat ke database.
 * 4. Mengirimkan notifikasi struk pesanan baru secara instan ke Bot Telegram.
 * 5. Melakukan polling status pesanan secara realtime tanpa reload manual.
 */

session_start();

require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';

// 2. MENANGKAP PARAMETER DARI URL / REQUEST

// Tangkap ID Pesanan dan Metode Bayar (default: 'kasir' jika tidak dispesifikasikan)
$order_id = $_GET['order_id'] ?? ($_POST['order_id'] ?? '');
$metode   = strtolower($_GET['metode'] ?? 'kasir');

// Validasi proteksi: Jika tidak ada order_id, hentikan dan minta pesan dari menu
if (empty($order_id)) {
    die("
    <div style='font-family: serif; text-align: center; padding: 70px 20px; background-color: #F7F4EE; min-height: 100vh;'>
        <h2 style='color: #5C4033;'>Pesanan Tidak Ditemukan</h2>
        <p style='color: #7A6252; margin-top: 8px;'>Silakan lakukan pemesanan kembali dari menu kafe.</p>
        <a href='Menu.php' style='display:inline-block; margin-top:15px; color:#5C4033; font-weight:bold;'>Kembali ke Menu</a>
    </div>
    ");
}

// 3. HANDLER REQUEST AJAX (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // AKSI A: Konfirmasi Pembayaran QRIS Sukses / Simulasi Bayar
    if ($_POST['action'] === 'simulasi_bayar_sukses') {
        try {
            $pdo->beginTransaction();

            // A.1. Ambil total tagihan pesanan dari database
            // Ambil total tagihan pesanan & nomor meja
$stmt_cek = $pdo->prepare("SELECT total, id_meja FROM pesanan WHERE id_pesanan = :id");
$stmt_cek->execute([':id' => $order_id]);
$row_pesanan = $stmt_cek->fetch(PDO::FETCH_ASSOC);

$total_asli  = (float)($row_pesanan['total'] ?? 0);
$no_meja     = (int)($row_pesanan['id_meja'] ?? 0);
$total_bayar = $total_asli + $no_meja; // Tagihan yang dicatat ke transaksi sudah pas dengan kode unik

            // A.2. Perbarui status pesanan menjadi 'diproses' (masuk ke antrean dapur)
            $stmt_up = $pdo->prepare("UPDATE pesanan SET status = 'diproses' WHERE id_pesanan = :id");
            $stmt_up->execute([':id' => $order_id]);

            // A.3. Catat / Perbarui data transaksi ke tabel 'transaksi'
            // Catatan: kasir_username diset NULL karena dibayar mandiri oleh pelanggan via QRIS
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

            // Komit transaksi database
            $pdo->commit();

            // A.4. Mengirimkan Notifikasi Struk Pesanan ke Bot Telegram
            try {
                // Ambil nomor meja
                $stmt_info = $pdo->prepare("SELECT id_meja FROM pesanan WHERE id_pesanan = :id");
                $stmt_info->execute([':id' => $order_id]);
                $no_meja = $stmt_info->fetchColumn() ?: '-';

                // Ambil rincian menu yang dipesan
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
                    $daftar_menu .= "• " . (int)$item['jumlah'] . "x <b>" . htmlspecialchars($item['nama']) . "</b>";
                    if (!empty($item['catatan'])) {
                        $daftar_menu .= " <i>(" . htmlspecialchars($item['catatan']) . ")</i>";
                    }
                    $daftar_menu .= "\n";
                }
                if (empty($daftar_menu)) {
                    $daftar_menu = "• <i>Rincian menu tidak tersedia</i>\n";
                }

                $jam = date('H:i') . ' WIB';
                $total_fmt = "Rp " . number_format($total_bayar, 0, ',', '.');

                // Susun pesan format HTML untuk Telegram
                $notif_text = "<b>PESANAN BARU TERBAYAR! (QRIS)</b> ☕\n";
                $notif_text .= "━━━━━━━━━━━━━━━━━━━━\n";
                $notif_text .= "<b>ID Pesanan</b>   : <code>{$order_id}</code>\n";
                $notif_text .= "<b>ID Transaksi</b> : <code>{$id_trx}</code>\n";
                $notif_text .= "<b>Lokasi</b>       : Meja {$no_meja}\n";
                $notif_text .= "<b>Total Bayar</b>  : <b>{$total_fmt}</b>\n";
                $notif_text .= "<b>Waktu</b>        : {$jam}\n";
                $notif_text .= "━━━━━━━━━━━━━━━━━━━━\n";
                $notif_text .= "<b>Pesanan:</b>\n{$daftar_menu}";
                $notif_text .= "━━━━━━━━━━━━━━━━━━━━\n";
                $notif_text .= "<i>Status: Diproses Dapur. Harap segera disiapkan!</i>";

                kirim_notifikasi_telegram($notif_text);
            } catch (Exception $e_notif) {
                // Notifikasi gagal tidak boleh membatalkan pembayaran yang sudah sah
                error_log("Gagal mengirim notifikasi Telegram: " . $e_notif->getMessage());
            }

            echo json_encode(['status' => 'success', 'message' => 'Pembayaran QRIS berhasil diselesaikan!']);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }

// AKSI B: Polling Status Pesanan Realtime (Untuk Cek Jika Kasir Mengonfirmasi)
    if ($_POST['action'] === 'cek_status') {
        $stmt_status = $pdo->prepare("SELECT status FROM pesanan WHERE id_pesanan = :id");
        $stmt_status->execute([':id' => $order_id]);
        $status_terkini = $stmt_status->fetchColumn() ?: 'menunggu_pembayaran';

        echo json_encode(['status' => $status_terkini]);
        exit;
    }
}

// 4. MENGAMBIL DATA PESANAN UNTUK RENDERING TAMPILAN (HTML)
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

// Ekstraksi variabel untuk tampilan
$nomor_meja     = (int)$order['id_meja'];
$total_asli     = (float)$order['total'];

// Jika bayar via QRIS, tambahkan kode unik sesuai nomor meja (misal Meja 3 = +Rp 3)
// Jika bayar tunai di kasir, nominal tetap nominal asli
$kode_unik      = ($metode === 'qris' || strtolower($order['metode_bayar'] ?? '') === 'qris') ? $nomor_meja : 0;
$total_bayar    = $total_asli + $kode_unik;

$status_pesanan = strtolower($order['status'] ?? 'menunggu_pembayaran');
$is_lunas       = ($status_pesanan === 'diproses' || $status_pesanan === 'selesai');
$is_qris        = ($metode === 'qris' || strtolower($order['metode_bayar']) === 'qris');

// 5. PEMBUATAN STRING QRIS DINAMIS STANDAR EMVCo
// Data QRIS Statis Toko Asli (Vanvan Store, Digital & Kreatif)
$qris_statis_merchant = '00020101021126610014COM.GO-JEK.WWW01189360091438740543600210G8740543600303UMI51440014ID.CO.QRIS.WWW0215ID10264748273380303UMI5204899953033605802ID5925Vanvan Store, Digital & K6006BLITAR61056617162070703A0163042E2B';

// Ubah menjadi QRIS dinamis dengan tagihan pas sesuai total belanja
$qris_dinamis_string  = buat_qris_dinamis($qris_statis_merchant, $total_bayar);

// URL gambar QR Code beresolusi tinggi (260x260 px)
$qris_img_url         = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=8&data=" . urlencode($qris_dinamis_string);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - Cysto Cafe</title>
    
    <!-- CSS Utama Tema Pelanggan & Layout Mobile -->
    <link rel="stylesheet" href="../assets/css/menuPelanggan.css">
    <!-- CSS Khusus Halaman Pembayaran & Status Pesanan -->
    <link rel="stylesheet" href="../assets/css/pembayaran.css">
</head>
<body>

    <div class="mobile-container">
        
        <!-- Header Aplikasi -->
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

        <!-- Judul Halaman -->
        <h2 class="page-title">
            <?= $is_lunas ? 'Status Pesanan' : 'Instruksi Pembayaran' ?>
        </h2>

        <!-- KONDISI 1: PESANAN SUDAH LUNAS / SEDANG DIPROSES DAPUR             -->
        <?php if ($is_lunas): ?>

            <div class="pay-card-main">
                <div class="success-icon-wrap">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>

                <span class="pay-status-badge badge-success-pay">
                    Pembayaran Berhasil!
                </span>

                <h3 style="font-size: 18px; color: var(--color-text-title); margin-bottom: 6px;">
                    Pesanan Sedang Diproses!
                </h3>
                <p style="font-size: 13px; color: #7A6252;">
                    Pembayaran telah diterima oleh sistem. Dapur dan barista sedang menyiapkan pesanan mejamu.
                </p>

                <!-- Status Timeline Tracker -->
                <div class="order-timeline">
                    <div class="timeline-step done">
                        <div class="timeline-dot"></div>
                        <span class="timeline-label">Terbayar</span>
                    </div>
                    <div class="timeline-step active">
                        <div class="timeline-dot">⏱</div>
                        <span class="timeline-label">Diproses</span>
                    </div>
                    <div class="timeline-step">
                        <div class="timeline-dot">3</div>
                        <span class="timeline-label">Diantar</span>
                    </div>
                </div>

                <!-- Bukti Ringkasan Transaksi -->
                <div class="success-receipt">
                    <div class="receipt-row">
    <span>Subtotal Menu:</span>
    <span>Rp <?= number_format($total_asli, 0, ',', '.') ?></span>
</div>
<?php if ($kode_unik > 0): ?>
<div class="receipt-row" style="color: #2B6CB0; font-size: 12px;">
    <span>Kode Unik Verifikasi (Meja <?= $nomor_meja ?>):</span>
    <span>+ Rp <?= number_format($kode_unik, 0, ',', '.') ?></span>
</div>
<?php endif; ?>
<div class="receipt-row">
    <span>Total Terbayar:</span>
    <span>Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
</div>
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

        <!-- KONDISI 2: METODE QRIS (MENUNGGU SCAN & PEMBAYARAN)                -->
        <?php elseif ($is_qris): ?>
            
            <div class="pay-card-main">
                <span class="pay-status-badge badge-pending">
                    ⏱ Menunggu Scan QRIS
                </span>

                <div class="qris-header">
                    <span class="qris-badge-title">QRIS</span>
                    <span style="font-size: 11px; background: #C0292B; color: white; padding: 2px 6px; border-radius: 4px; font-weight: bold;">GPN</span>
                </div>

                <!-- Informasi Nama Toko Resmi QRIS -->
                <div style="font-size: 13.5px; font-weight: 700; color: #3E2723; margin-bottom: 2px;">
                    VANVAN STORE, DIGITAL & KREATIF
                </div>
                <div style="font-size: 11px; color: #8C7D70; margin-bottom: 10px;">
                    NMID: ID1026474827338
                </div>

                <p style="font-size: 13px; color: #7A6252;">Scan kode QR di bawah untuk menyelesaikan pembayaran:</p>

                <!-- Box Tampilan Gambar QR Code Dinamis -->
                <div class="qris-box-qr">
                    <img src="<?= e($qris_img_url) ?>" 
                         alt="QR Code Pembayaran QRIS Dinamis" 
                         class="qris-img"
                         onerror="this.onerror=null; this.src='../assets/images/coffee-icon.svg';">
                </div>

                <div class="qris-timer">
                    Batas Waktu Bayar: <span id="countdown">14:59</span>
                </div>

                <!-- Rincian Total Tagihan + Kode Unik Meja -->
<div style="background: #FAF7F2; border-radius: 12px; padding: 12px 14px; margin: 12px 0; text-align: left; font-size: 13px;">
    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; color: #7A6252;">
        <span>Subtotal Pesanan:</span>
        <span>Rp <?= number_format($total_asli, 0, ',', '.') ?></span>
    </div>
    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; color: #2B6CB0; font-size: 12px;">
        <span>
            Kode Verifikasi (Meja <?= $nomor_meja ?>):
            <span style="display:block; font-size: 10.5px; color: #8C7D70;">*Otomatis untuk validasi pesanan</span>
        </span>
        <span style="font-weight: bold;">+ Rp <?= number_format($kode_unik, 0, ',', '.') ?></span>
    </div>
    <div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 1px dashed #E5DFD7; font-weight: 800; color: var(--color-primary); font-size: 16px;">
        <span>Total Bayar:</span>
        <span>Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
    </div>
</div>

<div style="font-size: 12px; color: #8C7D70; margin-top: 2px;">
    Kode Pesanan: <strong><?= e($order_id) ?></strong>
</div>

                <!-- Panduan Cara Bayar untuk Pelanggan -->
                <div class="info-step-list">
                    <strong>Cara Pembayaran:</strong>
                    <ol>
                        <li>Buka aplikasi <strong>E-Wallet, M-bangking, Virual Accoount anda</strong>, atau m-Banking pilihanmu.</li>
                        <li>Pilih menu <strong>Scan QR / QRIS</strong>.</li>
                        <li>Arahkan kamera ke QR Code di atas.</li>
                        <li>Periksa total nominal, lalu konfirmasi pembayaran di aplikasi m-Banking.</li>
                    </ol>
                </div>

                <!-- Tombol Konfirmasi Selesai Bayar / Simulasi Sukses -->
                <div class="mock-sandbox-box">
                    <div class="mock-sandbox-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                        <span>QRIS REAL SCAN & KONFIRMASI</span>
                    </div>
                    <div class="mock-sandbox-desc">
                        Scan QR di atas langsung via m-Banking/E-Wallet (nama toko & tagihan akan otomatis terbaca). Setelah transfer selesai (atau saat demo presentasi), klik tombol di bawah:
                    </div>
                    <button type="button" class="btn-simulate-pay" id="btnSimulatePay" onclick="jalankanSimulasiBayar()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>Saya Sudah Bayar / Konfirmasi Lunas</span>
                    </button>
                </div>
            </div>

        <!-- KONDISI 3: METODE TUNAI (BAYAR MANUAL DI KASIR)                    -->
        <?php else: ?>

            <div class="pay-card-main">
                <span class="pay-status-badge badge-pending">
                    ⏱ Menunggu Pembayaran Tunai
                </span>

                <h3 style="font-size: 16px; color: var(--color-text-title); margin-bottom: 6px;">
                    Tunjukkan Kode Ini ke Kasir
                </h3>
                <p style="font-size: 12.5px; color: #7A6252;">Sebutkan kode pesananmu kepada kasir untuk melakukan pembayaran:</p>

                <!-- Penekanan Kode Pesanan -->
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
                        <li>Kasir akan mengonfirmasi pesananmu dan pesanan akan segera diantar ke mejamu.</li>
                    </ol>
                </div>
            </div>

        <?php endif; ?>

        <!-- Tombol Kembali ke Halaman Menu -->
        <a href="Menu.php?meja=<?= e($nomor_meja) ?>" class="btn-home">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            <span>Kembali ke Menu / Pesan Lagi</span>
        </a>

    </div>

    <!-- JAVASCRIPT: TIMER HITUNG MUNDUR, AJAX SIMULASI, & POLLING REALTIME      -->
    <script>
    const orderId = "<?= e($order_id) ?>";
    const isLunas = <?= $is_lunas ? 'true' : 'false' ?>;

    <?php if ($is_qris && !$is_lunas): ?>
    // 1. COUNTDOWN TIMER PEMBAYARAN QRIS (15 MENIT)
    let timeLeft = 15 * 60; // 15 menit dalam detik
    const timerDisplay = document.getElementById('countdown');
    const timerInterval = setInterval(() => {
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            timerDisplay.innerText = "Kadaluarsa";
            return;
        }
        timeLeft--;
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timerDisplay.innerText = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
    }, 1000);

    // 2. FUNGSI KONFIRMASI BAYAR (AJAX POST)
    function jalankanSimulasiBayar() {
        const btn = document.getElementById('btnSimulatePay');
        btn.disabled = true;
        btn.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;">
                <line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line>
                <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line>
                <line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line>
                <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line>
            </svg>
            <span>Memproses Pembayaran...</span>
        `;

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
                // Refresh halaman untuk menampilkan tampilan Lunas
                setTimeout(() => {
                    window.location.reload();
                }, 600);
            } else {
                alert("Simulasi gagal: " + (data.message || 'Terjadi kesalahan'));
                btn.disabled = false;
                btn.innerHTML = "Saya Sudah Bayar / Konfirmasi Lunas";
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = "Saya Sudah Bayar / Konfirmasi Lunas";
        });
    }

    // 3. POLLING STATUS SECARA REALTIME (SETIAP 3 DETIK)
    // Memeriksa jika kasir mengonfirmasi pesanan dari dashboard kasir
    // atau webhook otomatis mengubah status pesanan menjadi 'diproses'
    setInterval(() => {
        if (isLunas) return;

        const formData = new FormData();
        formData.append('action', 'cek_status');
        formData.append('order_id', orderId);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'diproses' || data.status === 'selesai') {
                window.location.reload();
            }
        })
        .catch(() => {});
    }, 3000);
    <?php endif; ?>
    </script>

</body>
</html>
