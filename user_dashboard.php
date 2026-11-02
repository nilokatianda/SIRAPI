<?php
session_start();
require_once "koneksi.php";

// Proteksi akses: harus login
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php");
    exit;
}

$id_user = (int)$_SESSION['id_pengguna'];
$nama_user = $_SESSION['nama_pengguna'] ?? 'Pengguna';
$nim_user = $_SESSION['nim'] ?? '-';
$jabatan_user = $_SESSION['jabatan'] ?? 'Staff/Anggota';
$role_user = $_SESSION['role'] ?? 'pengguna';

// 1. Hitung Statistik Peminjaman Pengguna Ini
$q_aktif = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE id_pengguna = ? AND status_peminjaman = 'Dipinjam'");
mysqli_stmt_bind_param($q_aktif, "i", $id_user);
mysqli_stmt_execute($q_aktif);
$c_aktif = mysqli_fetch_assoc(mysqli_stmt_get_result($q_aktif))['total'] ?? 0;

$q_kembali = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE id_pengguna = ? AND status_peminjaman = 'Dikembalikan'");
mysqli_stmt_bind_param($q_kembali, "i", $id_user);
mysqli_stmt_execute($q_kembali);
$c_kembali = mysqli_fetch_assoc(mysqli_stmt_get_result($q_kembali))['total'] ?? 0;

// 2. Ambil Riwayat Peminjaman Terakhir Pengguna Ini
$sql_pinjam = "SELECT p.id_peminjaman, a.nama_arsip, p.tanggal_pinjam, p.tanggal_kembali, p.status_peminjaman
               FROM peminjaman p
               JOIN arsip a ON p.id_arsip = a.id_arsip
               WHERE p.id_pengguna = ?
               ORDER BY p.id_peminjaman DESC LIMIT 6";
