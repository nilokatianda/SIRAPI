<?php
require_once "../includes/auth_check.php";
require_once "../includes/koneksi.php";
require_once "../includes/denda_helper.php";

ensure_denda_schema($conn);

$id_pinjam = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$role = $_SESSION['role'] ?? 'pengguna';
$id_user_auth = (int)($_SESSION['id_pengguna'] ?? 0);
$nama_admin_cetak = $_SESSION['nama_pengguna'] ?? 'Petugas Arsip';

if ($id_pinjam <= 0) {
    die("ID transaksi tidak valid. <a href='index.php'>Kembali ke Daftar Sirkulasi</a>");
}

// Ambil data detail transaksi peminjaman lengkap
$sql = "
    SELECT 
        p.id_peminjaman, p.tanggal_pinjam, p.tanggal_kembali, p.tanggal_dikembalikan,
        p.status_peminjaman, p.denda, p.status_denda, p.metode_pembayaran, p.tanggal_bayar_denda,
        a.id_arsip, a.nama_arsip, a.nama_file, a.file_arsip,
        u.id_pengguna, u.nama_pengguna, u.nim, u.jabatan, u.username,
        k.nama_kegiatan,
        pr.nama_periode, pr.nama_divisi
    FROM peminjaman p
    LEFT JOIN arsip a ON p.id_arsip = a.id_arsip
    LEFT JOIN pengguna u ON p.id_pengguna = u.id_pengguna
    LEFT JOIN kegiatan k ON a.id_kegiatan = k.id_kegiatan
    LEFT JOIN periode pr ON k.id_periode = pr.id_periode
    WHERE p.id_peminjaman = ?
";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    die("Gagal menyiapkan query: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $id_pinjam);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$trx = mysqli_fetch_assoc($res);

if (!$trx) {
    die("Data transaksi peminjaman #$id_pinjam tidak ditemukan. <a href='index.php'>Kembali</a>");
}

// Validasi hak akses: Pengguna biasa hanya boleh mencetak struk miliknya sendiri
if ($role !== 'admin' && (int)$trx['id_pengguna'] !== $id_user_auth) {
    die("Akses ditolak: Anda tidak memiliki wewenang mencetak struk anggota lain. <a href='index.php'>Kembali</a>");
}

