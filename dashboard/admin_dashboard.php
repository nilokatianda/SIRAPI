<?php
require_once __DIR__ . "/../includes/auth_check.php";
require_admin();
require_once __DIR__ . "/../includes/koneksi.php";
require_once __DIR__ . "/../includes/denda_helper.php";

ensure_denda_schema($conn);

$nama_admin = $_SESSION['nama_pengguna'] ?? 'Administrator';
$nim_admin = $_SESSION['nim'] ?? '-';
$jabatan_admin = $_SESSION['jabatan'] ?? 'Admin / Sekretaris';

$pesan_sukses = "";
$pesan_error = "";

// Hitung Statistik Lengkap untuk 6 Stat Cards
$count_arsip = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM arsip"))['total'] ?? 0;
$count_pengguna = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pengguna"))['total'] ?? 0;
$count_periode = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM periode"))['total'] ?? 0;
$count_kegiatan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM kegiatan"))['total'] ?? 0;
$count_aktif = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE status_peminjaman = 'Dipinjam'"))['total'] ?? 0;
$count_kembali = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE status_peminjaman = 'Dikembalikan'"))['total'] ?? 0;

// Hitung total denda tertunggak secara dinamis
$q_all_tx = mysqli_query($conn, "SELECT id_peminjaman, tanggal_kembali, tanggal_dikembalikan, status_peminjaman, denda, status_denda FROM peminjaman");
$total_denda_tertunggak = 0;
$count_telat_aktif = 0;
if ($q_all_tx) {
    while ($r_tx = mysqli_fetch_assoc($q_all_tx)) {
        $inf = get_info_denda($r_tx);
        if ($inf['harus_bayar']) {
            $total_denda_tertunggak += $inf['nominal'];
            $count_telat_aktif++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SIRAPI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-w: 260px;
            --primary: #047857;
            --primary-dark: #064e3b;
            --primary-light: #10b981;
            --primary-soft: #ecfdf5;
            --primary-border: #a7f3d0;
            --bg-page: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow-subtle: 0 1px 3px rgba(0, 0, 0, 0.04), 0 4px 12px rgba(0, 0, 0, 0.02);
            --shadow-card: 0 4px 16px -2px rgba(15, 23, 42, 0.05);
            --radius-card: 16px;
            --radius-pill: 9999px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            font-size: 13px;
        }

        /* SIDEBAR KIRI */
        .sidebar {
            width: var(--sidebar-w);
            background: #ffffff;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            flex-shrink: 0;
            z-index: 40;
            padding: 24px 18px;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-main);
            margin-bottom: 28px;
            padding: 0 6px;
        }

        .sidebar-logo {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #047857 0%, #064e3b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(4, 120, 87, 0.25);
            flex-shrink: 0;
        }

        .sidebar-title h2 {
            font-size: 17px;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.1;
            color: #0f172a;
        }

        .sidebar-title p {
            font-size: 11px;
            color: var(--text-muted);
            line-height: 1.3;
        }

        .menu-section-label {
            font-size: 10.5px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 10px 10px 4px;
            margin-top: 6px;
        }

        .sidebar-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 12px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 600;
            color: #475569;
            transition: all 0.2s ease;
        }

        .sidebar-menu a:hover {
            background: #f1f5f9;
            color: var(--primary);
        }

        .sidebar-menu a.active {
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 700;
            border: 1px solid var(--primary-border);
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .btn-sidebar-logout {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 10px;
            color: #dc2626;
            text-decoration: none;
            font-weight: 700;
            font-size: 12px;
            background: #fef2f2;
            border: 1px solid #fee2e2;
            transition: all 0.2s;
        }

        .btn-sidebar-logout:hover {
            background: #fee2e2;
            transform: translateX(2px);
        }

        /* MAIN CONTENT */
        .main-content {
            flex-grow: 1;
            padding: 24px 32px 60px;
            overflow-y: auto;
            max-width: 1400px;
        }

        /* TOP BAR */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .top-greeting h1 {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }

        .top-greeting p {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .live-date-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 6px 14px;
            border-radius: var(--radius-pill);
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .live-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }

        .profile-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 5px 12px 5px 6px;
            border-radius: var(--radius-pill);
        }

        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 800;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--primary-border);
        }

        .profile-info {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .profile-info strong {
            font-size: 12px;
            color: var(--text-main);
        }

        .profile-info span {
            font-size: 10.5px;
            color: var(--text-muted);
        }

        /* BANNER HERO */
        .admin-banner {
            background: linear-gradient(135deg, #064e3b 0%, #047857 70%, #0f766e 100%);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            color: #ffffff;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(4, 120, 87, 0.3);
        }

        .banner-content {
            position: relative;
            z-index: 2;
            max-width: 650px;
        }

        .banner-tag {
            display: inline-block;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 4px 12px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .banner-content h2 {
            font-size: 21px;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 6px;
            color: #ffffff;
        }

        .banner-content p {
            font-size: 12.5px;
            color: #d1fae5;
            line-height: 1.5;
            margin-bottom: 18px;
        }

        .banner-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-banner-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            color: #064e3b;
            padding: 9px 18px;
            border-radius: var(--radius-pill);
            font-size: 12.5px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }

        .btn-banner-primary:hover {
            transform: translateY(-2px);
            background: #f0fdf4;
        }

        .btn-banner-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 9px 18px;
            border-radius: var(--radius-pill);
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-banner-secondary:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        /* 6 STAT CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 18px 18px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-subtle);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s ease;
            position: relative;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-card);
            border-color: #cbd5e1;
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .stat-title {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon-wrap {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon-wrap.emerald { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .stat-icon-wrap.blue    { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .stat-icon-wrap.indigo  { background: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe; }
        .stat-icon-wrap.purple  { background: #faf5ff; color: #9333ea; border: 1px solid #e9d5ff; }
        .stat-icon-wrap.amber   { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
        .stat-icon-wrap.rose    { background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; }

        .stat-number {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.8px;
            line-height: 1;
            margin-bottom: 6px;
        }

        .stat-subtext {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* SECTION HEADERS */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 800;
            color: var(--text-main);
        }

        .section-title svg {
            color: var(--primary);
        }

        /* MANAGEMENT HUB GRID */
        .management-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
            margin-bottom: 28px;
        }

        .hub-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 16px 18px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-subtle);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s ease;
        }

        .hub-card:hover {
            border-color: var(--primary-border);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(4, 120, 87, 0.08);
        }

        .hub-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }

        .hub-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: var(--primary-soft);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid var(--primary-border);
        }

        .hub-details h3 {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 2px;
        }

        .hub-details p {
            font-size: 11px;
            color: var(--text-muted);
            line-height: 1.35;
        }

        .hub-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
        }

        .btn-hub-main {
            flex-grow: 1;
            text-align: center;
            background: #f1f5f9;
            color: #334155;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-hub-main:hover {
            background: var(--primary);
            color: #ffffff;
        }

        .btn-hub-add {
            background: var(--primary-soft);
            color: var(--primary);
            border: 1px solid var(--primary-border);
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .btn-hub-add:hover {
            background: var(--primary);
            color: #ffffff;
        }

        /* 2-COLUMN OPERATIONAL GRID */
        .operational-grid {
            display: grid;
            grid-template-columns: 1.75fr 1fr;
            gap: 24px;
        }

        .panel-box {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 20px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-subtle);
        }

        .panel-header-custom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 14px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .panel-title-text {
            font-size: 14px;
            font-weight: 800;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* FILTER TABS & SEARCH */
        .table-tools {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .filter-pills {
            display: flex;
            gap: 6px;
        }

        .filter-btn {
            background: #f1f5f9;
            color: #475569;
            border: none;
            padding: 5px 12px;
            border-radius: var(--radius-pill);
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn.active, .filter-btn:hover {
            background: var(--primary);
            color: white;
        }

        .search-mini {
            padding: 6px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-pill);
            font-size: 11.5px;
            outline: none;
            width: 170px;
            background: #ffffff;
        }

        .search-mini:focus {
            border-color: var(--primary);
        }

        /* TABEL MONITORING */
        .table-responsive {
            overflow-x: auto;
        }

        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 12px;
        }

        .table-custom th {
            background: #f8fafc;
            color: #475569;
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
        }

        .table-custom td {
            padding: 11px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .table-custom tr:hover td {
            background: #f8fafc;
        }

        .badge-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: var(--radius-pill);
            font-size: 10.5px;
            font-weight: 700;
        }

        .badge-status.pinjam {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .badge-status.kembali {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        /* FORM CEPAT */
        .form-group-modern {
            margin-bottom: 14px;
        }

        .form-group-modern label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .form-group-modern select,
        .form-group-modern input {
            width: 100%;
            padding: 9px 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 12.5px;
            outline: none;
            background: #ffffff;
            transition: all 0.2s;
        }

        .form-group-modern select:focus,
        .form-group-modern input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.12);
        }

        .btn-submit-tx {
            width: 100%;
            padding: 11px;
            background: linear-gradient(135deg, #047857 0%, #064e3b 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12.5px;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(4, 120, 87, 0.25);
        }

        .btn-submit-tx:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(4, 120, 87, 0.35);
        }

        .info-card-rule {
            margin-top: 18px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 14px;
            font-size: 11.5px;
            color: #166534;
        }

        .info-card-rule h4 {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .alert-custom {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-custom.success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-custom.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        @media (max-width: 1180px) {
            .operational-grid { grid-template-columns: 1fr; }
            .sidebar { width: 220px; }
        }

        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; min-height: auto; position: static; }
            .main-content { padding: 16px; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR KIRI ADMIN -->
    <aside class="sidebar">
        <a href="../index.php" class="sidebar-brand">
            <div class="sidebar-logo">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                    <path d="M6 6h10"/>
                    <path d="M6 10h10"/>
                </svg>
            </div>
            <div class="sidebar-title">
                <h2>SIRAPI</h2>
                <p>Workspace Administrator<br>Sistem Kearsipan UKM</p>
            </div>
        </a>

        <div class="menu-section-label">Ringkasan Utama</div>
        <ul class="sidebar-menu">
            <li>
                <a href="admin_dashboard.php" class="active">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                    Dashboard Admin
                </a>
            </li>
        </ul>

        <div class="menu-section-label">Kelola Data Master</div>
        <ul class="sidebar-menu">
            <li>
                <a href="../arsip/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
                    Katalog Arsip
                </a>
            </li>
            <li>
                <a href="../kegiatan/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Kegiatan Divisi
                </a>
            </li>
            <li>
                <a href="../periode/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                    Periode & Divisi
                </a>
            </li>
            <li>
                <a href="../pengguna/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
                    Akun Pengguna
                </a>
            </li>
        </ul>

        <div class="menu-section-label">Sirkulasi & Pembukuan</div>
        <ul class="sidebar-menu">
            <li>
                <a href="../peminjaman/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Sirkulasi & Monitoring Denda
                </a>
            </li>
            <li>
                <a href="../index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m10 15 5-3-5-3v6Z"/></svg>
                    Beranda Publik
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="btn-sidebar-logout">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Keluar (Logout)
            </a>
        </div>
    </aside>

    <!-- KONTEN UTAMA ADMIN -->
    <main class="main-content">
        <!-- TOP BAR -->
        <header class="top-bar">
            <div class="top-greeting">
                <h1>Selamat Datang, <?php echo htmlspecialchars($nama_admin); ?> 👋</h1>
                <p>Pusat Kendali Sistem Informasi Rekap Arsip & Peminjaman (SIRAPI)</p>
            </div>
            <div class="top-actions">
                <div class="live-date-pill">
                    <span class="live-dot"></span>
                    <span><?php echo date('d M Y'); ?></span>
                </div>
                <div class="profile-pill">
                    <div class="profile-avatar">A</div>
                    <div class="profile-info">
                        <strong><?php echo htmlspecialchars($nama_admin); ?></strong>
                        <span><?php echo htmlspecialchars($jabatan_admin); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- ALERTS -->
        <?php if ($pesan_sukses !== ""): ?>
            <div class="alert-custom success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <div><?php echo htmlspecialchars($pesan_sukses); ?></div>
            </div>
        <?php endif; ?>
        <?php if ($pesan_error !== ""): ?>
            <div class="alert-custom error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div><?php echo htmlspecialchars($pesan_error); ?></div>
            </div>
        <?php endif; ?>

        <!-- BANNER HERO ADMIN -->
        <section class="admin-banner">
            <div class="banner-content">
                <div class="banner-tag">Master Management Workspace</div>
                <h2>Kelola Seluruh Siklus Kearsipan UKM dalam Satu Tempat</h2>
                <p>Mulai dari pendataan berkas dokumen, masa bakti periode & divisi, program kegiatan, akun anggota, hingga sirkulasi pinjaman dan verifikasi denda.</p>
                <div class="banner-buttons">
                    <a href="../peminjaman/index.php" class="btn-banner-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Sirkulasi & Monitoring Denda
                    </a>
                    <a href="../arsip/tambah.php" class="btn-banner-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Upload Arsip Baru
                    </a>
                </div>
            </div>
        </section>

        <!-- 6 KARTU STATISTIK -->
        <section class="stats-grid">
            <!-- 1. Total Arsip -->
            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-title">Total Arsip</span>
                    <div class="stat-icon-wrap emerald">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
                    </div>
                </div>
                <div class="stat-number"><?php echo $count_arsip; ?></div>
                <div class="stat-subtext">Berkas fisik & digital</div>
            </div>

            <!-- 2. Total Pengguna -->
            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-title">Pengguna Terdaftar</span>
                    <div class="stat-icon-wrap blue">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
                    </div>
                </div>
                <div class="stat-number"><?php echo $count_pengguna; ?></div>
                <div class="stat-subtext">Akun anggota & staff</div>
            </div>

            <!-- 3. Periode & Divisi -->
            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-title">Periode & Divisi</span>
                    <div class="stat-icon-wrap indigo">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                    </div>
                </div>
                <div class="stat-number"><?php echo $count_periode; ?></div>
                <div class="stat-subtext">Unit kepengurusan</div>
            </div>

            <!-- 4. Total Kegiatan -->
            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-title">Total Kegiatan</span>
                    <div class="stat-icon-wrap purple">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                </div>
                <div class="stat-number"><?php echo $count_kegiatan; ?></div>
                <div class="stat-subtext">Program kerja divisi</div>
            </div>

            <!-- 5. Sedang Dipinjam -->
            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-title">Sedang Dipinjam</span>
                    <div class="stat-icon-wrap amber">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                </div>
                <div class="stat-number"><?php echo $count_aktif; ?></div>
                <div class="stat-subtext">Arsip di luar kantor</div>
            </div>

            <!-- 6. Denda Tertunggak -->
            <div class="stat-card <?php echo $total_denda_tertunggak > 0 ? 'alert-card' : ''; ?>">
                <div class="stat-top">
                    <span class="stat-title">Denda Tertunggak</span>
                    <div class="stat-icon-wrap rose">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    </div>
                </div>
                <div class="stat-number" style="<?php echo $total_denda_tertunggak > 0 ? 'color: #dc2626; font-size: 20px;' : 'color: #047857; font-size: 20px;'; ?>">
                    <?php echo $total_denda_tertunggak > 0 ? 'Rp ' . number_format($total_denda_tertunggak, 0, ',', '.') : 'Rp 0'; ?>
                </div>
                <div class="stat-subtext">
                    <?php echo $total_denda_tertunggak > 0 ? '⚠️ ' . $count_telat_aktif . ' transaksi telat' : 'Semua transaksi lancar'; ?>
                </div>
            </div>
        </section>

    </main>
</body>
</html>