$stmt_pinjam = mysqli_prepare($conn, $sql_pinjam);
mysqli_stmt_bind_param($stmt_pinjam, "i", $id_user);
mysqli_stmt_execute($stmt_pinjam);
$result_pinjam = mysqli_stmt_get_result($stmt_pinjam);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pengguna - SIRAPI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #0d4a22;
            --sidebar-hover: #145e2e;
            --sidebar-active: #15803d;
            --primary: #15803d;
            --primary-light: #dcfce7;
            --primary-soft: #f0fdf4;
            --bg-page: #f4f9f5;
            --card-bg: #ffffff;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --radius-card: 16px;
            --radius-pill: 9999px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
        }

        /* ==========================================================================
           SIDEBAR KIRI (DARK GREEN)
           ========================================================================== */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #0b3d1c 0%, #0d4a22 50%, #072b13 100%);
            color: #ffffff;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            min-height: 100vh;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            margin-bottom: 24px;
            text-decoration: none;
            color: white;
        }

        .sidebar-logo {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }

        .sidebar-title h2 {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin: 0;
            line-height: 1.1;
        }

        .sidebar-title p {
            font-size: 10px;
            color: #bbf7d0;
            margin-top: 3px;
            line-height: 1.3;
        }

        .sidebar-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex-grow: 1;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #dcfce7;
            text-decoration: none;
            padding: 11px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .sidebar-menu a:hover {
            background-color: var(--sidebar-hover);
            color: #ffffff;
            transform: translateX(3px);
        }

        .sidebar-menu a.active {
            background-color: var(--sidebar-active);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            font-weight: 700;
        }

        .sidebar-footer {
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
        }

        .btn-sidebar-logout {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(239, 68, 68, 0.18);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.35);
            padding: 10px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            transition: all 0.2s;
        }

        .btn-sidebar-logout:hover {
            background: rgba(239, 68, 68, 0.3);
            color: #ffffff;
        }

        /* ==========================================================================
           MAIN CONTENT AREA
           ========================================================================== */
        .main-content {
            flex-grow: 1;
            padding: 24px 32px 50px;
            overflow-y: auto;
        }

        /* Header Bar */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .top-greeting h1 {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .top-greeting p {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0;
        }

        .profile-pill {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #ffffff;
            padding: 8px 18px;
            border-radius: var(--radius-pill);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color);
        }

        .profile-avatar {
            width: 36px;
            height: 36px;
            background: #dcfce7;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .profile-info {
            text-align: right;
            font-size: 12px;
            line-height: 1.35;
        }

        .profile-info strong {
            display: block;
            color: #0f172a;
            font-size: 12px;
        }

        .profile-info span {
            color: var(--text-muted);
            font-size: 11px;
        }

        /* Banner Card Hijau Lembut */
        .hero-banner-card {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 60%, #86efac 100%);
            border-radius: var(--radius-card);
            padding: 30px 36px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(21, 128, 61, 0.08);
            border: 1px solid #86efac;
        }

        .hero-banner-text {
            max-width: 480px;
            z-index: 2;
        }

        .hero-banner-text h2 {
            font-size: 26px;
            font-weight: 800;
            color: #0d4a22;
            line-height: 1.25;
            margin-bottom: 18px;
            border: none;
            padding: 0;
        }

        .btn-banner-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #0d4a22;
            color: #ffffff;
            padding: 11px 22px;
            border-radius: var(--radius-pill);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(13, 74, 34, 0.3);
            transition: all 0.2s ease;
        }

        .btn-banner-action:hover {
            background: #15803d;
            transform: translateY(-2px);
        }

        .hero-banner-art {
            position: absolute;
            right: 25px;
            top: 50%;
            transform: translateY(-50%);
            width: 220px;
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* 3 Kartu Statistik Horizontal */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: #ffffff;
            border-radius: var(--radius-card);
            padding: 20px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            border: 1px solid var(--border-color);
            transition: all 0.25s ease;
        }

        .stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(21, 128, 61, 0.08);
            border-color: var(--primary-border);
        }

        .stat-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 20px;
        }

        .stat-icon.green { background: #15803d; }
        .stat-icon.teal { background: #0d9488; }
        .stat-icon.emerald { background: #059669; }

        .stat-meta .label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 2px;
        }

        .stat-meta .val {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
        }

        .stat-arrow {
            color: #94a3b8;
            font-size: 16px;
        }

        /* Grid Bagian Bawah (2 Kolom) */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1.7fr 1fr;
            gap: 24px;
        }

        .panel-card {
            background: #ffffff;
            border-radius: var(--radius-card);
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            border: 1px solid var(--border-color);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .panel-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .panel-header a.link-more {
            font-size: 12px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .panel-header a.link-more:hover {
            text-decoration: underline;
        }

        /* Tabel Peminjaman Terakhir */
        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 12px;
        }

        .table-custom th {
            background: #f8fafc;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
        }

        .table-custom td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            vertical-align: middle;
        }

        .table-custom tr:last-child td {
            border-bottom: none;
        }

        .pill-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 700;
        }

        .pill-status.kembali {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .pill-status.pinjam {
            background-color: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        /* Akses Cepat Tiles */
        .quick-action-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 18px;
        }

        .quick-tile {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 18px 12px;
            text-align: center;
            text-decoration: none;
            color: #0f172a;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .quick-tile:hover {
            background: var(--primary-soft);
            border-color: var(--primary-border);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .quick-tile-icon {
            font-size: 24px;
            color: var(--primary);
        }

        .quick-tile span {
            font-size: 12px;
            font-weight: 700;
            line-height: 1.3;
        }

        /* Info Card */
        .info-card-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .info-card-box h4 {
            font-size: 12px;
            font-weight: 700;
            color: #15803d;
            margin-bottom: 3px;
        }

        .info-card-box p {
            font-size: 11px;
            color: #475569;
            line-height: 1.5;
            margin: 0;
        }

        @media (max-width: 1024px) {
            .stats-row { grid-template-columns: 1fr; }
            .bottom-grid { grid-template-columns: 1fr; }
            .sidebar { width: 210px; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR KIRI -->
    <aside class="sidebar">
        <a href="index.php" class="sidebar-brand">
            <div class="sidebar-logo">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                    <path d="M6 6h10"/>
                    <path d="M6 10h10"/>
                </svg>
            </div>
            <div class="sidebar-title">
                <h2>SIRAPI</h2>
                <p>Sistem Informasi Kearsipan<br>UKM Kependudukan</p>
            </div>
        </a>

        <ul class="sidebar-menu">
            <li>
                <a href="user_dashboard.php" class="active">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="arsip/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
                    Katalog Arsip
                </a>
            </li>
            <li>
                <a href="peminjaman/tambah.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    Ajukan Peminjaman
                </a>
            </li>
            <li>
                <a href="peminjaman/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Peminjaman Saya
                </a>
            </li>
            <li>
                <a href="laporan/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Riwayat Laporan
                </a>
            </li>
            <li>
                <a href="index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m10 15 5-3-5-3v6Z"/></svg>
                    Beranda Publik
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <a href="logout.php" class="btn-sidebar-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Keluar (Logout)
            </a>
        </div>
    </aside>

    <!-- KONTEN UTAMA -->
    <main class="main-content">
        <!-- Top Bar Greeting & User Pill -->
        <header class="top-bar">
            <div class="top-greeting">
                <h1>Selamat datang, <?php echo htmlspecialchars($nama_user); ?>!</h1>
                <p>Portal Anggota UKM Kependudukan</p>
            </div>
            <div class="profile-pill">
                <div class="profile-avatar">&#128100;</div>
                <div class="profile-info">
                    <strong>NIM: <?php echo htmlspecialchars($nim_user); ?></strong>
                    <span>Jabatan: <?php echo htmlspecialchars($jabatan_user); ?></span>
                </div>
            </div>
        </header>

        <!-- Hero Banner Card -->
        <section class="hero-banner-card">
            <div class="hero-banner-text">
                <h2>Temukan dan pinjam arsip UKM dengan mudah.</h2>
                <a href="arsip/index.php" class="btn-banner-action">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    Jelajahi Katalog Arsip &rarr;
                </a>
            </div>
            <div class="hero-banner-art">
                <svg width="180" height="140" viewBox="0 0 240 180" fill="none">
                    <rect x="20" y="30" width="180" height="130" rx="16" fill="#15803d" />
                    <rect x="40" y="10" width="140" height="130" rx="10" fill="#ffffff" fill-opacity="0.9" />
                    <rect x="55" y="25" width="80" height="8" rx="4" fill="#cbd5e1" />
                    <rect x="55" y="40" width="110" height="6" rx="3" fill="#e2e8f0" />
                    <rect x="55" y="52" width="100" height="6" rx="3" fill="#e2e8f0" />
                    <path d="M10 50C10 40 20 35 30 35H90L110 55H210C220 55 230 65 230 75V150C230 160 220 170 210 170H30C20 170 10 160 10 150V50Z" fill="#166534" />
                    <circle cx="120" cy="110" r="24" fill="#ffffff" fill-opacity="0.2" />
                    <path d="M110 110L117 117L130 103" stroke="#ffffff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
        </section>

        <!-- 3 Kartu Statistik -->
        <section class="stats-row">
            <div class="stat-box">
                <div class="stat-left">
                    <div class="stat-icon green">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div class="stat-meta">
                        <div class="label">Peminjaman Aktif</div>
                        <div class="val"><?php echo $c_aktif; ?></div>
                    </div>
                </div>
                <div class="stat-arrow">&rsaquo;</div>
            </div>

            <div class="stat-box">
                <div class="stat-left">
                    <div class="stat-icon teal">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    </div>
                    <div class="stat-meta">
                        <div class="label">Menunggu Persetujuan</div>
                        <div class="val">0</div>
                    </div>
                </div>
                <div class="stat-arrow">&rsaquo;</div>
            </div>

            <div class="stat-box">
                <div class="stat-left">
                    <div class="stat-icon emerald">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div class="stat-meta">
                        <div class="label">Sudah Dikembalikan</div>
                        <div class="val"><?php echo $c_kembali; ?></div>
                    </div>
                </div>
                <div class="stat-arrow">&rsaquo;</div>
            </div>
        </section>

        <!-- Bagian Bawah: Tabel Peminjaman Terakhir & Akses Cepat -->
        <section class="bottom-grid">
            <!-- Tabel Peminjaman Terakhir -->
            <div class="panel-card">
                <div class="panel-header">
                    <div class="panel-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Peminjaman Terakhir
                    </div>
                    <a href="peminjaman/index.php" class="link-more">Lihat Semua &rarr;</a>
                </div>

                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Nama Arsip</th>
                            <th>Tanggal Pinjam</th>
                            <th>Batas Kembali</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_pinjam && mysqli_num_rows($result_pinjam) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result_pinjam)): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars((string)$row['nama_arsip']); ?></strong></td>
                                    <td><?php echo htmlspecialchars((string)$row['tanggal_pinjam']); ?></td>
                                    <td><?php echo !empty($row['tanggal_kembali']) ? htmlspecialchars((string)$row['tanggal_kembali']) : '-'; ?></td>
                                    <td>
                                        <?php if (($row['status_peminjaman'] ?? '') === 'Dikembalikan'): ?>
                                            <span class="pill-status kembali">Dikembalikan</span>
                                        <?php else: ?>
                                            <span class="pill-status pinjam">Dipinjam</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #94a3b8; padding: 25px;">
                                    Belum ada transaksi peminjaman tercatat.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Panel Akses Cepat & Info -->
            <div>
                <div class="panel-card" style="margin-bottom: 20px;">
                    <div class="panel-header">
                        <div class="panel-title">
                            <span style="color: #eab308;">&#9889;</span> Akses Cepat
                        </div>
                    </div>
                    <div class="quick-action-grid">
                        <a href="arsip/index.php" class="quick-tile">
                            <div class="quick-tile-icon">&#128214;</div>
                            <span>Lihat Katalog Arsip</span>
                        </a>
                        <a href="peminjaman/tambah.php" class="quick-tile">
                            <div class="quick-tile-icon">&#128640;</div>
                            <span>Ajukan Peminjaman</span>
                        </a>
                    </div>
                </div>

                <div class="info-card-box">
                    <div style="font-size: 18px; color: #15803d;">&#9432;</div>
                    <div>
                        <h4>Informasi</h4>
                        <p>Pilih arsip dari katalog untuk melihat detail dan mengajukan permohonan peminjaman arsip kegiatan UKM.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
