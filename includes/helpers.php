<?php
/**
 * ==============================================================================
 * MODUL PUSTAKA BANTUAN (HELPERS) & FUNGSI UTILITAS
 * Lokasi File: includes/helpers.php
 * Proyek     : Sistem Informasi Kafe (Cysto Cafe) - PBL
 * ==============================================================================
 * Berisi sekumpulan fungsi pembantu global yang digunakan di seluruh aplikasi:
 * 1. Sanitasi teks input/output untuk mencegah serangan XSS.
 * 2. Perhitungan checksum CRC16 standar EMVCo QRIS nasional.
 * 3. Konversi payload QRIS statis menjadi dinamis (nominal tagihan otomatis).
 * 4. Pengiriman pesan notifikasi otomatis via API Bot Telegram.
 * ==============================================================================
 */

/**
 * ------------------------------------------------------------------------------
 * FUNGSI 1: SANITASI OUTPUT HTML (XSS PROTECTION)
 * ------------------------------------------------------------------------------
 * Membungkus data yang berasal dari input pengguna/database sebelum dicetak
 * ke browser untuk mencegah injeksi script berbahaya (Cross-Site Scripting).
 *
 * @param mixed $value Teks atau data yang akan dicetak
 * @return string Teks yang sudah disanitasi aman untuk HTML
 */
function e($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * ------------------------------------------------------------------------------
 * FUNGSI 2: PERHITUNGAN CHECKSUM CRC16-CCITT STANDAR EMVCo QRIS
 * ------------------------------------------------------------------------------
 * Menghitung kode verifikasi 4 karakter heksadesimal di akhir string QRIS.
 * Menggunakan polinomial 0x1021 dengan nilai awal 0xFFFF sesuai spesifikasi ASPI.
 *
 * @param string $str String muatan QRIS tanpa 4 karakter checksum di belakangnya
 * @return string 4 karakter heksadesimal (contoh: "2E2B", "6FB4")
 */
function qris_crc16($str)
{
    $crc = 0xFFFF;
    for ($c = 0; $c < strlen($str); $c++) {
        $crc ^= (ord($str[$c]) << 8);
        for ($i = 0; $i < 8; $i++) {
            if ($crc & 0x8000) {
                $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
            } else {
                $crc = ($crc << 1) & 0xFFFF;
            }
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

/**
 * ------------------------------------------------------------------------------
 * FUNGSI 3: KONVERSI QRIS STATIS MENJADI DINAMIS DENGAN NOMINAL OTOMATIS
 * ------------------------------------------------------------------------------
 * Menyuntikkan nominal tagihan ke dalam kode QRIS agar saat di-scan oleh pelanggan
 * menggunakan aplikasi m-Banking/E-Wallet, nominal pembayaran otomatis terisi pas.
 *
 * Langkah-langkah algoritma:
 * 1. Membuang tag checksum lama (Tag 6304 + 4 karakter hex).
 * 2. Mengubah indikator tipe QR dari statis (010211) menjadi dinamis (010212).
 * 3. Menyusun Tag 54 (Transaction Amount) sesuai nominal belanja.
 * 4. Menyisipkan Tag 54 sebelum Tag 58 (Kode Negara 5802ID).
 * 5. Menghitung dan menempelkan checksum CRC16 baru di akhir string.
 *
 * @param string $static_qris String mentah QRIS statis toko merchant
 * @param float|int $nominal  Jumlah tagihan pembayaran (rupiah)
 * @return string String QRIS dinamis lengkap siap di-render menjadi QR Code
 */
function buat_qris_dinamis($static_qris, $nominal)
{
    // 1. Buang checksum lama (tag 6304 dan 4 karakter hex di belakangnya)
    $pos_crc = strrpos($static_qris, '6304');
    $base = ($pos_crc !== false) ? substr($static_qris, 0, $pos_crc) : substr($static_qris, 0, -8);

    // 2. Ubah indikator jenis QR: 010211 (statis) -> 010212 (dinamis)
    $base = preg_replace('/010211/', '010212', $base, 1);

    // 3. Susun Tag 54 (Transaction Amount): Format = [ID 54][Panjang 2 digit][Nominal]
    $nominal_str = (string)(int)$nominal;
    $tag54 = '54' . sprintf('%02d', strlen($nominal_str)) . $nominal_str;

    // 4. Sisipkan Tag 54 tepat sebelum Tag 58 (Country Code '5802ID')
    $parts = explode('5802ID', $base, 2);
    if (count($parts) === 2) {
        $payload = $parts[0] . $tag54 . '5802ID' . $parts[1] . '6304';
    } else {
        $payload = $base . $tag54 . '6304';
    }

    // 5. Hitung CRC16 baru dan gabungkan menjadi string final
    return $payload . qris_crc16($payload);
}

/**
 * ------------------------------------------------------------------------------
 * FUNGSI 4: PENGIRIMAN NOTIFIKASI OTOMATIS KE TELEGRAM BOT
 * ------------------------------------------------------------------------------
 * Mengirim pesan instan melalui protokol HTTPS POST ke Telegram Bot API.
 * Digunakan untuk mengabari kasir/barista ketika ada transaksi QRIS yang sukses.
 *
 * @param string $pesan Pesan notifikasi dengan format HTML
 * @param array|null $reply_markup Tombol inline keyboard opsional (jika dibutuhkan)
 * @return string|false Respon JSON dari Telegram atau false jika gagal
 */
function kirim_notifikasi_telegram($pesan, $reply_markup = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }

    $token   = $config['telegram_bot_token'] ?? '';
    $chat_id = $config['telegram_chat_id'] ?? '';

    // Jika kredensial belum dikonfigurasi, abaikan pengiriman
    if (empty($token) || empty($chat_id)) {
        return false;
    }

    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id'    => $chat_id,
        'text'       => $pesan,
        'parse_mode' => 'HTML'
    ];

    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }

    // Inisialisasi request HTTP cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout maksimal 5 detik agar tidak membebani respon user
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);

    return $res;
}
