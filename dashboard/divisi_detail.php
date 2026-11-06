<?php
require_once __DIR__ . "/../includes/auth_check.php";
require_once __DIR__ . "/../includes/koneksi.php";
require_once __DIR__ . "/../includes/denda_helper.php";

ensure_denda_schema($conn);

$nama_user = $_SESSION['nama_pengguna'] ?? 'Pengguna';
$nim_user = $_SESSION['nim'] ?? '-';
$jabatan_user = $_SESSION['jabatan'] ?? 'Staff / Anggota';

$id_periode = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_periode <= 0) {
    header("Location: user_dashboard.php");
    exit;
}

// 1. Ambil Data Divisi & Periode
$stmt_p = mysqli_prepare($conn, "SELECT id_periode, nama_periode, nama_divisi FROM periode WHERE id_periode = ?");
mysqli_stmt_bind_param($stmt_p, "i", $id_periode);
mysqli_stmt_execute($stmt_p);
$res_p = mysqli_stmt_get_result($stmt_p);
$divisi = mysqli_fetch_assoc($res_p);

if (!$divisi) {
    die("Data Divisi tidak ditemukan. <a href='user_dashboard.php'>Kembali ke Dashboard</a>");
}

$nama_divisi = $divisi['nama_divisi'];
$nama_periode = $divisi['nama_periode'];

// 2. Ambil Kegiatan dan Dokumen Arsip di Divisi Ini
$sql_kegiatan = "
    SELECT 
        k.id_kegiatan,
        k.nama_kegiatan,
        k.deskripsi AS deskripsi_kegiatan,
        k.tanggal_kegiatan,
        a.id_arsip,
        a.nama_arsip,
        a.nama_file,
        a.file_arsip,
        a.tanggal_upload
    FROM kegiatan k
    LEFT JOIN arsip a ON k.id_kegiatan = a.id_kegiatan
    WHERE k.id_periode = ?
    ORDER BY k.tanggal_kegiatan DESC, a.nama_arsip ASC
";
$stmt_k = mysqli_prepare($conn, $sql_kegiatan);
mysqli_stmt_bind_param($stmt_k, "i", $id_periode);
mysqli_stmt_execute($stmt_k);
$res_k = mysqli_stmt_get_result($stmt_k);

$kegiatan_list = [];
$total_arsip_divisi = 0;

