<?php
session_start();

// Hapus semua data session
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// Bersihkan cache browser saat logout agar halaman sebelumnya tidak bisa dibuka lewat tombol Back
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Clear-Site-Data: "cache"');

// Kembalikan ke halaman awal pemilihan role
header('Location: ../index.php');
exit;
