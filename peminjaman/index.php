<?php
require_once "../includes/auth_check.php";
require_once "../includes/koneksi.php";
require_once "../includes/denda_helper.php";

// Pastikan skema kolom denda sudah tersedia
ensure_denda_schema($conn);

// Mengambil informasi pengguna yang sedang login
$role = $_SESSION['role'] ?? 'pengguna';
$id_pengguna_auth = (int)($_SESSION['id_pengguna'] ?? 0);
$nama_user = $_SESSION['nama_pengguna'] ?? 'Pengguna';

// Menentukan halaman dashboard dan label kembali
if ($role === 'admin') {
    $dashboard_url = '../dashboard/admin_dashboard.php';
    $dashboard_label = 'Kembali ke Dashboard Admin';
} else {
    $dashboard_url = '../dashboard/user_dashboard.php';
    $dashboard_label = 'Kembali ke Dashboard Pengguna';
}

$pesan_sukses = "";
$pesan_error = "";

// 1. Aksi Konfirmasi Pembayaran Denda (Pengguna / Admin via Modal QRIS / E-Wallet)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action_denda']) && $_POST['action_denda'] === 'bayar_denda') {
    $id_bayar = (int)($_POST['id_peminjaman_denda'] ?? 0);
    $metode_bayar = trim($_POST['metode_pembayaran_pilihan'] ?? 'QRIS');

    $q_info = mysqli_query($conn, "SELECT tanggal_kembali, tanggal_dikembalikan, denda FROM peminjaman WHERE id_peminjaman = $id_bayar");
    if ($q_info && $row_info = mysqli_fetch_assoc($q_info)) {
        $hari = hitung_hari_terlambat($row_info['tanggal_kembali'], $row_info['tanggal_dikembalikan']);
        $nom = hitung_nominal_denda($hari);
        if ($nom <= 0 && (int)$row_info['denda'] > 0) {
            $nom = (int)$row_info['denda'];
        }

        $stmt_pay = mysqli_prepare($conn, "UPDATE peminjaman SET denda = ?, status_denda = 'Lunas', metode_pembayaran = ?, tanggal_bayar_denda = NOW() WHERE id_peminjaman = ?");
        if ($stmt_pay) {
            mysqli_stmt_bind_param($stmt_pay, "isi", $nom, $metode_bayar, $id_bayar);
            if (mysqli_stmt_execute($stmt_pay)) {
                $pesan_sukses = "Pembayaran denda transaksi #$id_bayar sebesar Rp " . number_format($nom, 0, ',', '.') . " via $metode_bayar berhasil dikonfirmasi LUNAS!";
            }
        }
    }
}

// 2. Aksi Admin: Catat Transaksi Baru dari Formulir Peminjaman Cepat
if ($role === 'admin' && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'tambah_transaksi') {
    $id_arsip = (int)($_POST['id_arsip'] ?? 0);
    $id_peminjam = (int)($_POST['id_pengguna'] ?? 0);
    $tanggal_pinjam = trim($_POST['tanggal_pinjam'] ?? '');
    $tanggal_kembali = trim($_POST['tanggal_kembali'] ?? '');
    $status_peminjaman = 'Dipinjam';

    if ($id_arsip <= 0 || $id_peminjam <= 0 || $tanggal_pinjam === "") {
        $pesan_error = "Peminjam, Dokumen Arsip, dan Tanggal Pinjam wajib diisi!";
    } else {
        if ($tanggal_kembali === "") {
            $tgl_obj = DateTime::createFromFormat('!Y-m-d', $tanggal_pinjam);
            if ($tgl_obj) {
                $tgl_obj->modify('+5 days');
                $tanggal_kembali = $tgl_obj->format('Y-m-d');
            }
        }
        $tgl_kembali_val = ($tanggal_kembali !== "") ? $tanggal_kembali : null;
        $sql = "INSERT INTO peminjaman (id_arsip, id_pengguna, tanggal_pinjam, tanggal_kembali, status_peminjaman) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iisss", $id_arsip, $id_peminjam, $tanggal_pinjam, $tgl_kembali_val, $status_peminjaman);
            if (mysqli_stmt_execute($stmt)) {
                $pesan_sukses = "Transaksi peminjaman baru berhasil dicatat (Batas kembali: $tgl_kembali_val)!";
            } else {
                $pesan_error = "Gagal mencatat transaksi: " . mysqli_stmt_error($stmt);
            }
        } else {
            $pesan_error = "Gagal menyiapkan query: " . mysqli_error($conn);
        }
    }
}