// Hitung data denda & keterlambatan secara dinamis
$info_denda = get_info_denda($trx);
$kode_arsip = "ARS-" . str_pad($trx['id_arsip'] ?? 0, 2, '0', STR_PAD_LEFT);
$no_resi = "STRUK-" . date('Ymd', strtotime($trx['tanggal_pinjam'])) . "-" . str_pad($trx['id_peminjaman'], 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Bukti Peminjaman #<?= $trx['id_peminjaman'] ?> - SIRAPI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #047857;
            --primary-dark: #064e3b;
            --bg-page: #f1f5f9;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            background-color: var(--bg-page);
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 30px 16px;
        }

        /* Bilah Aksi / Tombol Kontrol (Tidak Tercetak Saat Print) */
        .no-print-bar {
            width: 100%;
            max-width: 440px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 10px;
        }

        .btn-action-print {
            background: linear-gradient(135deg, #047857 0%, #064e3b 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(4, 120, 87, 0.3);
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-action-print:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(4, 120, 87, 0.4);
        }

        .btn-action-back {
            background: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-action-back:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        /* CARD STRUK KASIR / SLIP PEMINJAMAN */
        .receipt-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 16px;
            padding: 28px 24px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            position: relative;
        }

        /* HEADER STRUK */
        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #cbd5e1;
            padding-bottom: 16px;
            margin-bottom: 16px;
        }

        .receipt-logo-icon {
            width: 42px;
            height: 42px;
            background: #ecfdf5;
            color: #047857;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 8px;
            border: 1.5px solid #a7f3d0;
        }

        .receipt-org-title {
            font-size: 17px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.3px;
        }

        .receipt-sub-title {
            font-size: 11.5px;
            color: #64748b;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .receipt-badge-title {
            display: inline-block;
            margin-top: 10px;
            padding: 4px 14px;
            background: #f1f5f9;
            color: #0f172a;
            font-size: 11px;
            font-weight: 800;
            border-radius: 9999px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border: 1px solid #cbd5e1;
        }

        /* META TRANSAKSI (NO STRUK & TANGGAL) */
        .receipt-meta-box {
            display: flex;
            justify-content: space-between;
            font-size: 11.5px;
            color: #475569;
            margin-bottom: 14px;
            padding: 8px 12px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #f1f5f9;
            font-family: 'Space Mono', monospace;
        }

        /* SECTION TITLE */
        .section-label {
            font-size: 10.5px;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
            margin-top: 14px;
        }

        /* TABEL ITEM / DAFTAR RINCIAN */
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 12px;
        }

        .receipt-table tr td {
            padding: 5px 0;
            vertical-align: top;
        }

        .receipt-table tr td:first-child {
            color: #64748b;
            width: 40%;
        }

        .receipt-table tr td:last-child {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
            width: 60%;
        }

        .doc-name-highlight {
            color: #047857 !important;
            font-size: 13px !important;
            font-weight: 800 !important;
        }

        .receipt-divider {
            border-top: 1px dashed #cbd5e1;
            margin: 14px 0;
        }

        /* KOTAK STATUS PINJAM & DENDA */
        .status-box-receipt {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 12px 14px;
            margin: 14px 0;
        }

        .status-box-receipt.alert {
            background: #fef2f2;
            border-color: #fecaca;
        }

        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .status-row:last-child {
            margin-bottom: 0;
        }

        .status-badge-val {
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
        }

        .status-badge-val.dipinjam {
            background: #fef3c7;
            color: #b45309;
        }

        .status-badge-val.kembali {
            background: #dcfce7;
            color: #15803d;
        }

        .status-badge-val.telat {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-badge-val.lunas {
            background: #dcfce7;
            color: #15803d;
        }

        /* CATATAN & ATURAN */
        .receipt-notice {
            background: #fafaf9;
            border: 1px solid #e7e5e4;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 10.5px;
            color: #78716c;
            line-height: 1.5;
            margin-top: 14px;
        }

        .receipt-notice strong {
            color: #44403c;
        }

        /* AREA TANDA TANGAN */
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 24px;
            padding-top: 14px;
            border-top: 1px dashed #cbd5e1;
            text-align: center;
            font-size: 11px;
        }

        .signature-box .title {
            color: #64748b;
            margin-bottom: 45px;
        }

        .signature-box .name {
            font-weight: 800;
            color: #0f172a;
            border-top: 1px solid #94a3b8;
            padding-top: 4px;
            display: inline-block;
            min-width: 120px;
        }

        /* FOOTER STRUK */
        .receipt-footer-text {
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
            margin-top: 20px;
            line-height: 1.4;
        }

        /* MEDIA PRINT (SAAT DI-PRINT / SAVE AS PDF) */
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .no-print-bar {
                display: none !important;
            }

            .receipt-card {
                box-shadow: none !important;
                border: 1px solid #cbd5e1 !important;
                max-width: 100% !important;
                width: 100% !important;
                padding: 16px !important;
                border-radius: 0 !important;
            }
        }
    </style>