if ($res_k) {
    while ($row = mysqli_fetch_assoc($res_k)) {
        $k_id = $row['id_kegiatan'];
        if (!isset($kegiatan_list[$k_id])) {
            $kegiatan_list[$k_id] = [
                'nama_kegiatan' => $row['nama_kegiatan'],
                'deskripsi' => $row['deskripsi_kegiatan'],
                'tanggal_kegiatan' => $row['tanggal_kegiatan'],
                'arsip' => []
            ];
        }

        if (!empty($row['id_arsip'])) {
            $kegiatan_list[$k_id]['arsip'][] = [
                'id_arsip' => $row['id_arsip'],
                'nama_arsip' => $row['nama_arsip'],
                'nama_file' => $row['nama_file'],
                'file_arsip' => $row['file_arsip'],
                'tanggal_upload' => $row['tanggal_upload']
            ];
            $total_arsip_divisi++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kegiatan & Arsip <?php echo htmlspecialchars($nama_divisi); ?> - SIRAPI</title>
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
            --shadow-card: 0 4px 18px -2px rgba(15, 23, 42, 0.06);
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
            line-height: 1.5;
        }

        /* SIDEBAR PENGGUNA */
        .sidebar {
            width: var(--sidebar-w);
            background: linear-gradient(180deg, #064e3b 0%, #047857 60%, #022c22 100%);
            color: #ffffff;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            min-height: 100vh;
            position: sticky;
            top: 0;
            box-shadow: 4px 0 24px rgba(6, 78, 59, 0.15);
            z-index: 10;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            margin-bottom: 20px;
            text-decoration: none;
            color: white;
        }

        .sidebar-logo {
            width: 42px;
            height: 42px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            border-radius: 12px;
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
            line-height: 1.1;
        }

        .sidebar-title p {
            font-size: 10px;
            color: #a7f3d0;
            margin-top: 3px;
            font-weight: 500;
        }

        .menu-label {
            font-size: 10px;
            font-weight: 700;
            color: #6ee7b7;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 10px 12px 4px;
        }

        .sidebar-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 12px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 10px;
            text-decoration: none;
            color: #e2e8f0;
            font-size: 12.5px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            transform: translateX(3px);
        }

        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
        }

        .sidebar-warning-box {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(251, 191, 36, 0.35);
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 12px;
            color: #fef3c7;
            backdrop-filter: blur(4px);
        }

        .sidebar-warning-header {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11.5px;
            font-weight: 800;
            color: #fef08a;
            margin-bottom: 5px;
            letter-spacing: -0.2px;
        }

        .sidebar-warning-header svg {
            color: #f59e0b;
            flex-shrink: 0;
        }

        .sidebar-warning-text {
            font-size: 11px;
            line-height: 1.45;
            color: #fde68a;
            margin: 0;
        }

        .sidebar-warning-text strong {
            color: #ffffff;
            font-weight: 700;
        }

        .btn-sidebar-logout {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 10px;
            color: #fca5a5;
            text-decoration: none;
            font-weight: 700;
            font-size: 12px;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            transition: all 0.2s;
        }

        .btn-sidebar-logout:hover {
            background: rgba(239, 68, 68, 0.3);
            color: #ffffff;
        }

        /* MAIN CONTENT AREA */
        .main-content {
            flex-grow: 1;
            padding: 24px 32px 60px;
            overflow-y: auto;
            max-width: 1200px;
        }

        /* BACK & BREADCRUMB BAR */
        .nav-back-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn-back-dashboard {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: var(--radius-pill);
            color: var(--text-main);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: var(--shadow-subtle);
            transition: all 0.2s;
        }

        .btn-back-dashboard:hover {
            background: var(--primary-soft);
            color: var(--primary);
            border-color: var(--primary-border);
            transform: translateX(-3px);
        }

        .breadcrumb-trail {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .breadcrumb-trail a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .breadcrumb-trail .sep {
            color: #cbd5e1;
        }

        .breadcrumb-trail .current {
            color: var(--text-main);
            font-weight: 700;
        }

        /* HERO CARD DIVISI */
        .divisi-hero-card {
            background: linear-gradient(135deg, #064e3b 0%, #047857 70%, #0f766e 100%);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            color: #ffffff;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px -5px rgba(4, 120, 87, 0.25);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .divisi-hero-text h1 {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ffffff;
        }

        .divisi-hero-text p {
            font-size: 12.5px;
            color: #d1fae5;
            margin-bottom: 14px;
        }

        .divisi-stat-pills {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .stat-pill-hero {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 5px 12px;
            border-radius: var(--radius-pill);
            font-size: 11.5px;
            font-weight: 600;
            color: #ffffff;
        }

        /* SEARCH BOX */
        .search-divisi-box {
            position: relative;
            margin-bottom: 24px;
            max-width: 480px;
        }

        .search-divisi-box input {
            width: 100%;
            padding: 10px 16px 10px 38px;
            border-radius: var(--radius-pill);
            border: 1px solid var(--border-color);
            background: #ffffff;
            font-size: 12.5px;
            outline: none;
            box-shadow: var(--shadow-subtle);
            transition: all 0.2s;
        }

        .search-divisi-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.12);
        }

        .search-divisi-box svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }

        /* LIST KEGIATAN & ARSIP ACCORDION */
        .kegiatan-section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .kegiatan-section-heading h2 {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .kegiatan-list-wrap {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .kegiatan-detail-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            padding: 18px 20px;
            box-shadow: var(--shadow-subtle);
            transition: all 0.2s;
        }

        .kegiatan-detail-card:hover {
            border-color: var(--primary-border);
            box-shadow: var(--shadow-card);
        }

        .kegiatan-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
            gap: 12px;
        }

        .kegiatan-title-block {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .kegiatan-icon-badge {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--primary-soft);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            border: 1px solid var(--primary-border);
            flex-shrink: 0;
        }

        .kegiatan-title-text {
            font-size: 14px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 2px;
        }

        .kegiatan-meta-date {
            font-size: 11px;
            color: var(--text-muted);
        }

        .kegiatan-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge-doc-count {
            background: #f1f5f9;
            color: #475569;
            padding: 4px 10px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 700;
        }

        .kegiatan-chevron {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            transition: transform 0.25s ease;
        }

        .kegiatan-detail-card.is-open .kegiatan-chevron {
            transform: rotate(180deg);
            background: var(--primary-soft);
            color: var(--primary);
        }

        .kegiatan-description-box {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px dashed #e2e8f0;
            font-size: 12px;
            color: #475569;
            line-height: 1.5;
        }

        /* DAFTAR DOKUMEN ARSIP DI DALAM KEGIATAN */
        .kegiatan-documents-container {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .documents-label-hint {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .arsip-doc-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            transition: all 0.2s;
            gap: 12px;
        }

        .arsip-doc-row:hover {
            background: #ffffff;
            border-color: var(--primary-border);
            box-shadow: 0 2px 8px rgba(4, 120, 87, 0.08);
        }

        .arsip-doc-left {
            display: flex;
            align-items: center;
            gap: 10px;
            overflow: hidden;
        }

        .doc-file-badge {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #fee2e2;
            color: #dc2626;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10.5px;
            font-weight: 800;
            flex-shrink: 0;
            border: 1px solid #fecaca;
        }

        .doc-file-badge.doc {
            background: #e0f2fe;
            color: #0284c7;
            border-color: #bae6fd;
        }

        .doc-meta-info {
            overflow: hidden;
        }

        .doc-title {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .doc-details {
            font-size: 10.5px;
            color: var(--text-muted);
            margin-top: 1px;
        }

        .doc-actions-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .btn-view-file {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--primary-soft);
            color: var(--primary);
            border: 1px solid var(--primary-border);
            padding: 5px 12px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-view-file:hover {
            background: var(--primary);
            color: #ffffff;
        }

        .btn-borrow-file {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #f1f5f9;
            color: #334155;
            padding: 5px 10px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-borrow-file:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .empty-doc-notice {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            padding: 14px;
            border-radius: 10px;
            text-align: center;
            color: var(--text-muted);
            font-size: 11.5px;
        }

        .empty-kegiatan-box {
            text-align: center;
            padding: 40px 20px;
            background: #ffffff;
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
        }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; min-height: auto; position: static; }
            .main-content { padding: 16px; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR KIRI PENGGUNA -->
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
                <p>Portal Penelusur Arsip<br>UKM Kependudukan</p>
            </div>
        </a>

        <div class="menu-label">Navigasi Kearsipan</div>
        <ul class="sidebar-menu">
            <li>
                <a href="user_dashboard.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    Daftar Divisi (Dashboard)
                </a>
            </li>
            <li>
                <a href="../peminjaman/tambah.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Ajukan Peminjaman
                </a>
            </li>
            <li>
                <a href="../peminjaman/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Peminjaman Saya
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
            <!-- WARNING ATURAN KETERLAMBATAN PEMINJAMAN -->
            <div class="sidebar-warning-box">
                <div class="sidebar-warning-header">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <span>Perhatian Peminjaman</span>
                </div>
                <p class="sidebar-warning-text">
                    Maksimal peminjaman adalah <strong>5 hari</strong>. Pengembalian <strong>setelah 5 hari</strong> dianggap <strong>telat</strong> dan otomatis dikenakan denda keterlambatan.
                </p>
            </div>

            <a href="../auth/logout.php" class="btn-sidebar-logout">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Keluar (Logout)
            </a>
        </div>
    </aside>

    <!-- KONTEN UTAMA -->
    <main class="main-content">
        <!-- BREADCRUMB & BACK ROW -->
        <div class="nav-back-row">
            <a href="user_dashboard.php" class="btn-back-dashboard">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Kembali ke Daftar Divisi
            </a>

            <div class="breadcrumb-trail">
                <a href="user_dashboard.php">Dashboard</a>
                <span class="sep">&rsaquo;</span>
                <span>Periode: <?php echo htmlspecialchars($nama_periode); ?></span>
                <span class="sep">&rsaquo;</span>
                <span class="current"><?php echo htmlspecialchars($nama_divisi); ?></span>
            </div>
        </div>

        <!-- HERO BANNER DIVISI -->
        <section class="divisi-hero-card">
            <div class="divisi-hero-text">
                <h1>🏢 <?php echo htmlspecialchars($nama_divisi); ?></h1>
                <p>Dokumen arsip dan program kerja kegiatan resmi UKM Kependudukan</p>
                <div class="divisi-stat-pills">
                    <span class="stat-pill-hero">📅 Periode: <?php echo htmlspecialchars($nama_periode); ?></span>
                    <span class="stat-pill-hero">🎯 <?php echo count($kegiatan_list); ?> Kegiatan Terdaftar</span>
                    <span class="stat-pill-hero">📄 <?php echo $total_arsip_divisi; ?> Berkas Dokumen</span>
                </div>
            </div>
        </section>

        <!-- SEARCH BOX IN DIVISI -->
        <div class="search-divisi-box">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="kegiatanSearch" placeholder="Cari nama kegiatan atau judul arsip di divisi ini..." onkeyup="filterKegiatanList()">
        </div>

        <!-- SECTION HEADING -->
        <div class="kegiatan-section-heading">
            <h2>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Daftar Kegiatan (Klik untuk membuka dokumen arsip)
            </h2>
            <span style="font-size: 11.5px; color: var(--text-muted);">Tersedia <?php echo count($kegiatan_list); ?> kegiatan</span>
        </div>

        <!-- DAFTAR KEGIATAN & ARSIP -->
        <div class="kegiatan-list-wrap" id="kegiatanContainer">
            <?php if (!empty($kegiatan_list)): ?>
                <?php foreach ($kegiatan_list as $k_id => $k_item): ?>
                    <div class="kegiatan-detail-card" data-kegiatan-item>
                        <!-- HEADER KEGIATAN: Klik untuk Buka / Tutup Dokumen -->
                        <div class="kegiatan-card-header" onclick="toggleKegiatanDocs(this)">
                            <div class="kegiatan-title-block">
                                <div class="kegiatan-icon-badge">🎯</div>
                                <div>
                                    <div class="kegiatan-title-text"><?php echo htmlspecialchars($k_item['nama_kegiatan']); ?></div>
                                    <div class="kegiatan-meta-date">
                                        📅 Pelaksanaan: <?php echo !empty($k_item['tanggal_kegiatan']) ? date('d F Y', strtotime($k_item['tanggal_kegiatan'])) : 'Tanggal tidak disetel'; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="kegiatan-header-actions">
                                <span class="badge-doc-count">
                                    📄 <?php echo count($k_item['arsip']); ?> Dokumen
                                </span>
                                <div class="kegiatan-chevron">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                            </div>
                        </div>

                        <!-- DESKRIPSI KEGIATAN -->
                        <?php if (!empty($k_item['deskripsi'])): ?>
                            <div class="kegiatan-description-box">
                                <?php echo htmlspecialchars($k_item['deskripsi']); ?>
                            </div>
                        <?php endif; ?>

                        <!-- DAFTAR DOKUMEN ARSIP (Hidden by default, Muncul saat Kegiatan diklik) -->
                        <div class="kegiatan-documents-container" style="display: none;">
                            <?php if (!empty($k_item['arsip'])): ?>
                                <div class="documents-label-hint">Berkas Arsip Resmi:</div>
                                <?php foreach ($k_item['arsip'] as $doc): 
                                    $file_name = basename($doc['file_arsip'] ?? '');
                                    $file_path = dirname(__DIR__) . "/uploads/arsip/" . $file_name;
                                    $file_exists = ($file_name !== "" && is_file($file_path));
                                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                ?>
                                    <div class="arsip-doc-row" data-doc-item>
                                        <div class="arsip-doc-left">
                                            <div class="doc-file-badge <?php echo ($ext === 'pdf') ? '' : 'doc'; ?>">
                                                <?php echo ($ext === 'pdf') ? 'PDF' : (($ext === 'doc' || $ext === 'docx') ? 'DOC' : '📄'); ?>
                                            </div>
                                            <div class="doc-meta-info">
                                                <div class="doc-title" title="<?php echo htmlspecialchars($doc['nama_arsip']); ?>">
                                                    <?php echo htmlspecialchars($doc['nama_arsip']); ?>
                                                </div>
                                                <div class="doc-details">
                                                    Upload: <?php echo !empty($doc['tanggal_upload']) ? date('d/m/Y', strtotime($doc['tanggal_upload'])) : '-'; ?>
                                                    <?php if (!empty($doc['nama_file'])): ?>
                                                        • <?php echo htmlspecialchars($doc['nama_file']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="doc-actions-wrap">
                                            <?php if ($file_exists): ?>
                                                <a href="../uploads/arsip/<?php echo rawurlencode($file_name); ?>" target="_blank" rel="noopener" class="btn-view-file">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                    Lihat Dokumen
                                                </a>
                                            <?php else: ?>
                                                <span style="font-size: 10.5px; color: #94a3b8; padding: 4px 6px;">Arsip Fisik</span>
                                            <?php endif; ?>

                                            <a href="../peminjaman/tambah.php" class="btn-borrow-file" title="Ajukan Peminjaman Berkas Fisik Ini">
                                                Pinjam &rarr;
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-doc-notice">
                                    Belum ada berkas dokumen arsip diunggah untuk kegiatan ini.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-kegiatan-box">
                    <div style="font-size: 36px; margin-bottom: 12px;">📂</div>
                    <h3 style="font-size: 15px; margin-bottom: 4px;">Belum Ada Kegiatan</h3>
                    <p style="color: var(--text-muted); font-size: 12px;">Belum ada program kegiatan yang dicatat untuk divisi ini.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Toggle Buka / Tutup Dokumen Arsip di Setiap Kegiatan
        function toggleKegiatanDocs(headerEl) {
            const card = headerEl.closest('.kegiatan-detail-card');
            const docContainer = card.querySelector('.kegiatan-documents-container');
            const isOpen = card.classList.contains('is-open');

            if (isOpen) {
                card.classList.remove('is-open');
                docContainer.style.display = 'none';
            } else {
                card.classList.add('is-open');
                docContainer.style.display = 'flex';
            }
        }

        // Filter Pencarian Instan Kegiatan & Dokumen di Divisi Ini
        function filterKegiatanList() {
            const query = document.getElementById('kegiatanSearch').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.kegiatan-detail-card');

            cards.forEach(card => {
                const text = card.innerText.toLowerCase();
                const docContainer = card.querySelector('.kegiatan-documents-container');

                if (query === '') {
                    card.style.display = '';
                } else if (text.includes(query)) {
                    card.style.display = '';
                    card.classList.add('is-open');
                    if (docContainer) docContainer.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>

