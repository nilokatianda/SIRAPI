<?php
require_once __DIR__ . "/../includes/auth_check.php";
require_admin();
require_once __DIR__ . "/../includes/koneksi.php";
require_once __DIR__ . "/../includes/denda_helper.php";

ensure_denda_schema($conn);

// Fungsi otomatisasi pembuatan tabel pengeluaran jika belum ada
function ensure_pengeluaran_schema($conn) {
    static $checked = false;
    if ($checked) return;

    $create_sql = "
        CREATE TABLE IF NOT EXISTS pengeluaran (
            id_pengeluaran INT AUTO_INCREMENT PRIMARY KEY,
            tanggal_pengeluaran DATE NOT NULL,
            judul_pengeluaran VARCHAR(150) NOT NULL,
            kategori_pengeluaran VARCHAR(60) NOT NULL,
            jenis_periode VARCHAR(30) NOT NULL DEFAULT 'Bulanan',
            nominal INT NOT NULL DEFAULT 0,
            keterangan TEXT NULL,
            id_pengguna INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    mysqli_query($conn, $create_sql);

    // Inisialisasi data awal jika tabel baru saja dibuat dan masih kosong
    $check_empty = mysqli_query($conn, "SELECT COUNT(*) as total FROM pengeluaran");
    if ($check_empty && ($row_c = mysqli_fetch_assoc($check_empty)) && (int)$row_c['total'] === 0) {
        $tgl_kemarin = date('Y-m-d', strtotime('-7 days'));
        $tgl_lalu = date('Y-m-d', strtotime('-15 days'));
        $init_sql = "
            INSERT INTO pengeluaran (tanggal_pengeluaran, judul_pengeluaran, kategori_pengeluaran, jenis_periode, nominal, keterangan) VALUES
            ('$tgl_lalu', 'Pemulihan & Disaster Recovery Database Arsip', 'Pemulihan & Backup Sistem', 'Bulanan', 50000, 'Pembersihan log transaksi dan pencadangan cold storage database sirapi'),
            ('$tgl_kemarin', 'Pemeliharaan Kuota Server & Cloud Storage PDF', 'Kapasitas Server & Cloud Storage', 'Bulanan', 75000, 'Alokasi penyimpanan digital dokumen lampiran kegiatan UKM')
        ";
        mysqli_query($conn, $init_sql);
    }

    $checked = true;
}

ensure_pengeluaran_schema($conn);

$nama_admin = $_SESSION['nama_pengguna'] ?? 'Administrator';
$id_admin_auth = (int)($_SESSION['id_pengguna'] ?? 0);
$jabatan_admin = $_SESSION['jabatan'] ?? 'Admin / Sekretaris';

$pesan_sukses = "";
$pesan_error = "";
$active_view = isset($_GET['view']) && $_GET['view'] === 'form' ? 'form' : 'riwayat';

// 1. Aksi Tambah Pengeluaran Baru
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'tambah_pengeluaran') {
    $tanggal = trim($_POST['tanggal_pengeluaran'] ?? date('Y-m-d'));
    $judul = trim($_POST['judul_pengeluaran'] ?? '');
    $kategori = trim($_POST['kategori_pengeluaran'] ?? 'Pemulihan & Backup Sistem');
    $jenis_periode = trim($_POST['jenis_periode'] ?? 'Bulanan');
    $nominal = (int)($_POST['nominal'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($judul === "" || $nominal <= 0 || $tanggal === "") {
        $pesan_error = "Tanggal, Nama Pemeliharaan, dan Nominal Biaya wajib diisi!";
        $active_view = 'form';
    } else {
        $stmt_ins = mysqli_prepare($conn, "INSERT INTO pengeluaran (tanggal_pengeluaran, judul_pengeluaran, kategori_pengeluaran, jenis_periode, nominal, keterangan, id_pengguna) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt_ins) {
            mysqli_stmt_bind_param($stmt_ins, "ssssisi", $tanggal, $judul, $kategori, $jenis_periode, $nominal, $keterangan, $id_admin_auth);
            if (mysqli_stmt_execute($stmt_ins)) {
                $pesan_sukses = "Biaya pemeliharaan berhasil dicatat sebesar Rp " . number_format($nominal, 0, ',', '.') . "!";
                $active_view = 'riwayat';
            } else {
                $pesan_error = "Gagal menyimpan pengeluaran: " . mysqli_stmt_error($stmt_ins);
                $active_view = 'form';
            }
        }
    }
}

// 2. Aksi Hapus Pengeluaran
if (isset($_GET['hapus_id'])) {
    $id_del = (int)$_GET['hapus_id'];
    $stmt_del = mysqli_prepare($conn, "DELETE FROM pengeluaran WHERE id_pengeluaran = ?");
    if ($stmt_del) {
        mysqli_stmt_bind_param($stmt_del, "i", $id_del);
        if (mysqli_stmt_execute($stmt_del)) {
            $pesan_sukses = "Riwayat pengeluaran #$id_del berhasil dihapus.";
            $active_view = 'riwayat';
        }
    }
}

// 3. Ambil Perhitungan Finansial Terpadu (Pemasukan Denda vs Pengeluaran Maintenance)
$q_inflow = mysqli_query($conn, "SELECT COALESCE(SUM(denda), 0) as total_pemasukan FROM peminjaman WHERE status_denda = 'Lunas'");
$total_pemasukan = (int)mysqli_fetch_assoc($q_inflow)['total_pemasukan'];

$q_outflow = mysqli_query($conn, "SELECT COALESCE(SUM(nominal), 0) as total_pengeluaran, COUNT(*) as frekuensi FROM pengeluaran");
$r_outflow = mysqli_fetch_assoc($q_outflow);
$total_pengeluaran = (int)($r_outflow['total_pengeluaran'] ?? 0);
$count_pengeluaran = (int)($r_outflow['frekuensi'] ?? 0);

$saldo_kas = $total_pemasukan - $total_pengeluaran;

// 4. Ambil Seluruh Data Pengeluaran
$sql_list = "
    SELECT p.*, u.nama_pengguna 
    FROM pengeluaran p
    LEFT JOIN pengguna u ON p.id_pengguna = u.id_pengguna
    ORDER BY p.tanggal_pengeluaran DESC, p.id_pengeluaran DESC
";
$res_pengeluaran = mysqli_query($conn, $sql_list);

// 5. Handler Ekspor Data (PDF, CSV, dan SVG)
if (isset($_GET['export'])) {
    $export_mode = strtolower(trim($_GET['export']));

    $res_exp = mysqli_query($conn, $sql_list);
    $data_rows = [];
    if ($res_exp) {
        while ($r = mysqli_fetch_assoc($res_exp)) {
            $data_rows[] = $r;
        }
    }

    if ($export_mode === 'csv') {
        // Output Spreadsheet CSV (Kompatibel dengan Microsoft Excel & Google Sheets)
        $filename = "rekap_pemeliharaan_SIRAPI_" . date('Ymd_His') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM

        fputcsv($out, [
            'ID',
            'Tanggal Pelaksanaan',
            'Kegiatan Pemeliharaan Website',
            'Kategori IT',
            'Siklus Periode',
            'Nominal Biaya (Rp)',
            'Keterangan / Pelaksana',
            'Petugas Pencatat'
        ]);

        foreach ($data_rows as $row) {
            fputcsv($out, [
                '#' . $row['id_pengeluaran'],
                date('d/m/Y', strtotime($row['tanggal_pengeluaran'])),
                $row['judul_pengeluaran'],
                $row['kategori_pengeluaran'],
                $row['jenis_periode'],
                $row['nominal'],
                $row['keterangan'] ?: '-',
                $row['nama_pengguna'] ?: 'Administrator'
            ]);
        }
        fclose($out);
        exit;

    } elseif ($export_mode === 'pdf') {
        // Template Cetak Resmi Rekapitulasi Pemeliharaan SIRAPI (Print to PDF)
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <title>Laporan Rekapitulasi Biaya Pemeliharaan Website - SIRAPI</title>
            <style>
                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    margin: 30px 40px;
                    color: #0f172a;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .kop-surat {
                    border-bottom: 3px double #047857;
                    padding-bottom: 12px;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    gap: 16px;
                }
                .kop-logo {
                    font-size: 38px;
                }
                .kop-text h2 {
                    font-size: 18px;
                    font-weight: 800;
                    margin: 0;
                    color: #047857;
                    letter-spacing: -0.3px;
                }
                .kop-text p {
                    margin: 2px 0 0;
                    color: #475569;
                    font-size: 11.5px;
                }
                .laporan-title-block {
                    text-align: center;
                    margin: 20px 0;
                }
                .laporan-title-block h3 {
                    font-size: 15px;
                    font-weight: 800;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin: 0;
                    color: #0f172a;
                }
                .laporan-title-block p {
                    font-size: 11px;
                    color: #64748b;
                    margin-top: 4px;
                }
                .summary-chips {
                    display: flex;
                    justify-content: space-between;
                    gap: 12px;
                    margin-bottom: 20px;
                }
                .chip-box {
                    flex: 1;
                    padding: 10px 14px;
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                }
                .chip-box .lbl {
                    font-size: 10.5px;
                    color: #64748b;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                .chip-box .val {
                    font-size: 15px;
                    font-weight: 800;
                    color: #0f172a;
                    margin-top: 2px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 24px;
                }
                th, td {
                    border: 1px solid #cbd5e1;
                    padding: 8px 10px;
                    font-size: 11px;
                }
                th {
                    background: #f1f5f9;
                    color: #1e293b;
                    font-weight: 700;
                    text-transform: uppercase;
                }
                .text-right {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
                }
                .badge-per {
                    display: inline-block;
                    padding: 2px 6px;
                    border-radius: 4px;
                    font-size: 10px;
                    font-weight: 700;
                    background: #e2e8f0;
                }
                .badge-per.bulanan {
                    background: #dcfce7;
                    color: #15803d;
                }
                .badge-per.insidental {
                    background: #fef3c7;
                    color: #b45309;
                }
                .sign-row {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 40px;
                    page-break-inside: avoid;
                }
                .sign-col {
                    text-align: center;
                    width: 220px;
                }
                .sign-space {
                    height: 60px;
                }
                .sign-name {
                    font-weight: 700;
                    text-decoration: underline;
                    color: #0f172a;
                }
                .sign-role {
                    font-size: 10.5px;
                    color: #64748b;
                }
                .no-print-bar {
                    background: #047857;
                    color: white;
                    padding: 10px 16px;
                    margin: -30px -40px 20px -40px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .btn-print-action {
                    background: #ffffff;
                    color: #047857;
                    border: none;
                    padding: 6px 14px;
                    border-radius: 6px;
                    font-weight: 700;
                    cursor: pointer;
                    font-size: 12px;
                }
                @media print {
                    .no-print-bar {
                        display: none !important;
                    }
                    body {
                        margin: 0;
                        padding: 15px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="no-print-bar">
                <div><strong>SIRAPI Dokumen Resmi</strong> &bull; Cetak / Simpan Laporan Pemeliharaan</div>
                <div>
                    <button class="btn-print-action" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
                    <button class="btn-print-action" onclick="window.close()" style="background: transparent; color: white; border: 1px solid white; margin-left: 8px;">✕ Tutup</button>
                </div>
            </div>

            <div class="kop-surat">
                <div class="kop-logo">🏛️</div>
                <div class="kop-text">
                    <h2>SISTEM INFORMASI REKAP ARSIP & PEMINJAMAN (SIRAPI)</h2>
                    <p>Unit Kearsipan Mahasiswa &bull; Divisi Pengelolaan Infrastruktur & Data Kearsipan</p>
                    <p>Laporan Resmi Pemeliharaan Website, Pencadangan Database, & Pengeluaran IT</p>
                </div>
            </div>

            <div class="laporan-title-block">
                <h3>Laporan Rekapitulasi Pemeliharaan Website & Operasional IT</h3>
                <p>Dicetak pada: <?= date('d F Y, H:i') ?> WIB &bull; Administrator: <?= htmlspecialchars($nama_admin) ?></p>
            </div>

            <div class="summary-chips">
                <div class="chip-box">
                    <div class="lbl">Total Kas Masuk Denda</div>
                    <div class="val" style="color: #047857;">Rp <?= number_format($total_pemasukan, 0, ',', '.') ?></div>
                </div>
                <div class="chip-box">
                    <div class="lbl">Total Biaya Pemeliharaan</div>
                    <div class="val" style="color: #dc2626;">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></div>
                </div>
                <div class="chip-box">
                    <div class="lbl">Sisa Saldo Kas Operasional</div>
                    <div class="val" style="color: <?= $saldo_kas >= 0 ? '#1d4ed8' : '#b91c1c' ?>;">Rp <?= number_format($saldo_kas, 0, ',', '.') ?></div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th style="width: 80px;">Tanggal</th>
                        <th>Kegiatan Pemeliharaan</th>
                        <th style="width: 140px;">Kategori IT</th>
                        <th style="width: 80px;" class="text-center">Siklus</th>
                        <th style="width: 100px;" class="text-right">Biaya (Rp)</th>
                        <th>Keterangan / Pelaksana</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($data_rows) > 0): ?>
                        <?php $no = 1; foreach ($data_rows as $row): ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal_pengeluaran'])) ?></td>
                                <td><strong><?= htmlspecialchars($row['judul_pengeluaran']) ?></strong></td>
                                <td><?= htmlspecialchars($row['kategori_pengeluaran']) ?></td>
                                <td class="text-center">
                                    <span class="badge-per <?= strtolower($row['jenis_periode']) ?>">
                                        <?= htmlspecialchars($row['jenis_periode']) ?>
                                    </span>
                                </td>
                                <td class="text-right"><strong>Rp <?= number_format($row['nominal'], 0, ',', '.') ?></strong></td>
                                <td><?= htmlspecialchars($row['keterangan'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f8fafc; font-weight: 700;">
                            <td colspan="5" class="text-right">TOTAL PENGELUARAN PEMELIHARAAN:</td>
                            <td class="text-right" style="color: #dc2626;">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></td>
                            <td></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 20px; color: #94a3b8;">Belum ada catatan pemeliharaan yang dicatat.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="sign-row">
                <div class="sign-col">
                    <div>Mengetahui,</div>
                    <div class="sign-role">Penanggung Jawab Kearsipan</div>
                    <div class="sign-space"></div>
                    <div class="sign-name">Ketua Divisi IT</div>
                    <div class="sign-role">NIM. 2026010099</div>
                </div>
                <div class="sign-col">
                    <div>Dicatat & Diverifikasi oleh,</div>
                    <div class="sign-role">Admin Pengelola SIRAPI</div>
                    <div class="sign-space"></div>
                    <div class="sign-name"><?= htmlspecialchars($nama_admin) ?></div>
                    <div class="sign-role"><?= htmlspecialchars($jabatan_admin) ?></div>
                </div>
            </div>

            <script>
                window.addEventListener('load', () => {
                    setTimeout(() => window.print(), 400);
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biaya Pemeliharaan & Pengeluaran IT - SIRAPI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --sidebar-w: 260px;
            --primary: #047857;
            --primary-dark: #064e3b;
            --primary-soft: #ecfdf5;
            --primary-border: #a7f3d0;
            --bg-page: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --radius-card: 16px;
            --shadow-subtle: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            font-size: 13px;
        }

        /* SIDEBAR KIRI ADMIN (SELARAS DENGAN NATURE EMERALD DASHBOARD PENGGUNA) */
        .sidebar {
            width: var(--sidebar-w);
            background: linear-gradient(180deg, #064e3b 0%, #047857 60%, #022c22 100%);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            flex-shrink: 0;
            z-index: 40;
            padding: 24px 16px;
            box-shadow: 4px 0 24px rgba(6, 78, 59, 0.15);
            border-right: none;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            margin-bottom: 20px;
            text-decoration: none;
            color: #ffffff;
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
            box-shadow: none;
            flex-shrink: 0;
            font-size: 20px;
        }

        .sidebar-title h2 {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.1;
            color: #ffffff;
        }

        .sidebar-title span {
            font-size: 10.5px;
            color: #a7f3d0;
            margin-top: 3px;
            font-weight: 500;
            line-height: 1.3;
        }

        .menu-section-label {
            font-size: 10px;
            font-weight: 700;
            color: #6ee7b7;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 12px 12px 4px;
            margin-top: 4px;
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
            padding: 9.5px 14px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 600;
            color: #e2e8f0;
            transition: all 0.2s ease;
        }

        .sidebar-menu a svg {
            color: #a7f3d0;
            transition: all 0.2s ease;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            transform: translateX(3px);
        }

        .sidebar-menu a:hover svg {
            color: #ffffff;
        }

        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .sidebar-menu a.active svg {
            color: #ffffff;
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
        }

        .btn-sidebar-logout {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 10px 14px;
            border-radius: 10px;
            color: #fecaca;
            text-decoration: none;
            font-weight: 700;
            font-size: 12px;
            background: rgba(220, 38, 38, 0.18);
            border: 1px solid rgba(248, 113, 113, 0.35);
            transition: all 0.2s;
        }

        .btn-sidebar-logout:hover {
            background: rgba(220, 38, 38, 0.35);
            color: #ffffff;
            transform: translateX(2px);
        }
            background: #fee2e2;
        }

        /* MAIN CONTENT AREA */
        .main-content {
            flex: 1;
            padding: 28px 36px 60px;
            overflow-y: auto;
        }

        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .top-greeting h1 {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.4px;
        }

        .top-greeting p {
            font-size: 12.5px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* 4 STATS GRID KEUANGAN */
        .stats-grid-fin {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card-fin {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 18px 20px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-subtle);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .stat-top-fin {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .stat-title-fin {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-badge-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .stat-num-fin {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }

        .stat-desc-fin {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* ========================================================
           TAB / VIEW SWITCHER: AJUKAN MAINTENANCE VS RIWAYAT
           ======================================================== */
        .view-switch-nav {
            display: inline-flex;
            background: #e2e8f0;
            padding: 4px;
            border-radius: 10px;
            margin-bottom: 20px;
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

        /* CONTAINER PANEL RIWAYAT (FULL WIDTH) */
        .full-width-panel {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-subtle);
            width: 100%;
        }

        .panel-heading-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 10px;
        }

        .panel-heading-row h2 {
            font-size: 15px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #0f172a;
            border-bottom: none !important;
            padding-bottom: 0 !important;
            margin: 0 !important;
        }

        /* TOMBOL AKSI EKSPOR LAPORAN (PDF, CSV, SVG) */
        .btn-export-fin {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-export-fin.pdf {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .btn-export-fin.pdf:hover {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-export-fin.csv {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .btn-export-fin.csv:hover {
            background: #d1fae5;
            color: #064e3b;
        }

        /* TOOLBAR FILTER & SEARCH DALAM 1 BARIS BERSIH */
        .table-toolbar-fin {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }

        .filter-tabs-fin {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }

        .tab-btn-fin {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .tab-btn-fin:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .tab-btn-fin.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        .table-toolbar-fin .table-search-box {
            width: 240px;
            min-width: 200px;
            max-width: 300px;
            height: 38px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            margin: 0;
            background: #ffffff;
        }

        .table-toolbar-fin .table-search-box input {
            font-size: 12px !important;
        }

        /* HEADER TABEL LEBIH RINGKAS & TIDAK MENCOLOK */
        #tableFin thead tr {
            background: #f8fafc !important;
        }

        #tableFin th {
            background: #f8fafc !important;
            color: #475569 !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            padding: 10px 14px !important;
            border-top: 1px solid #e2e8f0 !important;
            border-bottom: 1.5px solid #e2e8f0 !important;
        }

        #tableFin tbody td {
            padding: 12px 14px !important;
            font-size: 12.5px !important;
            vertical-align: middle;
        }

        .badge-periode {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
        }

        .badge-periode.mingguan {
            background: #e0f2fe;
            color: #0284c7;
        }

        .badge-periode.bulanan {
            background: #ecfdf5;
            color: #047857;
        }

        .badge-periode.insidental {
            background: #fef3c7;
            color: #b45309;
        }
            color: #047857;
        }

        .badge-periode.insidental {
            background: #fef3c7;
            color: #b45309;
        }

        /* ========================================================
           CONTAINER FORM ALA GOOGLE FORM TERPUSAT (VIEW FORM)
           ======================================================== */
        .gform-page-container {
            max-width: 680px;
            margin: 0 auto;
        }

        .gform-hero-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: var(--shadow-subtle);
            overflow: hidden;
            margin-bottom: 18px;
        }

        .gform-top-accent {
            height: 10px;
            background: linear-gradient(90deg, #047857 0%, #10b981 100%);
        }

        .gform-header-content {
            padding: 24px 28px;
        }

        .gform-header-content h2 {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.3px;
        }

        .gform-header-content p {
            font-size: 13px;
            color: #64748b;
            margin-top: 6px;
            line-height: 1.5;
        }

        .gform-question-card {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 14px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
            transition: all 0.2s ease;
        }

        .gform-question-card:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.12);
        }

        .gform-question-card label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .required-star {
            color: #dc2626;
            font-weight: 800;
            margin-left: 2px;
        }

        .gform-question-card input,
        .gform-question-card select,
        .gform-question-card textarea {
            width: 100%;
            padding: 10px 14px;
            border-radius: 9px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            font-family: inherit;
            outline: none;
            background: #f8fafc;
            transition: all 0.2s;
        }

        .gform-question-card input:focus,
        .gform-question-card select:focus,
        .gform-question-card textarea:focus {
            background: #ffffff;
            border-color: var(--primary);
        }

        .gform-btn-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 24px;
            padding: 12px 6px;
        }

        .btn-gform-submit {
            background: linear-gradient(135deg, #047857 0%, #064e3b 100%);
            color: #ffffff;
            border: none;
            padding: 12px 28px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(4, 120, 87, 0.25);
            transition: all 0.2s;
        }

        .btn-gform-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(4, 120, 87, 0.35);
        }

        .btn-gform-cancel {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
            padding: 11px 22px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-gform-cancel:hover {
            background: #f1f5f9;
            color: #0f172a;
        }
    </style>
</head>
<body>

    <!-- SIDEBAR ADMIN -->
    <aside class="sidebar">
        <a href="admin_dashboard.php" class="sidebar-brand">
            <div class="sidebar-logo">🏛️</div>
            <div class="sidebar-title">
                <h2>SIRAPI</h2>
                <span>Kearsipan & Finansial</span>
            </div>
        </a>

        <div class="menu-section-label">Ringkasan Utama</div>
        <ul class="sidebar-menu">
            <li>
                <a href="admin_dashboard.php">
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
                    Kegiatan
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
                <a href="pengeluaran.php" class="active">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    Biaya Pemeliharaan & IT
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

    <!-- KONTEN UTAMA -->
    <main class="main-content">
        <!-- HEADER TOP -->
        <header class="top-bar">
            <div class="top-greeting">
                <h1>Biaya Pemeliharaan Website & Operasional IT 🛠️</h1>
                <p>Pencatatan anggaran maintenance rutin, disaster recovery database, kuota storage cloud, dan neraca kas kearsipan.</p>
            </div>
            <div>
                <a href="admin_dashboard.php" class="btn-back-dashboard" style="text-decoration: none;">
                    &larr; Kembali ke Dashboard
                </a>
            </div>
        </header>

        <!-- ALERTS NOTIFIKASI -->
        <?php if ($pesan_sukses !== ""): ?>
            <div class="alert-custom success" style="margin-bottom: 20px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <div><?= htmlspecialchars($pesan_sukses) ?></div>
            </div>
        <?php endif; ?>
        <?php if ($pesan_error !== ""): ?>
            <div class="alert-custom error" style="margin-bottom: 20px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div><?= htmlspecialchars($pesan_error) ?></div>
            </div>
        <?php endif; ?>

        <!-- 4 KARTU STATISTIK KEUANGAN -->
        <section class="stats-grid-fin">
            <!-- 1. Total Pemasukan Denda -->
            <div class="stat-card-fin">
                <div class="stat-top-fin">
                    <span class="stat-title-fin">Total Kas Masuk (Denda)</span>
                    <div class="stat-badge-icon" style="background: #ecfdf5; color: #047857;">📈</div>
                </div>
                <div class="stat-num-fin" style="color: #047857;">Rp <?= number_format($total_pemasukan, 0, ',', '.') ?></div>
                <div class="stat-desc-fin">Akumulasi denda lunas terkonfirmasi</div>
            </div>

            <!-- 2. Total Pengeluaran Maintenance -->
            <div class="stat-card-fin">
                <div class="stat-top-fin">
                    <span class="stat-title-fin">Total Biaya Pemeliharaan</span>
                    <div class="stat-badge-icon" style="background: #fef2f2; color: #dc2626;">📉</div>
                </div>
                <div class="stat-num-fin" style="color: #dc2626;">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></div>
                <div class="stat-desc-fin">Dari <?= $count_pengeluaran ?> sesi pemeliharaan sistem</div>
            </div>

            <!-- 3. Sisa Saldo Kas Kearsipan -->
            <div class="stat-card-fin">
                <div class="stat-top-fin">
                    <span class="stat-title-fin">Sisa Saldo Kas Operasional</span>
                    <div class="stat-badge-icon" style="background: <?= $saldo_kas >= 0 ? '#eff6ff' : '#fef2f2' ?>; color: <?= $saldo_kas >= 0 ? '#1d4ed8' : '#b91c1c' ?>;">💰</div>
                </div>
                <div class="stat-num-fin" style="color: <?= $saldo_kas >= 0 ? '#1d4ed8' : '#dc2626' ?>;">
                    Rp <?= number_format($saldo_kas, 0, ',', '.') ?>
                </div>
                <div class="stat-desc-fin"><?= $saldo_kas >= 0 ? 'Surplus kas operasional stabil' : 'Defisit dana pemeliharaan' ?></div>
            </div>

        </section>

        <!-- ========================================================
             TAB NAVIGASI UTAMA: MEMISAHKAN RIWAYAT VS AJUKAN MAINTENANCE
             (TIDAK MUNCUL BERSAMAAN / TIDAK BERTABRAKAN)
             ======================================================== -->
        <div class="view-switch-nav">
            <button type="button" 
                    class="btn-view-tab <?= $active_view === 'riwayat' ? 'active' : '' ?>" 
                    id="tabBtnRiwayat" 
                    onclick="pindahTampilan('riwayat')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Riwayat Pemeliharaan Website
            </button>
            <button type="button" 
                    class="btn-view-tab <?= $active_view === 'form' ? 'active' : '' ?>" 
                    id="tabBtnForm" 
                    onclick="pindahTampilan('form')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>
                Ajukan / Catat Maintenance Baru
            </button>
        </div>

        <!-- ========================================================
             TAMPILAN 1: TABEL RIWAYAT (HANYA MUNCUL KETIKA TAB RIWAYAT AKTIF)
             ======================================================== -->
        <div id="viewRiwayat" style="display: <?= $active_view === 'riwayat' ? 'block' : 'none' ?>;">
            <div class="full-width-panel">
                <div class="panel-heading-row">
                    <h2>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Riwayat Pemeliharaan Website
                    </h2>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span class="table-count-badge" id="finCount">Total <?= mysqli_num_rows($res_pengeluaran) ?> Data</span>
                        <a href="pengeluaran.php?export=pdf" target="_blank" class="btn-export-fin pdf" title="Cetak atau Unduh Laporan Rekapitulasi PDF Resmi">
                            📄 Unduh PDF
                        </a>
                        <a href="pengeluaran.php?export=csv" class="btn-export-fin csv" title="Unduh Berkas Spreadsheet CSV / Excel">
                            📊 Unduh CSV
                        </a>
                    </div>
                </div>

                <!-- TOOLBAR FILTER SIKLUS & PENCARIAN INSTAN (SATU BARIS RINGKAS) -->
                <div class="table-toolbar-fin">
                    <div class="filter-tabs-fin">
                        <button type="button" class="tab-btn-fin active" onclick="filterPeriodeFin(this, 'all')">Semua</button>
                        <button type="button" class="tab-btn-fin" onclick="filterPeriodeFin(this, 'Bulanan')">Bulanan</button>
                        <button type="button" class="tab-btn-fin" onclick="filterPeriodeFin(this, 'Insidental')">Insidental</button>
                    </div>

                    <div class="table-search-box">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="finSearch" placeholder="Cari kegiatan..." onkeyup="searchFinTable()">
                    </div>
                </div>

                <!-- TABEL DATA RIWAYAT PENGELUARAN LEGA, RINGKAS & TIDAK OVERLOAD -->
                <div class="table-responsive">
                    <table class="table-custom" id="tableFin">
                        <thead>
                            <tr>
                                <th style="width: 45px;">#</th>
                                <th style="width: 100px;">Tanggal</th>
                                <th>Kegiatan & Kategori</th>
                                <th style="width: 105px;">Siklus</th>
                                <th style="width: 130px;">Biaya</th>
                                <th>Keterangan / Pelaksana</th>
                                <th style="text-align: right; width: 75px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res_pengeluaran && mysqli_num_rows($res_pengeluaran) > 0): ?>
                                <?php while ($row_p = mysqli_fetch_assoc($res_pengeluaran)): 
                                    $p_class = strtolower($row_p['jenis_periode']);
                                ?>
                                    <tr data-periode="<?= htmlspecialchars($row_p['jenis_periode']) ?>">
                                        <td style="color: #64748b; font-weight: 600;">#<?= $row_p['id_pengeluaran'] ?></td>
                                        <td style="font-weight: 600; color: #334155;"><?= date('d/m/Y', strtotime($row_p['tanggal_pengeluaran'])) ?></td>
                                        <td>
                                            <div style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 2px;">
                                                <?= htmlspecialchars($row_p['judul_pengeluaran']) ?>
                                            </div>
                                            <div style="font-size: 11px; color: #059669; font-weight: 600;">
                                                <?= htmlspecialchars($row_p['kategori_pengeluaran']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge-periode <?= $p_class ?>">
                                                <?= htmlspecialchars($row_p['jenis_periode']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="color: #dc2626; font-size: 13px; font-weight: 800;">
                                                Rp <?= number_format($row_p['nominal'], 0, ',', '.') ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <div style="font-size: 11.5px; color: #64748b; line-height: 1.4;" title="<?= htmlspecialchars($row_p['keterangan'] ?? '-') ?>">
                                                <?= htmlspecialchars($row_p['keterangan'] ?: '-') ?>
                                            </div>
                                        </td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <a href="pengeluaran.php?hapus_id=<?= (int)$row_p['id_pengeluaran'] ?>" 
                                               style="color: #dc2626; text-decoration: none; font-size: 11px; font-weight: 700; background: #fee2e2; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;" 
                                               onclick="return confirm('Hapus catatan pengeluaran ini?')">
                                               🗑️ Hapus
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 32px; color: var(--text-muted);">
                                        Belum ada catatan biaya pemeliharaan website. Klik tab <strong>"+ Ajukan / Catat Maintenance Baru"</strong> di atas untuk mengisi formulir.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================================
             TAMPILAN 2: FORMULIR ALA GOOGLE FORM
             (HANYA MUNCUL KETIKA TAB AJUKAN MAINTENANCE AKTIF)
             ======================================================== -->
        <div id="viewForm" style="display: <?= $active_view === 'form' ? 'block' : 'none' ?>;">
            <div class="gform-page-container">
                <!-- HERO CARD HEADER GOOGLE FORM -->
                <div class="gform-hero-card">
                    <div class="gform-top-accent"></div>
                    <div class="gform-header-content">
                        <h2>📝 Formulir Pengajuan & Pencatatan Maintenance Website</h2>
                        <p>Silakan isi rincian anggaran biaya pemeliharaan sistem, kuota cloud storage file dokumen arsip, atau pemulihan berkala database kearsipan.</p>
                    </div>
                </div>

                <!-- FORM BODY DENGAN QUESTION CARDS TERSTRUKTUR -->
                <form method="POST" action="pengeluaran.php">
                    <input type="hidden" name="action" value="tambah_pengeluaran">

                    <!-- QUESTION CARD 1: TANGGAL -->
                    <div class="gform-question-card">
                        <label for="tanggal_pengeluaran">
                            Tanggal Pelaksanaan Pemeliharaan <span class="required-star">*</span>
                        </label>
                        <input type="date" id="tanggal_pengeluaran" name="tanggal_pengeluaran" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <!-- QUESTION CARD 2: NAMA KEGIATAN -->
                    <div class="gform-question-card">
                        <label for="judul_pengeluaran">
                            Nama Kegiatan / Item Pemeliharaan Website <span class="required-star">*</span>
                        </label>
                        <input type="text" id="judul_pengeluaran" name="judul_pengeluaran" placeholder="Contoh: Pemulihan & Disaster Recovery Database Mingguan" required>
                    </div>

                    <!-- QUESTION CARD 3: KATEGORI IT -->
                    <div class="gform-question-card">
                        <label for="kategori_pengeluaran">
                            Kategori Pemeliharaan IT <span class="required-star">*</span>
                        </label>
                        <select id="kategori_pengeluaran" name="kategori_pengeluaran" required>
                            <option value="Pemulihan & Backup Sistem">Pemulihan & Backup Sistem (Disaster Recovery)</option>
                            <option value="Kapasitas Server & Cloud Storage">Kapasitas Server & Cloud Storage PDF</option>
                            <option value="Maintenance & Optimasi Database">Maintenance & Optimasi Database</option>
                            <option value="Audit Keamanan & Lisensi Web">Audit Keamanan & Lisensi SSL/Web</option>
                            <option value="Operasional IT Lainnya">Operasional IT Lainnya</option>
                        </select>
                    </div>

                    <!-- QUESTION CARD 4: SIKLUS PERIODE -->
                    <div class="gform-question-card">
                        <label for="jenis_periode">
                            Siklus Periode Pemeliharaan <span class="required-star">*</span>
                        </label>
                        <select id="jenis_periode" name="jenis_periode" required>
                            <option value="Bulanan">Bulanan (1 Bulan Sekali)</option>
                            <option value="Insidental">Insidental (Darurat / Perbaikan Kritis)</option>
                        </select>
                    </div>

                    <!-- QUESTION CARD 5: NOMINAL BIAYA -->
                    <div class="gform-question-card">
                        <label for="nominal">
                            Nominal Biaya Pemeliharaan (Rp) <span class="required-star">*</span>
                        </label>
                        <input type="number" id="nominal" name="nominal" placeholder="Contoh: 50000" min="1000" required>
                    </div>

                    <!-- QUESTION CARD 6: KETERANGAN / TEKNISI -->
                    <div class="gform-question-card">
                        <label for="keterangan">
                            Keterangan Tambahan / Teknisi Pelaksana (Opsional)
                        </label>
                        <textarea id="keterangan" name="keterangan" rows="3" placeholder="Contoh: Dikerjakan oleh Tim IT Kearsipan untuk pencadangan rutin basis data"></textarea>
                    </div>

                    <!-- TOMBOL AKSI -->
                    <div class="gform-btn-row">
                        <button type="button" class="btn-gform-cancel" onclick="pindahTampilan('riwayat')">
                            &larr; Batal & Lihat Riwayat
                        </button>
                        <button type="submit" class="btn-gform-submit">
                            ✓ Kirim & Simpan Data
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <script>
    // Fungsi Pindah Tampilan (Riwayat vs Form Pengajuan) - Tidak Bertabrakan
    function pindahTampilan(view) {
        const viewRiwayat = document.getElementById('viewRiwayat');
        const viewForm = document.getElementById('viewForm');
        const btnRiwayat = document.getElementById('tabBtnRiwayat');
        const btnForm = document.getElementById('tabBtnForm');

        if (view === 'form') {
            viewRiwayat.style.display = 'none';
            viewForm.style.display = 'block';
            btnRiwayat.classList.remove('active');
            btnForm.classList.add('active');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            viewRiwayat.style.display = 'block';
            viewForm.style.display = 'none';
            btnRiwayat.classList.add('active');
            btnForm.classList.remove('active');
        }
    }

    // Filter Berdasarkan Siklus Periode di Tabel Riwayat (Mingguan / Bulanan / Insidental)
    function filterPeriodeFin(btn, targetPeriode) {
        const tabs = document.querySelectorAll('.tab-btn-fin');
        tabs.forEach(t => t.classList.remove('active'));
        btn.classList.add('active');

        const rows = document.querySelectorAll('#tableFin tbody tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const periode = row.getAttribute('data-periode');
            if (targetPeriode === 'all' || periode === targetPeriode) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        document.getElementById('finCount').textContent = `Menampilkan ${visibleCount} Data`;
    }

    // Live Search Instan Tabel Pengeluaran
    function searchFinTable() {
        const query = document.getElementById('finSearch').value.toLowerCase().trim();
        const rows = document.querySelectorAll('#tableFin tbody tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            if (query === '' || text.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        document.getElementById('finCount').textContent = `Menampilkan ${visibleCount} Data`;
    }
    </script>
</body>
</html>