// 3. Aksi Admin: Update Cepat Status Transaksi Jadi 'Dikembalikan'
if ($role === 'admin' && isset($_GET['selesai_id'])) {
    $id_selesai = (int)$_GET['selesai_id'];
    $tgl_sekarang = date('Y-m-d');

    $q_info = mysqli_query($conn, "SELECT tanggal_kembali, denda, status_denda FROM peminjaman WHERE id_peminjaman = $id_selesai");
    if ($q_info && $d_info = mysqli_fetch_assoc($q_info)) {
        $hari = hitung_hari_terlambat($d_info['tanggal_kembali'], $tgl_sekarang);
        $nom = hitung_nominal_denda($hari);
        $st_denda = ($nom > 0) ? (($d_info['status_denda'] === 'Lunas') ? 'Lunas' : 'Belum Dibayar') : 'Bebas Denda';

        $stmt_up = mysqli_prepare($conn, "UPDATE peminjaman SET status_peminjaman = 'Dikembalikan', tanggal_dikembalikan = ?, denda = ?, status_denda = ? WHERE id_peminjaman = ?");
        if ($stmt_up) {
            mysqli_stmt_bind_param($stmt_up, "sisi", $tgl_sekarang, $nom, $st_denda, $id_selesai);
            if (mysqli_stmt_execute($stmt_up)) {
                $pesan_sukses = "Status transaksi #$id_selesai berhasil diubah menjadi Dikembalikan." . ($nom > 0 ? " Keterlambatan: $hari hari (Denda: Rp " . number_format($nom, 0, ',', '.') . " - $st_denda)." : " (Tepat waktu, Bebas Denda).");
            }
        }
    }
}

// 4. Aksi Admin: Verifikasi Cepat Tandai Denda Lunas
if ($role === 'admin' && isset($_GET['lunas_id'])) {
    $id_lunas = (int)$_GET['lunas_id'];
    $q_info = mysqli_query($conn, "SELECT tanggal_kembali, tanggal_dikembalikan, denda FROM peminjaman WHERE id_peminjaman = $id_lunas");
    if ($q_info && $row_info = mysqli_fetch_assoc($q_info)) {
        $hari = hitung_hari_terlambat($row_info['tanggal_kembali'], $row_info['tanggal_dikembalikan']);
        $nom = hitung_nominal_denda($hari);
        if ($nom <= 0 && (int)$row_info['denda'] > 0) $nom = (int)$row_info['denda'];

        $up = mysqli_query($conn, "UPDATE peminjaman SET denda = $nom, status_denda = 'Lunas', metode_pembayaran = 'Verifikasi Admin', tanggal_bayar_denda = NOW() WHERE id_peminjaman = $id_lunas");
        if ($up) {
            $pesan_sukses = "Denda transaksi #$id_lunas telah diverifikasi LUNAS oleh Admin.";
        }
    }
}

