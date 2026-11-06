<?php
require_once __DIR__ . "/../includes/auth_check.php";
require_once __DIR__ . "/../includes/koneksi.php";
require_once __DIR__ . "/../includes/denda_helper.php";

ensure_denda_schema($conn);

$id_user = (int)$_SESSION['id_pengguna'];
$nama_user = $_SESSION['nama_pengguna'] ?? 'Pengguna';
$nim_user = $_SESSION['nim'] ?? '-';
$jabatan_user = $_SESSION['jabatan'] ?? 'Staff / Anggota';
$role_user = $_SESSION['role'] ?? 'pengguna';

$pesan_sukses = "";

// Aksi Konfirmasi Pembayaran Denda via Modal QRIS / E-Wallet (jika ada)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action_denda']) && $_POST['action_denda'] === 'bayar_denda') {
    $id_bayar = (int)($_POST['id_peminjaman_denda'] ?? 0);
    $metode_bayar = trim($_POST['metode_pembayaran_pilihan'] ?? 'QRIS');

    $q_info = mysqli_query($conn, "SELECT tanggal_kembali, tanggal_dikembalikan, denda FROM peminjaman WHERE id_peminjaman = $id_bayar AND id_pengguna = $id_user");
    if ($q_info && $row_info = mysqli_fetch_assoc($q_info)) {
        $hari = hitung_hari_terlambat($row_info['tanggal_kembali'], $row_info['tanggal_dikembalikan']);
        $nom = hitung_nominal_denda($hari);
        if ($nom <= 0 && (int)$row_info['denda'] > 0) $nom = (int)$row_info['denda'];

        $stmt_pay = mysqli_prepare($conn, "UPDATE peminjaman SET denda = ?, status_denda = 'Lunas', metode_pembayaran = ?, tanggal_bayar_denda = NOW() WHERE id_peminjaman = ?");
        if ($stmt_pay) {
            mysqli_stmt_bind_param($stmt_pay, "isi", $nom, $metode_bayar, $id_bayar);
            if (mysqli_stmt_execute($stmt_pay)) {
                $pesan_sukses = "Pembayaran denda transaksi #$id_bayar sebesar Rp " . number_format($nom, 0, ',', '.') . " via $metode_bayar berhasil dikonfirmasi LUNAS!";
            }
        }
    }
}

// 1. Hitung Statistik Ringkasan Peminjaman Pribadi
$q_aktif = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE id_pengguna = ? AND status_peminjaman = 'Dipinjam'");
mysqli_stmt_bind_param($q_aktif, "i", $id_user);
mysqli_stmt_execute($q_aktif);
$c_aktif = mysqli_fetch_assoc(mysqli_stmt_get_result($q_aktif))['total'] ?? 0;

$q_kembali = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE id_pengguna = ? AND status_peminjaman = 'Dikembalikan'");
mysqli_stmt_bind_param($q_kembali, "i", $id_user);
mysqli_stmt_execute($q_kembali);
$c_kembali = mysqli_fetch_assoc(mysqli_stmt_get_result($q_kembali))['total'] ?? 0;

// Hitung total denda belum dibayar pengguna ini
$q_all_user = mysqli_query($conn, "SELECT id_peminjaman, tanggal_kembali, tanggal_dikembalikan, status_peminjaman, denda, status_denda FROM peminjaman WHERE id_pengguna = $id_user");
$total_tagihan_denda = 0;
$jumlah_telat = 0;
if ($q_all_user) {
    while ($r_d = mysqli_fetch_assoc($q_all_user)) {
        $inf = get_info_denda($r_d);
        if ($inf['harus_bayar']) {
            $total_tagihan_denda += $inf['nominal'];
            $jumlah_telat++;
        }
    }
}

// 2. Ambil Daftar Divisi dan Ringkasan Kegiatan & Dokumen
$sql_divisi = "
    SELECT 
        p.id_periode,
        COALESCE(p.nama_periode, 'Umum') AS nama_periode,
        COALESCE(p.nama_divisi, 'Umum') AS nama_divisi,
        COUNT(DISTINCT k.id_kegiatan) AS total_kegiatan,
        COUNT(DISTINCT a.id_arsip) AS total_arsip
    FROM periode p
    LEFT JOIN kegiatan k ON p.id_periode = k.id_periode
    LEFT JOIN arsip a ON k.id_kegiatan = a.id_kegiatan
    GROUP BY p.id_periode, p.nama_periode, p.nama_divisi
    ORDER BY p.nama_periode DESC, p.nama_divisi ASC
