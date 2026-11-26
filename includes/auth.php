<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Matikan cache & bfcache browser agar tombol Back setelah logout selalu cek ulang ke server
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION['user_id'])) {
    header('Location: ../admin/Login.php');
    exit;
}