// 5. Query Data Berdasarkan Role
if ($role === 'admin') {
    // Dropdown list untuk form cepat admin
    $list_arsip = mysqli_query($conn, "SELECT id_arsip, nama_arsip FROM arsip ORDER BY nama_arsip ASC");
    $list_pengguna = mysqli_query($conn, "SELECT id_pengguna, nama_pengguna, nim FROM pengguna ORDER BY nama_pengguna ASC");

    // Ambil seluruh data transaksi peminjaman untuk admin monitoring
    $sql_transaksi = "
        SELECT
            p.id_peminjaman,
            p.id_arsip,
            p.id_pengguna,
            a.nama_arsip,
            u.nama_pengguna,
            u.nim,
            p.tanggal_pinjam,
            p.tanggal_kembali,
            p.status_peminjaman,
            p.denda,
            p.status_denda,
            p.tanggal_dikembalikan,
            p.metode_pembayaran
        FROM peminjaman p
        LEFT JOIN arsip a ON p.id_arsip = a.id_arsip
        LEFT JOIN pengguna u ON p.id_pengguna = u.id_pengguna
        ORDER BY p.id_peminjaman DESC
    ";
    $result_transaksi = mysqli_query($conn, $sql_transaksi);
} else {
    // Ambil data peminjaman miliknya sendiri untuk role pengguna
    $stmt_user = mysqli_prepare($conn, "
        SELECT
            p.id_peminjaman,
            p.id_arsip,
            p.id_pengguna,
            a.nama_arsip,
            p.tanggal_pinjam,
            p.tanggal_kembali,
            p.status_peminjaman,
            p.denda,
            p.status_denda,
            p.tanggal_dikembalikan,
            p.metode_pembayaran
        FROM peminjaman p
        LEFT JOIN arsip a ON p.id_arsip = a.id_arsip
        WHERE p.id_pengguna = ?
        ORDER BY p.id_peminjaman DESC
    ");
    mysqli_stmt_bind_param($stmt_user, "i", $id_pengguna_auth);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);

    // Hitung ringkasan statistik peminjaman milik pengguna
    $user_pinjam_count = 0;
    $user_kembali_count = 0;
    $user_denda_total = 0;
    if ($result_user) {
        $rows_cache = [];
        while ($r = mysqli_fetch_assoc($result_user)) {
            $rows_cache[] = $r;
            if ($r['status_peminjaman'] === 'Dipinjam') $user_pinjam_count++;
            if ($r['status_peminjaman'] === 'Dikembalikan') $user_kembali_count++;
            $inf = get_info_denda($r);
            if ($inf['harus_bayar']) $user_denda_total += $inf['nominal'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $role === 'admin' ? 'Monitoring Sirkulasi & Denda' : 'Peminjaman Saya' ?> - SIRAPI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
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

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            font-size: 13px;
        }

        .page-wrapper {
            max-width: 1360px;
            margin: 0 auto;
            padding: 24px 24px 60px;
        }

        /* TOP NAVIGATION */
        .page-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
            gap: 16px;
            flex-wrap: wrap;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .btn-nav-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #ffffff;
            color: #334155;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-pill);
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 700;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-subtle);
        }

        .btn-nav-back:hover {
            background: #f1f5f9;
            color: var(--primary);
            border-color: #cbd5e1;
            transform: translateX(-2px);
        }

        .header-title-block h1 {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
            margin: 0;
        }

        .header-title-block p {
            font-size: 12px;
            color: var(--text-muted);
            margin: 3px 0 0 0;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .role-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary-soft);
            color: var(--primary);
            border: 1px solid var(--primary-border);
            padding: 6px 14px;
            border-radius: var(--radius-pill);
            font-size: 11.5px;
            font-weight: 700;
        }

        /* 2-COLUMN OPERATIONAL GRID */
        .operational-grid {
            display: grid;
            grid-template-columns: 1.75fr 1fr;
            gap: 24px;
            align-items: start;
        }

        .panel-box {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 22px;
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

        .panel-title-text svg {
            color: var(--primary);
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
            padding: 6px 14px;
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
            padding: 7px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-pill);
            font-size: 11.5px;
            outline: none;
            width: 190px;
            background: #ffffff;
            transition: all 0.2s;
        }

        .search-mini:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.12);
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
            padding: 11px 12px;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
        }

        .table-custom td {
            padding: 12px 12px;
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

        /* ALERTS */
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

        /* USER STATS SUMMARY CARDS */
        .user-stat-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .user-stat-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            padding: 16px 20px;
            box-shadow: var(--shadow-subtle);
        }

        .user-stat-card .label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-stat-card .value {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
            margin-top: 4px;
        }

        .user-stat-card.alert .value {
            color: #dc2626;
        }

        @media (max-width: 1100px) {
            .operational-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- TOP NAVIGATION BAR -->
    <header class="page-header-bar">
        <div class="header-left">
            <a href="<?= htmlspecialchars($dashboard_url) ?>" class="btn-nav-back">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                <?= htmlspecialchars($dashboard_label) ?>
            </a>
            <div class="header-title-block">
                <h1><?= $role === 'admin' ? 'Monitoring Sirkulasi & Denda' : 'Riwayat Peminjaman & Denda Saya' ?></h1>
                <p><?= $role === 'admin' ? 'Pantau peredaran arsip, batas pengembalian, status denda real-time, dan catat pinjaman cepat' : 'Daftar arsip yang Anda pinjam beserta tanggal pengembalian dan status pembayaran denda' ?></p>
            </div>
        </div>
        <div class="header-right">
            <div class="role-badge-pill">
                <span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981;"></span>
                <span><?= $role === 'admin' ? 'Akses Admin' : 'Anggota UKM' ?>: <?= htmlspecialchars($nama_user) ?></span>
            </div>
            <?php if ($role === 'pengguna'): ?>
                <a href="tambah.php" class="btn-nav-back" style="background: var(--primary); color: white; border-color: var(--primary);">
                    + Ajukan Peminjaman
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- NOTIFIKASI ALERT -->
    <?php if ($pesan_sukses !== ""): ?>
        <div class="alert-custom success">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <div><?= htmlspecialchars($pesan_sukses) ?></div>
        </div>
    <?php endif; ?>
    <?php if ($pesan_error !== ""): ?>
        <div class="alert-custom error">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div><?= htmlspecialchars($pesan_error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
        <!-- ========================================== -->
        <!-- TAMPILAN OPERASIONAL ADMIN (2-COLUMN GRID) -->
        <!-- ========================================== -->
        <div class="operational-grid">
            <!-- SISI KIRI: TABEL MONITORING SIRKULASI & DENDA -->
            <div class="panel-box">
                <div class="panel-header-custom">
                    <div class="panel-title-text">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        Monitoring Sirkulasi & Denda Real-Time
                    </div>
                </div>

                <div class="table-tools">
                    <div class="filter-pills">
                        <button type="button" class="filter-btn active" onclick="filterTable('all')">Semua</button>
                        <button type="button" class="filter-btn" onclick="filterTable('dipinjam')">Dipinjam</button>
                        <button type="button" class="filter-btn" onclick="filterTable('telat')">Telat/Denda</button>
                        <button type="button" class="filter-btn" onclick="filterTable('kembali')">Dikembalikan</button>
                    </div>
                    <input type="text" id="txSearch" class="search-mini" placeholder="Cari peminjam / arsip..." onkeyup="searchTxTable()">
                </div>

                <div class="table-responsive">
                    <table class="table-custom" id="tableMonitoring">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Peminjam</th>
                                <th>Arsip</th>
                                <th>Batas Kembali</th>
                                <th>Status Pinjam</th>
                                <th>Denda & Status</th>
                                <th style="text-align: right;">Aksi Cepat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_transaksi && mysqli_num_rows($result_transaksi) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result_transaksi)): 
                                    $info_d = get_info_denda($row);
                                    $status_class = ($row['status_peminjaman'] === 'Dipinjam') ? 'pinjam' : 'kembali';
                                    $is_telat = $info_d['is_terlambat'] ? '1' : '0';
                                ?>
                                    <tr data-status="<?= strtolower($row['status_peminjaman']) ?>" data-telat="<?= $is_telat ?>">
                                        <td><strong>#<?= (int)$row['id_peminjaman'] ?></strong></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['nama_pengguna'] ?? 'Umum') ?></strong>
                                            <?php if (!empty($row['nim'])): ?>
                                                <div style="font-size: 10px; color: var(--text-muted);"><?= htmlspecialchars($row['nim']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="max-width: 170px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($row['nama_arsip'] ?? '-') ?>">
                                                <?= htmlspecialchars($row['nama_arsip'] ?? '-') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['tanggal_kembali'])): ?>
                                                <span><?= date('d/m/Y', strtotime($row['tanggal_kembali'])) ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge-status <?= $status_class ?>">
                                                <?= htmlspecialchars($row['status_peminjaman']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($info_d['harus_bayar']): ?>
                                                <span class="badge-denda-telat">⚠️ Telat <?= $info_d['hari_terlambat'] ?> Hari</span>
                                                <span class="nominal-denda-text"><?= $info_d['nominal_format'] ?></span>
                                            <?php elseif ($row['status_denda'] === 'Lunas'): ?>
                                                <span class="badge-denda-lunas">✓ Lunas</span>
                                                <div style="font-size: 10px; color: #047857;"><?= $info_d['nominal_format'] ?></div>
                                            <?php else: ?>
                                                <span class="badge-denda-bebas">✓ Bebas Denda</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <?php if ($row['status_peminjaman'] === 'Dipinjam'): ?>
                                                <a href="index.php?selesai_id=<?= (int)$row['id_peminjaman'] ?>" 
                                                   class="filter-btn" 
                                                   style="background: #10b981; color: white; text-decoration: none; padding: 4px 8px; font-size: 11px;"
                                                   onclick="return confirm('Tandai arsip ini sudah dikembalikan hari ini?')">
                                                   ✓ Selesai
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($info_d['harus_bayar']): ?>
                                                <button type="button" 
                                                        class="filter-btn" 
                                                        style="background: #059669; color: white; padding: 4px 8px; font-size: 11px;"
                                                        onclick="bukaModalDenda(<?= (int)$row['id_peminjaman'] ?>, '<?= addslashes(htmlspecialchars($row['nama_arsip'] ?? 'Arsip', ENT_QUOTES)) ?>', <?= (int)$info_d['hari_terlambat'] ?>, '<?= $info_d['nominal_format'] ?>', <?= (int)$info_d['nominal'] ?>)">
                                                    💳 QRIS
                                                </button>
                                                <a href="index.php?lunas_id=<?= (int)$row['id_peminjaman'] ?>" 
                                                   class="filter-btn" 
                                                   style="background: #f1f5f9; color: #334155; text-decoration: none; padding: 4px 8px; font-size: 11px;"
                                                   onclick="return confirm('Tandai denda transaksi ini sudah LUNAS?')">
                                                   ✓ Lunas
                                                </a>
                                            <?php endif; ?>

                                            <a href="edit.php?id=<?= (int)$row['id_peminjaman'] ?>" 
                                               style="margin-left: 4px; color: #64748b; text-decoration: none; font-size: 11px;" 
                                               title="Edit Data Peminjaman">
                                               ✏️
                                            </a>
                                            <a href="hapus.php?id=<?= (int)$row['id_peminjaman'] ?>" 
                                               style="margin-left: 4px; color: #ef4444; text-decoration: none; font-size: 11px;" 
                                               onclick="return confirm('Yakin ingin menghapus riwayat peminjaman ini?')"
                                               title="Hapus">
                                               🗑️
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                        Belum ada riwayat transaksi peminjaman tercatat.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SISI KANAN: FORMULIR PEMINJAMAN CEPAT -->
            <div class="panel-box" id="form-peminjaman">
                <div class="panel-header-custom">
                    <div class="panel-title-text">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Catat Peminjaman Cepat
                    </div>
                </div>

                <form method="POST" action="" style="max-width: 100%; padding: 0; background: transparent; border: none; box-shadow: none; margin: 0;">
                    <input type="hidden" name="action" value="tambah_transaksi">
                    <input type="hidden" name="status_peminjaman" value="Dipinjam">

                    <div class="form-group-modern">
                        <label for="id_arsip">PILIH DOKUMEN ARSIP</label>
                        <select name="id_arsip" id="id_arsip" required>
                            <option value="">-- Pilih Arsip --</option>
                            <?php 
                            if ($list_arsip) {
                                mysqli_data_seek($list_arsip, 0);
                                while ($ar = mysqli_fetch_assoc($list_arsip)) {
                                    echo '<option value="' . (int)$ar['id_arsip'] . '">' . htmlspecialchars($ar['nama_arsip']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group-modern">
                        <label for="id_pengguna">ANGGOTA PEMINJAM</label>
                        <select name="id_pengguna" id="id_pengguna" required>
                            <option value="">-- Pilih Anggota UKM --</option>
                            <?php 
                            if ($list_pengguna) {
                                mysqli_data_seek($list_pengguna, 0);
                                while ($u = mysqli_fetch_assoc($list_pengguna)) {
                                    $nim_label = !empty($u['nim']) ? ' (' . $u['nim'] . ')' : '';
                                    echo '<option value="' . (int)$u['id_pengguna'] . '">' . htmlspecialchars($u['nama_pengguna']) . $nim_label . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group-modern">
                        <label for="adm_tgl_pinjam">TANGGAL PINJAM</label>
                        <input type="date" name="tanggal_pinjam" id="adm_tgl_pinjam" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group-modern">
                        <label for="adm_tgl_kembali">BATAS TANGGAL PENGEMBALIAN</label>
                        <input type="date" name="tanggal_kembali" id="adm_tgl_kembali" value="<?= date('Y-m-d', strtotime('+5 days')) ?>">
                        <small style="color: var(--text-muted); font-size: 10.5px; display: block; margin-top: 4px;">
                            * Otomatis dihitung 5 hari ke depan terhitung sejak tanggal pinjam.
                        </small>
                    </div>

                    <button type="submit" class="btn-submit-tx">
                        + Simpan Transaksi Peminjaman
                    </button>
                </form>

                <div class="info-card-rule">
                    <h4>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Aturan Keterlambatan SIRAPI
                    </h4>
                    <p style="margin: 0; line-height: 1.5;">
                        Peminjaman maksimal <strong>5 hari</strong>. Jika lewat, denda hari 1 (Rp 20.000), hari 2 (Rp 22.500), hari 3 (Rp 25.000), hari 4 (Rp 30.000), dan +Rp 5.000/hari berikutnya.
                    </p>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- ============================================== -->
        <!-- TAMPILAN PENGGUNA BIASA (PEMINJAMAN SAYA)      -->
        <!-- ============================================== -->
        <div class="user-stat-summary">
            <div class="user-stat-card">
                <div class="label">Sedang Dipinjam</div>
                <div class="value"><?= $user_pinjam_count ?></div>
            </div>
            <div class="user-stat-card">
                <div class="label">Sudah Dikembalikan</div>
                <div class="value"><?= $user_kembali_count ?></div>
            </div>
            <div class="user-stat-card <?= $user_denda_total > 0 ? 'alert' : '' ?>">
                <div class="label">Tagihan Denda Keterlambatan</div>
                <div class="value">
                    <?= $user_denda_total > 0 ? 'Rp ' . number_format($user_denda_total, 0, ',', '.') : 'Bebas Denda' ?>
                </div>
            </div>
        </div>

        <div class="panel-box">
            <div class="panel-header-custom">
                <div class="panel-title-text">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Daftar Riwayat Peminjaman Berkas Arsip
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama Arsip</th>
                            <th>Tanggal Pinjam</th>
                            <th>Batas Kembali</th>
                            <th>Status Pinjam</th>
                            <th>Keterlambatan & Tagihan</th>
                            <th style="text-align: right;">Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows_cache)): ?>
                            <?php foreach ($rows_cache as $row): 
                                $info = get_info_denda($row);
                                $status_class = ($row['status_peminjaman'] === 'Dipinjam') ? 'pinjam' : 'kembali';
                            ?>
                                <tr>
                                    <td><strong>#<?= (int)$row['id_peminjaman'] ?></strong></td>
                                    <td><strong><?= htmlspecialchars($row['nama_arsip'] ?? 'Dokumen Arsip') ?></strong></td>
                                    <td><?= date('d/m/Y', strtotime($row['tanggal_pinjam'])) ?></td>
                                    <td><?= !empty($row['tanggal_kembali']) ? date('d/m/Y', strtotime($row['tanggal_kembali'])) : '-' ?></td>
                                    <td>
                                        <span class="badge-status <?= $status_class ?>">
                                            <?= htmlspecialchars($row['status_peminjaman']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($info['is_terlambat']): ?>
                                            <span class="badge-denda-telat">⚠️ Telat <?= $info['hari_terlambat'] ?> Hari</span>
                                            <span class="nominal-denda-text"><?= $info['nominal_format'] ?></span>
                                            <?php if ($info['status'] === 'Lunas'): ?>
                                                <div style="font-size: 10px; color: #047857; margin-top: 2px;">✓ Lunas (<?= htmlspecialchars($row['metode_pembayaran'] ?? 'QRIS') ?>)</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge-denda-bebas">✓ Bebas Denda</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if ($info['harus_bayar']): ?>
                                            <button type="button" 
                                                    class="filter-btn" 
                                                    style="background: #059669; color: white;"
                                                    onclick="bukaModalDenda(<?= (int)$row['id_peminjaman'] ?>, '<?= addslashes(htmlspecialchars($row['nama_arsip'] ?? 'Arsip', ENT_QUOTES)) ?>', <?= (int)$info['hari_terlambat'] ?>, '<?= $info['nominal_format'] ?>', <?= (int)$info['nominal'] ?>)">
                                                💳 Bayar QRIS / E-Wallet
                                            </button>
                                        <?php elseif ($row['status_denda'] === 'Lunas'): ?>
                                            <span class="badge-denda-lunas">✓ Sudah Dibayar</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 11px;">Tidak ada tagihan</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                    Anda belum memiliki riwayat peminjaman arsip.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- RENDER POPUP MODAL PEMBAYARAN DENDA (QRIS & E-WALLET) -->
<?php render_modal_pembayaran_denda(); ?>

<script>
    // Auto-calculate +5 days on loan date change
    const admPinjam = document.getElementById('adm_tgl_pinjam');
    const admKembali = document.getElementById('adm_tgl_kembali');
    if (admPinjam && admKembali) {
        admPinjam.addEventListener('change', function() {
            if (this.value) {
                const d = new Date(this.value + 'T00:00:00');
                d.setDate(d.getDate() + 5);
                admKembali.value = d.toISOString().split('T')[0];
            }
        });
    }

    // Live Filter Tabs for Admin Table
    function filterTable(type) {
        const btns = document.querySelectorAll('.filter-pills .filter-btn');
        btns.forEach(b => b.classList.remove('active'));
        if (event && event.target) event.target.classList.add('active');

        const rows = document.querySelectorAll('#tableMonitoring tbody tr');
        rows.forEach(r => {
            const status = r.getAttribute('data-status');
            const telat = r.getAttribute('data-telat');

            if (type === 'all') {
                r.style.display = '';
            } else if (type === 'dipinjam') {
                r.style.display = (status === 'dipinjam') ? '' : 'none';
            } else if (type === 'kembali') {
                r.style.display = (status === 'kembali') ? '' : 'none';
            } else if (type === 'telat') {
                r.style.display = (telat === '1') ? '' : 'none';
            }
        });
    }

    // Instant Live Search Table
    function searchTxTable() {
        const input = document.getElementById('txSearch').value.toLowerCase();
        const rows = document.querySelectorAll('#tableMonitoring tbody tr');
        rows.forEach(r => {
            const text = r.innerText.toLowerCase();
            r.style.display = text.includes(input) ? '' : 'none';
        });
    }
</script>

</body>
</html>