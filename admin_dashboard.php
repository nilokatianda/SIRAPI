<?php
session_start();
require_once "koneksi.php";

// Proteksi akses: harus admin
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$nama_admin = $_SESSION['nama_pengguna'] ?? 'Administrator';
$nim_admin = $_SESSION['nim'] ?? '-';
$jabatan_admin = $_SESSION['jabatan'] ?? 'Admin/Sekretaris';

$pesan_sukses = "";
$pesan_error = "";

// 1. Logika Catat Transaksi Baru dari Dashboard Admin
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'tambah_transaksi') {
    $id_arsip = (int)($_POST['id_arsip'] ?? 0);
    $id_pengguna = (int)($_POST['id_pengguna'] ?? 0);
    $tanggal_pinjam = trim($_POST['tanggal_pinjam'] ?? '');
    $tanggal_kembali = trim($_POST['tanggal_kembali'] ?? '');
    $status_peminjaman = trim($_POST['status_peminjaman'] ?? 'Dipinjam');

    if ($id_arsip <= 0 || $id_pengguna <= 0 || $tanggal_pinjam === "") {
        $pesan_error = "Peminjam, Arsip, dan Tanggal Pinjam wajib diisi.";
    } else {
        $tgl_kembali_val = ($tanggal_kembali !== "") ? $tanggal_kembali : null;
        $sql = "INSERT INTO peminjaman (id_arsip, id_pengguna, tanggal_pinjam, tanggal_kembali, status_peminjaman) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iisss", $id_arsip, $id_pengguna, $tanggal_pinjam, $tgl_kembali_val, $status_peminjaman);
            if (mysqli_stmt_execute($stmt)) {
                $pesan_sukses = "Transaksi peminjaman arsip baru berhasil dicatat!";
            } else {
                $pesan_error = "Gagal mencatat transaksi: " . mysqli_stmt_error($stmt);
            }
        } else {
            $pesan_error = "Gagal menyiapkan query: " . mysqli_error($conn);
        }
    }
}

// 2. Logika Update Cepat Status Transaksi Jadi 'Dikembalikan'
if (isset($_GET['selesai_id'])) {
    $id_selesai = (int)$_GET['selesai_id'];
    $tgl_sekarang = date('Y-m-d');
    $stmt_up = mysqli_prepare($conn, "UPDATE peminjaman SET status_peminjaman = 'Dikembalikan', tanggal_kembali = ? WHERE id_peminjaman = ?");
    mysqli_stmt_bind_param($stmt_up, "si", $tgl_sekarang, $id_selesai);
    if (mysqli_stmt_execute($stmt_up)) {
        $pesan_sukses = "Status transaksi #$id_selesai berhasil diubah menjadi Dikembalikan.";
    }
}

// 3. Dropdown options
$list_arsip = mysqli_query($conn, "SELECT id_arsip, nama_arsip FROM arsip ORDER BY nama_arsip ASC");
$list_pengguna = mysqli_query($conn, "SELECT id_pengguna, nama_pengguna, nim FROM pengguna ORDER BY nama_pengguna ASC");

// 4. Hitung Statistik Admin
$count_arsip = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM arsip"))['total'] ?? 0;
$count_pengguna = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pengguna"))['total'] ?? 0;
$count_aktif = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE status_peminjaman = 'Dipinjam'"))['total'] ?? 0;
$count_kembali = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE status_peminjaman = 'Dikembalikan'"))['total'] ?? 0;

// 5. Seluruh Catatan Transaksi
$sql_transaksi = "SELECT p.id_peminjaman, p.tanggal_pinjam, p.tanggal_kembali, p.status_peminjaman,
                         u.nama_pengguna, u.nim, a.nama_arsip
                  FROM peminjaman p
                  LEFT JOIN pengguna u ON p.id_pengguna = u.id_pengguna
                  LEFT JOIN arsip a ON p.id_arsip = a.id_arsip
                  ORDER BY p.id_peminjaman DESC";
