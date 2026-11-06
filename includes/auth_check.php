<?php
/**
 * SIRAPI - Middleware Otentikasi & Proteksi Akses Halaman
 * Memastikan setiap halaman internal terlindungi dan tidak bisa diakses
 * langsung tanpa login aktif (misalnya jika URL disalin ke peramban atau perangkat lain).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah pengguna sudah memiliki sesi login aktif
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    // Simpan URL yang sedang diakses agar setelah login dapat diarahkan kembali
    if (!empty($_SERVER['REQUEST_URI'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    }

    header("Location: /SIRAPI/auth/login.php?pesan=belum_login");
    exit;
}

// Penentuan otomatis URL dan Label Dashboard sesuai role login
$user_role = $_SESSION['role'] ?? 'pengguna';

if ($user_role === 'admin') {
    $dashboard_url = "/SIRAPI/dashboard/admin_dashboard.php";
    $dashboard_label = "Kembali ke Dashboard Admin";
} else {
    $dashboard_url = "/SIRAPI/dashboard/user_dashboard.php";
    $dashboard_label = "Kembali ke Dashboard Pengguna";
}

// Fungsi pembantu jika halaman hanya boleh diakses oleh Admin
if (!function_exists('require_admin')) {
    function require_admin() {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header("Location: /SIRAPI/dashboard/user_dashboard.php");
            exit;
        }
    }
}
?>

