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
$active_view = isset($_GET['view']) && $_GET['view'] === 'form' ? 'form' : 'riwayat';

if (isset($_GET['sukses_pinjam'])) {
    $id_baru = (int)$_GET['sukses_pinjam'];
    $pesan_sukses = "Peminjaman baru (#$id_baru) berhasil diajukan! <a href='cetak_struk.php?id=$id_baru' target='_blank' style='display:inline-flex; align-items:center; gap:4px; margin-left:10px; background:#047857; color:#ffffff; padding:4px 10px; border-radius:6px; font-weight:700; text-decoration:none;'>🧾 Cetak Struk Bukti Peminjaman</a>";
}

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
        $active_view = 'form';
    } else {
        // Cek ketersediaan stok arsip
        $q_cek_stok = mysqli_query($conn, "
            SELECT a.jumlah_arsip, COUNT(CASE WHEN p.status_peminjaman = 'Dipinjam' THEN 1 END) as sedang_dipinjam 
            FROM arsip a 
            LEFT JOIN peminjaman p ON a.id_arsip = p.id_arsip 
            WHERE a.id_arsip = $id_arsip
            GROUP BY a.id_arsip, a.jumlah_arsip
        ");
        $boleh_pinjam = true;
        if ($q_cek_stok && $r_stok = mysqli_fetch_assoc($q_cek_stok)) {
            $tersedia = max(0, (int)$r_stok['jumlah_arsip'] - (int)$r_stok['sedang_dipinjam']);
            if ($tersedia <= 0) {
                $boleh_pinjam = false;
                $pesan_error = "Arsip ini sedang habis dipinjam seluruhnya (" . (int)$r_stok['sedang_dipinjam'] . " berkas aktif dipinjam).";
                $active_view = 'form';
            }
        }

        if ($boleh_pinjam) {
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
                    $new_id = mysqli_insert_id($conn);
                    $pesan_sukses = "Transaksi peminjaman baru (#$new_id) berhasil dicatat (Batas kembali: $tgl_kembali_val)! <a href='cetak_struk.php?id=$new_id' target='_blank' style='display:inline-flex; align-items:center; gap:4px; margin-left:10px; background:#047857; color:#ffffff; padding:4px 10px; border-radius:6px; font-weight:700; text-decoration:none;'>🧾 Cetak Struk Bukti</a>";
                    $active_view = 'riwayat';
                } else {
                    $pesan_error = "Gagal mencatat transaksi: " . mysqli_stmt_error($stmt);
                    $active_view = 'form';
                }
            } else {
                $pesan_error = "Gagal menyiapkan query: " . mysqli_error($conn);
                $active_view = 'form';
            }
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

// 5. Filter Waktu & Analisis Diagram Garis (Khusus Admin)
$filter_waktu = $_GET['filter_waktu'] ?? '30_hari';
$tgl_mulai_req = trim($_GET['tgl_mulai'] ?? '');
$tgl_selesai_req = trim($_GET['tgl_selesai'] ?? '');

$tgl_hari_ini = date('Y-m-d');
$label_filter_text = '30 Hari Terakhir (Per Bulan)';
$tgl_awal = date('Y-m-d', strtotime('-29 days'));
$tgl_akhir = $tgl_hari_ini;

if ($filter_waktu === '7_hari') {
    $tgl_awal = date('Y-m-d', strtotime('-6 days'));
    $tgl_akhir = $tgl_hari_ini;
    $label_filter_text = '7 Hari Terakhir (Per Minggu)';
} elseif ($filter_waktu === 'minggu_ini') {
    $tgl_awal = date('Y-m-d', strtotime('monday this week'));
    $tgl_akhir = date('Y-m-d', strtotime('sunday this week'));
    $label_filter_text = 'Minggu Ini (' . date('d M', strtotime($tgl_awal)) . ' - ' . date('d M Y', strtotime($tgl_akhir)) . ')';
} elseif ($filter_waktu === 'bulan_ini') {
    $tgl_awal = date('Y-m-01');
    $tgl_akhir = date('Y-m-t');
    $label_filter_text = 'Bulan Ini (' . date('F Y') . ')';
} elseif ($filter_waktu === 'custom' && !empty($tgl_mulai_req) && !empty($tgl_selesai_req)) {
    $tgl_awal = $tgl_mulai_req;
    $tgl_akhir = $tgl_selesai_req;
    if ($tgl_awal > $tgl_akhir) {
        $tmp = $tgl_awal;
        $tgl_awal = $tgl_akhir;
        $tgl_akhir = $tmp;
    }
    $label_filter_text = 'Rentang Kustom: ' . date('d/m/Y', strtotime($tgl_awal)) . ' s/d ' . date('d/m/Y', strtotime($tgl_akhir));
} elseif ($filter_waktu === 'semua') {
    $q_bound = mysqli_query($conn, "SELECT MIN(tanggal_pinjam) as min_t, MAX(tanggal_pinjam) as max_t FROM peminjaman");
    $r_bound = mysqli_fetch_assoc($q_bound);
    $tgl_awal = $r_bound['min_t'] ?? date('Y-m-01');
    $tgl_akhir = $r_bound['max_t'] ?? $tgl_hari_ini;
    $label_filter_text = 'Semua Riwayat Waktu';
}

// 6. Query Data Berdasarkan Role
if ($role === 'admin') {
    // Dropdown list untuk form cepat admin (Lengkap dengan hitungan stok tersedia)
    $list_arsip = mysqli_query($conn, "
        SELECT 
            a.id_arsip, a.nama_arsip, a.jumlah_arsip,
            COUNT(CASE WHEN p.status_peminjaman = 'Dipinjam' THEN 1 END) as sedang_dipinjam
        FROM arsip a
        LEFT JOIN peminjaman p ON a.id_arsip = p.id_arsip
        GROUP BY a.id_arsip, a.nama_arsip, a.jumlah_arsip
        ORDER BY a.nama_arsip ASC
    ");
    $list_pengguna = mysqli_query($conn, "SELECT id_pengguna, nama_pengguna, nim FROM pengguna ORDER BY nama_pengguna ASC");

    // Ambil data agregat harian untuk diagram garis
    $map_chart_counts = [];
    $sql_chart = "
        SELECT DATE(tanggal_pinjam) AS tgl, COUNT(*) AS jml
        FROM peminjaman
        WHERE tanggal_pinjam BETWEEN '$tgl_awal' AND '$tgl_akhir'
        GROUP BY DATE(tanggal_pinjam)
    ";
    $res_chart = mysqli_query($conn, $sql_chart);
    if ($res_chart) {
        while ($rc = mysqli_fetch_assoc($res_chart)) {
            $map_chart_counts[$rc['tgl']] = (int)$rc['jml'];
        }
    }

    // Bangun rentang tanggal lengkap untuk sumbu X grafik
    $chart_labels = [];
    $chart_values = [];
    $total_periode_pinjam = 0;
    $max_pinjam_hari = 0;
    $peak_day_str = '-';
    $total_hari_count = 0;

    $dt_curr = new DateTime($tgl_awal);
    $dt_end = new DateTime($tgl_akhir);
    $dt_end->modify('+1 day');
    $period_range = new DatePeriod($dt_curr, new DateInterval('P1D'), $dt_end);

    foreach ($period_range as $d) {
        $ymd = $d->format('Y-m-d');
        $val = $map_chart_counts[$ymd] ?? 0;
        $chart_labels[] = $d->format('d M');
        $chart_values[] = $val;
        $total_periode_pinjam += $val;
        $total_hari_count++;
        if ($val > $max_pinjam_hari) {
            $max_pinjam_hari = $val;
            $peak_day_str = $d->format('d M Y') . " ($val berkas)";
        }
    }
    $avg_harian = ($total_hari_count > 0) ? round($total_periode_pinjam / $total_hari_count, 1) : 0;

    // Hitung status sirkulasi pada rentang waktu terpilih
    $stat_dipinjam_periode = 0;
    $stat_kembali_periode = 0;
    $q_stat = mysqli_query($conn, "
        SELECT status_peminjaman, COUNT(*) as jml 
        FROM peminjaman 
        WHERE tanggal_pinjam BETWEEN '$tgl_awal' AND '$tgl_akhir'
        GROUP BY status_peminjaman
    ");
    if ($q_stat) {
        while ($sp = mysqli_fetch_assoc($q_stat)) {
            if ($sp['status_peminjaman'] === 'Dipinjam') $stat_dipinjam_periode = (int)$sp['jml'];
            if ($sp['status_peminjaman'] === 'Dikembalikan') $stat_kembali_periode = (int)$sp['jml'];
        }
    }

    // Ambil data transaksi peminjaman untuk admin monitoring (terfilter sesuai periode)
    $where_clause = ($filter_waktu === 'semua') 
        ? "" 
        : "WHERE p.tanggal_pinjam BETWEEN '$tgl_awal' AND '$tgl_akhir'";

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
        $where_clause
        ORDER BY p.id_peminjaman DESC
    ";
    $result_transaksi = mysqli_query($conn, $sql_transaksi);

    // ========================================================
    // 7. HANDLER EKSPOR DATA PDF & CSV (KHUSUS ADMIN)
    // ========================================================
    if (isset($_GET['export'])) {
        $export_type = strtolower(trim($_GET['export']));

        // Query data untuk ekspor sesuai $where_clause
        $res_export = mysqli_query($conn, $sql_transaksi);

        if ($export_type === 'csv') {
            // Output file CSV (Dapat dibuka langsung di Excel / Sheets)
            $filename = "rekap_peminjaman_SIRAPI_" . date('Ymd_His') . ".csv";
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $out = fopen('php://output', 'w');
            // UTF-8 BOM agar Microsoft Excel mengenali format karakter dengan benar
            fputs($out, "\xEF\xBB\xBF");

            // Baris Header CSV
            fputcsv($out, [
                'ID Transaksi',
                'Nama Anggota Peminjam',
                'NIM',
                'Nama Dokumen Arsip',
                'Tanggal Pinjam',
                'Batas Pengembalian',
                'Status Peminjaman',
                'Tanggal Dikembalikan',
                'Hari Terlambat',
                'Nominal Denda (Rp)',
                'Status Denda',
                'Metode Pembayaran'
            ]);

            if ($res_export) {
                while ($r_exp = mysqli_fetch_assoc($res_export)) {
                    $info_e = get_info_denda($r_exp);
                    fputcsv($out, [
                        $r_exp['id_peminjaman'],
                        $r_exp['nama_pengguna'] ?? 'Umum',
                        $r_exp['nim'] ?? '-',
                        $r_exp['nama_arsip'] ?? '-',
                        $r_exp['tanggal_pinjam'],
                        $r_exp['tanggal_kembali'] ?? '-',
                        $r_exp['status_peminjaman'],
                        $r_exp['tanggal_dikembalikan'] ?? '-',
                        $info_e['hari_terlambat'],
                        $info_e['nominal'],
                        $info_e['status'],
                        $r_exp['metode_pembayaran'] ?? '-'
                    ]);
                }
            }
            fclose($out);
            exit;

        } elseif ($export_type === 'pdf') {
            // Render template cetak resmi Print-to-PDF dokumen SIRAPI
            ?>
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="UTF-8">
                <title>Laporan Rekapitulasi Peminjaman Arsip - SIRAPI</title>
                <style>
                    body {
                        font-family: 'Segoe UI', Arial, sans-serif;
                        margin: 24px;
                        color: #1e293b;
                        font-size: 11.5px;
                        line-height: 1.4;
                    }
                    .kop-surat {
                        border-bottom: 3px double #047857;
                        padding-bottom: 12px;
                        margin-bottom: 18px;
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                    }
                    .kop-brand h1 {
                        margin: 0;
                        font-size: 18px;
                        color: #047857;
                        text-transform: uppercase;
                        letter-spacing: -0.3px;
                    }
                    .kop-brand p {
                        margin: 3px 0 0;
                        font-size: 11px;
                        color: #64748b;
                    }
                    .kop-meta {
                        text-align: right;
                        font-size: 10.5px;
                        color: #475569;
                    }
                    .summary-grid {
                        display: flex;
                        gap: 12px;
                        margin-bottom: 18px;
                    }
                    .summary-card {
                        flex: 1;
                        background: #f8fafc;
                        border: 1px solid #e2e8f0;
                        border-radius: 8px;
                        padding: 10px 14px;
                    }
                    .summary-card .lbl {
                        font-size: 9.5px;
                        font-weight: bold;
                        color: #64748b;
                        text-transform: uppercase;
                    }
                    .summary-card .val {
                        font-size: 16px;
                        font-weight: bold;
                        color: #047857;
                        margin-top: 2px;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 11px;
                    }
                    th {
                        background: #047857;
                        color: #ffffff;
                        padding: 8px 10px;
                        text-align: left;
                        font-size: 10px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        border: 1px solid #047857;
                    }
                    td {
                        padding: 8px 10px;
                        border: 1px solid #cbd5e1;
                        vertical-align: middle;
                    }
                    tr:nth-child(even) {
                        background: #f8fafc;
                    }
                    .badge-pill {
                        display: inline-block;
                        padding: 2px 6px;
                        border-radius: 4px;
                        font-size: 9.5px;
                        font-weight: bold;
                    }
                    .badge-dipinjam {
                        background: #fef3c7;
                        color: #b45309;
                    }
                    .badge-dikembalikan {
                        background: #dcfce7;
                        color: #15803d;
                    }
                    .text-telat {
                        color: #dc2626;
                        font-weight: bold;
                    }
                    .text-lunas {
                        color: #047857;
                        font-weight: bold;
                    }
                    .ttd-wrapper {
                        margin-top: 36px;
                        display: flex;
                        justify-content: flex-end;
                    }
                    .ttd-box {
                        text-align: center;
                        font-size: 11px;
                        width: 220px;
                    }
                    .no-print-toolbar {
                        background: #f1f5f9;
                        border: 1px solid #cbd5e1;
                        padding: 12px 18px;
                        border-radius: 10px;
                        margin-bottom: 20px;
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                    }
                    .btn-print {
                        background: #047857;
                        color: white;
                        border: none;
                        padding: 8px 18px;
                        border-radius: 6px;
                        font-weight: bold;
                        cursor: pointer;
                        font-size: 12px;
                    }
                    .btn-back-link {
                        color: #64748b;
                        text-decoration: none;
                        font-weight: bold;
                        font-size: 12px;
                    }
                    @media print {
                        .no-print-toolbar {
                            display: none !important;
                        }
                        body {
                            margin: 10mm;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="no-print-toolbar">
                    <div>
                        <strong>Dokumen Siap Cetak / Unduh PDF</strong>
                        <div style="font-size: 11px; color: #64748b;">Gunakan tombol di sebelah kanan atau tekan Ctrl+P lalu pilih "Save as PDF".</div>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button onclick="window.print()" class="btn-print">🖨️ Cetak / Unduh PDF</button>
                        <a href="javascript:window.close()" class="btn-back-link">Tutup</a>
                    </div>
                </div>

                <div class="kop-surat">
                    <div class="kop-brand">
                        <h1>SIRAPI • Sistem Kearsipan UKM</h1>
                        <p>Laporan Resmi Rekapitulasi Sirkulasi Peminjaman Berkas Arsip & Monitoring Denda</p>
                    </div>
                    <div class="kop-meta">
                        Dicetak pada: <strong><?= date('d/m/Y H:i') ?> WIB</strong><br>
                        Petugas: <strong><?= htmlspecialchars($nama_user) ?> (Admin)</strong>
                    </div>
                </div>

                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="lbl">Filter Periode Laporan</div>
                        <div class="val" style="font-size: 13px; margin-top: 4px;"><?= htmlspecialchars($label_filter_text) ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="lbl">Total Transaksi</div>
                        <div class="val"><?= mysqli_num_rows($res_export) ?> Berkas</div>
                    </div>
                    <div class="summary-card">
                        <div class="lbl">Sirkulasi Aktif</div>
                        <div class="val" style="color: #b45309;"><?= $stat_dipinjam_periode ?> Dipinjam</div>
                    </div>
                    <div class="summary-card">
                        <div class="lbl">Sudah Selesai</div>
                        <div class="val" style="color: #15803d;"><?= $stat_kembali_periode ?> Kembali</div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width: 25px;">No</th>
                            <th>Anggota Peminjam</th>
                            <th>NIM</th>
                            <th>Dokumen Arsip</th>
                            <th>Tanggal Pinjam</th>
                            <th>Batas Kembali</th>
                            <th>Status Pinjam</th>
                            <th>Keterlambatan & Denda</th>
                            <th>Status Denda</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($res_export) > 0): ?>
                            <?php $nomor = 1; while ($row_p = mysqli_fetch_assoc($res_export)): 
                                $info_p = get_info_denda($row_p);
                            ?>
                                <tr>
                                    <td style="text-align: center;"><?= $nomor++ ?></td>
                                    <td><strong><?= htmlspecialchars($row_p['nama_pengguna'] ?? 'Umum') ?></strong></td>
                                    <td><?= htmlspecialchars($row_p['nim'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row_p['nama_arsip'] ?? '-') ?></td>
                                    <td><?= date('d/m/Y', strtotime($row_p['tanggal_pinjam'])) ?></td>
                                    <td><?= !empty($row_p['tanggal_kembali']) ? date('d/m/Y', strtotime($row_p['tanggal_kembali'])) : '-' ?></td>
                                    <td>
                                        <span class="badge-pill <?= $row_p['status_peminjaman'] === 'Dipinjam' ? 'badge-dipinjam' : 'badge-dikembalikan' ?>">
                                            <?= htmlspecialchars($row_p['status_peminjaman']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($info_p['harus_bayar']): ?>
                                            <span class="text-telat">Telat <?= $info_p['hari_terlambat'] ?> Hari</span> (<?= $info_p['nominal_format'] ?>)
                                        <?php elseif ($row_p['status_denda'] === 'Lunas'): ?>
                                            <span class="text-lunas">Lunas (<?= $info_p['nominal_format'] ?>)</span>
                                        <?php else: ?>
                                            Bebas Denda
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row_p['status_denda']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 24px; color: #64748b;">
                                    Tidak ada data transaksi peminjaman pada periode waktu ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="ttd-wrapper">
                    <div class="ttd-box">
                        <p><?= date('d F Y') ?><br>Staff Pengelola Arsip UKM,</p>
                        <div style="height: 55px;"></div>
                        <p><strong>( <?= htmlspecialchars($nama_user) ?> )</strong></p>
                    </div>
                </div>

                <script>
                    window.onload = function() {
                        window.print();
                    };
                </script>
            </body>
            </html>
            <?php
            exit;
        }
    }
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
    <title><?= $role === 'admin' ? 'Monitoring & Riwayat Peminjaman' : 'Peminjaman Saya' ?> - SIRAPI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php if ($role === 'admin'): ?>
        <!-- LIBRARY CHART.JS RESMI VIA CDN -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
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
            max-width: 1380px;
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

        /* ======================================================== */
        /* ANALYTICS & LINE CHART PANEL (KHUSUS ADMIN)              */
        /* ======================================================== */
        .analytics-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 22px 24px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-subtle);
            margin-bottom: 24px;
        }

        .analytics-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .analytics-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .analytics-title-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--primary-soft);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--primary-border);
            flex-shrink: 0;
        }

        .analytics-title h2 {
            font-size: 15.5px;
            font-weight: 800;
            color: var(--text-main);
            margin: 0;
        }

        .analytics-title p {
            font-size: 11.5px;
            color: var(--text-muted);
            margin: 2px 0 0 0;
        }

        .active-range-badge {
            background: #f1f5f9;
            color: #334155;
            padding: 6px 12px;
            border-radius: var(--radius-pill);
            font-size: 11.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--border-color);
        }

        /* FILTER BAR & DOWNLOAD ACTIONS */
        .analytics-controls-bar {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 10px 14px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }

        .quick-filter-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn-filter-preset {
            padding: 6px 12px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            color: #475569;
            background: #ffffff;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-filter-preset:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-filter-preset.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(4, 120, 87, 0.25);
        }

        /* TOMBOL UNDUH PDF & CSV */
        .export-actions-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-export-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 13px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-export-action.pdf {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .btn-export-action.pdf:hover {
            background: #dc2626;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
        }

        .btn-export-action.csv {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .btn-export-action.csv:hover {
            background: #047857;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(4, 120, 87, 0.25);
        }

        /* PANEL KOTAK PILIH TANGGAL (TOGGLEABLE / ACCORDION) */
        .custom-date-box {
            background: #ffffff;
            border: 1px solid var(--primary-border);
            border-top: 3px solid var(--primary);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 18px;
            box-shadow: 0 4px 16px rgba(4, 120, 87, 0.08);
            animation: fadeIn 0.25s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .custom-date-box h4 {
            font-size: 11.5px;
            font-weight: 800;
            color: var(--primary-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .custom-date-form-grid {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .custom-date-form-grid .date-input-wrap {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .custom-date-form-grid .date-input-wrap span {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        .custom-date-form-grid input[type="date"] {
            padding: 7px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 12px;
            outline: none;
            background: #ffffff;
            font-family: inherit;
            color: #0f172a;
        }

        .custom-date-form-grid input[type="date"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.12);
        }

        .btn-apply-custom-date {
            padding: 8px 18px;
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 14px;
        }

        .btn-apply-custom-date:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-close-custom-date {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid var(--border-color);
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            margin-top: 14px;
            transition: all 0.2s;
        }

        .btn-close-custom-date:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* 4 METRIC CHIPS */
        .analytics-metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .metric-chip-card {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            flex-direction: column;
        }

        .metric-chip-label {
            font-size: 10.5px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .metric-chip-value {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-main);
            margin-top: 4px;
        }

        .metric-chip-sub {
            font-size: 10.5px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .chart-container-box {
            position: relative;
            width: 100%;
            height: 250px;
        }

        /* ======================================================== */
        /* TAB SWITCHER & FOCUSED VIEW NAVIGATION                    */
        /* ======================================================== */
        .view-switch-nav {
            display: inline-flex;
            background: #e2e8f0;
            padding: 4px;
            border-radius: 10px;
            margin: 20px 0 16px;
            gap: 4px;
        }

        .btn-view-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            border: none;
            background: transparent;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-view-tab:hover {
            color: #0f172a;
        }

        .btn-view-tab.active {
            background: #ffffff;
            color: var(--primary);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
        }

        .btn-switch-to-form {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #047857;
            color: #ffffff;
            border: none;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-switch-to-form:hover {
            background: #064e3b;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(4, 120, 87, 0.25);
        }

        .btn-cancel-form {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cancel-form:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        /* FORM PINJAM CEPAT FOCUSED VIEW (TIDAK NYEMPIL DI SAMPING) */
        .form-pinjam-container {
            max-width: 640px;
            margin: 10px auto 40px;
        }

        .form-pinjam-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-subtle);
            overflow: hidden;
        }

        .form-pinjam-header {
            background: linear-gradient(135deg, #047857 0%, #064e3b 100%);
            color: #ffffff;
            padding: 22px 26px;
        }

        .form-pinjam-header h2 {
            font-size: 17px;
            font-weight: 800;
            margin: 0 0 4px;
            color: #ffffff;
            border: none !important;
            padding: 0 !important;
        }

        .form-pinjam-header p {
            margin: 0;
            font-size: 12px;
            color: #a7f3d0;
        }

        .form-pinjam-body {
            padding: 24px 26px;
        }

        .panel-box {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 22px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-subtle);
            width: 100%;
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

        .active-filter-indicator {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 11.5px;
            color: #065f46;
            margin-bottom: 14px;
        }

        .active-filter-indicator a {
            color: #047857;
            font-weight: 700;
            text-decoration: underline;
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
                <h1><?= $role === 'admin' ? 'Monitoring & Riwayat Peminjaman' : 'Riwayat Peminjaman & Denda Saya' ?></h1>
                <p><?= $role === 'admin' ? 'Visualisasi tren sirkulasi arsip, unduh rekapitulasi PDF & CSV, audit batas pengembalian, dan formulir pinjam cepat' : 'Daftar arsip yang Anda pinjam beserta tanggal pengembalian dan status pembayaran denda' ?></p>
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
            <div><?= $pesan_sukses ?></div>
        </div>
    <?php endif; ?>
    <?php if ($pesan_error !== ""): ?>
        <div class="alert-custom error">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div><?= htmlspecialchars($pesan_error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
        <!-- ======================================================== -->
        <!-- 1. ANALISIS & DIAGRAM GARIS TREN PEMINJAMAN (LINE CHART) -->
        <!-- ======================================================== -->
        <section class="analytics-card">
            <div class="analytics-header">
                <div class="analytics-title">
                    <div class="analytics-title-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                    <div>
                        <h2>Diagram Garis Tren Sirkulasi Peminjaman</h2>
                        <p>Mempermudah monitoring track intensitas peminjaman arsip berdasarkan rentang waktu</p>
                    </div>
                </div>
                <div class="active-range-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span><?= htmlspecialchars($label_filter_text) ?></span>
                </div>
            </div>

            <!-- CONTROLS BAR: PRESETS + TOMBOL TOGGLE FILTER TANGGAL + UNDUH PDF & CSV -->
            <div class="analytics-controls-bar">
                <!-- PRESET BUTTONS (MINGGU / BULAN / SEMUA) -->
                <div class="quick-filter-group">
                    <a href="index.php?filter_waktu=7_hari" class="btn-filter-preset <?= $filter_waktu === '7_hari' ? 'active' : '' ?>">
                        7 Hari Terakhir
                    </a>
                    <a href="index.php?filter_waktu=minggu_ini" class="btn-filter-preset <?= $filter_waktu === 'minggu_ini' ? 'active' : '' ?>">
                        Minggu Ini
                    </a>
                    <a href="index.php?filter_waktu=30_hari" class="btn-filter-preset <?= $filter_waktu === '30_hari' ? 'active' : '' ?>">
                        30 Hari Terakhir
                    </a>
                    <a href="index.php?filter_waktu=bulan_ini" class="btn-filter-preset <?= $filter_waktu === 'bulan_ini' ? 'active' : '' ?>">
                        Bulan Ini
                    </a>
                    <a href="index.php?filter_waktu=semua" class="btn-filter-preset <?= $filter_waktu === 'semua' ? 'active' : '' ?>">
                        Semua Waktu
                    </a>

                    <!-- TOMBOL TOGGLE BUKA/TUTUP FORM FILTER TANGGAL KUSTOM -->
                    <button type="button" 
                            class="btn-filter-preset <?= $filter_waktu === 'custom' ? 'active' : '' ?>" 
                            onclick="toggleCustomDateBox()" 
                            id="btnToggleFilter" 
                            style="cursor: pointer; background: <?= $filter_waktu === 'custom' ? 'var(--primary)' : '#f8fafc' ?>;">
                        <span>🗓️ Filter Tanggal</span>
                        <span id="toggleArrow" style="font-size: 9px; margin-left: 2px;"><?= $filter_waktu === 'custom' ? '▴' : '▾' ?></span>
                    </button>
                </div>

                <!-- GRUP TOMBOL UNDUH PDF & CSV SESUAI FILTER PERIODE AKTIF -->
                <div class="export-actions-group">
                    <a href="index.php?export=pdf&filter_waktu=<?= urlencode($filter_waktu) ?>&tgl_mulai=<?= urlencode($tgl_mulai_req) ?>&tgl_selesai=<?= urlencode($tgl_selesai_req) ?>" 
                       target="_blank" 
                       class="btn-export-action pdf" 
                       title="Cetak atau Unduh Laporan Rekapitulasi dalam format PDF Resmi">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Unduh PDF
                    </a>
                    <a href="index.php?export=csv&filter_waktu=<?= urlencode($filter_waktu) ?>&tgl_mulai=<?= urlencode($tgl_mulai_req) ?>&tgl_selesai=<?= urlencode($tgl_selesai_req) ?>" 
                       class="btn-export-action csv" 
                       title="Unduh seluruh data riwayat peminjaman ini dalam format Spreadsheet CSV / Excel">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Unduh CSV
                    </a>
                </div>
            </div>

            <!-- KOTAK FORM PILIH TANGGAL (TOGGLEABLE - HANYA MUNCUL KETIKA DIPENCET TOMBOL FILTER) -->
            <div id="customDateBox" class="custom-date-box" style="display: <?= $filter_waktu === 'custom' ? 'block' : 'none' ?>;">
                <h4>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    PILIH RENTANG TANGGAL PEMINJAMAN:
                </h4>

                <form method="GET" action="index.php">
                    <input type="hidden" name="filter_waktu" value="custom">
                    
                    <div class="custom-date-form-grid">
                        <div class="date-input-wrap">
                            <span>Mulai Tanggal:</span>
                            <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tgl_awal) ?>" required>
                        </div>
                        
                        <div style="font-size: 11px; font-weight: bold; color: var(--text-muted); margin-top: 14px;">s/d</div>

                        <div class="date-input-wrap">
                            <span>Sampai Tanggal:</span>
                            <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tgl_akhir) ?>" required>
                        </div>

                        <button type="submit" class="btn-apply-custom-date">
                            ✓ Terapkan Filter
                        </button>
                        
                        <button type="button" class="btn-close-custom-date" onclick="toggleCustomDateBox()">
                            ✕ Tutup Panel
                        </button>

                        <?php if ($filter_waktu === 'custom'): ?>
                            <a href="index.php?filter_waktu=30_hari" class="btn-close-custom-date" style="color: #dc2626;">
                                ↺ Reset ke Default
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- 4 METRIC CHIPS RINGKASAN -->
            <div class="analytics-metrics-grid">
                <div class="metric-chip-card">
                    <span class="metric-chip-label">Total Dipinjam</span>
                    <span class="metric-chip-value" style="color: var(--primary);"><?= $total_periode_pinjam ?></span>
                    <span class="metric-chip-sub">Berkas arsip dalam rentang ini</span>
                </div>
                <div class="metric-chip-card">
                    <span class="metric-chip-label">Rata-rata Sirkulasi</span>
                    <span class="metric-chip-value"><?= $avg_harian ?></span>
                    <span class="metric-chip-sub">Berkas dipinjam / hari</span>
                </div>
                <div class="metric-chip-card">
                    <span class="metric-chip-label">Puncak Peminjaman</span>
                    <span class="metric-chip-value" style="font-size: 16px; margin-top: 6px;"><?= htmlspecialchars($peak_day_str) ?></span>
                    <span class="metric-chip-sub">Frekuensi transaksi tertinggi</span>
                </div>
                <div class="metric-chip-card">
                    <span class="metric-chip-label">Status Pengembalian</span>
                    <span class="metric-chip-value" style="font-size: 16px; margin-top: 6px;">
                        <span style="color: #b45309;"><?= $stat_dipinjam_periode ?> Aktif</span> • 
                        <span style="color: #15803d;"><?= $stat_kembali_periode ?> Selesai</span>
                    </span>
                    <span class="metric-chip-sub">Dari transaksi periode ini</span>
                </div>
            </div>

            <!-- AREA KANVAS DIAGRAM GARIS -->
            <div class="chart-container-box">
                <canvas id="loanLineChart"></canvas>
            </div>
        </section>

        <!-- ======================================================== -->
        <!-- 2. TAMPILAN OPERASIONAL ADMIN: TAB NAVIGATION            -->
        <!--    (MEMISAHKAN RIWAYAT & FORMULIR - TIDAK NYEMPIL)      -->
        <!-- ======================================================== -->
        <div class="view-switch-nav">
            <button type="button" 
                    class="btn-view-tab <?= $active_view === 'riwayat' ? 'active' : '' ?>" 
                    id="tabBtnRiwayatPinjam" 
                    onclick="pindahTampilanPinjam('riwayat')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                Riwayat & Monitoring Sirkulasi
            </button>
            <button type="button" 
                    class="btn-view-tab <?= $active_view === 'form' ? 'active' : '' ?>" 
                    id="tabBtnFormPinjam" 
                    onclick="pindahTampilanPinjam('form')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                + Catat Peminjaman Cepat
            </button>
        </div>

        <!-- TAMPILAN 1: TABEL MONITORING (FULL WIDTH LEGA & LEBAR) -->
        <div id="viewRiwayatPinjam" style="display: <?= $active_view === 'riwayat' ? 'block' : 'none' ?>;">
            <div class="panel-box">
                <div class="panel-header-custom">
                    <div class="panel-title-text">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        Riwayat Peminjaman & Verifikasi Denda
                    </div>
                    <button type="button" class="btn-switch-to-form" onclick="pindahTampilanPinjam('form')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        + Catat Peminjaman Cepat
                    </button>
                </div>

                <!-- INDIKATOR FILTER RENTANG WAKTU -->
                <div class="active-filter-indicator">
                    <span>
                        📅 Menampilkan data periode: <strong><?= htmlspecialchars($label_filter_text) ?></strong> 
                        (<?= mysqli_num_rows($result_transaksi) ?> transaksi)
                    </span>
                    <?php if ($filter_waktu !== 'semua'): ?>
                        <a href="index.php?filter_waktu=semua">Lihat Semua Riwayat</a>
                    <?php endif; ?>
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
                                            <div style="font-size: 10px; color: var(--text-muted);">
                                                Pinjam: <?= date('d/m/Y', strtotime($row['tanggal_pinjam'])) ?>
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
                                                <a href="index.php?selesai_id=<?= (int)$row['id_peminjaman'] ?>&filter_waktu=<?= urlencode($filter_waktu) ?>&tgl_mulai=<?= urlencode($tgl_mulai_req) ?>&tgl_selesai=<?= urlencode($tgl_selesai_req) ?>" 
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
                                                <a href="index.php?lunas_id=<?= (int)$row['id_peminjaman'] ?>&filter_waktu=<?= urlencode($filter_waktu) ?>&tgl_mulai=<?= urlencode($tgl_mulai_req) ?>&tgl_selesai=<?= urlencode($tgl_selesai_req) ?>" 
                                                   class="filter-btn" 
                                                   style="background: #f1f5f9; color: #334155; text-decoration: none; padding: 4px 8px; font-size: 11px;"
                                                   onclick="return confirm('Tandai denda transaksi ini sudah LUNAS?')">
                                                   ✓ Lunas
                                                </a>
                                            <?php endif; ?>

                                            <a href="cetak_struk.php?id=<?= (int)$row['id_peminjaman'] ?>" 
                                               target="_blank" 
                                               class="filter-btn" 
                                               style="background: #e0f2fe; color: #0369a1; text-decoration: none; padding: 4px 8px; font-size: 11px; margin-left: 2px;" 
                                               title="Cetak Struk Bukti Peminjaman">
                                               🧾 Struk
                                            </a>

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
                                        Tidak ada riwayat transaksi peminjaman pada periode ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAMPILAN 2: FORMULIR PEMINJAMAN CEPAT (TERPUSAT, LEGA, TIDAK NYEMPIL) -->
        <div id="viewFormPinjam" style="display: <?= $active_view === 'form' ? 'block' : 'none' ?>;">
            <div class="form-pinjam-container">
                <div class="form-pinjam-card">
                    <div class="form-pinjam-header">
                        <h2>📝 Catat Peminjaman Arsip Cepat</h2>
                        <p>Pilih dokumen arsip dan anggota peminjam. Batas waktu pengembalian otomatis dihitung 5 hari ke depan.</p>
                    </div>

                    <div class="form-pinjam-body">
                        <form method="POST" action="" style="max-width: 100%; padding: 0; background: transparent; border: none; box-shadow: none; margin: 0;">
                            <input type="hidden" name="action" value="tambah_transaksi">
                            <input type="hidden" name="status_peminjaman" value="Dipinjam">

                            <div class="form-group-modern" style="margin-bottom: 18px;">
                                <label for="id_arsip" style="font-weight: 700; font-size: 12px; color: #334155; margin-bottom: 6px; display: block;">
                                    PILIH DOKUMEN ARSIP <span style="color: #dc2626;">*</span>
                                </label>
                                <select name="id_arsip" id="id_arsip" required style="width: 100%; height: 42px; border-radius: 8px; border: 1.5px solid #cbd5e1; padding: 0 12px; font-size: 13px;">
                                    <option value="">-- Pilih Arsip --</option>
                                    <?php 
                                    if ($list_arsip) {
                                        mysqli_data_seek($list_arsip, 0);
                                        while ($ar = mysqli_fetch_assoc($list_arsip)) {
                                            $stok_tot = (int)($ar['jumlah_arsip'] ?? 1);
                                            $stok_pinjam = (int)($ar['sedang_dipinjam'] ?? 0);
                                            $stok_tersedia = max(0, $stok_tot - $stok_pinjam);

                                            if ($stok_tersedia > 0) {
                                                echo '<option value="' . (int)$ar['id_arsip'] . '">' . htmlspecialchars($ar['nama_arsip']) . ' (Tersedia: ' . $stok_tersedia . ' / ' . $stok_tot . ')</option>';
                                            } else {
                                                echo '<option value="' . (int)$ar['id_arsip'] . '" disabled style="color: #94a3b8;">' . htmlspecialchars($ar['nama_arsip']) . ' (Habis Dipinjam - 0/' . $stok_tot . ')</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group-modern" style="margin-bottom: 18px;">
                                <label for="id_pengguna" style="font-weight: 700; font-size: 12px; color: #334155; margin-bottom: 6px; display: block;">
                                    ANGGOTA PEMINJAM <span style="color: #dc2626;">*</span>
                                </label>
                                <select name="id_pengguna" id="id_pengguna" required style="width: 100%; height: 42px; border-radius: 8px; border: 1.5px solid #cbd5e1; padding: 0 12px; font-size: 13px;">
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

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px;">
                                <div class="form-group-modern">
                                    <label for="adm_tgl_pinjam" style="font-weight: 700; font-size: 12px; color: #334155; margin-bottom: 6px; display: block;">
                                        TANGGAL PINJAM <span style="color: #dc2626;">*</span>
                                    </label>
                                    <input type="date" name="tanggal_pinjam" id="adm_tgl_pinjam" value="<?= date('Y-m-d') ?>" required style="width: 100%; height: 42px; border-radius: 8px; border: 1.5px solid #cbd5e1; padding: 0 12px; font-size: 13px;">
                                </div>

                                <div class="form-group-modern">
                                    <label for="adm_tgl_kembali" style="font-weight: 700; font-size: 12px; color: #334155; margin-bottom: 6px; display: block;">
                                        BATAS TANGGAL PENGEMBALIAN
                                    </label>
                                    <input type="date" name="tanggal_kembali" id="adm_tgl_kembali" value="<?= date('Y-m-d', strtotime('+5 days')) ?>" style="width: 100%; height: 42px; border-radius: 8px; border: 1.5px solid #cbd5e1; padding: 0 12px; font-size: 13px;">
                                </div>
                            </div>
                            <small style="color: var(--text-muted); font-size: 11px; display: block; margin-top: -10px; margin-bottom: 18px;">
                                * Otomatis dihitung 5 hari ke depan terhitung sejak tanggal pinjam.
                            </small>

                            <div class="info-card-rule" style="margin-bottom: 24px;">
                                <h4>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    Aturan Keterlambatan SIRAPI
                                </h4>
                                <p style="margin: 0; line-height: 1.5; font-size: 11.5px;">
                                    Peminjaman maksimal <strong>5 hari</strong>. Jika lewat, denda hari 1 (Rp 20.000), hari 2 (Rp 25.000), hari 3 (Rp 30.000), dan +Rp 5.000/hari berikutnya.
                                </p>
                            </div>

                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                                <button type="button" class="btn-cancel-form" onclick="pindahTampilanPinjam('riwayat')">
                                    &larr; Batal & Lihat Riwayat
                                </button>
                                <button type="submit" class="btn-submit-tx" style="padding: 12px 28px; width: auto; font-size: 13px; font-weight: 700;">
                                    + Simpan Transaksi Peminjaman
                                </button>
                            </div>
                        </form>
                    </div>
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

                                        <a href="cetak_struk.php?id=<?= (int)$row['id_peminjaman'] ?>" 
                                           target="_blank" 
                                           class="filter-btn" 
                                           style="background: #e0f2fe; color: #0369a1; text-decoration: none; padding: 4px 8px; font-size: 11px; margin-left: 6px;" 
                                           title="Cetak Struk Bukti Peminjaman">
                                            🧾 Struk
                                        </a>
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
    // Fungsi Pindah Tampilan Admin: Riwayat vs Form Peminjaman Cepat (Tidak Nyempil)
    function pindahTampilanPinjam(view) {
        const viewRiwayat = document.getElementById('viewRiwayatPinjam');
        const viewForm = document.getElementById('viewFormPinjam');
        const btnRiwayat = document.getElementById('tabBtnRiwayatPinjam');
        const btnForm = document.getElementById('tabBtnFormPinjam');

        if (!viewRiwayat || !viewForm) return;

        if (view === 'form') {
            viewRiwayat.style.display = 'none';
            viewForm.style.display = 'block';
            if (btnRiwayat) btnRiwayat.classList.remove('active');
            if (btnForm) btnForm.classList.add('active');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            viewRiwayat.style.display = 'block';
            viewForm.style.display = 'none';
            if (btnRiwayat) btnRiwayat.classList.add('active');
            if (btnForm) btnForm.classList.remove('active');
        }
    }

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

    // Toggle Box Pilih Rentang Tanggal Kustom (Accordion Effect)
    function toggleCustomDateBox() {
        const box = document.getElementById('customDateBox');
        const arrow = document.getElementById('toggleArrow');
        if (!box) return;

        if (box.style.display === 'none' || box.style.display === '') {
            box.style.display = 'block';
            if (arrow) arrow.innerText = '▴';
        } else {
            box.style.display = 'none';
            if (arrow) arrow.innerText = '▾';
        }
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

    <?php if ($role === 'admin'): ?>
    // ==========================================
    // INITIALIZATION DIAGRAM GARIS (CHART.JS)
    // ==========================================
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('loanLineChart');
        if (!ctx) return;

        const labels = <?= json_encode($chart_labels) ?>;
        const dataValues = <?= json_encode($chart_values) ?>;

        const chartContext = ctx.getContext('2d');
        
        // Buat efek gradien area hijau transparan khas SIRAPI
        const gradientFill = chartContext.createLinearGradient(0, 0, 0, 240);
        gradientFill.addColorStop(0, 'rgba(16, 185, 129, 0.28)');
        gradientFill.addColorStop(1, 'rgba(16, 185, 129, 0.01)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Peminjaman Arsip',
                    data: dataValues,
                    borderColor: '#047857',
                    borderWidth: 3,
                    backgroundColor: gradientFill,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#047857',
                    pointBorderWidth: 2.5,
                    pointRadius: 4.5,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#047857',
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '700' },
                        bodyFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12 },
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return '📌 ' + context.parsed.y + ' Berkas Arsip Dipinjam';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            font: { family: "'Plus Jakarta Sans', sans-serif", size: 10.5 },
                            color: '#64748b',
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 12
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9',
                            drawBorder: false
                        },
                        ticks: {
                            precision: 0,
                            stepSize: 1,
                            font: { family: "'Plus Jakarta Sans', sans-serif", size: 10.5 },
                            color: '#64748b'
                        }
                    }
                }
            }
        });
    });
    <?php endif; ?>
</script>

</body>
</html>