</head>
<body>

    <!-- BILAH TOMBOL TIDAK TERCETAK -->
    <div class="no-print-bar">
        <a href="index.php" class="btn-action-back">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            Kembali
        </a>
        <button type="button" class="btn-action-print" onclick="window.print()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
            Cetak Struk Sekarang
        </button>
    </div>

    <!-- STRUK BUKTI PEMINJAMAN -->
    <div class="receipt-card">
        <!-- HEADER STRUK -->
        <div class="receipt-header">
            <div class="receipt-logo-icon">📂</div>
            <div class="receipt-org-title">SIRAPI • KEARSIPAN UKM</div>
            <div class="receipt-sub-title">Sistem Rekapitulasi Arsip & Peminjaman</div>
            <div class="receipt-badge-title">STRUK BUKTI PEMINJAMAN ARSIP</div>
        </div>

        <!-- NOMOR RESI & WAKTU CETAK -->
        <div class="receipt-meta-box">
            <span><?= htmlspecialchars($no_resi) ?></span>
            <span><?= date('d/m/Y H:i') ?> WIB</span>
        </div>

        <!-- INFORMASI ANGGOTA PEMINJAM -->
        <div class="section-label">Data Anggota Peminjam</div>
        <table class="receipt-table">
            <tr>
                <td>Nama Lengkap</td>
                <td><?= htmlspecialchars($trx['nama_pengguna'] ?? 'Umum') ?></td>
            </tr>
            <tr>
                <td>NIM / No. Induk</td>
                <td><?= htmlspecialchars($trx['nim'] ?? '-') ?></td>
            </tr>
            <tr>
                <td>Jabatan / Status</td>
                <td><?= htmlspecialchars($trx['jabatan'] ?? 'Anggota UKM') ?></td>
            </tr>
        </table>

        <div class="receipt-divider"></div>

        <!-- INFORMASI DOKUMEN ARSIP -->
        <div class="section-label">Rincian Dokumen Arsip</div>
        <table class="receipt-table">
            <tr>
                <td>Kode Arsip</td>
                <td><span style="font-family: monospace; font-weight: 800;"><?= htmlspecialchars($kode_arsip) ?></span></td>
            </tr>
            <tr>
                <td>Nama Dokumen</td>
                <td class="doc-name-highlight"><?= htmlspecialchars($trx['nama_arsip'] ?? '-') ?></td>
            </tr>
            <tr>
                <td>Kegiatan Terkait</td>
                <td><?= htmlspecialchars($trx['nama_kegiatan'] ?? '-') ?></td>
            </tr>
            <tr>
                <td>Divisi & Periode</td>
                <td><?= htmlspecialchars(($trx['nama_divisi'] ?? '-') . " (" . ($trx['nama_periode'] ?? '-') . ")") ?></td>
            </tr>
        </table>

        <div class="receipt-divider"></div>

        <!-- PARAMETER WAKTU & SIRKULASI -->
        <div class="section-label">Waktu Sirkulasi Peminjaman</div>
        <table class="receipt-table">
            <tr>
                <td>Tanggal Pinjam</td>
                <td><?= date('d F Y', strtotime($trx['tanggal_pinjam'])) ?></td>
            </tr>
            <tr>
                <td>Batas Kembali (Max 5 Hari)</td>
                <td>
                    <strong style="color: #b45309;">
                        <?= !empty($trx['tanggal_kembali']) ? date('d F Y', strtotime($trx['tanggal_kembali'])) : '-' ?>
                    </strong>
                </td>
            </tr>
            <?php if (!empty($trx['tanggal_dikembalikan'])): ?>
                <tr>
                    <td>Tgl Aktual Kembali</td>
                    <td><?= date('d F Y', strtotime($trx['tanggal_dikembalikan'])) ?></td>
                </tr>
            <?php endif; ?>
        </table>

        <!-- KOTAK STATUS SIRKULASI & DENDA -->
        <div class="status-box-receipt <?= $info_denda['harus_bayar'] ? 'alert' : '' ?>">
            <div class="status-row">
                <span>Status Berkas:</span>
                <span class="status-badge-val <?= $trx['status_peminjaman'] === 'Dipinjam' ? 'dipinjam' : 'kembali' ?>">
                    <?= htmlspecialchars($trx['status_peminjaman']) ?>
                </span>
            </div>

            <?php if ($info_denda['is_terlambat']): ?>
                <div class="status-row">
                    <span>Keterlambatan:</span>
                    <span class="status-badge-val telat"><?= $info_denda['hari_terlambat'] ?> Hari</span>
                </div>
                <div class="status-row">
                    <span>Nominal Denda:</span>
                    <strong style="color: #dc2626;"><?= $info_denda['nominal_format'] ?></strong>
                </div>
                <div class="status-row">
                    <span>Status Denda:</span>
                    <span class="status-badge-val <?= $trx['status_denda'] === 'Lunas' ? 'lunas' : 'telat' ?>">
                        <?= htmlspecialchars($trx['status_denda']) ?>
                    </span>
                </div>
                <?php if (!empty($trx['metode_pembayaran'])): ?>
                    <div class="status-row">
                        <span>Metode Pembayaran:</span>
                        <span><?= htmlspecialchars($trx['metode_pembayaran']) ?></span>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="status-row">
                    <span>Status Denda:</span>
                    <span class="status-badge-val lunas">✓ Bebas Denda (Tepat Waktu)</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- ATURAN & KETENTUAN -->
        <div class="receipt-notice">
            <strong>Ketentuan Peminjam:</strong><br>
            1. Simpan struk ini sebagai tanda bukti sah peminjaman berkas fisik kearsipan.<br>
            2. Batas waktu peminjaman adalah maksimal 5 hari kalender.<br>
            3. Keterlambatan dikenakan denda bertingkat (H1: 20rb, H2: 25rb, H3: 30rb, >H3: +5rb/hari).<br>
            4. Berkas wajib dijaga keasliannya dan dilarang mencoret/merusak dokumen.
        </div>

        <div class="receipt-divider" style="margin-top: 20px;"></div>

        <!-- FOOTER RESI -->
        <div class="receipt-footer-text" style="margin-top: 12px;">
            Dokumen ini dihasilkan secara otomatis oleh Sistem SIRAPI.<br>
            Terima kasih telah tertib dalam administrasi kearsipan UKM.
        </div>
    </div>

</body>
</html>

