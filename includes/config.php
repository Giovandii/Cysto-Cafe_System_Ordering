<?php
/**
 * ==============================================================================
 * KONFIGURASI SISTEM & KREDENSIAL APLIKASI
 * Lokasi File: includes/config.php
 * Proyek     : Sistem Informasi Kafe (Cysto Cafe) - PBL
 * ==============================================================================
 * File ini memusatkan seluruh konfigurasi database dan kredensial API Midtrans.
 * ==============================================================================
 */

return [
    // -------------------------------------------------------------------------
    // 1. KONEKSI BASIS DATA (POSTGRESQL)
    // -------------------------------------------------------------------------
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_port' => getenv('DB_PORT') ?: '5432',
    'db_name' => getenv('DB_NAME') ?: 'pbl_cafe',
    'db_user' => getenv('DB_USER') ?: 'postgres',
    'db_pass' => getenv('DB_PASS') ?: 'Kurapostgres',

    // -------------------------------------------------------------------------
    // 2. KREDENSIAL MIDTRANS PAYMENT GATEWAY (MODE SANDBOX)
    // -------------------------------------------------------------------------
    'midtrans_server_key'    => 'SB-Mid-server-icm1Ttr4uwpfluktlFFz8~Pk',
    'midtrans_client_key'    => 'Mid-client-FAwcspa_f8El_FWK',
    'midtrans_is_production' => false,
];