";
$res_divisi = mysqli_query($conn, $sql_divisi);

$divisi_list = [];
$unique_periode = [];
$grand_total_arsip = 0;
$grand_total_kegiatan = 0;

if ($res_divisi) {
    while ($r = mysqli_fetch_assoc($res_divisi)) {
        $divisi_list[] = $r;
        $p_val = $r['nama_periode'];
        if (!isset($unique_periode[$p_val])) {
            $unique_periode[$p_val] = 0;
        }
        $unique_periode[$p_val] += (int)$r['total_arsip'];
        $grand_total_arsip += (int)$r['total_arsip'];
        $grand_total_kegiatan += (int)$r['total_kegiatan'];
    }
}
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
            max-width: 1300px;
        }

        /* TOP BAR */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
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

        .profile-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 5px 14px 5px 6px;
            border-radius: var(--radius-pill);
            box-shadow: var(--shadow-subtle);
        }

        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 800;
            font-size: 13px;
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

        /* 3 STATS ROW DI PALING ATAS */
        .top-stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .top-stat-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-subtle);
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
        }

        .top-stat-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary-border);
            box-shadow: var(--shadow-card);
        }

        .top-stat-lbl {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .top-stat-val {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
            line-height: 1.1;
        }

        .top-stat-icon {
            font-size: 22px;
            opacity: 0.85;
        }

        /* EXPLORER HERO BANNER */
        .explorer-hero {
            background: linear-gradient(135deg, #064e3b 0%, #047857 70%, #0f766e 100%);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            color: #ffffff;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px -5px rgba(4, 120, 87, 0.25);
        }

        .explorer-hero h1 {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
            color: #ffffff;
        }

        .explorer-hero p {
            font-size: 12.5px;
            color: #d1fae5;
            max-width: 650px;
            line-height: 1.45;
            margin-bottom: 16px;
        }

        .search-box-wrap {
            position: relative;
            max-width: 500px;
        }

        .search-box-wrap input {
            width: 100%;
            padding: 11px 16px 11px 42px;
            border-radius: var(--radius-pill);
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.95);
            font-size: 12.5px;
            outline: none;
            color: #0f172a;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
            transition: all 0.2s;
        }

        .search-box-wrap input:focus {
            background: #ffffff;
            border-color: #ffffff;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .search-box-wrap svg {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #047857;
            pointer-events: none;
        }

        /* SECTION TITLE */
        .explorer-section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .explorer-section-title h3 {
            font-size: 14.5px;
            font-weight: 800;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* PERIODE TABS PILLS */
        .periode-tabs {
            display: flex;
            align-items: center;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 6px;
            margin-bottom: 20px;
        }

        .periode-pill {
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 6px 14px;
            border-radius: var(--radius-pill);
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .periode-pill:hover {
            border-color: var(--primary-border);
            color: var(--primary);
            background: var(--primary-soft);
        }

        .periode-pill.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(4, 120, 87, 0.25);
        }

        .badge-count-pill {
            background: rgba(0, 0, 0, 0.08);
            padding: 2px 7px;
            border-radius: var(--radius-pill);
            font-size: 10px;
            font-weight: 700;
        }

        .periode-pill.active .badge-count-pill {
            background: rgba(255, 255, 255, 0.25);
            color: white;
        }

        /* GRID KARTU DIVISI */
        .divisi-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        .divisi-item-card {
            background: #ffffff;
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            padding: 20px;
            box-shadow: var(--shadow-subtle);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 180px;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }

        .divisi-item-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary-border);
            box-shadow: 0 10px 24px -4px rgba(4, 120, 87, 0.15);
        }

        .divisi-item-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10b981 0%, #047857 100%);
            opacity: 0.8;
            transition: all 0.2s;
        }

        .divisi-item-card:hover::before {
            height: 6px;
        }

        .divisi-top-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary-soft);
            color: var(--primary);
            border: 1px solid var(--primary-border);
            padding: 3px 9px;
            border-radius: var(--radius-pill);
            font-size: 10.5px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .divisi-main-info {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 14px;
        }

        .divisi-icon-large {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #f0fdf4;
            color: #047857;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            border: 1px solid #bbf7d0;
            flex-shrink: 0;
        }

        .divisi-titles h3 {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.25;
            margin-bottom: 3px;
        }

        .divisi-titles p {
            font-size: 11.5px;
            color: var(--text-muted);
            margin: 0;
        }

        .divisi-metrics-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px dashed #f1f5f9;
            margin-bottom: 12px;
        }

        .divisi-metric-chip {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 600;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .divisi-metric-chip strong {
            color: #0f172a;
            font-weight: 800;
        }

        .btn-open-divisi {
            width: 100%;
            background: #f1f5f9;
            color: #1e293b;
            padding: 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .divisi-item-card:hover .btn-open-divisi {
            background: var(--primary);
            color: #ffffff;
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
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; min-height: auto; position: static; }
            .main-content { padding: 16px; }
            .divisi-cards-grid { grid-template-columns: 1fr; }
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

        <div class="menu-label">Navigasi Arsip</div>
        <ul class="sidebar-menu">
            <li>
                <a href="../peminjaman/index.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Detail Peminjaman & Denda
                </a>
            </li>
            <li>
                <a href="../peminjaman/tambah.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Ajukan Peminjaman
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

    <!-- KONTEN UTAMA PENGGUNA -->
    <main class="main-content">
        <!-- TOP BAR -->
        <header class="top-bar">
            <div class="top-greeting">
                <h1>Halo, <?php echo htmlspecialchars($nama_user); ?> 👋</h1>
                <p>Pilih divisi untuk melihat kegiatan dan membuka dokumen arsip di halaman khusus</p>
            </div>
            <div class="top-actions">
                <div class="profile-pill">
                    <div class="profile-avatar">👤</div>
                    <div class="profile-info">
                        <strong><?php echo htmlspecialchars($nama_user); ?></strong>
                        <span>NIM: <?php echo htmlspecialchars($nim_user); ?> • <?php echo htmlspecialchars($jabatan_user); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- ALERTS -->
        <?php if ($pesan_sukses !== ""): ?>
            <div class="alert-custom">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <div><?php echo htmlspecialchars($pesan_sukses); ?></div>
            </div>
        <?php endif; ?>

        <!-- 3 KARTU STATISTIK RINGKASAN DI PALING ATAS -->
        <section class="top-stats-row">
            <!-- Sedang Dipinjam -->
            <a href="../peminjaman/index.php" class="top-stat-card" title="Klik untuk melihat detail peminjaman aktif">
                <div>
                    <div class="top-stat-lbl">Sedang Dipinjam</div>
                    <div class="top-stat-val"><?php echo $c_aktif; ?></div>
                </div>
                <div class="top-stat-icon">📖</div>
            </a>

            <!-- Sudah Dikembalikan -->
            <a href="../peminjaman/index.php" class="top-stat-card" title="Klik untuk melihat riwayat pengembalian">
                <div>
                    <div class="top-stat-lbl">Sudah Dikembalikan</div>
                    <div class="top-stat-val"><?php echo $c_kembali; ?></div>
                </div>
                <div class="top-stat-icon" style="color: #047857; font-weight: 800;">✓</div>
            </a>

            <!-- Tagihan Denda Keterlambatan -->
            <a href="../peminjaman/index.php" class="top-stat-card" style="<?php echo $total_tagihan_denda > 0 ? 'border-color: #fca5a5; background: #fff5f5;' : ''; ?>" title="Klik untuk melihat detail tagihan atau bayar QRIS">
                <div>
                    <div class="top-stat-lbl">Tagihan Denda Keterlambatan</div>
                    <div class="top-stat-val" style="<?php echo $total_tagihan_denda > 0 ? 'color: #dc2626; font-size: 20px;' : 'color: #047857; font-size: 20px;'; ?>">
                        <?php echo $total_tagihan_denda > 0 ? 'Rp ' . number_format($total_tagihan_denda, 0, ',', '.') : 'Bebas Denda'; ?>
                    </div>
                </div>
                <div class="top-stat-icon">💳</div>
            </a>
        </section>

        <!-- HERO BANNER -->
        <section class="explorer-hero">
            <h1>Pusat Penelusuran Arsip UKM Kependudukan</h1>
            <p>Pilih salah satu divisi organisasi di bawah ini untuk masuk ke halaman khusus kegiatan dan membuka dokumen arsip yang berkaitan.</p>
            
            <div class="search-box-wrap">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="divisiSearch" placeholder="Cari nama divisi atau periode kepengurusan..." onkeyup="filterDivisiCards()">
            </div>
        </section>

        <!-- TABS FILTER PERIODE -->
        <div class="explorer-section-title">
            <h3>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                Pilih Periode Kepengurusan
            </h3>
            <span style="font-size: 11.5px; color: var(--text-muted);"></span>
        </div>

        <div class="periode-tabs">
            <button type="button" class="periode-pill active" onclick="selectPeriode('all')">
                <span>Semua Periode</span>
                <span class="badge-count-pill"><?php echo count($divisi_list); ?> Divisi</span>
            </button>
            <?php foreach ($unique_periode as $p_val => $total_a): ?>
                <button type="button" class="periode-pill" onclick="selectPeriode('<?php echo htmlspecialchars(addslashes($p_val), ENT_QUOTES); ?>')">
                    <span><?php echo htmlspecialchars($p_val); ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- GRID KARTU DIVISI BERSIH (KLIK MASUK KE HALAMAN KHUSUS) -->
        <div class="divisi-cards-grid" id="divisiContainer">
            <?php if (!empty($divisi_list)): ?>
                <?php foreach ($divisi_list as $div): ?>
                    <a href="divisi_detail.php?id=<?php echo (int)$div['id_periode']; ?>" 
                       class="divisi-item-card" 
                       data-periode="<?php echo htmlspecialchars($div['nama_periode'], ENT_QUOTES); ?>"
                       data-divisi-card>
                        <div>
                            <div class="divisi-top-badge">
                                📅 <?php echo htmlspecialchars($div['nama_periode']); ?>
                            </div>
                            <div class="divisi-main-info">
                                <div class="divisi-icon-large">🏢</div>
                                <div class="divisi-titles">
                                    <h3><?php echo htmlspecialchars($div['nama_divisi']); ?></h3>
                                    <p>UKM Kependudukan</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="divisi-metrics-row">
                                <div class="divisi-metric-chip">
                                    🎯 <strong><?php echo (int)$div['total_kegiatan']; ?></strong> Kegiatan
                                </div>
                                <div class="divisi-metric-chip">
                                    📄 <strong><?php echo (int)$div['total_arsip']; ?></strong> Dokumen
                                </div>
                            </div>
                            <div class="btn-open-divisi">
                                Masuk ke Halaman Divisi &rarr;
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 50px 20px; background: white; border-radius: var(--radius-card); border: 1px solid var(--border-color);">
                    <div style="font-size: 36px; margin-bottom: 10px;">🏢</div>
                    <h3 style="font-size: 16px; margin-bottom: 6px;">Belum Ada Divisi Terdaftar</h3>
                    <p style="color: var(--text-muted); font-size: 12.5px;">Data periode kepengurusan dan divisi belum diinput oleh administrator.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Filter Periode Tabs
        function selectPeriode(periodeName) {
            const pills = document.querySelectorAll('.periode-pill');
            pills.forEach(p => p.classList.remove('active'));
            if (event && event.target) {
                const btn = event.target.closest('.periode-pill');
                if (btn) btn.classList.add('active');
            }

            const cards = document.querySelectorAll('[data-divisi-card]');
            cards.forEach(card => {
                const p = card.getAttribute('data-periode');
                if (periodeName === 'all' || p === periodeName) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Live Search Filter Divisi
        function filterDivisiCards() {
            const query = document.getElementById('divisiSearch').value.toLowerCase();
            const cards = document.querySelectorAll('[data-divisi-card]');

            cards.forEach(card => {
                const text = card.innerText.toLowerCase();
                card.style.display = text.includes(query) ? 'flex' : 'none';
            });
        }
    </script>
</body>
</html>