$result_transaksi = mysqli_query($conn, $sql_transaksi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin & Transaksi - SIRAPI</title>
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

        /* Sidebar Kiri */
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
            padding: 10px 14px;
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

        /* Main Content */
        .main-content {
            flex-grow: 1;
            padding: 24px 32px 50px;
            overflow-y: auto;
        }

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
            background: #fef3c7;
            color: #b45309;
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
            color: #b45309;
            font-weight: 700;
            font-size: 11px;
        }

        /* Banner Card Admin */
        .hero-banner-card {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 60%, #86efac 100%);
            border-radius: var(--radius-card);
            padding: 28px 36px;
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
            max-width: 500px;
            z-index: 2;
        }

        .hero-banner-text h2 {
            font-size: 26px;
            font-weight: 800;
            color: #0d4a22;
            line-height: 1.25;
            margin-bottom: 16px;
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
            width: 200px;
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* 4 Kartu Statistik */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: #ffffff;
            border-radius: var(--radius-card);
            padding: 18px 20px;
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
            gap: 14px;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 18px;
        }

        .stat-icon.green { background: #15803d; }
        .stat-icon.amber { background: #d97706; }
        .stat-icon.teal { background: #0d9488; }
        .stat-icon.blue { background: #2563eb; }

        .stat-meta .label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 2px;
        }

        .stat-meta .val {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
        }

        /* Grid Bawah: Tabel Transaksi (Lebar) & Form Catat (Kanan) */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
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
        }

        /* Form Catat Transaksi */
        .form-admin-group {
            margin-bottom: 14px;
        }

        .form-admin-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .form-admin-group select,
        .form-admin-group input {
            width: 100%;
            padding: 9px 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 12px;
            outline: none;
            background: #ffffff;
        }

        .form-admin-group select:focus,
        .form-admin-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.15);
        }

        .btn-save-tx {
            width: 100%;
            padding: 11px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 6px;
        }

        .btn-save-tx:hover {
            background: #14532d;
        }

        /* Tabel Transaksi */
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
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
        }

        .table-custom td {
            padding: 11px 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            vertical-align: middle;
        }

        .pill-status {
            display: inline-block;
            padding: 3px 9px;
            border-radius: var(--radius-pill);
            font-size: 10px;
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

        .btn-action-tx {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
            margin-left: 4px;
        }

        .btn-action-tx.selesai {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .btn-action-tx.selesai:hover {
            background-color: #bbf7d0;
        }

        .alert-box {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 18px;
        }

        .alert-box.success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-box.error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        @media (max-width: 1100px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .bottom-grid { grid-template-columns: 1fr; }
            .sidebar { width: 210px; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR KIRI ADMIN -->
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
                <p>Panel Administrator<br>UKM Kependudukan</p>
            </div>
        </a>

        <ul class="sidebar-menu">
            <li>
                <a href="admin_dashboard.php" class="active">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                    Dashboard & Transaksi
                </a>
            </li>
            <li>
                <a href="pengguna/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Data Pengguna
                </a>
            </li>
            <li>
                <a href="arsip/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
                    Data Arsip
                </a>
            </li>
            <li>
                <a href="peminjaman/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Data Peminjaman
                </a>
            </li>
            <li>
                <a href="kegiatan/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Data Kegiatan
                </a>
            </li>
            <li>
                <a href="periode/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                    Data Periode
                </a>
            </li>
            <li>
                <a href="laporan/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
                    Laporan Transaksi
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

    <!-- KONTEN UTAMA ADMIN -->
    <main class="main-content">
        <!-- Top Bar Header -->
        <header class="top-bar">
            <div class="top-greeting">
                <h1>Selamat datang, <?php echo htmlspecialchars($nama_admin); ?>!</h1>
                <p>Portal Administrator & Pencatatan Transaksi UKM Kependudukan</p>
            </div>
            <div class="profile-pill">
                <div class="profile-avatar">&#128737;</div>
                <div class="profile-info">
                    <strong>NIM: <?php echo htmlspecialchars($nim_admin); ?></strong>
                    <span>Role: <?php echo htmlspecialchars($jabatan_admin); ?></span>
                </div>
            </div>
        </header>

        <!-- Banner Card Admin -->
        <section class="hero-banner-card">
            <div class="hero-banner-text">
                <h2>Pusat Kendali & Catatan Transaksi Arsip UKM</h2>
                <a href="#form-catat" class="btn-banner-action">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Catat Transaksi Peminjaman &rarr;
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

        <?php if ($pesan_sukses !== ""): ?>
            <div class="alert-box success"><?php echo htmlspecialchars($pesan_sukses); ?></div>
        <?php endif; ?>
        <?php if ($pesan_error !== ""): ?>
            <div class="alert-box error"><?php echo htmlspecialchars($pesan_error); ?></div>
        <?php endif; ?>

        <!-- 4 Kartu Statistik -->
        <section class="stats-row">
            <div class="stat-box">
                <div class="stat-left">
                    <div class="stat-icon amber">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="stat-meta">
                        <div class="label">Sedang Dipinjam</div>
                        <div class="val"><?php echo $count_aktif; ?></div>
                    </div>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-left">
                    <div class="stat-icon green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div class="stat-meta">
                        <div class="label">Sudah Dikembalikan</div>
                        <div class="val"><?php echo $count_kembali; ?></div>
                    </div>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-left">
                    <div class="stat-icon teal">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div class="stat-meta">
                        <div class="label">Total Arsip Terdata</div>
                        <div class="val"><?php echo $count_arsip; ?></div>
                    </div>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-left">
                    <div class="stat-icon blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
                    </div>
                    <div class="stat-meta">
                        <div class="label">Total Pengguna</div>
                        <div class="val"><?php echo $count_pengguna; ?></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Grid Bawah: Tabel Transaksi & Form Pencatatan -->
        <section class="bottom-grid">
            <!-- Tabel Transaksi Peminjaman Terkini -->
            <div class="panel-card">
                <div class="panel-header">
                    <div class="panel-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Catatan Seluruh Transaksi Peminjaman
                    </div>
                    <a href="laporan/index.php" class="link-more">Cetak Laporan &rarr;</a>
                </div>

                <div style="overflow-x: auto;">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Peminjam</th>
                                <th>Nama Arsip</th>
                                <th>Tgl Pinjam</th>
                                <th>Kembali</th>
                                <th>Status</th>
                                <th>Aksi Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_transaksi && mysqli_num_rows($result_transaksi) > 0): ?>
                                <?php while ($tx = mysqli_fetch_assoc($result_transaksi)): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars((string)($tx['nama_pengguna'] ?? '-')); ?></strong>
                                            <div style="font-size: 10px; color: #64748b;"><?php echo htmlspecialchars((string)($tx['nim'] ?? '')); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars((string)($tx['nama_arsip'] ?? '-')); ?></td>
                                        <td><?php echo htmlspecialchars((string)$tx['tanggal_pinjam']); ?></td>
                                        <td><?php echo !empty($tx['tanggal_kembali']) ? htmlspecialchars((string)$tx['tanggal_kembali']) : '<span style="color:#94a3b8;">-</span>'; ?></td>
                                        <td>
                                            <?php if (($tx['status_peminjaman'] ?? '') === 'Dikembalikan'): ?>
                                                <span class="pill-status kembali">Dikembalikan</span>
                                            <?php else: ?>
                                                <span class="pill-status pinjam">Dipinjam</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (($tx['status_peminjaman'] ?? '') === 'Dipinjam'): ?>
                                                <a href="admin_dashboard.php?selesai_id=<?php echo $tx['id_peminjaman']; ?>" 
                                                   class="btn-action-tx selesai" 
                                                   onclick="return confirm('Tandai arsip ini sudah dikembalikan?')">
                                                   &check; Kembali
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #94a3b8; padding: 25px;">
                                        Belum ada catatan transaksi peminjaman.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Form Pencatatan Transaksi Baru Langsung di Dashboard -->
            <div class="panel-card" id="form-catat">
                <div class="panel-header">
                    <div class="panel-title">
                        <span>&#9998;</span> Catat Transaksi Baru
                    </div>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="tambah_transaksi">
                    <div class="form-admin-group">
                        <label>Peminjam (Anggota / Staff)</label>
                        <select name="id_pengguna" required>
                            <option value="">-- Pilih Anggota --</option>
                            <?php 
                            mysqli_data_seek($list_pengguna, 0);
                            while ($u = mysqli_fetch_assoc($list_pengguna)): 
                            ?>
                                <option value="<?php echo $u['id_pengguna']; ?>">
                                    <?php echo htmlspecialchars($u['nama_pengguna']); ?> (NIM: <?php echo htmlspecialchars($u['nim'] ?? '-'); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-admin-group">
                        <label>Arsip yang Dipinjam</label>
                        <select name="id_arsip" required>
                            <option value="">-- Pilih Arsip --</option>
                            <?php 
                            mysqli_data_seek($list_arsip, 0);
                            while ($a = mysqli_fetch_assoc($list_arsip)): 
                            ?>
                                <option value="<?php echo $a['id_arsip']; ?>">
                                    <?php echo htmlspecialchars($a['nama_arsip']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-admin-group">
                        <label>Tanggal Pinjam</label>
                        <input type="date" name="tanggal_pinjam" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-admin-group">
                        <label>Batas Kembali (Opsional)</label>
                        <input type="date" name="tanggal_kembali">
                    </div>

                    <div class="form-admin-group">
                        <label>Status Transaksi</label>
                        <select name="status_peminjaman">
                            <option value="Dipinjam">Dipinjam</option>
                            <option value="Dikembalikan">Dikembalikan</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-save-tx">+ Simpan Catatan Transaksi</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
