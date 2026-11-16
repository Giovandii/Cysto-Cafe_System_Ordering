<?php
/**
 * ==============================================================================
 * MODUL PUSTAKA BANTUAN (HELPERS) & FUNGSI UTILITAS
 * Lokasi File: includes/helpers.php
 * Proyek     : Sistem Informasi Kafe (Cysto Cafe) - PBL
 * ==============================================================================
 * Fitur:
 * 1. Sanitasi teks input/output untuk mencegah serangan XSS.
 * 2. Request QRIS Resmi ke Midtrans Core API (Sandbox Mode).
 * 3. Pengecekan status transaksi ke Midtrans secara realtime.
 * ==============================================================================
 */

/**
 * ------------------------------------------------------------------------------
 * FUNGSI 1: SANITASI OUTPUT HTML (XSS PROTECTION)
 * ------------------------------------------------------------------------------
 */
function e($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * ------------------------------------------------------------------------------
 * FUNGSI 2: BUAT TRANSAKSI QRIS RESMI VIA MIDTRANS CORE API
 * ------------------------------------------------------------------------------
 * Mengembalikan gambar QR Code resmi dari Midtrans beserta string QRIS EMVCo-nya.
 *
 * @param string $order_id     ID unik pesanan kafe
 * @param int|float $gross_amount Total tagihan pesanan
 * @return array|false Informasi transaksi QRIS dari Midtrans
 */
function buat_midtrans_qris($order_id, $gross_amount)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }

    $server_key = $config['midtrans_server_key'] ?? '';
    if (empty($server_key)) {
        return false;
    }

    $is_prod = $config['midtrans_is_production'] ?? false;
    $url = $is_prod 
        ? 'https://api.midtrans.com/v2/charge' 
        : 'https://api.sandbox.midtrans.com/v2/charge';

    $payload = [
        'payment_type' => 'qris',
        'transaction_details' => [
            'order_id'     => $order_id . '-' . substr(time(), -4),
            'gross_amount' => (int)$gross_amount
        ],
        'qris' => [
            'acquirer' => 'gopay'
        ]
    ];

    $auth = base64_encode($server_key . ':');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . $auth
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $res = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($res, true);
    if (!empty($json['actions'][0]['url'])) {
        return [
            'midtrans_order_id' => $json['order_id'],
            'qr_image_url'      => $json['actions'][0]['url'],
            'qr_string'         => $json['qr_string'] ?? '',
            'transaction_id'    => $json['transaction_id'] ?? ''
        ];
    }

    return false;
}

/**
 * ------------------------------------------------------------------------------
 * FUNGSI 3: CEK STATUS TRANSAKSI KE MIDTRANS
 * ------------------------------------------------------------------------------
 * Digunakan untuk mengecek apakah pelanggan sudah membayar QRIS di simulator.
 */
function cek_status_midtrans($midtrans_order_id)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }

    $server_key = $config['midtrans_server_key'] ?? '';
    if (empty($server_key) || empty($midtrans_order_id)) {
        return false;
    }

    $is_prod = $config['midtrans_is_production'] ?? false;
    $url = $is_prod 
        ? "https://api.midtrans.com/v2/{$midtrans_order_id}/status" 
        : "https://api.sandbox.midtrans.com/v2/{$midtrans_order_id}/status";

    $auth = base64_encode($server_key . ':');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Basic ' . $auth
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($res, true);
    return $json['transaction_status'] ?? 'pending';
}
