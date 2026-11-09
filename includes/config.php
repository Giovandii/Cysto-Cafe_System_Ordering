<?php
/**
 * ==============================================================================
 * KONFIGURASI SISTEM & KREDENSIAL APLIKASI
 * Lokasi File: includes/config.php
 * Proyek     : Sistem Informasi Kafe (Cysto Cafe) - PBL
 * ==============================================================================
 * File ini memusatkan seluruh konfigurasi database dan kredensial API pihak ketiga.
 * Memisahkan konfigurasi dari logika kode program adalah praktik standar untuk:
 * 1. Keamanan data (mencegah hardcode berulang di banyak file).
 * 2. Kemudahan saat migrasi dari lingkungan lokal (localhost) ke server hosting.
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
    'db_pass' => getenv('DB_PASS') ?: '12345678',

    // -------------------------------------------------------------------------
    // 2. KREDENSIAL BOT TELEGRAM (NOTIFIKASI KASIR / DAPUR)
    // -------------------------------------------------------------------------
    // Token didapatkan dari @BotFather saat pembuatan bot
    'telegram_bot_token' => '9068284207:AAEqJ23N2niGGwuOszneh0QCQLaUCjciAuY',
    // ID penerima (akun kasir/owner atau ID group kasir) didapatkan via @userinfobot
    'telegram_chat_id'   => '1644562601',
];
